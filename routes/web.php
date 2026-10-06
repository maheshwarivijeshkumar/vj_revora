<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Branding\BrandPreviewController;
use App\Http\Controllers\Crm\BulkLeadController;
use App\Http\Controllers\Crm\CompanyController;
use App\Http\Controllers\Crm\CompanyWriteController;
use App\Http\Controllers\Crm\ContactController;
use App\Http\Controllers\Crm\ContactWriteController;
use App\Http\Controllers\Crm\DealBoardController;
use App\Http\Controllers\Crm\DealWriteController;
use App\Http\Controllers\Crm\LeadController;
use App\Http\Controllers\Crm\LeadDetailController;
use App\Http\Controllers\Crm\LeadWriteController;
use App\Http\Controllers\Marketing\ContactController as ContactEnquiryController;
use App\Http\Controllers\Marketing\WaitlistController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\ApiKeyController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\WebhookController;
use App\Http\Controllers\Site\ComingSoonController;
use App\Http\Controllers\Site\ManifestController;
use App\Http\Controllers\Site\MarketingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
|
| Inertia drives the internal UI (ADR-001). Controllers here call the same
| domain actions the /api/v1 controllers use, so the UI and the public API
| cannot drift apart.
|
*/

// --- Marketing site ---------------------------------------------------------
//
// SITE_MODE decides what "/" is. Both the coming-soon page and the full site
// stay routable either way, so marketing copy can be reviewed before launch
// without exposing it publicly.

Route::controller(MarketingController::class)->group(function (): void {
    if (config('site.mode') === 'live') {
        Route::get('/', 'home')->name('home');
    } else {
        Route::get('/', ComingSoonController::class)->name('home');
        Route::get('/home', 'home')->name('landing');
    }

    Route::get('/product', 'product')->name('product');
    Route::get('/solutions', 'solutions')->name('solutions');
    Route::get('/integrations', 'integrations')->name('integrations');
    Route::get('/pricing', 'pricing')->name('pricing');
    Route::get('/security', 'security')->name('security');
    Route::get('/developers', 'developers')->name('developers');
    Route::get('/about', 'about')->name('about');
    Route::get('/contact', 'contact')->name('contact');
});

if (config('site.mode') === 'live') {
    Route::get('/coming-soon', ComingSoonController::class)->name('coming-soon');
}

Route::post('/waitlist', [WaitlistController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('waitlist.store');

Route::post('/contact', [ContactEnquiryController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('contact.store');

Route::get('/site.webmanifest', ManifestController::class)->name('manifest');

// --- Brand selection --------------------------------------------------------
// Live preview of the eight candidate identities, so the final logo can be
// chosen against the real interface. Disabled by BRAND_PREVIEW=false.
//
// Served at /branding, not /brand: the latter collides with the public/brand/
// asset directory, which web servers resolve before the application ever runs.

Route::controller(BrandPreviewController::class)->group(function (): void {
    Route::get('/branding', 'index')->name('brand.index');
    Route::post('/branding', 'update')->name('brand.update');
    Route::delete('/branding', 'destroy')->name('brand.destroy');
});

// --- Auth -------------------------------------------------------------------

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1');
});

// --- Application ------------------------------------------------------------

Route::middleware(['auth', 'tenant.active'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::inertia('/dashboard', 'Dashboard')->name('dashboard');

    // Global search (§45, §120). No permission of its own: the service searches
    // only the entities the user may view, so an empty result is the right
    // answer for someone with none of them.
    Route::get('/search', SearchController::class)
        ->middleware('throttle:60,1')
        ->name('search');

    // --- CRM ---------------------------------------------------------------

    Route::get('/leads', [LeadController::class, 'index'])
        ->middleware('permission:lead.view')
        ->name('leads.index');

    // Declared before the write routes so `/leads/bulk` is not swallowed by
    // the `{lead}` parameter, and after the index so `/leads` still lists.
    Route::get('/leads/{lead}', [LeadDetailController::class, 'show'])
        ->middleware('permission:lead.view')
        ->name('leads.show');

    Route::post('/leads', [LeadWriteController::class, 'store'])
        ->middleware('permission:lead.create')
        ->name('leads.store');

    Route::patch('/leads/{lead}', [LeadWriteController::class, 'update'])
        ->middleware('permission:lead.update')
        ->name('leads.update');

    Route::delete('/leads/{lead}', [LeadWriteController::class, 'destroy'])
        ->middleware('permission:lead.delete')
        ->name('leads.destroy');

    // Bulk actions (§113). The permission depends on the action in the body, so
    // the route carries only lead.view and the controller checks the rest.
    Route::post('/leads/bulk', [BulkLeadController::class, 'store'])
        ->middleware('permission:lead.view')
        ->name('leads.bulk');

    Route::get('/bulk-operations/{operation}', [BulkLeadController::class, 'show'])
        ->middleware('permission:lead.view')
        ->name('bulk-operations.show');

    Route::get('/contacts', [ContactController::class, 'index'])
        ->middleware('permission:contact.view')
        ->name('contacts.index');

    Route::post('/contacts', [ContactWriteController::class, 'store'])
        ->middleware('permission:contact.create')
        ->name('contacts.store');

    Route::patch('/contacts/{contact}', [ContactWriteController::class, 'update'])
        ->middleware('permission:contact.update')
        ->name('contacts.update');

    Route::delete('/contacts/{contact}', [ContactWriteController::class, 'destroy'])
        ->middleware('permission:contact.delete')
        ->name('contacts.destroy');

    Route::get('/companies', [CompanyController::class, 'index'])
        ->middleware('permission:company.view')
        ->name('companies.index');

    Route::post('/companies', [CompanyWriteController::class, 'store'])
        ->middleware('permission:company.create')
        ->name('companies.store');

    Route::patch('/companies/{company}', [CompanyWriteController::class, 'update'])
        ->middleware('permission:company.update')
        ->name('companies.update');

    Route::delete('/companies/{company}', [CompanyWriteController::class, 'destroy'])
        ->middleware('permission:company.delete')
        ->name('companies.destroy');

    Route::get('/deals', [DealBoardController::class, 'index'])
        ->middleware('permission:deal.view')
        ->name('deals.index');

    Route::post('/deals', [DealWriteController::class, 'store'])
        ->middleware('permission:deal.create')
        ->name('deals.store');

    Route::patch('/deals/{deal}', [DealWriteController::class, 'update'])
        ->middleware('permission:deal.update')
        ->name('deals.update');

    Route::delete('/deals/{deal}', [DealWriteController::class, 'destroy'])
        ->middleware('permission:deal.delete')
        ->name('deals.destroy');

    Route::post('/deals/{deal}/move', [DealBoardController::class, 'move'])
        ->middleware('permission:deal.update')
        ->name('deals.move');

    // --- Settings ----------------------------------------------------------

    Route::get('/settings/api-keys', [ApiKeyController::class, 'index'])
        ->middleware('permission:api.view')
        ->name('settings.api-keys');

    Route::post('/settings/api-keys', [ApiKeyController::class, 'store'])
        ->middleware('permission:api.create')
        ->name('settings.api-keys.store');

    Route::delete('/settings/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])
        ->middleware('permission:api.revoke')
        ->name('settings.api-keys.destroy');

    Route::get('/settings/audit', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view')
        ->name('settings.audit');

    Route::get('/settings/webhooks', [WebhookController::class, 'index'])
        ->middleware('permission:webhook.view')
        ->name('settings.webhooks');

    Route::middleware('permission:webhook.manage')->group(function (): void {
        Route::post('/settings/webhooks', [WebhookController::class, 'store'])
            ->name('settings.webhooks.store');

        Route::patch('/settings/webhooks/{endpoint}', [WebhookController::class, 'update'])
            ->name('settings.webhooks.update');

        Route::delete('/settings/webhooks/{endpoint}', [WebhookController::class, 'destroy'])
            ->name('settings.webhooks.destroy');

        Route::post('/settings/webhooks/{endpoint}/test', [WebhookController::class, 'test'])
            ->name('settings.webhooks.test');

        Route::post('/settings/webhooks/deliveries/{delivery}/replay', [WebhookController::class, 'replay'])
            ->name('settings.webhooks.replay');
    });
});
