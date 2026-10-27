<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Activity\Enums\TaskPriority;
use App\Domain\Activity\Enums\TaskStatus;
use App\Domain\Crm\Enums\SubjectType;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Notes, tasks and logged activities against any entity (§21).
 *
 * One controller rather than three per entity. The subject arrives as a type
 * and an id, resolved through SubjectType — never from the request string,
 * which would let anyone address any model in the application.
 */
final class TimelineController extends Controller
{
    // --- Notes --------------------------------------------------------------

    public function storeNote(Request $request): RedirectResponse
    {
        $validated = $this->validateSubject($request, [
            'body' => ['required', 'string', 'max:10000'],
            'is_pinned' => ['boolean'],
        ]);

        $subject = $this->subject($validated['subject_type'], $validated['subject_id']);

        if ($request->boolean('is_pinned')) {
            $this->unpinOthers($subject);
        }

        $note = Note::create([
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'body' => $validated['body'],
            'is_pinned' => $request->boolean('is_pinned'),
            'user_id' => $request->user()?->id,
        ]);

        // The timeline is one list, so a note appears in it rather than only in
        // a notes tab the reader has to merge in their head.
        app(ActivityRecorder::class)->record(
            $subject,
            ActivityType::Note,
            'Note added',
            $note->body,
        );

        return back()->with('success', 'Note added.');
    }

    public function updateNote(Request $request, Note $note): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['sometimes', 'string', 'max:10000'],
            'is_pinned' => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('is_pinned') && ! $note->is_pinned) {
            $this->unpinOthers($note->subject);
        }

        $note->fill($validated)->save();

        return back()->with('success', 'Note updated.');
    }

    public function destroyNote(Note $note): RedirectResponse
    {
        // Soft deleted: a note is somebody's words, and an accidental delete
        // should be recoverable.
        $note->delete();

        return back()->with('success', 'Note removed.');
    }

    // --- Tasks --------------------------------------------------------------

    public function storeTask(Request $request): RedirectResponse
    {
        $validated = $this->validateSubject($request, $this->taskRules(), optional: true);

        $subject = isset($validated['subject_type'])
            ? $this->subject($validated['subject_type'], $validated['subject_id'])
            : null;

        $task = Task::create([
            'subject_type' => $subject === null ? null : $subject::class,
            'subject_id' => $subject?->getKey(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => TaskStatus::Open,
            'priority' => TaskPriority::from($validated['priority'] ?? 'normal'),
            'due_at' => $request->date('due_at'),
            'assigned_to' => $validated['assigned_to'] ?? $request->user()?->id,
            'created_by' => $request->user()?->id,
        ]);

        if ($subject !== null) {
            app(ActivityRecorder::class)->record(
                $subject,
                ActivityType::Task,
                "Task created: {$task->title}",
            );
        }

        return back()->with('success', 'Task created.');
    }

    public function updateTask(Request $request, Task $task): RedirectResponse
    {
        $validated = $request->validate([
            ...$this->taskRules(forUpdate: true),
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
        ]);

        $task->fill(array_diff_key($validated, array_flip(['status', 'due_at'])));

        if ($request->has('due_at')) {
            $task->due_at = $request->date('due_at');
        }

        if ($request->filled('status')) {
            $status = TaskStatus::from($request->string('status')->toString());
            $wasOpen = $task->status->isOpen();
            $task->status = $status;

            // Stamped when it stops being outstanding, and cleared if it is
            // reopened — otherwise a reopened task reads as already finished.
            $task->completed_at = $status->isOpen() ? null : ($task->completed_at ?? now());

            if ($wasOpen && $status === TaskStatus::Done && $task->subject !== null) {
                app(ActivityRecorder::class)->record(
                    $task->subject,
                    ActivityType::Task,
                    "Task completed: {$task->title}",
                );
            }
        }

        $task->save();

        return back()->with('success', 'Task updated.');
    }

    public function destroyTask(Task $task): RedirectResponse
    {
        $task->delete();

        return back()->with('success', 'Task removed.');
    }

    // --- Logged activities ---------------------------------------------------

    public function storeActivity(Request $request): RedirectResponse
    {
        $validated = $this->validateSubject($request, [
            // Only the types a person can log: notes, tasks and status changes
            // are recorded as a side effect of doing them, and offering them
            // here would invite two records of one event.
            'type' => ['required', Rule::in(ActivityType::loggableValues())],
            'summary' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
            'occurred_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
        ]);

        $subject = $this->subject($validated['subject_type'], $validated['subject_id']);

        app(ActivityRecorder::class)->record(
            $subject,
            ActivityType::from($validated['type']),
            $validated['summary'],
            $validated['body'] ?? null,
            $request->date('occurred_at'),
            $validated['duration_minutes'] ?? null,
        );

        return back()->with('success', 'Activity logged.');
    }

    // --- Helpers --------------------------------------------------------------

    /**
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, mixed>
     */
    private function validateSubject(Request $request, array $rules, bool $optional = false): array
    {
        $presence = $optional ? 'nullable' : 'required';

        return $request->validate([
            ...$rules,
            'subject_type' => [$presence, Rule::in(SubjectType::values())],
            'subject_id' => [$presence, 'integer'],
        ]);
    }

    /**
     * Resolves the subject through the shared allowlist (ADR-009).
     */
    private function subject(string $type, int $id): Model
    {
        return SubjectType::from($type)->resolve($id);
    }

    /**
     * Clears the pin from the subject's other notes.
     *
     * Exactly one note can be the first thing somebody reads, so pinning a
     * second has to unpin the first rather than leaving two claiming the spot.
     */
    private function unpinOthers(?Model $subject): void
    {
        if ($subject === null) {
            return;
        }

        Note::query()
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->where('is_pinned', true)
            ->update(['is_pinned' => false]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function taskRules(bool $forUpdate = false): array
    {
        $presence = $forUpdate ? 'sometimes' : 'required';

        return [
            'title' => [$presence, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => [
                'nullable',
                'integer',
                // Scoped by hand: `exists` builds its own query on the table,
                // so Eloquent's global tenant scope never runs and a user from
                // another workspace would validate (ADR-009).
                Rule::exists('users', 'id')->where(
                    'tenant_id',
                    app(TenantContext::class)->tenantOrFail()->id,
                ),
            ],
        ];
    }
}
