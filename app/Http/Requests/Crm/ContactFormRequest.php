<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Creating or editing a contact from the UI (§21, §42).
 */
final class ContactFormRequest extends FormRequest
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
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'owner_id' => ['nullable', 'integer', $this->existsInWorkspace('users')],

            // Either pick a company that exists or name a new one. Both are
            // offered because a rep adding a contact usually knows the company
            // name and should not have to leave the form to create it first.
            'company_id' => ['nullable', 'integer', $this->existsInWorkspace('companies')],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_role' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * An `exists` rule confined to the current workspace.
     *
     * `exists` builds its own query straight on the table, so Eloquent's global
     * tenant scope never runs and an id from another workspace would otherwise
     * validate (ADR-009).
     */
    private function existsInWorkspace(string $table): Exists
    {
        return Rule::exists($table, 'id')->where(
            'tenant_id',
            app(TenantContext::class)->tenantOrFail()->id,
        );
    }

    /**
     * Empty inputs arrive as "", which is not "not given": stored as-is,
     * `email = ''` would match the next blank contact when they are matched by
     * identifier (§18).
     */
    protected function prepareForValidation(): void
    {
        $nullable = [
            'first_name', 'last_name', 'email', 'phone', 'job_title',
            'country', 'owner_id', 'company_id', 'company_name', 'company_role',
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // A contact with neither an email nor a phone cannot be matched,
            // messaged or found again, so it is refused rather than stored as
            // an orphan.
            if (! $this->filled('email') && ! $this->filled('phone')) {
                $validator->errors()->add(
                    'email',
                    'Give an email address or a phone number so this person can be reached.',
                );
            }

            // Both would be ambiguous: attach to the chosen company, or create
            // the typed one and attach that? Rather than guess, ask.
            if ($this->filled('company_id') && $this->filled('company_name')) {
                $validator->errors()->add(
                    'company_name',
                    'Either choose an existing company or name a new one, not both.',
                );
            }

            // A role describes a relationship that does not exist yet.
            if ($this->filled('company_role')
                && ! $this->filled('company_id')
                && ! $this->filled('company_name')) {
                $validator->errors()->add(
                    'company_role',
                    'Choose a company before giving a role at it.',
                );
            }
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
            'company_id.exists' => 'That company is not in this workspace.',
        ];
    }
}
