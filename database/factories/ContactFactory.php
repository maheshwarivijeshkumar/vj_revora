<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
final class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            // A real UAE mobile prefix, not just a plausible-looking one: only
            // 50, 52, 54, 55, 56 and 58 are assigned, so `+9715` plus random
            // digits produced numbers that fail validation about 40% of the
            // time — and test data that does not validate is a trap for every
            // test written after it.
            'phone' => '+971'.fake()->randomElement(['50', '52', '54', '55', '56', '58'])
                .fake()->numerify('#######'),
            'job_title' => fake()->jobTitle(),
        ];
    }
}
