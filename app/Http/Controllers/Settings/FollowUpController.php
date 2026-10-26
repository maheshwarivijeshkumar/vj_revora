<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Domain\Messaging\Actions\EnsureDefaultFollowUp;
use App\Domain\Messaging\Enums\EnrollmentStatus;
use App\Domain\Messaging\TemplateRenderer;
use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\FollowUpEnrollment;
use App\Models\FollowUpStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Email templates and the automatic follow-up sequence (§26, §28).
 */
final class FollowUpController extends Controller
{
    public function index(EnsureDefaultFollowUp $defaults): Response
    {
        $sequence = $defaults->handle()->load('steps');

        $counts = FollowUpEnrollment::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('settings/FollowUp', [
            'sequence' => [
                'id' => $sequence->id,
                'is_active' => $sequence->is_active,
                'no_reply_after_hours' => intdiv($sequence->no_reply_after_minutes, 60),
                'steps' => $sequence->steps->map(fn (FollowUpStep $step): array => [
                    'position' => $step->position,
                    'delay_hours' => round($step->delay_minutes / 60, 2),
                    'template_id' => $step->template()->withTrashed()->value('uuid'),
                ])->all(),
            ],
            'templates' => EmailTemplate::query()->orderBy('name')->get()->map(fn (EmailTemplate $t): array => [
                'id' => $t->uuid,
                'name' => $t->name,
                'subject' => $t->subject,
                'body' => $t->body,
            ])->all(),
            'placeholders' => TemplateRenderer::PLACEHOLDERS,
            'stats' => collect(EnrollmentStatus::cases())->mapWithKeys(fn (EnrollmentStatus $s): array => [
                $s->value => ['label' => $s->label(), 'tone' => $s->tone(), 'count' => (int) ($counts[$s->value] ?? 0)],
            ])->all(),
        ]);
    }

    /**
     * Saves the schedule. Delays are counted from the first email, and must
     * rise step by step, or "the 2nd email at 6 hours" would mean nothing.
     */
    public function updateSequence(Request $request, EnsureDefaultFollowUp $defaults): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'no_reply_after_hours' => ['required', 'integer', 'between:1,720'],
            'steps' => ['required', 'array', 'min:1', 'max:10'],
            'steps.*.delay_hours' => ['required', 'numeric', 'between:0,720'],
            'steps.*.template_id' => ['required', 'string', Rule::exists('email_templates', 'uuid')->whereNull('deleted_at')],
        ], [
            'steps.*.template_id.required' => 'Choose a template for every step.',
        ]);

        $delays = array_map(fn (array $s): float => (float) $s['delay_hours'], $validated['steps']);

        if ($delays !== array_values(array_unique($delays)) || $delays !== collect($delays)->sort()->values()->all()) {
            return back()->withErrors(['steps' => 'Each email must go out later than the one before it.']);
        }

        if ((int) $validated['no_reply_after_hours'] <= max([0.0, ...$delays])) {
            return back()->withErrors(['no_reply_after_hours' => 'The "no reply" time must come after the last email.']);
        }

        $sequence = $defaults->handle();

        DB::transaction(function () use ($sequence, $validated): void {
            $sequence->forceFill([
                'is_active' => $validated['is_active'],
                'no_reply_after_minutes' => (int) $validated['no_reply_after_hours'] * 60,
            ])->save();

            $sequence->steps()->delete();

            foreach (array_values($validated['steps']) as $i => $step) {
                $sequence->steps()->create([
                    'position' => $i + 1,
                    'delay_minutes' => (int) round((float) $step['delay_hours'] * 60),
                    'email_template_id' => EmailTemplate::query()->where('uuid', $step['template_id'])->value('id'),
                ]);
            }
        });

        return back()->with('success', $validated['is_active']
            ? 'Follow-up schedule saved. New leads will get these emails.'
            : 'Follow-up schedule saved and turned off.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        EmailTemplate::create($this->validateTemplate($request));

        return back()->with('success', 'Template created.');
    }

    public function updateTemplate(Request $request, EmailTemplate $template): RedirectResponse
    {
        $template->fill($this->validateTemplate($request))->save();

        return back()->with('success', 'Template saved.');
    }

    public function destroyTemplate(EmailTemplate $template): RedirectResponse
    {
        if (FollowUpStep::query()->where('email_template_id', $template->id)->exists()) {
            return back()->with('error', 'This template is used in the follow-up schedule. Swap it out there first.');
        }

        $template->delete();

        return back()->with('success', 'Template deleted.');
    }

    /**
     * @return array{name: string, subject: string, body: string}
     */
    private function validateTemplate(Request $request): array
    {
        /** @var array{name: string, subject: string, body: string} */
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);
    }
}
