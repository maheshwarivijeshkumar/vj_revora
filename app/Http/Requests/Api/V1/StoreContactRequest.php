<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Creating a contact over the API (§47).
 */
final class StoreContactRequest extends FormRequest
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
            // Resolved to a company rather than stored on the contact.
            'company' => ['nullable', 'string', 'max:255'],
            'company_domain' => ['nullable', 'string', 'max:255'],
            'external_system' => ['nullable', 'string', 'max:64'],
            'external_record_id' => ['nullable', 'string', 'max:191'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * A contact with no email and no phone cannot be matched, messaged or
     * found again, so it is refused rather than stored as an orphan.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('email') || $this->filled('phone')) {
                return;
            }

            $validator->errors()->add(
                'email',
                'Provide an email address or a phone number so this person can be identified.',
            );
        });
    }
}
