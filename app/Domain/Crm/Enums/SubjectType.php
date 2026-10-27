<?php

declare(strict_types=1);

namespace App\Domain\Crm\Enums;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Model;

/**
 * The records that notes, tasks and documents can hang off (§21).
 *
 * An allowlist expressed as a type, so no endpoint can resolve a model class
 * straight from a request string — which would turn "attach a file to this" into
 * a way to write rows against User, ApiKey, or anything else that exists.
 *
 * One enum rather than a constant per controller: two copies of an allowlist is
 * how the newer one quietly gains an entry the older one rejects.
 */
enum SubjectType: string
{
    case Lead = 'lead';
    case Contact = 'contact';
    case Company = 'company';
    case Deal = 'deal';

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Lead => Lead::class,
            self::Contact => Contact::class,
            self::Company => Company::class,
            self::Deal => Deal::class,
        };
    }

    /**
     * Loads the record through the tenant scope.
     *
     * Scoping comes from the global scope rather than a check here, so there is
     * no ownership test to forget: an id from another workspace simply does not
     * resolve (ADR-009).
     */
    public function resolve(int $id): Model
    {
        $model = $this->modelClass();

        return $model::query()->whereKey($id)->firstOrFail();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
