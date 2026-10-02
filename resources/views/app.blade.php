@php($brand = app(\App\Domain\Branding\Brand::class)->current())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-brand="{{ $brand['key'] }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="{{ $brand['primary'] }}">
        <meta name="description" content="{{ $brand['tagline'] }}">

        {{-- Brand assets follow the active brand, so finalising a logo needs no
             template edits. See config/brand.php. --}}
        <link rel="icon" href="{{ $brand['assets']['favicon_ico'] }}" sizes="any">
        <link rel="icon" href="{{ $brand['assets']['favicon_svg'] }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ $brand['assets']['app_icon_180'] }}">
        <link rel="manifest" href="{{ route('manifest') }}">

        {{-- Applies the stored theme before first paint. Without this the page
             renders light then repaints dark, which is visible on every
             navigation for dark-mode users. Deliberately inline and blocking. --}}
        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('theme');
                    var system = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (stored === 'dark' || ((!stored || stored === 'system') && system)) {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) {
                    /* Private mode or blocked storage: fall back to light. */
                }
            })();
        </script>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ $brand['name'] }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
