<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Creating or editing a company from the UI (§21, §42).
 */
final class CompanyFormRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            // Either is accepted and both are reduced to one comparable host,
            // because matching a contact's email domain against a company is
            // only sound if the stored value is stable (§21).
            'domain' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'url', 'max:255'],
            'industry' => ['nullable', 'string', 'max:120'],
            'size' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:40'],
            'owner_id' => ['nullable', 'integer', $this->existsInWorkspace('users')],
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
        $nullable = ['domain', 'website', 'industry', 'size', 'country', 'phone', 'owner_id'];

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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A company needs a name.',
            'website.url' => 'Enter a full address, including https://.',
            'country.size' => 'Use the two-letter country code, such as AE or GB.',
            'owner_id.exists' => 'That person is not in this workspace.',
        ];
    }
}
