<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DealController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\UsageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API v1
|--------------------------------------------------------------------------
|
| Mounted at /api/v1 (see bootstrap/app.php).
|
| A first-class surface, not a byproduct of the UI: the developer portal,
| inbound ingestion and the CRM connectors all run on it (§47). Its
| controllers call the same domain actions the Inertia controllers use, so
| behaviour cannot drift between the two.
|
| Every route declares the scope it needs, so required access is visible in
| `route:list` rather than buried in a controller.
|
*/

Route::get('/health', fn () => ['status' => 'ok'])->name('api.health');

Route::middleware(['api.key', 'api.log', 'throttle:api'])->group(function (): void {
    // --- Leads --------------------------------------------------------------

    Route::get('/leads', [LeadController::class, 'index'])
        ->middleware('scope:leads.read');

    Route::get('/leads/{uuid}', [LeadController::class, 'show'])
        ->middleware('scope:leads.read');

    Route::post('/leads', [LeadController::class, 'store'])
        ->middleware('scope:leads.write');

    Route::patch('/leads/{uuid}', [LeadController::class, 'update'])
        ->middleware('scope:leads.write');

    Route::delete('/leads/{uuid}', [LeadController::class, 'destroy'])
        ->middleware('scope:leads.write');

    // Alias for §84's inbound ingestion. Same handler: an inbound lead is a
    // lead, and giving it a separate pipeline would mean two of everything.
    Route::post('/inbound/leads', [LeadController::class, 'store'])
        ->middleware('scope:leads.write');

    // --- Contacts -----------------------------------------------------------

    Route::get('/contacts', [ContactController::class, 'index'])
        ->middleware('scope:contacts.read');

    Route::get('/contacts/{uuid}', [ContactController::class, 'show'])
        ->middleware('scope:contacts.read');

    Route::post('/contacts', [ContactController::class, 'store'])
        ->middleware('scope:contacts.write');

    Route::patch('/contacts/{uuid}', [ContactController::class, 'update'])
        ->middleware('scope:contacts.write');

    Route::delete('/contacts/{uuid}', [ContactController::class, 'destroy'])
        ->middleware('scope:contacts.write');

    // Employment is a relationship with a role and a current employer, so it
    // gets its own endpoints rather than being a field on the contact.
    Route::post('/contacts/{uuid}/companies', [ContactController::class, 'attachCompany'])
        ->middleware('scope:contacts.write');

    Route::delete('/contacts/{uuid}/companies/{companyUuid}', [ContactController::class, 'detachCompany'])
        ->middleware('scope:contacts.write');

    // --- Companies ----------------------------------------------------------

    Route::get('/companies', [CompanyController::class, 'index'])
        ->middleware('scope:companies.read');

    Route::get('/companies/{uuid}', [CompanyController::class, 'show'])
        ->middleware('scope:companies.read');

    // Paginated rather than embedded in the company, because a company can
    // have thousands of people at it.
    Route::get('/companies/{uuid}/contacts', [CompanyController::class, 'contacts'])
        ->middleware('scope:contacts.read');

    Route::post('/companies', [CompanyController::class, 'store'])
        ->middleware('scope:companies.write');

    Route::patch('/companies/{uuid}', [CompanyController::class, 'update'])
        ->middleware('scope:companies.write');

    Route::delete('/companies/{uuid}', [CompanyController::class, 'destroy'])
        ->middleware('scope:companies.write');

    // --- Deals --------------------------------------------------------------

    Route::get('/deals', [DealController::class, 'index'])
        ->middleware('scope:deals.read');

    Route::get('/deals/{uuid}', [DealController::class, 'show'])
        ->middleware('scope:deals.read');

    Route::post('/deals', [DealController::class, 'store'])
        ->middleware('scope:deals.write');

    Route::patch('/deals/{uuid}', [DealController::class, 'update'])
        ->middleware('scope:deals.write');

    Route::post('/deals/{uuid}/move', [DealController::class, 'move'])
        ->middleware('scope:deals.write');

    Route::delete('/deals/{uuid}', [DealController::class, 'destroy'])
        ->middleware('scope:deals.write');

    Route::get('/pipelines', [DealController::class, 'pipelines'])
        ->middleware('scope:deals.read');

    // --- Usage --------------------------------------------------------------

    Route::get('/usage', UsageController::class)
        ->middleware('scope:usage.read');
});
