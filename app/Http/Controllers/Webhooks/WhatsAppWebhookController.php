<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domain\Messaging\WhatsApp\ProcessWhatsAppWebhook;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The WhatsApp Cloud API callback (§15).
 *
 * GET is Meta's one-time subscription check; POST carries messages and
 * delivery statuses, signed with the app secret.
 *
 * @see https://developers.facebook.com/docs/graph-api/webhooks/getting-started
 */
final class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $token = (string) config('services.whatsapp.verify_token');

        if ($token !== ''
            && $request->query('hub_mode') === 'subscribe'
            && hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request, ProcessWhatsAppWebhook $process): Response
    {
        $secret = (string) config('services.whatsapp.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        // Unsigned or mis-signed: not from Meta, or a misconfigured secret.
        if ($secret === '' || ! hash_equals($expected, $signature)) {
            return response('Invalid signature', 401);
        }

        $process->handle((array) $request->json()->all());

        // Meta retries anything but a quick 200.
        return response('OK', 200);
    }
}
