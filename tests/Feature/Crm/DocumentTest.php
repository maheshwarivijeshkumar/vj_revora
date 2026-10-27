<?php

declare(strict_types=1);

use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Documents\DocumentStorage;
use App\Domain\Tenancy\TenantContext;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Document;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Document attachments
|--------------------------------------------------------------------------
|
| §21, §55. Files hang off any entity polymorphically, land on a private disk,
| and come back out only through a controller that has checked the session, the
| tenant and the permission.
|
*/

beforeEach(function (): void {
    Storage::fake('local');

    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    Queue::fake();

    $this->seed(PermissionSeeder::class);

    $role = Role::findOrCreate('Rep', 'web');
    $role->syncPermissions([
        'lead.view', 'document.view', 'document.create', 'document.delete',
    ]);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);

    $this->lead = Lead::factory()->create(['tenant_id' => $this->tenant->id]);
});

/**
 * A real upload, not `UploadedFile::fake()`.
 *
 * The fake overrides `getMimeType()` to return whatever type it was told to
 * claim, so it cannot exercise the check that matters here: Laravel's
 * `mimetypes` rule reads the file's actual contents. These tests build the real
 * class over a real file, which is the code path production takes.
 */
function realUpload(string $name, string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $contents);

    return new UploadedFile($path, $name, null, null, test: true);
}

/** A 1x1 PNG, so there is genuine image data behind the extension. */
function pngBytes(): string
{
    return (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8AAAwAB/gFn3lP8AAAAAElFTkSuQmCC',
        true,
    );
}

function attachFile(?UploadedFile $file = null, array $overrides = []): TestResponse
{
    return test()->actingAs(test()->user)
        ->from('/leads/'.test()->lead->id)
        ->post('/documents', [
            'subject_type' => 'lead',
            'subject_id' => test()->lead->id,
            'file' => $file ?? UploadedFile::fake()->create('contract.pdf', 64, 'application/pdf'),
            ...$overrides,
        ]);
}

// --- Uploading ---------------------------------------------------------------

it('attaches a file to a lead and stores it on the private disk', function (): void {
    attachFile()->assertRedirect('/leads/'.$this->lead->id);

    $document = Document::query()->sole();

    expect($document->subject_type)->toBe(Lead::class)
        ->and($document->subject_id)->toBe($this->lead->id)
        ->and($document->tenant_id)->toBe($this->tenant->id)
        ->and($document->original_name)->toBe('contract.pdf')
        ->and($document->uploaded_by)->toBe($this->user->id);

    Storage::disk('local')->assertExists($document->path);
});

it('never puts anything user-supplied in the stored path', function (): void {
    // The sort of name an attacker submits: traversal, a second extension, and
    // characters that would break a response header.
    attachFile(UploadedFile::fake()->create('../../../.env.php"evil.pdf', 8, 'application/pdf'));

    $document = Document::query()->sole();

    expect($document->path)->toBe("documents/{$this->tenant->id}/{$document->uuid}.pdf")
        ->and($document->original_name)->not->toContain('..')
        ->and($document->original_name)->not->toContain('"');
});

it('keeps each tenant files in their own directory', function (): void {
    attachFile();

    expect(Document::query()->sole()->path)
        ->toStartWith("documents/{$this->tenant->id}/");
});

it('records the detected mime type, not the one the client claimed', function (): void {
    // A real PNG arriving under a .pdf name. The row has to describe the file
    // that was actually stored, not the name it arrived under.
    attachFile(realUpload('logo.pdf', pngBytes()));

    $document = Document::query()->sole();

    expect($document->mime_type)->toBe('image/png')
        // And the stored path takes the detected extension, so nothing on disk
        // is named for a type it is not.
        ->and($document->path)->toEndWith('.png');
});

it('shows the attachment in the record timeline', function (): void {
    attachFile();

    $activity = Activity::query()->sole();

    expect($activity->type)->toBe(ActivityType::Document)
        ->and($activity->summary)->toContain('contract.pdf')
        ->and($activity->subject_id)->toBe($this->lead->id);
});

it('uses the given title in the timeline entry and the list', function (): void {
    attachFile(null, ['title' => 'Signed MSA']);

    expect(Activity::query()->sole()->summary)->toContain('Signed MSA')
        ->and(Document::query()->sole()->displayName())->toBe('Signed MSA');
});

// --- What is refused ---------------------------------------------------------

it('refuses a file type that is not on the allowlist', function (): void {
    attachFile(UploadedFile::fake()->create('payload.php', 4, 'text/x-php'))
        ->assertSessionHasErrors('file');

    expect(Document::query()->count())->toBe(0);
});

it('refuses an svg, because it carries script', function (): void {
    attachFile(UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'))
        ->assertSessionHasErrors('file');

    expect(Document::query()->count())->toBe(0);
});

it('refuses a script renamed to an allowed extension', function (): void {
    // The submitted name passes the extension list; `mimetypes` reads the
    // contents and refuses it. This is the check an allowlist of extensions
    // alone would miss.
    attachFile(realUpload('invoice.pdf', '<?php echo shell_exec($_GET["c"]); ?>'))
        ->assertSessionHasErrors('file');

    expect(Document::query()->count())->toBe(0);
});

it('refuses an html file renamed to an image, which would be stored xss', function (): void {
    attachFile(realUpload('avatar.png', '<html><script>alert(document.cookie)</script>'))
        ->assertSessionHasErrors('file');

    expect(Document::query()->count())->toBe(0);
});

it('refuses a file over the size limit', function (): void {
    $overLimit = (int) config('documents.max_size_kb') + 1;

    attachFile(UploadedFile::fake()->create('big.pdf', $overLimit, 'application/pdf'))
        ->assertSessionHasErrors('file');
});

it('refuses a subject type outside the allowlist', function (): void {
    attachFile(null, ['subject_type' => 'user'])->assertSessionHasErrors('subject_type');

    attachFile(null, ['subject_type' => User::class])->assertSessionHasErrors('subject_type');

    expect(Document::query()->count())->toBe(0);
});

it('does not attach to a record from another workspace', function (): void {
    $other = Tenant::factory()->create();
    $theirLead = Lead::factory()->create(['tenant_id' => $other->id]);

    test()->actingAs($this->user)
        ->post('/documents', [
            'subject_type' => 'lead',
            'subject_id' => $theirLead->id,
            'file' => UploadedFile::fake()->create('contract.pdf', 8, 'application/pdf'),
        ])
        ->assertNotFound();

    expect(Document::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('returns the existing row when the same file is attached twice', function (): void {
    // A double-clicked button, not two documents.
    attachFile(UploadedFile::fake()->createWithContent('contract.pdf', 'identical bytes'));
    attachFile(UploadedFile::fake()->createWithContent('contract.pdf', 'identical bytes'));

    expect(Document::query()->count())->toBe(1);
});

it('keeps both when the files differ', function (): void {
    attachFile(UploadedFile::fake()->createWithContent('a.pdf', 'first'));
    attachFile(UploadedFile::fake()->createWithContent('b.pdf', 'second'));

    expect(Document::query()->count())->toBe(2);
});

// --- Downloading -------------------------------------------------------------

it('streams a stored file back as an attachment', function (): void {
    attachFile();

    $document = Document::query()->sole();

    $response = $this->actingAs($this->user)->get("/documents/{$document->uuid}");

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))
        ->toStartWith('attachment')
        ->toContain('contract.pdf');
});

it('allows inline viewing only for types on the inline allowlist', function (): void {
    attachFile(UploadedFile::fake()->image('plan.png'));
    $image = Document::query()->sole();

    expect(
        $this->actingAs($this->user)
            ->get("/documents/{$image->uuid}?inline=1")
            ->headers->get('Content-Disposition')
    )->toStartWith('inline');
});

it('downloads a non-previewable type even when inline is asked for', function (): void {
    // Otherwise a crafted upload renders on this origin, with this session.
    attachFile(UploadedFile::fake()->create('notes.txt', 4, 'text/plain'));
    $document = Document::query()->sole();

    expect($document->isInlineSafe())->toBeFalse()
        ->and(
            $this->actingAs($this->user)
                ->get("/documents/{$document->uuid}?inline=1")
                ->headers->get('Content-Disposition')
        )->toStartWith('attachment');
});

it('does not serve a file belonging to another workspace', function (): void {
    attachFile();
    $document = Document::query()->sole();

    $other = Tenant::factory()->create();
    app(TenantContext::class)->set($other);
    app(PermissionRegistrar::class)->setPermissionsTeamId($other->id);

    $role = Role::findOrCreate('Rep', 'web');
    $role->syncPermissions(['document.view']);
    $intruder = User::factory()->create(['tenant_id' => $other->id]);
    $intruder->assignRole($role);

    // The uuid is correct and the permission is held; the tenant scope is what
    // refuses it (ADR-009).
    $this->actingAs($intruder)->get("/documents/{$document->uuid}")->assertNotFound();
});

it('refuses a download without the view permission', function (): void {
    attachFile();
    $document = Document::query()->sole();

    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($stranger)->get("/documents/{$document->uuid}")->assertForbidden();
});

it('404s when the row survives but the file is gone', function (): void {
    attachFile();
    $document = Document::query()->sole();

    Storage::disk('local')->delete($document->path);

    $this->actingAs($this->user)->get("/documents/{$document->uuid}")->assertNotFound();
});

// --- Removing ----------------------------------------------------------------

it('soft deletes the row and keeps the file so a restore is possible', function (): void {
    attachFile();
    $document = Document::query()->sole();

    $this->actingAs($this->user)
        ->from('/leads/'.$this->lead->id)
        ->delete("/documents/{$document->uuid}")
        ->assertRedirect('/leads/'.$this->lead->id);

    expect(Document::query()->count())->toBe(0)
        ->and(Document::query()->withTrashed()->count())->toBe(1);

    Storage::disk('local')->assertExists($document->path);
});

it('removes the bytes only on a purge', function (): void {
    attachFile();
    $document = Document::query()->sole();

    app(DocumentStorage::class)->purge($document);

    Storage::disk('local')->assertMissing($document->path);

    expect(Document::query()->withTrashed()->count())->toBe(0);
});

// --- Attaching to other entities ---------------------------------------------

it('attaches to a company just as readily as to a lead', function (): void {
    $company = Company::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->user)
        ->from('/companies')
        ->post('/documents', [
            'subject_type' => 'company',
            'subject_id' => $company->id,
            'file' => UploadedFile::fake()->create('terms.pdf', 8, 'application/pdf'),
        ])
        ->assertRedirect('/companies');

    expect($company->documents()->count())->toBe(1);
});

// --- Presentation ------------------------------------------------------------

it('shows the files tab on the lead screen', function (): void {
    attachFile(null, ['title' => 'Signed MSA']);

    $this->actingAs($this->user)
        ->get('/leads/'.$this->lead->id)
        ->assertInertia(fn ($page) => $page
            ->has('documents', 1)
            ->where('documents.0.name', 'Signed MSA')
            ->where('documents.0.can_preview', true));
});

it('reports a size a person can read', function (): void {
    attachFile();

    expect(Document::query()->sole()->sizeForHumans())->toBe('64 KB');
});

// --- Scheduled purge ---------------------------------------------------------

it('purges files whose rows were removed outside the recovery window', function (): void {
    attachFile();
    $document = Document::query()->sole();
    $document->delete();
    // Older than the default 30-day window.
    $document->forceFill(['deleted_at' => now()->subDays(40)])->saveQuietly();

    $this->artisan('documents:purge')->assertSuccessful();

    Storage::disk('local')->assertMissing($document->path);

    expect(Document::query()->withTrashed()->count())->toBe(0);
});

it('leaves a recently removed document recoverable', function (): void {
    attachFile();
    $document = Document::query()->sole();
    $document->delete();

    $this->artisan('documents:purge')->assertSuccessful();

    Storage::disk('local')->assertExists($document->path);

    expect(Document::query()->withTrashed()->count())->toBe(1);
});

it('leaves a live document alone however old it is', function (): void {
    attachFile();
    $document = Document::query()->sole();
    $document->forceFill(['created_at' => now()->subYears(3)])->saveQuietly();

    $this->artisan('documents:purge')->assertSuccessful();

    Storage::disk('local')->assertExists($document->path);
});

it('deletes nothing on a dry run', function (): void {
    attachFile();
    $document = Document::query()->sole();
    $document->delete();
    $document->forceFill(['deleted_at' => now()->subDays(40)])->saveQuietly();

    $this->artisan('documents:purge', ['--dry-run' => true])->assertSuccessful();

    Storage::disk('local')->assertExists($document->path);

    expect(Document::query()->withTrashed()->count())->toBe(1);
});

it('refuses a purge with no grace period', function (): void {
    // --days=0 would make every delete immediate, which is not a soft delete.
    $this->artisan('documents:purge', ['--days' => 0])->assertFailed();
});

it('purges across every workspace, since storage is shared', function (): void {
    attachFile();
    $ours = Document::query()->sole();
    $ours->delete();
    $ours->forceFill(['deleted_at' => now()->subDays(40)])->saveQuietly();

    $other = Tenant::factory()->create();
    app(TenantContext::class)->set($other);
    $theirLead = Lead::factory()->create(['tenant_id' => $other->id]);
    $theirs = app(DocumentStorage::class)->store(
        $theirLead,
        UploadedFile::fake()->create('theirs.pdf', 8, 'application/pdf'),
    );
    $theirs->delete();
    $theirs->forceFill(['deleted_at' => now()->subDays(40)])->saveQuietly();

    $this->artisan('documents:purge')->assertSuccessful();

    Storage::disk('local')->assertMissing($ours->path);
    Storage::disk('local')->assertMissing($theirs->path);
});
