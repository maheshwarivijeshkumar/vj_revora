<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
|
| Controls what visitors see at "/". Pre-launch that is the coming-soon page;
| once the product is ready it becomes the full marketing site (§124).
|
| Both pages exist at all times — only the root route changes — so the landing
| page can be reviewed at /home while the public root still says coming soon.
|
*/

return [

    /**
     * 'coming_soon' — "/" renders the coming-soon page, landing at /home.
     * 'live'        — "/" renders the landing page, coming soon at /coming-soon.
     */
    'mode' => env('SITE_MODE', 'coming_soon'),

    /*
    |--------------------------------------------------------------------------
    | Contact
    |--------------------------------------------------------------------------
    |
    | Rendered in the nav, footer and CTA sections. Left blank the related
    | links are hidden rather than pointing nowhere.
    |
    */

    'contact' => [
        'email' => env('SITE_CONTACT_EMAIL'),
        'phone' => env('SITE_CONTACT_PHONE'),
        'whatsapp' => env('SITE_WHATSAPP'),
        'demo_url' => env('SITE_DEMO_URL'),
    ],

    'social' => [
        'linkedin' => env('SITE_LINKEDIN'),
        'youtube' => env('SITE_YOUTUBE'),
        'instagram' => env('SITE_INSTAGRAM'),
        'x' => env('SITE_X'),
    ],

];
