<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Deals\Actions\CreateDeal;
use App\Domain\Leads\Actions\CaptureLead;
use App\Domain\Leads\Services\LeadNormalizer;
use App\Models\Company;
use App\Models\LeadSource;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Enough leads and deals to exercise the table and the board locally.
 *
 * Runs the real capture and create pipelines rather than inserting rows, so
 * demo data is scored, deduplicated and routed exactly as production data
 * would be. Seeding raw rows would hide bugs in the very code this data
 * exists to demonstrate.
 */
final class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $capture = app(CaptureLead::class);
        $normalizer = app(LeadNormalizer::class);
        $createDeal = app(CreateDeal::class);

        $source = LeadSource::where('key', 'website')->first()
            ?? LeadSource::first();

        foreach ($this->people() as $person) {
            $capture->handle($normalizer->normalize($person), $source);
        }

        $pipeline = Pipeline::where('entity_type', 'deal')->first();

        if ($pipeline === null) {
            return;
        }

        $stages = $pipeline->stages()->get();
        $owners = User::query()->pluck('id');

        foreach ($this->opportunities() as $i => $opportunity) {
            $company = Company::firstOrCreate(
                ['name' => $opportunity['company']],
                ['website' => 'https://'.str($opportunity['company'])->slug().'.example'],
            );

            $createDeal->handle(
                [
                    'title' => $opportunity['title'],
                    'value' => $opportunity['value'],
                    'company_id' => $company->id,
                    'owner_id' => $owners->get($i % max(1, $owners->count())),
                    'expected_close_date' => now()->addDays(14 + $i * 5)->toDateString(),
                ],
                $pipeline,
                // Spread across the first few stages so the board is not one
                // tall column.
                $stages->get($i % 5),
            );
        }

        $this->command->info('Demo leads and deals created.');
    }

    /**
     * @return list<array<string, string>>
     */
    private function people(): array
    {
        return [
            ['first_name' => 'Amara', 'last_name' => 'Okafor', 'email' => 'amara@northwind.example', 'phone' => '+971501234501', 'company' => 'Northwind Trading', 'job_title' => 'Head of Growth', 'interest' => 'Requesting a demo', 'budget' => '50k', 'consent' => 'on', 'utm_source' => 'linkedin'],
            ['first_name' => 'Daniel', 'last_name' => 'Reyes', 'email' => 'daniel@cascade.example', 'phone' => '+971501234502', 'company' => 'Cascade Labs', 'job_title' => 'Sales Director', 'consent' => 'on', 'utm_source' => 'google'],
            ['first_name' => 'Priya', 'last_name' => 'Nair', 'email' => 'priya@meridian.example', 'phone' => '+971501234503', 'company' => 'Meridian Group', 'interest' => 'Book a demo next week', 'consent' => 'on'],
            ['first_name' => 'Tomas', 'last_name' => 'Weber', 'email' => 'tomas@halcyon.example', 'company' => 'Halcyon Partners', 'job_title' => 'COO', 'budget' => '120k', 'consent' => 'on'],
            ['first_name' => 'Leila', 'last_name' => 'Haddad', 'email' => 'leila@junipergroup.example', 'phone' => '+971501234505', 'company' => 'Juniper Group', 'consent' => 'on', 'utm_source' => 'meta'],
            ['first_name' => 'Marcus', 'last_name' => 'Bell', 'email' => 'marcus@vantage.example', 'company' => 'Vantage Retail'],
            ['first_name' => 'Sofia', 'last_name' => 'Rossi', 'email' => 'sofia@lumen.example', 'phone' => '+971501234507', 'company' => 'Lumen Studio', 'job_title' => 'Founder', 'interest' => 'demo', 'consent' => 'on'],
            ['first_name' => 'Kwame', 'last_name' => 'Mensah', 'email' => 'kwame@atlasfreight.example', 'phone' => '+971501234508', 'company' => 'Atlas Freight', 'budget' => '80k', 'consent' => 'on'],
            ['first_name' => 'Hana', 'last_name' => 'Sato', 'email' => 'hana@kestrel.example', 'company' => 'Kestrel Media', 'job_title' => 'Marketing Lead', 'consent' => 'on'],
            ['first_name' => 'Owen', 'last_name' => 'Fitzgerald', 'email' => 'owen@brightpath.example', 'phone' => '+971501234510', 'company' => 'Brightpath Education'],
            ['first_name' => 'Yara', 'last_name' => 'Mansour', 'email' => 'yara@solstice.example', 'phone' => '+971501234511', 'company' => 'Solstice Homes', 'interest' => 'Requesting a demo', 'budget' => '35k', 'consent' => 'on'],
            ['first_name' => 'Elias', 'last_name' => 'Nordvik', 'email' => 'elias@granite.example', 'company' => 'Granite Capital', 'job_title' => 'Partner', 'consent' => 'on'],
        ];
    }

    /**
     * @return list<array{title: string, company: string, value: int}>
     */
    private function opportunities(): array
    {
        return [
            ['title' => 'Northwind Trading — platform licence', 'company' => 'Northwind Trading', 'value' => 48000],
            ['title' => 'Cascade Labs — team rollout', 'company' => 'Cascade Labs', 'value' => 22500],
            ['title' => 'Meridian Group — pilot', 'company' => 'Meridian Group', 'value' => 12000],
            ['title' => 'Halcyon Partners — enterprise', 'company' => 'Halcyon Partners', 'value' => 132000],
            ['title' => 'Juniper Group — renewal', 'company' => 'Juniper Group', 'value' => 18750],
            ['title' => 'Vantage Retail — expansion', 'company' => 'Vantage Retail', 'value' => 64000],
            ['title' => 'Lumen Studio — starter', 'company' => 'Lumen Studio', 'value' => 5400],
            ['title' => 'Atlas Freight — multi-region', 'company' => 'Atlas Freight', 'value' => 91000],
            ['title' => 'Kestrel Media — agency plan', 'company' => 'Kestrel Media', 'value' => 27600],
            ['title' => 'Brightpath Education — intake season', 'company' => 'Brightpath Education', 'value' => 39500],
        ];
    }
}
