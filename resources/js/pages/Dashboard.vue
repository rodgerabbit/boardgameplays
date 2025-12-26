<template>
    <div>
        <!-- Header -->
        <div class="row" style="margin-bottom: 2rem;">
            <div class="twelve columns">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h1 style="margin: 0;">Dashboard</h1>
                    <form @submit.prevent="logout" style="margin: 0;">
                        <button type="submit" class="button">Logout</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Games Table -->
        <div class="row">
            <div class="twelve columns">
                <h2>Random Games</h2>
                <table class="u-full-width">
                    <thead>
                        <tr>
                            <th>Game Name</th>
                            <th>Players</th>
                            <th>Playing Time</th>
                            <th>Year Published</th>
                            <th>Publisher</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="game in games" :key="game.id">
                            <td>{{ game.name }}</td>
                            <td>{{ game.min_players }}-{{ game.max_players }}</td>
                            <td>{{ game.playing_time_minutes ? game.playing_time_minutes + ' min' : 'N/A' }}</td>
                            <td>{{ game.year_published || 'N/A' }}</td>
                            <td>{{ game.publisher || 'N/A' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';

defineProps({
    games: {
        type: Array,
        required: true,
    },
});

const logout = () => {
    router.post(route('logout'));
};
</script>

<style scoped>
table {
    border-collapse: collapse;
    width: 100%;
}

table th,
table td {
    border: 1px solid #cccccc;
    padding: 0.75rem;
    text-align: left;
}

table th {
    background-color: #f5f5f5;
    font-weight: bold;
}

table tbody tr:nth-child(even) {
    background-color: #fafafa;
}
</style>

