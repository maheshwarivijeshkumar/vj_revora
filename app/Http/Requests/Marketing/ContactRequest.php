<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public contact form.
 *
 * Server-side validation is authoritative (§42, §101.16). The form ships with
 * `novalidate` precisely so browser validation cannot stand in for this.
 */
final class ContactRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            // 'rfc' but not 'dns': a DNS lookup adds a network round-trip to
            // every submission and fails for valid addresses behind slow
            // resolvers. Deliverability is proven by sending, not here.
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'company' => ['nullable', 'string', 'max:160'],
            'topic' => ['required', Rule::in(['demo', 'pricing', 'technical', 'agency', 'other'])],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            'consent' => ['accepted'],
            // Honeypot: a real person never fills a hidden field.
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please tell us your name.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'message.required' => 'Please tell us what you need.',
            'message.min' => 'A sentence or two would help us reply usefully.',
            'consent.accepted' => 'Please confirm we can contact you about this.',
            'website.prohibited' => 'Something went wrong. Please try again.',
        ];
    }
}
