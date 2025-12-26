<template>
    <div>
        <!-- Header -->
        <div class="row" style="margin-bottom: 3rem;">
            <div class="twelve columns">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h1 style="margin: 0;">boardgameplays.com</h1>
                    <Link :href="route('login')" class="button">Login</Link>
                </div>
            </div>
        </div>

        <!-- Hero Section -->
        <div class="row" style="margin-bottom: 5rem;">
            <div class="twelve columns">
                <h2 style="text-align: center; margin-bottom: 2rem;">
                    Track Your Board Game Plays & Statistics
                </h2>
                <p style="text-align: center; font-size: 1.2rem; color: #555555; max-width: 800px; margin: 0 auto;">
                    The easiest way to log your board game sessions, analyze group statistics, 
                    and keep your collection synced with BoardGameGeek.
                </p>
            </div>
        </div>

        <!-- Features Section -->
        <div class="row">
            <div class="four columns">
                <div style="text-align: center; padding: 2rem;">
                    <h3>Easy Logging of Plays</h3>
                    <p class="text-grey">
                        Quickly record your game sessions with just a few clicks. 
                        No complicated forms or lengthy setup required. Log who played, 
                        who won, and when it happened in seconds.
                    </p>
                </div>
            </div>
            <div class="four columns">
                <div style="text-align: center; padding: 2rem;">
                    <h3>Group Statistics</h3>
                    <p class="text-grey">
                        Discover insights about your gaming group. See win rates, 
                        most played games, favorite players, and track your gaming 
                        history over time. Make data-driven decisions about what to play next.
                    </p>
                </div>
            </div>
            <div class="four columns">
                <div style="text-align: center; padding: 2rem;">
                    <h3>BoardGameGeek Integration</h3>
                    <p class="text-grey">
                        Seamlessly sync your collection with BoardGameGeek.com. 
                        Automatically import game details, ratings, and metadata. 
                        Keep your local records in sync with the world's largest 
                        board game database.
                    </p>
                </div>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="row" style="margin-top: 4rem; text-align: center;">
            <div class="twelve columns">
                <Link :href="route('register')" class="button button-primary" style="font-size: 1.2rem; padding: 1rem 3rem; margin-right: 1rem;">
                    Get Started
                </Link>
                <Link :href="route('login')" class="button" style="font-size: 1.2rem; padding: 1rem 3rem;">
                    Login
                </Link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<style scoped>
.text-grey {
    color: #555555;
}
</style>
