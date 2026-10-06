<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Disposable mailbox providers
    |--------------------------------------------------------------------------
    |
    | Addresses here exist to be thrown away, so a lead using one has told us
    | it does not want to be reached. Not exhaustive — there are thousands of
    | these domains and they churn — so this list is a floor, not a filter.
    | A commercial validator is the right answer at volume; see `driver`.
    |
    */
    'disposable_domains' => [
        '10minutemail.com', 'guerrillamail.com', 'mailinator.com',
        'temp-mail.org', 'tempmail.com', 'throwawaymail.com', 'yopmail.com',
        'getnada.com', 'trashmail.com', 'dispostable.com', 'maildrop.cc',
        'fakeinbox.com', 'sharklasers.com', 'grr.la', 'spam4.me',
        'mohmal.com', 'emailondeck.com', 'tempinbox.com', 'mytemp.email',
    ],

    /*
    |--------------------------------------------------------------------------
    | Role addresses
    |--------------------------------------------------------------------------
    |
    | A shared inbox rather than a person. Deliverable, so not invalid, but
    | nobody owns it: outreach written to "you" lands oddly and consent given
    | at one is hard to attribute to anyone (§88). Flagged, never discarded —
    | info@ is how plenty of small businesses genuinely reply.
    |
    */
    'role_addresses' => [
        'info', 'admin', 'administrator', 'sales', 'support', 'help',
        'contact', 'enquiries', 'inquiries', 'office', 'hello', 'hi',
        'billing', 'accounts', 'finance', 'hr', 'jobs', 'careers',
        'marketing', 'press', 'media', 'legal', 'privacy', 'security',
        'noreply', 'no-reply', 'donotreply', 'postmaster', 'webmaster',
        'abuse', 'team', 'mail', 'email', 'service', 'customerservice',
    ],

    /*
    |--------------------------------------------------------------------------
    | Placeholder values
    |--------------------------------------------------------------------------
    |
    | What people type to get past a required field. Matched whole, lowercased,
    | so a real surname like "Tester" is not caught by accident.
    |
    */
    'placeholders' => [
        'test', 'testing', 'tester', 'asdf', 'asdfasdf', 'qwerty', 'abc',
        'abcd', 'xxx', 'xxxx', 'aaa', 'none', 'na', 'n/a', 'nil', 'null',
        'nothing', 'nobody', 'anonymous', 'unknown', 'dummy', 'sample',
        'example', 'demo', 'firstname', 'lastname', 'fname', 'lname',
        'your name', 'full name', 'john doe', 'jane doe', 'foo', 'bar', 'baz',
    ],

    /*
    |--------------------------------------------------------------------------
    | Checks that reach the network
    |--------------------------------------------------------------------------
    |
    | An MX lookup is the single highest-value email check, and it is also a
    | DNS round trip per lead. It is off during a web request — a form must not
    | wait on somebody else's resolver — and on when verification runs from a
    | queue, where latency costs nobody anything.
    |
    */
    'check_mx' => env('VERIFY_CHECK_MX', true),

    /*
    |--------------------------------------------------------------------------
    | Default region for numbers written without a country code
    |--------------------------------------------------------------------------
    |
    | `050 123 4567` is only parseable against a country. The lead's own
    | country is preferred where it is known; this is the fallback. Guessing
    | from the server's locale instead would silently mangle numbers for every
    | workspace that is not in that country, so it is configuration rather than
    | inference — and null is a valid answer, which simply means such numbers
    | stay unparseable until someone types a +.
    |
    */
    'default_region' => env('VERIFY_DEFAULT_REGION'),

    'mx_timeout' => 3,

    /*
    |--------------------------------------------------------------------------
    | Confidence thresholds
    |--------------------------------------------------------------------------
    |
    | Verification starts at 100 and loses points per finding. These are where
    | the bands fall. Tunable because what counts as workable differs between
    | a high-volume inside-sales team and one chasing twenty accounts.
    |
    */
    'thresholds' => [
        'valid' => 80,
        'risky' => 40,
    ],

];
