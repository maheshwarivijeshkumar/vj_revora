<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Creating a lead over the API (§47, §84).
 *
 * Deliberately permissive about *which* identifying field is present: a
 * partner site may only have an email, a call centre only a phone number.
 * What it will not accept is a payload with nothing identifiable in it.
 */
final class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Scope is enforced by middleware, where it is visible on the route.
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
            'full_name' => ['nullable', 'string', 'max:255'],
            // 'rfc' but not 'dns': a DNS lookup per request adds latency the
            // caller pays for and fails for valid addresses behind slow
            // resolvers.
            'email' => ['nullable', 'string', 'email:rfc', 'max:255', 'required_without_all:phone,external_id,full_name'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'size:2'],
            'company' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:64'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'consent' => ['nullable'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'landing_page' => ['nullable', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required_without_all' => 'Provide at least an email, a phone number, an external_id or a full_name.',
            'email.email' => 'That email address is not valid.',
            'country.size' => 'Country must be a two-letter ISO code.',
        ];
    }
}
