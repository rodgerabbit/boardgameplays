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
    <body class="flex min-h-screen flex-col bg-background-dark text-text-dark antialiased">
        <header class="shrink-0 border-b border-surface-darker bg-surface-dark px-4 py-3">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2 shrink-0">
                    <img src="{{ asset('images/logo.png') }}" alt="boardgameplays logo" class="h-10 w-auto" />
                    <span class="text-lg font-semibold text-text-dark">boardgameplays.com</span>
                </a>
                <div class="flex flex-1 items-center justify-end gap-3 min-w-0 max-w-2xl">
                    <form action="{{ url('/') }}" method="get" class="w-40 shrink-0" role="search">
                        <div class="relative">
                            <span class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-text-muted-dark" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </span>
                            <input
                                type="search"
                                name="q"
                                placeholder="Quick search..."
                                class="w-full rounded-md border border-surface-darker bg-background-dark py-1.5 pl-7 pr-2 text-sm text-text-dark placeholder-text-muted-dark focus:border-brand-accent focus:outline-none focus:ring-1 focus:ring-brand-accent"
                                autocomplete="off"
                            />
                        </div>
                    </form>
                    @auth
                    <a href="#" class="relative rounded-lg p-2 text-text-muted-dark hover:bg-surface-darker hover:text-text-dark" aria-label="Notifications">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        <span class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-pink px-1 text-xs font-medium text-brand-ink">3</span>
                    </a>
                    <div class="relative shrink-0">
                        <details class="relative group/details">
                            <summary class="list-none cursor-pointer rounded-full ring-2 ring-transparent focus:outline-none focus:ring-2 focus:ring-brand-accent [&::-webkit-details-marker]:hidden">
                                @php
                                        $name = auth()->user()->name ?? '';
                                        $parts = preg_split('/\s+/', trim($name), 2);
                                        $initials = strtoupper(mb_substr($parts[0] ?? '', 0, 1)) . strtoupper(mb_substr($parts[1] ?? '', 0, 1));
                                        if ($initials === '') { $initials = 'U'; }
                                    @endphp
                                    <span class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-surface-darker text-sm font-medium text-text-dark ring-2 ring-surface-darker">
                                        {{ $initials }}
                                    </span>
                            </summary>
                            <div class="absolute right-0 top-full z-10 mt-2 w-48 rounded-lg border border-surface-darker bg-surface-dark py-1 shadow-lg">
                                <form method="post" action="{{ route('logout') }}" class="block">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-text-dark hover:bg-surface-darker">
                                        Log out
                                    </button>
                                </form>
                            </div>
                        </details>
                    </div>
                    @else
                    <a href="{{ route('login') }}" class="rounded-lg border border-brand-ink-soft bg-brand-pink px-3 py-2 text-sm font-medium text-brand-ink shadow-cartoon hover:bg-brand-pink-dark">Log in</a>
                    @endauth
                </div>
            </div>
        </header>
        <main class="flex min-h-0 flex-1 flex-col w-full">
            @inertia
        </main>
    </body>
</html>

