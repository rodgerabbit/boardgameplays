<template>
    <Head :title="title" />
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-text-dark">{{ title }}</h1>
        <p class="text-text-muted-dark">This page is a placeholder. Content will be added later.</p>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    title: {
        type: String,
        required: true,
    },
});
</script>
