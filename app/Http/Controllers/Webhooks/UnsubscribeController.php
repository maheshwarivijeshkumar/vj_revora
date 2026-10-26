<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domain\Messaging\Enums\EnrollmentStatus;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\FollowUpEnrollment;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Http\Response;

/**
 * The unsubscribe link in every email (§88).
 *
 * Reached from a signed URL, so only someone holding the email can use it.
 * Answers both a click (GET) and the one-click POST that Gmail and Yahoo send
 * from their own Unsubscribe button (RFC 8058). Nothing to confirm: a
 * second step is exactly what unsubscribe rules forbid.
 */
final class UnsubscribeController extends Controller
{
    public function __invoke(string $lead, TenantContext $context): Response
    {
        $record = $context->withoutScoping(fn (): ?Lead => Lead::query()->where('uuid', $lead)->first());

        if ($record !== null) {
            $tenant = $context->withoutScoping(fn (): ?Tenant => Tenant::query()->find($record->tenant_id));

            if ($tenant !== null) {
                $context->runAs($tenant, function () use ($record): void {
                    $record->forceFill(['email_opted_out_at' => $record->email_opted_out_at ?? now()])->save();

                    FollowUpEnrollment::query()
                        ->where('lead_id', $record->id)
                        ->where('status', EnrollmentStatus::Active->value)
                        ->update([
                            'status' => EnrollmentStatus::Stopped->value,
                            'next_run_at' => null,
                            'finished_at' => now(),
                            'last_error' => 'The lead unsubscribed.',
                        ]);
                });
            }
        }

        // The same answer either way, so the link reveals nothing about
        // whether an address is on file.
        return response(
            '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Unsubscribed</title></head><body style="font-family:Arial,sans-serif;max-width:32rem;margin:4rem auto;padding:0 1rem;color:#1f2937">'
            .'<h1 style="font-size:1.4rem">You have been unsubscribed</h1>'
            .'<p>You will not receive any more of these emails.</p></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }
}
