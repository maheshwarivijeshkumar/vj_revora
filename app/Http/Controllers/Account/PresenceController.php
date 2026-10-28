<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Active, away, or busy, shown on the account menu.
 */
final class PresenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'presence' => ['required', 'string', Rule::in(['active', 'away', 'busy'])],
        ]);

        $request->user()?->forceFill(['presence' => $validated['presence']])->save();

        return back();
    }
}
