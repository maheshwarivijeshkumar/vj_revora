<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Domain\Branding\Brand;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class ComingSoonController extends Controller
{
    public function __invoke(Brand $brand): Response
    {
        return Inertia::render('ComingSoon', [
            'brand' => $brand->current(),
            // Null hides the countdown entirely rather than showing a fake
            // date — an invented launch date is a promise to visitors.
            'launchAt' => config('app.launch_at'),
        ]);
    }
}
