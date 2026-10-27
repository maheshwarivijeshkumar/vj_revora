<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A workspace's WhatsApp Business number on the Cloud API (§15).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $phone_number_id
 * @property string|null $business_account_id
 * @property string|null $display_phone_number
 * @property string $access_token
 * @property string|null $welcome_template
 * @property string $template_language
 * @property bool $auto_send_welcome
 * @property ConnectionStatus $status
 * @property string|null $last_error
 */
final class WhatsAppAccount extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_accounts';

    protected $guarded = [];

    /** The token never leaves the server (§11). */
    protected $hidden = ['access_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'auto_send_welcome' => 'boolean',
            'status' => ConnectionStatus::class,
        ];
    }
}
