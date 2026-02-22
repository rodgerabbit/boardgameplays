<template>
    <div class="flex min-h-screen flex-col bg-background-dark text-text-dark antialiased">
        <AppHeader />
        <main class="flex min-h-0 flex-1 flex-col w-full">
            <slot />
        </main>
    </div>
</template>

<script setup>
import AppHeader from '@/components/AppHeader.vue';
import { useTheme } from '@/composables/useTheme';

useTheme();
</script>
