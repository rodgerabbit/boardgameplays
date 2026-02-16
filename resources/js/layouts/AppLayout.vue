<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <!-- Mobile: hamburger button at start of content area -->
        <div class="flex items-center gap-2 border-b border-surface-darker bg-surface-dark px-4 py-2 lg:hidden">
            <button
                type="button"
                class="rounded-lg p-2 text-text-dark hover:bg-surface-darker focus:outline-none focus:ring-2 focus:ring-brand-accent"
                aria-label="Open menu"
                :aria-expanded="sidebarOpen"
                @click="sidebarOpen = true"
            >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <span class="text-sm font-medium text-text-muted-dark">Menu</span>
        </div>

        <div class="relative flex min-h-0 flex-1">
            <!-- Mobile overlay when sidebar is open -->
            <div
                v-show="sidebarOpen"
                class="fixed inset-0 z-40 bg-background-dark/80 lg:hidden"
                aria-hidden="true"
                @click="sidebarOpen = false"
            />

            <!-- Sidebar: overlay on mobile, static on desktop -->
            <aside
                :class="[
                    'fixed inset-y-0 left-0 z-50 flex w-56 flex-col bg-surface-dark transition-transform duration-200 ease-out lg:static lg:z-auto lg:translate-x-0 lg:border-r lg:border-surface-darker',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                ]"
                aria-label="Sidebar"
            >
                <div class="flex items-center justify-between border-b border-surface-darker p-3">
                    <span class="text-sm font-semibold text-text-dark">Navigation</span>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-text-muted-dark hover:bg-surface-darker hover:text-text-dark lg:hidden"
                        aria-label="Close menu"
                        @click="sidebarOpen = false"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <SideNavigation @navigate="sidebarOpen = false" />
            </aside>

            <!-- Main content -->
            <main class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import SideNavigation from '@/components/SideNavigation.vue';

const sidebarOpen = ref(false);

function handleEscape(event) {
    if (event.key === 'Escape') sidebarOpen.value = false;
}

function closeSidebar() {
    sidebarOpen.value = false;
}

let removeRouterListener = () => {};

onMounted(() => {
    window.addEventListener('keydown', handleEscape);
    removeRouterListener = router.on('start', closeSidebar);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleEscape);
    removeRouterListener();
});
</script>
