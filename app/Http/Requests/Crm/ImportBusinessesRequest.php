<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The businesses a user chose from a discovery search.
 */
final class ImportBusinessesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('lead.create') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['required', 'uuid'],
            'ids' => ['required', 'array', 'min:1', 'max:20'],
            'ids.*' => ['required', 'string', 'max:255'],
        ];
    }
}
