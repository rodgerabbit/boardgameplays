@php
    $themePreference = auth()->check()
        ? (auth()->user()->theme_preference ?? \App\Models\User::THEME_SYSTEM)
        : \App\Models\User::THEME_SYSTEM;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" data-theme-preference="{{ $themePreference }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>BoardGamePlays</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

        <script>
            (function() {
                var pref = document.documentElement.getAttribute('data-theme-preference') || 'system';
                var isDark = pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', isDark);
                document.documentElement.classList.toggle('light', !isDark);
            })();
        </script>

        <!-- Scripts -->
        @routes
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="bg-background-dark text-text-dark antialiased">
        @inertia
    </body>
</html>

