<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Creating or editing a deal from the UI (§22, §42).
 *
 * `pipeline_stage_id` is absent on purpose. A stage move recalculates
 * probability, may close or reopen the deal, reorders two columns and writes a
 * stage-change record; treating it as a field assignment here would bypass all
 * of that, exactly as it would over the API. The board's drag handler and the
 * card's move menu are the way a deal changes stage.
 */
final class DealFormRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            // Four decimal places in the column so a currency with three minor
            // units still lands exactly; min:0 because a negative deal is a
            // refund, which this is not.
            'value' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'currency' => ['required', 'string', 'size:3'],
            'pipeline_id' => ['required', 'integer', $this->existsInWorkspace('pipelines')],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'integer', $this->existsInWorkspace('users')],
            'company_id' => ['nullable', 'integer', $this->existsInWorkspace('companies')],
            'contact_id' => ['nullable', 'integer', $this->existsInWorkspace('contacts')],
            'lost_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * An `exists` rule confined to the current workspace; see
     * ContactFormRequest for why this cannot be left to the global scope.
     */
    private function existsInWorkspace(string $table): Exists
    {
        return Rule::exists($table, 'id')->where(
            'tenant_id',
            app(TenantContext::class)->tenantOrFail()->id,
        );
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'probability', 'expected_close_date', 'owner_id',
            'company_id', 'contact_id', 'lost_reason',
        ];

        $cleaned = [];

        foreach ($nullable as $field) {
            if ($this->has($field) && trim((string) $this->input($field)) === '') {
                $cleaned[$field] = null;
            }
        }

        // An empty value box means nothing yet, not a free deal. Defaulted
        // rather than rejected, because a deal often exists before its number
        // does and refusing to save it would push people to type a fake one.
        if ($this->has('value') && trim((string) $this->input('value')) === '') {
            $cleaned['value'] = 0;
        }

        if ($cleaned !== []) {
            $this->merge($cleaned);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Give the deal a name you will recognise on the board.',
            'currency.size' => 'Use the three-letter currency code, such as AED or USD.',
            'pipeline_id.exists' => 'That pipeline is not in this workspace.',
            'owner_id.exists' => 'That person is not in this workspace.',
            'company_id.exists' => 'That company is not in this workspace.',
            'contact_id.exists' => 'That contact is not in this workspace.',
        ];
    }
}
