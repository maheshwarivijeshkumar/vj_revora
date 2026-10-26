<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Messaging\Enums\MailboxProvider;
use App\Models\EmailAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailAccount>
 */
final class EmailAccountFactory extends Factory
{
    protected $model = EmailAccount::class;

    /**
     * An SMTP + IMAP mailbox by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => MailboxProvider::Smtp,
            'email' => fake()->unique()->safeEmail(),
            'from_name' => fake()->company(),
            'status' => ConnectionStatus::Connected,
            'credentials' => [
                'smtp_host' => 'mail.example.com',
                'smtp_port' => 465,
                'smtp_encryption' => 'ssl',
                'imap_host' => 'mail.example.com',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'username' => 'sales@example.com',
                'password' => 'secret',
            ],
            'is_default' => true,
        ];
    }

    public function google(): static
    {
        return $this->state(fn (): array => [
            'provider' => MailboxProvider::Google,
            'credentials' => [
                'access_token' => 'google-access',
                'refresh_token' => 'google-refresh',
                'expires_at' => now()->addHour()->getTimestamp(),
            ],
        ]);
    }

    public function microsoft(): static
    {
        return $this->state(fn (): array => [
            'provider' => MailboxProvider::Microsoft,
            'credentials' => [
                'access_token' => 'ms-access',
                'refresh_token' => 'ms-refresh',
                'expires_at' => now()->addHour()->getTimestamp(),
            ],
        ]);
    }
}
