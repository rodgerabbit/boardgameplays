<template>
    <Head title="BoardGamePlays" />
    <div class="space-y-12">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-text-dark m-0">boardgameplays.com</h1>
            <Link
                :href="route('login')"
                class="rounded-lg border border-border bg-surface-dark px-4 py-2 text-sm font-medium text-text-dark hover:bg-surface-hover"
            >
                Login
            </Link>
        </div>

        <!-- Hero Section -->
        <section class="text-center">
            <h2 class="mb-8 text-2xl font-medium text-text-dark">
                Track Your Board Game Plays & Statistics
            </h2>
            <p class="mx-auto max-w-2xl text-lg text-text-muted-dark">
                The easiest way to log your board game sessions, analyze group statistics,
                and keep your collection synced with BoardGameGeek.
            </p>
        </section>

        <!-- Features Section -->
        <div class="grid gap-6 sm:grid-cols-3">
            <div class="rounded-xl border border-surface-darker bg-surface-dark p-8 text-center">
                <h3 class="mb-3 text-lg font-medium text-text-dark">Easy Logging of Plays</h3>
                <p class="text-sm text-text-muted-dark">
                    Quickly record your game sessions with just a few clicks.
                    No complicated forms or lengthy setup required. Log who played,
                    who won, and when it happened in seconds.
                </p>
            </div>
            <div class="rounded-xl border border-surface-darker bg-surface-dark p-8 text-center">
                <h3 class="mb-3 text-lg font-medium text-text-dark">Group Statistics</h3>
                <p class="text-sm text-text-muted-dark">
                    Discover insights about your gaming group. See win rates,
                    most played games, favorite players, and track your gaming
                    history over time. Make data-driven decisions about what to play next.
                </p>
            </div>
            <div class="rounded-xl border border-surface-darker bg-surface-dark p-8 text-center">
                <h3 class="mb-3 text-lg font-medium text-text-dark">BoardGameGeek Integration</h3>
                <p class="text-sm text-text-muted-dark">
                    Seamlessly sync your collection with BoardGameGeek.com.
                    Automatically import game details, ratings, and metadata.
                    Keep your local records in sync with the world's largest
                    board game database.
                </p>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="mt-16 flex flex-wrap justify-center gap-4 text-center">
            <Link
                :href="route('register')"
                class="inline-block rounded-lg border border-border bg-primary px-8 py-3 text-lg font-medium text-text-primary shadow-cartoon hover:bg-primary-hover"
            >
                Get Started
            </Link>
            <Link
                :href="route('login')"
                class="inline-block rounded-lg border border-border bg-surface-dark px-8 py-3 text-lg font-medium text-text-dark hover:bg-surface-hover"
            >
                Login
            </Link>
        </div>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>
