<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Skeleton CSS Framework -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/skeleton-css@2.0.4/css/skeleton.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/skeleton-css@2.0.4/css/normalize.min.css">

        <!-- Custom Styles -->
        <style>
            body {
                background-color: #ffffff;
                color: #000000;
            }
            .container {
                max-width: 1200px;
            }
            .text-grey {
                color: #555555;
            }
            .bg-grey {
                background-color: #f5f5f5;
            }
            .border-grey {
                border-color: #cccccc;
            }
        </style>

        <!-- Scripts -->
        @routes
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>

