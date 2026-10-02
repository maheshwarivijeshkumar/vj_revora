<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\ContactRequest;
use App\Models\ContactEnquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class ContactController extends Controller
{
    public function store(ContactRequest $request): RedirectResponse
    {
        ContactEnquiry::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'company' => $request->input('company'),
            'topic' => $request->string('topic')->toString(),
            'message' => $request->string('message')->toString(),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'consent' => true,
            'consent_at' => now(),
        ]);

        // Notification to the team lands with the messaging layer in Phase 2;
        // until then the enquiry is persisted and read from the admin side.

        return back()->with('success', 'Thanks. We will come back to you within one working day.');
    }
}
