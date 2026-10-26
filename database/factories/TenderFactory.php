<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenders\Enums\TenderTopic;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tender>
 */
final class TenderFactory extends Factory
{
    protected $model = Tender::class;

    /**
     * An open software tender by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reference = 'TS'.fake()->unique()->numerify('##########').'E';

        return [
            'source' => 'ppra',
            'reference' => $reference,
            'title' => 'Development of '.fake()->words(3, true).' software',
            'organization' => 'Ministry of '.fake()->word(),
            'city' => 'Islamabad',
            'sector' => 'Info and Comm Tech',
            'status' => 'Published',
            'advertised_on' => now()->subDays(3),
            'closing_at' => now()->addDays(10),
            'topic' => TenderTopic::Software,
            'is_it_related' => true,
            'url' => "https://epms.ppra.gov.pk/public/tenders/tender-details/{$reference}",
            'first_seen_at' => now()->subDays(3),
            'last_seen_at' => now(),
        ];
    }

    public function other(): static
    {
        return $this->state(fn (): array => [
            'title' => 'Supply of office furniture',
            'sector' => 'Furniture/Fixture',
            'topic' => TenderTopic::Other,
            'is_it_related' => false,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['closing_at' => now()->subDay()]);
    }
}
