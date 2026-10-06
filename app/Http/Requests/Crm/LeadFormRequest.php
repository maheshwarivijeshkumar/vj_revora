<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Creating or editing a lead from the UI (§42, §101.16).
 *
 * One class for both, because the rules are genuinely the same: the form offers
 * the same fields either way. Score and score band are absent from both, since
 * they are produced by the scoring engine and carry an explanation that a form
 * overwriting them would make untrue (§19).
 *
 * Separate from the API request of a similar name: a form posts a fixed set of
 * fields and may send empty strings, while an integration sends whatever its
 * provider happens to carry and needs the metadata passthrough. Sharing one rule
 * set across both would mean neither is right.
 */
final class LeadFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            // No `dns`: a live DNS lookup per submission makes the form as slow
            // as the slowest resolver and rejects valid addresses on a blip.
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'status' => ['required', Rule::enum(LeadStatus::class)],
            // Scoped by hand. `exists` builds its own query straight on the
            // table, so Eloquent's global tenant scope never runs and an id
            // from another workspace would validate (ADR-009). This is the one
            // place the fail-closed scope cannot protect, so it is explicit.
            'owner_id' => ['nullable', 'integer', $this->existsInWorkspace('users')],
            'lead_source_id' => ['nullable', 'integer', $this->existsInWorkspace('lead_sources')],
            'next_follow_up_at' => ['nullable', 'date'],
            'consent' => ['boolean'],
        ];
    }

    /**
     * An `exists` rule confined to the current workspace.
     */
    private function existsInWorkspace(string $table): Exists
    {
        return Rule::exists($table, 'id')->where(
            'tenant_id',
            app(TenantContext::class)->tenantOrFail()->id,
        );
    }

    /**
     * An empty text input arrives as "", which is not the same as "not given":
     * stored as-is it would make `email = ''` match the next blank lead in
     * deduplication. Normalising to null is what keeps the unique-ish columns
     * meaningful.
     */
    protected function prepareForValidation(): void
    {
        $nullable = [
            'first_name', 'last_name', 'email', 'phone', 'company_name',
            'job_title', 'website', 'country', 'owner_id', 'lead_source_id',
            'next_follow_up_at',
        ];

        $cleaned = [];

        foreach ($nullable as $field) {
            if ($this->has($field) && trim((string) $this->input($field)) === '') {
                $cleaned[$field] = null;
            }
        }

        if ($cleaned !== []) {
            $this->merge($cleaned);
        }
    }

    /**
     * A lead with neither an email nor a phone cannot be contacted, matched or
     * found again, which is the one thing a lead has to be (§18).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('email') || $this->filled('phone')) {
                return;
            }

            $validator->errors()->add(
                'email',
                'Give an email address or a phone number so this person can be reached.',
            );
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'country.size' => 'Use the two-letter country code, such as AE or GB.',
            'owner_id.exists' => 'That person is not in this workspace.',
            'lead_source_id.exists' => 'That lead source does not exist.',
        ];
    }
}
