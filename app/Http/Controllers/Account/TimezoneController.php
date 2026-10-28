<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Domain\Time\TimezoneCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The signed-in person's time zone, shown as (UTC+05:00) Islamabad, Karachi.
 */
final class TimezoneController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'timezone' => ['required', 'string', Rule::in(array_keys(TimezoneCatalog::zones()))],
        ]);

        $request->user()?->forceFill(['timezone' => $validated['timezone']])->save();

        return back();
    }
}
