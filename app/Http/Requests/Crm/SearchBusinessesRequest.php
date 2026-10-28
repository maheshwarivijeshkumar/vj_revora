<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Domain\Discovery\BusinessCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A search for local businesses in one city.
 */
final class SearchBusinessesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('lead.view') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'city' => ['required', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'country' => ['required', 'string', 'max:80'],
            'category' => ['required', 'string', Rule::in(BusinessCatalog::keys())],
            'source' => ['required', 'string', Rule::in(['google', 'directory', 'both'])],
        ];
    }
}
