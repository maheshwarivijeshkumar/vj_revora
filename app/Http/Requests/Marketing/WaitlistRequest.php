<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Coming-soon waitlist capture.
 *
 * Server-side validation is authoritative (§42, §101.16) — the form ships with
 * `novalidate` precisely so browser validation cannot stand in for this.
 */
final class WaitlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // 'rfc' but deliberately not 'dns': a DNS lookup on every
            // submission adds a network round-trip to the form, fails for
            // perfectly valid addresses behind slow resolvers, and is flaky in
            // tests. Deliverability is confirmed by actually sending, not here.
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'consent' => ['accepted'],
            // Honeypot: a real person never fills a hidden field. Cheaper and
            // less hostile than a CAPTCHA, and §2 forbids CAPTCHA-bypassing
            // behaviour anyway — this is the inverse, a quiet bot filter.
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'consent.accepted' => 'Please confirm you would like launch updates.',
            'website.prohibited' => 'Something went wrong. Please try again.',
        ];
    }
}
