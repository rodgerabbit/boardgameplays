<template>
    <header class="shrink-0 border-b border-surface-darker bg-surface-dark px-4 py-3">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <Link :href="route('home')" class="inline-flex items-center gap-2 shrink-0">
                <img src="/images/logo.png" alt="boardgameplays logo" class="h-10 w-auto" />
                <span class="text-lg font-semibold text-text-dark">boardgameplays.com</span>
            </Link>
            <div class="flex flex-1 items-center justify-end gap-3 min-w-0 max-w-2xl">
                <form :action="route('home')" method="get" class="w-40 shrink-0" role="search">
                    <div class="relative">
                        <span class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-text-muted-dark" aria-hidden="true">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input
                            type="search"
                            name="q"
                            placeholder="Quick search..."
                            class="w-full rounded-md border border-surface-darker bg-background-dark py-1.5 pl-7 pr-2 text-sm text-text-dark placeholder-text-muted-dark focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            autocomplete="off"
                        />
                    </div>
                </form>
                <template v-if="auth.user">
                    <a href="#" class="relative rounded-lg p-2 text-text-muted-dark hover:bg-surface-darker hover:text-text-dark" aria-label="Notifications">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        <span class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-xs font-medium text-text-primary">3</span>
                    </a>
                    <div class="relative shrink-0">
                        <details class="relative group/details">
                            <summary class="list-none cursor-pointer rounded-full ring-2 ring-transparent focus:outline-none focus:ring-2 focus:ring-primary [&::-webkit-details-marker]:hidden">
                                <img
                                    v-if="auth.user.profile_picture_url"
                                    :src="auth.user.profile_picture_url"
                                    alt="Profile"
                                    class="h-9 w-9 shrink-0 overflow-hidden rounded-full object-cover ring-2 ring-surface-darker"
                                />
                                <span
                                    v-else
                                    class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-surface-darker text-sm font-medium text-text-dark ring-2 ring-surface-darker"
                                >
                                    {{ userInitials }}
                                </span>
                            </summary>
                            <div class="absolute right-0 top-full z-10 mt-2 w-48 rounded-lg border border-surface-darker bg-surface-dark py-1 shadow-lg">
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-text-dark hover:bg-surface-darker"
                                    @click="logout"
                                >
                                    Log out
                                </button>
                            </div>
                        </details>
                    </div>
                </template>
                <Link
                    v-else
                    :href="route('login')"
                    class="rounded-lg border border-border bg-primary px-3 py-2 text-sm font-medium text-text-primary shadow-cartoon hover:bg-primary-hover"
                >
                    Log in
                </Link>
            </div>
        </div>
    </header>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const auth = computed(() => page.props.auth ?? { user: null });

const userInitials = computed(() => {
    const user = auth.value?.user;
    if (!user?.name?.trim()) return 'U';
    const parts = user.name.trim().split(/\s+/);
    const first = (parts[0] ?? '').charAt(0).toUpperCase();
    const second = (parts[1] ?? '').charAt(0).toUpperCase();
    return (first + second) || 'U';
});

function logout() {
    router.post(route('logout'));
}
</script>
