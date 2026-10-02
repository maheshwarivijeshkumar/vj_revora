<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Updating a contact over the API (§47).
 *
 * Company membership is absent on purpose: a person joining or leaving an
 * organisation is a relationship change with a pivot behind it, and treating
 * it as a field assignment would make "primary employer" a matter of whoever
 * wrote last. `/contacts/{id}/companies` does that explicitly.
 */
final class UpdateContactRequest extends FormRequest
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
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'company' => ['prohibited'],
            'company_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company.prohibited' => 'Use POST /contacts/{id}/companies to change who this person works for.',
            'company_id.prohibited' => 'Use POST /contacts/{id}/companies to change who this person works for.',
        ];
    }
}
