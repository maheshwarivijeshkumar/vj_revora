<?php

declare(strict_types=1);

use App\Http\Controllers\Account\NotificationController;
use App\Http\Controllers\Account\PresenceController;
use App\Http\Controllers\Account\TimezoneController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Branding\BrandPreviewController;
use App\Http\Controllers\Crm\BulkLeadController;
use App\Http\Controllers\Crm\BusinessDiscoveryController;
use App\Http\Controllers\Crm\CalendarController;
use App\Http\Controllers\Crm\CampaignController;
use App\Http\Controllers\Crm\ChangeOrderController;
use App\Http\Controllers\Crm\ClientController;
use App\Http\Controllers\Crm\CompanyController;
use App\Http\Controllers\Crm\CompanyWriteController;
use App\Http\Controllers\Crm\ContactController;
use App\Http\Controllers\Crm\ContactWriteController;
use App\Http\Controllers\Crm\DealBoardController;
use App\Http\Controllers\Crm\DealDetailController;
use App\Http\Controllers\Crm\DealWriteController;
use App\Http\Controllers\Crm\DirectoryController;
use App\Http\Controllers\Crm\DocumentController;
use App\Http\Controllers\Crm\DocumentLibraryController;
use App\Http\Controllers\Crm\EstimateController;
use App\Http\Controllers\Crm\InvoiceController;
use App\Http\Controllers\Crm\LeadClientController;
use App\Http\Controllers\Crm\LeadController;
use App\Http\Controllers\Crm\LeadConversationController;
use App\Http\Controllers\Crm\LeadDetailController;
use App\Http\Controllers\Crm\LeadExportController;
use App\Http\Controllers\Crm\LeadWriteController;
use App\Http\Controllers\Crm\MessageInboxController;
use App\Http\Controllers\Crm\OperationsBulkController;
use App\Http\Controllers\Crm\OpportunityController;
use App\Http\Controllers\Crm\ProjectController;
use App\Http\Controllers\Crm\ReportController;
use App\Http\Controllers\Crm\ScheduleController;
use App\Http\Controllers\Crm\TenderController;
use App\Http\Controllers\Crm\TimelineController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\Marketing\ContactController as ContactEnquiryController;
use App\Http\Controllers\Marketing\WaitlistController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\ApiKeyController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\EmailAccountController;
use App\Http\Controllers\Settings\FollowUpController;
use App\Http\Controllers\Settings\ServiceProfileController;
use App\Http\Controllers\Settings\SetupController;
use App\Http\Controllers\Settings\UpworkController;
use App\Http\Controllers\Settings\WebhookController;
use App\Http\Controllers\Settings\WhatsAppController;
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

    Route::patch('/account/timezone', [TimezoneController::class, 'update'])
        ->name('account.timezone');

    Route::patch('/account/presence', [PresenceController::class, 'update'])
        ->name('account.presence');

    Route::patch('/account/notifications/{notification}', [NotificationController::class, 'update'])
        ->name('account.notifications.read');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

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

    // Public procurement notices. Not workspace data, but read alongside leads,
    // so it shares their permission.
    Route::get('/tenders', [TenderController::class, 'index'])
        ->middleware('permission:lead.view')
        ->name('tenders.index');

    Route::post('/tenders/sync', [TenderController::class, 'sync'])
        ->middleware('permission:lead.view')
        ->name('tenders.sync');

    // Declared before the write routes so `/leads/bulk` is not swallowed by
    // the `{lead}` parameter, and after the index so `/leads` still lists.
    // Before `/leads/{lead}`, which would otherwise take "export" as an id.
    Route::get('/leads/export', LeadExportController::class)
        ->middleware('permission:lead.export')
        ->name('leads.export');

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

    // --- Timeline: notes, tasks and logged activities (§21) ----------------
    //
    // One set of routes for every entity. The subject arrives in the body as a
    // type and an id, resolved through an allowlist in the controller.

    Route::post('/notes', [TimelineController::class, 'storeNote'])
        ->middleware('permission:note.create')
        ->name('notes.store');

    Route::patch('/notes/{note}', [TimelineController::class, 'updateNote'])
        ->middleware('permission:note.update')
        ->name('notes.update');

    Route::delete('/notes/{note}', [TimelineController::class, 'destroyNote'])
        ->middleware('permission:note.delete')
        ->name('notes.destroy');

    Route::post('/tasks', [TimelineController::class, 'storeTask'])
        ->middleware('permission:task.create')
        ->name('tasks.store');

    Route::patch('/tasks/{task}', [TimelineController::class, 'updateTask'])
        ->middleware('permission:task.update')
        ->name('tasks.update');

    Route::delete('/tasks/{task}', [TimelineController::class, 'destroyTask'])
        ->middleware('permission:task.delete')
        ->name('tasks.destroy');

    Route::post('/activities', [TimelineController::class, 'storeActivity'])
        ->middleware('permission:activity.create')
        ->name('activities.store');

    // --- Documents (§21) ----------------------------------------------------
    //
    // Uploads live on a private disk; `download` is the only way back out, and
    // it runs the session, tenant and permission checks first.

    Route::get('/documents', DocumentLibraryController::class)
        ->middleware('permission:document.view')
        ->name('documents.index');

    Route::post('/documents', [DocumentController::class, 'store'])
        ->middleware('permission:document.create')
        ->name('documents.store');

    Route::get('/documents/{document}', [DocumentController::class, 'download'])
        ->middleware('permission:document.view')
        ->name('documents.download');

    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])
        ->middleware('permission:document.delete')
        ->name('documents.destroy');

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

    // Before the write routes so `/deals/{deal}` does not swallow them, and
    // after the board so `/deals` still lists.
    Route::get('/deals/{deal}', [DealDetailController::class, 'show'])
        ->middleware('permission:deal.view')
        ->name('deals.show');

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

    Route::get('/campaigns', [CampaignController::class, 'index'])
        ->middleware('permission:campaign.view')
        ->name('campaigns.index');

    Route::post('/campaigns/sync', [CampaignController::class, 'sync'])
        ->middleware('permission:campaign.manage')
        ->name('campaigns.sync');

    // Approved leads and won deals. The page hides whichever set the user
    // cannot view, and refuses the visit when they can see neither.
    Route::get('/calendar', CalendarController::class)->name('calendar');

    // Confirmed deletes from the operations lists. Permission is checked per resource.
    Route::post('/operations/bulk-delete', [OperationsBulkController::class, 'destroy'])
        ->name('operations.bulk-destroy');

    // Work that starts when a lead is won: client, project, estimate, invoice.
    Route::get('/clients', [ClientController::class, 'index'])->middleware('permission:company.view')->name('clients.index');
    Route::post('/clients', [ClientController::class, 'store'])->middleware('permission:company.create')->name('clients.store');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->middleware('permission:company.view')->name('clients.show');
    Route::patch('/clients/{client}', [ClientController::class, 'update'])->middleware('permission:company.update')->name('clients.update');
    Route::post('/leads/{lead}/client', [LeadClientController::class, 'store'])->middleware('permission:company.create')->name('leads.client.store');

    Route::get('/projects', [ProjectController::class, 'index'])->middleware('permission:company.view')->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('permission:company.create')->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->middleware('permission:company.view')->name('projects.show');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->middleware('permission:company.update')->name('projects.update');

    Route::get('/estimates', [EstimateController::class, 'index'])->middleware('permission:quote.view')->name('estimates.index');
    Route::post('/estimates', [EstimateController::class, 'store'])->middleware('permission:quote.create')->name('estimates.store');
    Route::get('/estimates/{estimate}', [EstimateController::class, 'show'])->middleware('permission:quote.view')->name('estimates.show');
    Route::post('/estimates/{estimate}/send', [EstimateController::class, 'send'])->middleware('permission:quote.update')->name('estimates.send');
    Route::post('/estimates/{estimate}/accept', [EstimateController::class, 'accept'])->middleware('permission:quote.update')->name('estimates.accept');
    Route::post('/estimates/{estimate}/decline', [EstimateController::class, 'decline'])->middleware('permission:quote.update')->name('estimates.decline');

    Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('permission:billing.view')->name('invoices.index');
    Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('permission:billing.manage')->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:billing.view')->name('invoices.show');
    Route::post('/estimates/{estimate}/invoice', [InvoiceController::class, 'fromEstimate'])->middleware('permission:billing.manage')->name('invoices.from-estimate');
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->middleware('permission:billing.manage')->name('invoices.send');
    Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->middleware('permission:billing.manage')->name('invoices.pay');
    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->middleware('permission:billing.manage')->name('invoices.void');

    Route::get('/schedule', [ScheduleController::class, 'index'])->middleware('permission:task.view')->name('schedule.index');
    Route::post('/schedule', [ScheduleController::class, 'store'])->middleware('permission:task.create')->name('schedule.store');
    Route::delete('/schedule/{scheduleEntry}', [ScheduleController::class, 'destroy'])->middleware('permission:task.delete')->name('schedule.destroy');

    Route::get('/crew', [DirectoryController::class, 'crew'])->middleware('permission:company.view')->name('crew.index');
    Route::post('/crew', [DirectoryController::class, 'storeCrew'])->middleware('permission:company.create')->name('crew.store');
    Route::delete('/crew/{crewMember}', [DirectoryController::class, 'destroyCrew'])->middleware('permission:company.delete')->name('crew.destroy');

    Route::get('/subcontractors', [DirectoryController::class, 'subcontractors'])->middleware('permission:company.view')->name('subcontractors.index');
    Route::post('/subcontractors', [DirectoryController::class, 'storeSubcontractor'])->middleware('permission:company.create')->name('subcontractors.store');
    Route::delete('/subcontractors/{subcontractor}', [DirectoryController::class, 'destroySubcontractor'])->middleware('permission:company.delete')->name('subcontractors.destroy');

    Route::get('/materials', [DirectoryController::class, 'materials'])->middleware('permission:product.view')->name('materials.index');
    Route::post('/materials', [DirectoryController::class, 'storeMaterial'])->middleware('permission:product.create')->name('materials.store');
    Route::delete('/materials/{material}', [DirectoryController::class, 'destroyMaterial'])->middleware('permission:product.delete')->name('materials.destroy');

    Route::get('/change-orders', [ChangeOrderController::class, 'index'])->middleware('permission:quote.view')->name('change-orders.index');
    Route::post('/change-orders', [ChangeOrderController::class, 'store'])->middleware('permission:quote.create')->name('change-orders.store');
    Route::patch('/change-orders/{changeOrder}', [ChangeOrderController::class, 'update'])->middleware('permission:quote.update')->name('change-orders.update');

    Route::get('/messages', MessageInboxController::class)->middleware('permission:conversation.view')->name('messages.index');
    Route::get('/reports', ReportController::class)->middleware('permission:report.view')->name('reports.index');

    // Local businesses by city. Search is read-only; adding them is a new lead.
    Route::get('/discovery', [BusinessDiscoveryController::class, 'index'])
        ->middleware('permission:lead.view')
        ->name('discovery.index');

    Route::post('/discovery/search', [BusinessDiscoveryController::class, 'search'])
        ->middleware(['permission:lead.view', 'throttle:10,1'])
        ->name('discovery.search');

    Route::post('/discovery/import', [BusinessDiscoveryController::class, 'import'])
        ->middleware(['permission:lead.create', 'throttle:20,1'])
        ->name('discovery.import');

    // --- Opportunities -----------------------------------------------------
    // Job posts scored against what the workspace sells. Tenders are separate.

    Route::get('/opportunities', [OpportunityController::class, 'index'])
        ->middleware('permission:lead.view')
        ->name('opportunities.index');

    Route::post('/opportunities/sync', [OpportunityController::class, 'sync'])
        ->middleware('permission:lead.update')
        ->name('opportunities.sync');

    Route::patch('/opportunities/resume', [OpportunityController::class, 'resume'])
        ->middleware('permission:settings.manage')
        ->name('opportunities.resume');

    Route::middleware('permission:lead.update')->group(function (): void {
        Route::patch('/opportunities/{match}', [OpportunityController::class, 'update'])
            ->name('opportunities.update');

        Route::post('/opportunities/{match}/draft', [OpportunityController::class, 'draft'])
            ->name('opportunities.draft');
    });

    Route::post('/opportunities/{match}/convert', [OpportunityController::class, 'convert'])
        ->middleware('permission:lead.create')
        ->name('opportunities.convert');

    Route::get('/settings/setup', SetupController::class)
        ->middleware('permission:settings.view')
        ->name('settings.setup');

    Route::get('/settings/services', [ServiceProfileController::class, 'index'])
        ->middleware('permission:settings.view')
        ->name('settings.services');

    Route::middleware('permission:settings.manage')->group(function (): void {
        Route::post('/settings/services/install', [ServiceProfileController::class, 'install'])
            ->name('settings.services.install');

        Route::post('/settings/services', [ServiceProfileController::class, 'store'])
            ->name('settings.services.store');

        Route::put('/settings/services/{profile}', [ServiceProfileController::class, 'update'])
            ->name('settings.services.update');

        Route::delete('/settings/services/{profile}', [ServiceProfileController::class, 'destroy'])
            ->name('settings.services.destroy');
    });

    Route::middleware('permission:integration.connect')->group(function (): void {
        Route::get('/settings/upwork/connect', [UpworkController::class, 'connect'])
            ->name('settings.upwork.connect');

        // Register this exact URL as the callback in the Upwork API key.
        Route::get('/settings/upwork/callback', [UpworkController::class, 'callback'])
            ->name('settings.upwork.callback');
    });

    Route::delete('/settings/upwork', [UpworkController::class, 'destroy'])
        ->middleware('permission:integration.disconnect')
        ->name('settings.upwork.destroy');

    // --- Messaging ---------------------------------------------------------
    // A lead's email and WhatsApp thread (§15, §25).

    Route::get('/leads/{lead}/conversation', [LeadConversationController::class, 'show'])
        ->middleware('permission:conversation.view')
        ->name('leads.conversation');

    Route::middleware('permission:message.send')->group(function (): void {
        Route::post('/leads/{lead}/conversation/email', [LeadConversationController::class, 'sendEmail'])
            ->name('leads.conversation.email');

        Route::post('/leads/{lead}/conversation/whatsapp', [LeadConversationController::class, 'sendWhatsApp'])
            ->name('leads.conversation.whatsapp');

        Route::post('/leads/{lead}/follow-up/stop', [LeadConversationController::class, 'stopFollowUp'])
            ->name('leads.follow-up.stop');
    });

    Route::get('/settings/email', [EmailAccountController::class, 'index'])
        ->middleware('permission:integration.view')
        ->name('settings.email');

    Route::middleware('permission:integration.connect')->group(function (): void {
        Route::post('/settings/email', [EmailAccountController::class, 'store'])
            ->name('settings.email.store');

        Route::get('/settings/email/{provider}/connect', [EmailAccountController::class, 'connect'])
            ->whereIn('provider', ['google', 'microsoft'])
            ->name('settings.email.connect');

        // Register these exact URLs in the Google Cloud and Azure apps.
        Route::get('/settings/email/{provider}/callback', [EmailAccountController::class, 'callback'])
            ->whereIn('provider', ['google', 'microsoft'])
            ->name('settings.email.callback');

        Route::patch('/settings/email/accounts/{account}', [EmailAccountController::class, 'update'])
            ->name('settings.email.update');

        Route::put('/settings/whatsapp', [WhatsAppController::class, 'update'])
            ->name('settings.whatsapp.update');
    });

    Route::post('/settings/email/accounts/{account}/sync', [EmailAccountController::class, 'sync'])
        ->middleware('permission:integration.sync')
        ->name('settings.email.sync');

    Route::delete('/settings/email/accounts/{account}', [EmailAccountController::class, 'destroy'])
        ->middleware('permission:integration.disconnect')
        ->name('settings.email.destroy');

    Route::get('/settings/whatsapp', [WhatsAppController::class, 'index'])
        ->middleware('permission:integration.view')
        ->name('settings.whatsapp');

    Route::delete('/settings/whatsapp', [WhatsAppController::class, 'destroy'])
        ->middleware('permission:integration.disconnect')
        ->name('settings.whatsapp.destroy');

    Route::get('/settings/follow-up', [FollowUpController::class, 'index'])
        ->middleware('permission:template.view')
        ->name('settings.follow-up');

    Route::put('/settings/follow-up', [FollowUpController::class, 'updateSequence'])
        ->middleware('permission:automation.manage')
        ->name('settings.follow-up.update');

    Route::post('/settings/templates', [FollowUpController::class, 'storeTemplate'])
        ->middleware('permission:template.create')
        ->name('settings.templates.store');

    Route::patch('/settings/templates/{template}', [FollowUpController::class, 'updateTemplate'])
        ->middleware('permission:template.update')
        ->name('settings.templates.update');

    Route::delete('/settings/templates/{template}', [FollowUpController::class, 'destroyTemplate'])
        ->middleware('permission:template.delete')
        ->name('settings.templates.destroy');

    // --- Integrations ------------------------------------------------------
    // Under /settings: the bare /integrations is the public marketing page.

    Route::get('/settings/integrations', [IntegrationController::class, 'index'])
        ->middleware('permission:integration.view')
        ->name('integrations.index');

    Route::middleware('permission:integration.connect')->group(function (): void {
        Route::put('/settings/integrations/apps/{provider}', [IntegrationController::class, 'updateApp'])
            ->whereIn('provider', ['meta', 'linkedin', 'whatsapp', 'google', 'microsoft', 'upwork'])
            ->name('integrations.apps.update');

        Route::get('/settings/integrations/{platform}/connect', [IntegrationController::class, 'connect'])
            ->name('integrations.connect');

        // Where the provider sends the user back after OAuth. Register this
        // exact URL in the Meta and LinkedIn app settings.
        Route::get('/settings/integrations/{platform}/callback', [IntegrationController::class, 'callback'])
            ->name('integrations.callback');
    });

    Route::post('/settings/integrations/connections/{connection}/sync', [IntegrationController::class, 'sync'])
        ->middleware('permission:integration.sync')
        ->name('integrations.sync');

    Route::delete('/settings/integrations/connections/{connection}', [IntegrationController::class, 'destroy'])
        ->middleware('permission:integration.disconnect')
        ->name('integrations.destroy');

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
