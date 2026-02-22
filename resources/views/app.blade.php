<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>BoardGamePlays</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

        <!-- Scripts -->
        @routes
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="bg-background-dark text-text-dark antialiased">
        @inertia
    </body>
</html>

