<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Lead-ad providers (§12, §13). The API version is pinned here so a bump
    | is a configuration change (§76); check the provider changelog before
    | changing it (§103).
    */

    'meta' => [
        'client_id' => env('META_APP_ID'),
        'client_secret' => env('META_APP_SECRET'),
        'api_version' => env('META_GRAPH_VERSION', 'v26.0'),
    ],

    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'api_version' => env('LINKEDIN_API_VERSION', '202609'),
    ],

    /*
    | Mailbox sign-in (Settings → Email). SMTP + IMAP mailboxes need nothing
    | here: their server details are entered per mailbox.
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        // "common" accepts work, school and personal accounts.
        'tenant' => env('MICROSOFT_TENANT', 'common'),
    ],

    /*
    | WhatsApp Cloud API webhook (Settings → WhatsApp). App-level, because
    | Meta sends every number's events to the one callback URL; each
    | workspace's own number and token are stored with that workspace.
    */

    'whatsapp' => [
        'app_secret' => env('WHATSAPP_APP_SECRET', env('META_APP_SECRET')),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'api_version' => env('WHATSAPP_GRAPH_VERSION', env('META_GRAPH_VERSION', 'v26.0')),
    ],

    /*
    | Upwork job search (Settings → Setup). Needs an API key Upwork has
    | approved with "Read marketplace Job Postings". Callback URL to register:
    | {APP_URL}/settings/upwork/callback
    */

    'upwork' => [
        'client_id' => env('UPWORK_CLIENT_ID'),
        'client_secret' => env('UPWORK_CLIENT_SECRET'),
    ],

    /*
    | Reply scoring: claude | openai | gemini | keywords. Without a key for
    | the chosen provider, replies are scored by keyword rules.
    */

    'ai' => [
        'reply_provider' => env('AI_REPLY_PROVIDER', 'keywords'),
        // Drafts proposals, pitches and tender letters: claude | openai |
        // gemini. Without a key, drafts are filled in from templates.
        'provider' => env('AI_PROVIDER', env('AI_REPLY_PROVIDER')),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
