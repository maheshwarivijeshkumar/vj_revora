<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\WaitlistRequest;
use App\Models\WaitlistSignup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class WaitlistController extends Controller
{
    public function store(WaitlistRequest $request): RedirectResponse
    {
        $email = (string) $request->string('email');

        // Idempotent by normalised email: signing up twice is a no-op, not a
        // duplicate row and not an error the visitor has to understand.
        WaitlistSignup::updateOrCreate(
            ['email_normalized' => Str::lower(trim($email))],
            [
                'email' => $email,
                'name' => $request->input('name'),
                'company' => $request->input('company'),
                'source' => 'coming_soon',
                'utm' => array_filter($request->only([
                    'utm_source', 'utm_medium', 'utm_campaign',
                    'utm_content', 'utm_term',
                ])) ?: null,
                'landing_page' => $request->input('landing_page'),
                'referrer' => $request->headers->get('referer'),
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'consent' => true,
                'consent_at' => now(),
            ],
        );

        return back()->with('success', "You're on the list. We'll be in touch before launch.");
    }
}
