<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Domain\Messaging\Actions\SendEmailToLead;
use App\Domain\Messaging\Actions\SendWhatsAppToLead;
use App\Domain\Messaging\Enums\EnrollmentStatus;
use App\Domain\Messaging\Exceptions\CannotMessageLead;
use App\Domain\Messaging\TemplateRenderer;
use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Models\EmailTemplate;
use App\Models\FollowUpEnrollment;
use App\Models\Lead;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A lead's email and WhatsApp conversation, and sending to it (§25, §15).
 *
 * The panel loads the thread on its own, so the lead page does not wait on
 * it and its controller does not need to know it exists.
 */
final class LeadConversationController extends Controller
{
    public function show(Lead $lead, SendWhatsAppToLead $whatsApp, TemplateRenderer $renderer): JsonResponse
    {
        $enrollment = FollowUpEnrollment::query()->where('lead_id', $lead->id)->latest('id')->first();
        $sender = EmailAccount::sender();

        return response()->json([
            'messages' => Message::query()
                ->where('lead_id', $lead->id)
                ->with('user:id,name')
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(500)
                ->get()
                ->map(fn (Message $m): array => [
                    'id' => $m->uuid,
                    'channel' => $m->channel->value,
                    'direction' => $m->direction->value,
                    'subject' => $m->subject,
                    'body' => $m->body,
                    'status' => $m->status->value,
                    'status_label' => $m->status->label(),
                    'error' => $m->error,
                    'step' => $m->step_position,
                    'by' => $m->user?->name,
                    'at' => ($m->sent_at ?? $m->received_at ?? $m->created_at)?->toIso8601String(),
                ])->all(),
            'follow_up' => $enrollment === null ? null : [
                'status' => $enrollment->status->value,
                'status_label' => $enrollment->status->label(),
                'status_tone' => $enrollment->status->tone(),
                'next_step' => $enrollment->status === EnrollmentStatus::Active ? $enrollment->next_position : null,
                'next_run_at' => $enrollment->next_run_at?->toIso8601String(),
                'last_error' => $enrollment->last_error,
            ],
            'engagement' => [
                'score' => $lead->engagement_score,
                'reason' => $lead->engagement_reason,
                'scored_at' => $lead->engagement_scored_at?->toIso8601String(),
            ],
            'email' => [
                'can_send' => $sender !== null && filled($lead->email) && $lead->email_opted_out_at === null,
                'opted_out' => $lead->email_opted_out_at !== null,
                'sender' => $sender?->email,
                'templates' => EmailTemplate::query()->orderBy('name')->get()->map(fn (EmailTemplate $t): array => [
                    'id' => $t->uuid,
                    'name' => $t->name,
                    'subject' => $renderer->render($t->subject, $lead, $sender),
                    'body' => $renderer->render($t->body, $lead, $sender),
                ])->all(),
            ],
            'whatsapp' => [
                'connected' => WhatsAppAccount::query()->exists(),
                'can_send' => filled($lead->phone) && $lead->whatsapp_opted_out_at === null,
                'opted_out' => $lead->whatsapp_opted_out_at !== null,
                'window_open' => $whatsApp->windowOpen($lead),
                'template' => WhatsAppAccount::query()->value('welcome_template'),
            ],
        ]);
    }

    public function sendEmail(Request $request, Lead $lead, SendEmailToLead $send): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $sender = EmailAccount::sender();

        if ($sender === null) {
            return back()->with('error', CannotMessageLead::noSender('email')->getMessage());
        }

        try {
            $send->handle($lead, $sender, $validated['subject'], $validated['body'], ['user_id' => $request->user()?->id]);
        } catch (CannotMessageLead|ProviderAuthException|ProviderRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Email sent to '.$lead->email.'.');
    }

    public function sendWhatsApp(Request $request, Lead $lead, SendWhatsAppToLead $send): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['text', 'template'])],
            'text' => ['required_if:type,text', 'nullable', 'string', 'max:4096'],
        ]);

        try {
            $validated['type'] === 'template'
                ? $send->template($lead, $request->user()?->id)
                : $send->text($lead, (string) $validated['text'], $request->user()?->id);
        } catch (CannotMessageLead|ProviderAuthException|ProviderRequestException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'WhatsApp message sent.');
    }

    public function stopFollowUp(Lead $lead): RedirectResponse
    {
        FollowUpEnrollment::query()
            ->where('lead_id', $lead->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->update([
                'status' => EnrollmentStatus::Stopped->value,
                'next_run_at' => null,
                'finished_at' => now(),
                'last_error' => 'Stopped by hand.',
            ]);

        return back()->with('success', 'Follow-up emails stopped for this lead.');
    }
}
