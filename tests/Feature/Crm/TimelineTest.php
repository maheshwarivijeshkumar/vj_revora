<?php

declare(strict_types=1);

use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Activity\Enums\TaskPriority;
use App\Domain\Activity\Enums\TaskStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Note;
use App\Models\Pipeline;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Notes, tasks and logged activities
|--------------------------------------------------------------------------
|
| §21. All three hang off any entity polymorphically, so one implementation
| serves leads, contacts, companies and deals.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    Queue::fake();

    $this->seed(PermissionSeeder::class);

    $role = Role::findOrCreate('Rep', 'web');
    $role->syncPermissions([
        'lead.view', 'note.create', 'note.update', 'note.delete',
        'task.create', 'task.update', 'task.delete', 'activity.create',
    ]);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);

    $this->lead = Lead::factory()->create(['tenant_id' => $this->tenant->id]);
});

function postNote(array $overrides = []): TestResponse
{
    return test()->actingAs(test()->user)
        ->from('/leads/'.test()->lead->id)
        ->post('/notes', [
            'subject_type' => 'lead',
            'subject_id' => test()->lead->id,
            'body' => 'Spoke to procurement; budget confirmed.',
            ...$overrides,
        ]);
}

function postTask(array $overrides = []): TestResponse
{
    return test()->actingAs(test()->user)
        ->post('/tasks', [
            'subject_type' => 'lead',
            'subject_id' => test()->lead->id,
            'title' => 'Send the proposal',
            ...$overrides,
        ]);
}

// --- Access ---------------------------------------------------------------------

it('gates each kind of timeline write separately', function (): void {
    $viewer = Role::findOrCreate('Viewer', 'web');
    $viewer->syncPermissions(['lead.view']);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole($viewer);

    $this->actingAs($user)->post('/notes', [
        'subject_type' => 'lead', 'subject_id' => $this->lead->id, 'body' => 'No',
    ])->assertForbidden();

    $this->actingAs($user)->post('/tasks', [
        'subject_type' => 'lead', 'subject_id' => $this->lead->id, 'title' => 'No',
    ])->assertForbidden();

    $this->actingAs($user)->post('/activities', [
        'subject_type' => 'lead', 'subject_id' => $this->lead->id,
        'type' => 'call', 'summary' => 'No',
    ])->assertForbidden();
});

// --- The subject allowlist ------------------------------------------------------

it('hangs a note off any of the four entities', function (): void {
    $pipeline = Pipeline::factory()->withDefaultStages()->create(['tenant_id' => $this->tenant->id]);

    $subjects = [
        'lead' => $this->lead,
        'contact' => Contact::factory()->create(['tenant_id' => $this->tenant->id]),
        'company' => Company::factory()->create(['tenant_id' => $this->tenant->id]),
        'deal' => Deal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $pipeline->firstStage()->id,
        ]),
    ];

    foreach ($subjects as $type => $subject) {
        postNote(['subject_type' => $type, 'subject_id' => $subject->id])
            ->assertSessionHasNoErrors();

        // One implementation for all four, so none of them quietly ends up
        // without notes.
        expect($subject->notes()->count())->toBe(1);
    }
});

it('refuses a subject type that is not on the allowlist', function (): void {
    // Resolving subject_type from the request would turn this into a way to
    // write rows against User, ApiKey or anything else that exists.
    postNote(['subject_type' => 'user'])->assertSessionHasErrors('subject_type');
    postNote(['subject_type' => 'App\\Models\\User'])
        ->assertSessionHasErrors('subject_type');

    expect(Note::count())->toBe(0);
});

it('refuses a subject from another workspace', function (): void {
    $other = Tenant::factory()->create();

    $foreign = app(TenantContext::class)->runAs(
        $other,
        fn () => Lead::factory()->create(['tenant_id' => $other->id]),
    );

    postNote(['subject_id' => $foreign->id])->assertNotFound();

    expect(Note::count())->toBe(0);
});

// --- Notes ----------------------------------------------------------------------

it('creates a note against the signed-in user', function (): void {
    postNote()->assertSessionHas('success');

    $note = Note::sole();

    expect($note->body)->toBe('Spoke to procurement; budget confirmed.')
        ->and($note->user_id)->toBe($this->user->id)
        ->and($note->subject_type)->toBe(Lead::class)
        ->and($note->subject_id)->toBe($this->lead->id);
});

it('shows a note in the timeline as well as the notes list', function (): void {
    postNote();

    // One list, rather than three the reader has to merge in their head.
    $activity = Activity::sole();

    expect($activity->type)->toBe(ActivityType::Note)
        ->and($activity->summary)->toBe('Note added')
        ->and($activity->user_id)->toBe($this->user->id);
});

it('keeps exactly one note pinned', function (): void {
    postNote(['body' => 'First', 'is_pinned' => true]);
    postNote(['body' => 'Second', 'is_pinned' => true]);

    // Exactly one note can be the first thing somebody reads, so pinning a
    // second unpins the first rather than leaving two claiming the spot.
    expect(Note::query()->where('is_pinned', true)->count())->toBe(1)
        ->and(Note::query()->where('is_pinned', true)->sole()->body)->toBe('Second');
});

it('orders pinned notes first, then newest', function (): void {
    postNote(['body' => 'Oldest']);
    postNote(['body' => 'Pinned', 'is_pinned' => true]);
    postNote(['body' => 'Newest']);

    $bodies = Note::query()->displayOrder()->pluck('body')->all();

    expect($bodies)->toBe(['Pinned', 'Newest', 'Oldest']);
});

it('edits and soft deletes a note', function (): void {
    postNote();
    $note = Note::sole();

    $this->actingAs($this->user)
        ->patch("/notes/{$note->id}", ['body' => 'Corrected'])
        ->assertSessionHas('success');

    expect($note->refresh()->body)->toBe('Corrected');

    $this->actingAs($this->user)->delete("/notes/{$note->id}");

    // A note is somebody's words; an accidental delete should be recoverable.
    expect(Note::count())->toBe(0)
        ->and(Note::withTrashed()->count())->toBe(1);
});

it('requires a body', function (): void {
    postNote(['body' => ''])->assertSessionHasErrors('body');
});

// --- Tasks ----------------------------------------------------------------------

it('creates a task assigned to the author by default', function (): void {
    postTask()->assertSessionHas('success');

    $task = Task::sole();

    expect($task->title)->toBe('Send the proposal')
        ->and($task->status)->toBe(TaskStatus::Open)
        ->and($task->priority)->toBe(TaskPriority::Normal)
        // Whoever wrote it down is the likeliest person to do it; making them
        // choose themselves every time is friction for nothing.
        ->and($task->assigned_to)->toBe($this->user->id)
        ->and($task->created_by)->toBe($this->user->id);
});

it('creates a task with no subject at all', function (): void {
    // "Call the new starters" belongs to nobody in particular, and forcing a
    // subject would push people to attach it to something arbitrary.
    $this->actingAs($this->user)
        ->post('/tasks', ['title' => 'Call the new starters'])
        ->assertSessionHasNoErrors();

    expect(Task::sole()->subject_type)->toBeNull();
});

it('stamps completion and clears it when reopened', function (): void {
    postTask();
    $task = Task::sole();

    $this->actingAs($this->user)->patch("/tasks/{$task->uuid}", ['status' => 'done']);

    expect($task->refresh()->completed_at)->not->toBeNull();

    $this->actingAs($this->user)->patch("/tasks/{$task->uuid}", ['status' => 'open']);

    // A reopened task that still reads as finished is worse than no stamp.
    expect($task->refresh()->completed_at)->toBeNull();
});

it('records completing a task on the timeline, once', function (): void {
    postTask();
    $task = Task::sole();

    $this->actingAs($this->user)->patch("/tasks/{$task->uuid}", ['status' => 'done']);
    $this->actingAs($this->user)->patch("/tasks/{$task->uuid}", ['status' => 'done']);

    // Saving an already-done task is not a second completion.
    expect(Activity::query()->where('summary', 'like', 'Task completed%')->count())
        ->toBe(1);
});

it('does not call a finished task overdue', function (): void {
    postTask(['due_at' => now()->subWeek()->toDateString()]);
    $task = Task::sole();

    expect($task->isOverdue())->toBeTrue();

    $this->actingAs($this->user)->patch("/tasks/{$task->uuid}", ['status' => 'done']);

    // However late it was finished: otherwise a completed list fills with red
    // nobody can clear.
    expect($task->refresh()->isOverdue())->toBeFalse();
});

it('sorts outstanding tasks by urgency, undated last', function (): void {
    postTask(['title' => 'Undated low', 'priority' => 'low']);
    postTask(['title' => 'Next week', 'due_at' => now()->addWeek()->toDateString()]);
    postTask(['title' => 'Overdue', 'due_at' => now()->subDay()->toDateString()]);
    postTask(['title' => 'High today', 'priority' => 'high', 'due_at' => now()->toDateString()]);

    $titles = Task::query()->outstanding()->pluck('title')->all();

    // Dated before undated, then priority, then date.
    expect($titles[3])->toBe('Undated low')
        ->and($titles)->toContain('Overdue', 'High today', 'Next week');
});

it('excludes finished tasks from outstanding', function (): void {
    postTask(['title' => 'Open one']);
    postTask(['title' => 'Cancelled one']);

    $cancelled = Task::query()->where('title', 'Cancelled one')->sole();

    $this->actingAs($this->user)
        ->patch("/tasks/{$cancelled->uuid}", ['status' => 'cancelled']);

    // Cancelled counts as finished: it was decided, and a decision is not
    // outstanding work.
    expect(Task::query()->outstanding()->pluck('title')->all())->toBe(['Open one']);
});

it('refuses an assignee from another workspace', function (): void {
    $other = Tenant::factory()->create();
    $stranger = User::factory()->create(['tenant_id' => $other->id]);

    postTask(['assigned_to' => $stranger->id])->assertSessionHasErrors('assigned_to');
});

it('requires a title', function (): void {
    postTask(['title' => ''])->assertSessionHasErrors('title');
});

// --- Logged activities ------------------------------------------------------------

it('logs a call with a duration and a backdated time', function (): void {
    $this->actingAs($this->user)->post('/activities', [
        'subject_type' => 'lead',
        'subject_id' => $this->lead->id,
        'type' => 'call',
        'summary' => 'Discovery call',
        'duration_minutes' => 25,
        'occurred_at' => now()->subDays(3)->toDateTimeString(),
    ])->assertSessionHas('success');

    $activity = Activity::sole();

    // A call logged on Monday for a conversation on Friday belongs on Friday.
    expect($activity->type)->toBe(ActivityType::Call)
        ->and($activity->duration_minutes)->toBe(25)
        ->and($activity->occurred_at->isBefore(now()->subDays(2)))->toBeTrue();
});

it('refuses to log a type that records itself', function (): void {
    // Notes, tasks and status changes are written as a side effect of doing
    // them; offering them here would invite two records of one event.
    foreach (['note', 'task', 'status_change'] as $type) {
        $this->actingAs($this->user)->post('/activities', [
            'subject_type' => 'lead',
            'subject_id' => $this->lead->id,
            'type' => $type,
            'summary' => 'Nope',
        ])->assertSessionHasErrors('type');
    }

    expect(Activity::count())->toBe(0);
});

it('bounds a logged duration to a day', function (): void {
    $this->actingAs($this->user)->post('/activities', [
        'subject_type' => 'lead',
        'subject_id' => $this->lead->id,
        'type' => 'meeting',
        'summary' => 'Marathon',
        'duration_minutes' => 5000,
    ])->assertSessionHasErrors('duration_minutes');
});

// --- Isolation -------------------------------------------------------------------

it('never shows one workspace timeline to another', function (): void {
    postNote();
    postTask();

    $other = Tenant::factory()->create();

    app(TenantContext::class)->runAs($other, function (): void {
        expect(Note::count())->toBe(0)
            ->and(Task::count())->toBe(0)
            ->and(Activity::count())->toBe(0);
    });
});
