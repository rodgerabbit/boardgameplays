<template>
    <nav
        aria-label="Main navigation"
        class="flex w-full flex-col border-r border-surface-darker bg-surface-dark lg:w-56 lg:flex-shrink-0"
    >
        <ul class="flex flex-col gap-1 p-3">
            <li v-for="item in navigationItems" :key="item.name">
                <component
                    :is="item.disabled ? 'span' : Link"
                    :href="item.disabled ? undefined : item.href"
                    :title="item.disabled ? item.disabledTitle : undefined"
                    :class="[
                        'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                        item.disabled
                            ? 'cursor-not-allowed text-text-muted-dark opacity-70'
                            : 'text-text-dark hover:bg-surface-darker hover:text-accent',
                        isActive(item) && !item.disabled && 'bg-surface-darker text-accent',
                    ]"
                    @click="!item.disabled && $emit('navigate')"
                >
                    <span class="flex h-5 w-5 flex-shrink-0 text-current" aria-hidden="true">
                        <component :is="item.icon" class="h-5 w-5 shrink-0" />
                    </span>
                    <span class="truncate">{{ item.name }}</span>
                </component>
            </li>
        </ul>
    </nav>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';

import IconDashboard from './icons/IconDashboard.vue';
import IconGroups from './icons/IconGroups.vue';
import IconCollection from './icons/IconCollection.vue';
import IconPlayLog from './icons/IconPlayLog.vue';
import IconStatistics from './icons/IconStatistics.vue';
import IconAchievements from './icons/IconAchievements.vue';
import IconBoardgames from './icons/IconBoardgames.vue';
import IconEvents from './icons/IconEvents.vue';
import IconSettings from './icons/IconSettings.vue';

const page = usePage();

const navigationItems = [
    { name: 'Dashboard', href: '/dashboard', icon: IconDashboard, disabled: false },
    { name: 'Groups', href: '/groups', icon: IconGroups, disabled: false },
    { name: 'Collection', href: '/collection', icon: IconCollection, disabled: false },
    { name: 'Play Log', href: '/play-log', icon: IconPlayLog, disabled: false },
    { name: 'Statistics', href: '/statistics', icon: IconStatistics, disabled: false },
    {
        name: 'Achievements',
        href: '#',
        icon: IconAchievements,
        disabled: true,
        disabledTitle: 'Not yet available',
    },
    { name: 'Boardgames', href: '/boardgames', icon: IconBoardgames, disabled: false },
    {
        name: 'Events',
        href: '#',
        icon: IconEvents,
        disabled: true,
        disabledTitle: 'Not yet available',
    },
    { name: 'Settings', href: '/settings', icon: IconSettings, disabled: false },
];

function isActive(item) {
    if (item.disabled) return false;
    const current = page.url;
    const rawPath = typeof item.href === 'string' ? item.href : '';
    if (rawPath === '#') return false;
    // Normalize href to pathname (handles both /settings and full URL from route())
    const path = rawPath.startsWith('http')
        ? new URL(rawPath).pathname
        : rawPath.split('?')[0];
    if (path === '/dashboard') return current === '/dashboard' || current.startsWith('/dashboard');
    return path !== '/' && current.startsWith(path);
}
</script>
