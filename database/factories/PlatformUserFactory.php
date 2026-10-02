<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlatformUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<PlatformUser>
 */
final class PlatformUserFactory extends Factory
{
    protected $model = PlatformUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'status' => 'active',
        ];
    }
}
