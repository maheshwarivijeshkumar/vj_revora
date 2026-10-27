<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Activity\Enums\TaskPriority;
use App\Domain\Activity\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shapes a record's timeline for the UI (§21, §44).
 *
 * One presenter for every entity, so the lead screen and the deal screen cannot
 * drift into showing the same data differently — which is how two pages end up
 * disagreeing about what "overdue" means.
 */
final class TimelinePresenter
{
    /** Enough history to tell the story without paging a tab nobody scrolls. */
    private const LIMIT = 50;

    /**
     * @return array<string, mixed>
     */
    public function forSubject(Model $subject): array
    {
        return [
            'activities' => $this->activities($subject),
            'notes' => $this->notes($subject),
            'tasks' => $this->tasks($subject),
        ];
    }

    /**
     * The option lists the composers need.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            // Only the types a person can log by hand.
            'activityTypes' => ActivityType::loggableOptions(),
            'taskStatuses' => TaskStatus::options(),
            'taskPriorities' => TaskPriority::options(),
            'owners' => User::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u): array => ['value' => (string) $u->id, 'label' => $u->name])
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activities(Model $subject): array
    {
        return Activity::query()
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->with('user:id,name')
            // Newest first: a history is read backwards from what just
            // happened. Id breaks ties within the same second.
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Activity $activity): array => [
                'id' => $activity->id,
                'type' => $activity->type->value,
                'type_label' => $activity->type->label(),
                'summary' => $activity->summary,
                'body' => $activity->body,
                'user' => $activity->user?->name,
                'duration_minutes' => $activity->duration_minutes,
                'occurred_at' => $activity->occurred_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function notes(Model $subject): array
    {
        return Note::query()
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->with('user:id,name')
            ->displayOrder()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Note $note): array => [
                'id' => $note->id,
                'body' => $note->body,
                'is_pinned' => $note->is_pinned,
                'user' => $note->user?->name,
                'created_at' => $note->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Outstanding tasks first, then recently finished ones.
     *
     * Finished tasks are kept visible rather than disappearing: "did anyone
     * actually send that proposal" is a question the record has to answer.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tasks(Model $subject): array
    {
        $outstanding = Task::query()
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->with('assignee:id,name')
            ->outstanding()
            ->get();

        $finished = Task::query()
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->with('assignee:id,name')
            ->whereNotIn('status', [TaskStatus::Open->value, TaskStatus::InProgress->value])
            ->orderByDesc('completed_at')
            ->limit(10)
            ->get();

        return $outstanding
            ->concat($finished)
            ->map(fn (Task $task): array => [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status->value,
                'status_label' => $task->status->label(),
                'status_tone' => $task->status->tone(),
                'priority' => $task->priority->value,
                'priority_label' => $task->priority->label(),
                'due_at' => $task->due_at?->toIso8601String(),
                // Computed once here so every screen agrees what overdue means.
                'is_overdue' => $task->isOverdue(),
                'assignee' => $task->assignee?->name,
            ])
            ->values()
            ->all();
    }
}
