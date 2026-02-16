<template>
    <Head title="Dashboard" />
    <div class="space-y-8">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-text-dark m-0">Dashboard</h1>
            <form @submit.prevent="logout" class="m-0">
                <button
                    type="submit"
                    class="rounded-lg border border-border bg-primary px-4 py-2 text-sm font-medium text-text-primary shadow-cartoon transition hover:bg-primary-hover"
                >
                    Logout
                </button>
            </form>
        </div>

        <!-- Overall Statistics Section -->
        <section>
            <h2 class="mb-4 text-lg font-medium text-text-dark">Your Statistics</h2>
            <div class="flex flex-wrap gap-4">
                <div class="min-w-[200px] flex-1 rounded-xl border border-surface-darker bg-surface-dark p-4">
                    <div class="text-3xl font-bold text-text-dark">
                        {{ userStatistics.total_games_played }}
                    </div>
                    <div class="mt-2 text-sm text-text-muted-dark">Total Games Played</div>
                </div>
                <div class="min-w-[200px] flex-1 rounded-xl border border-surface-darker bg-surface-dark p-4">
                    <div class="text-3xl font-bold text-text-dark">
                        {{ userStatistics.total_games_won }}
                    </div>
                    <div class="mt-2 text-sm text-text-muted-dark">Games Won</div>
                </div>
                <div class="min-w-[200px] flex-1 rounded-xl border border-surface-darker bg-surface-dark p-4">
                    <div class="text-3xl font-bold text-text-dark">
                        {{ userStatistics.total_games_played > 0
                            ? ((userStatistics.total_games_won / userStatistics.total_games_played) * 100).toFixed(1)
                            : '0' }}%
                    </div>
                    <div class="mt-2 text-sm text-text-muted-dark">Win Rate</div>
                </div>
            </div>
        </section>

        <!-- Last Games Played by User Section -->
        <section>
            <h2 class="mb-4 text-lg font-medium text-text-dark">Your Recent Games</h2>
            <p
                v-if="userPlaysPaginator.total > 0"
                class="mb-3 text-sm text-text-muted-dark"
            >
                Duplicate plays are shown greyed out and are not counted in your statistics.
            </p>
            <div class="overflow-x-auto rounded-xl border border-surface-darker">
                <table
                    v-if="userPlaysPaginator.data && userPlaysPaginator.data.length > 0"
                    class="w-full border-collapse"
                >
                    <thead>
                        <tr class="border-b border-surface-darker bg-surface-darker">
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Date</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Game</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Location</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Duration</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Players</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Your Result</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Logged by</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">BGG</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="play in userPlaysPaginator.data"
                            :key="play.id"
                            :class="[
                                'border-b border-surface-darker',
                                play.is_excluded
                                    ? 'bg-surface-darker/50 text-text-muted-dark [&_img]:opacity-70'
                                    : 'bg-surface-dark text-text-dark',
                            ]"
                        >
                            <td class="px-4 py-3 text-sm">
                                <span
                                    v-if="play.is_excluded"
                                    class="text-text-muted-dark"
                                    title="Duplicate play (not counted in statistics)"
                                >⊕ </span>
                                {{ formatDate(play.played_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <img
                                        v-if="play.board_game?.thumbnail_url"
                                        :src="play.board_game.thumbnail_url"
                                        :alt="(play.board_game?.name || 'Game') + ' thumbnail'"
                                        class="h-10 w-10 rounded object-cover"
                                    />
                                    <span>{{ play.board_game?.name || 'Unknown Game' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ play.location || 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">{{ play.game_length_minutes ? play.game_length_minutes + ' min' : 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div v-for="player in play.players" :key="player.id" class="mb-1">
                                    <span
                                        :class="[
                                            player.is_winner ? 'font-bold text-accent' : 'text-text-dark',
                                        ]"
                                    >
                                        {{ getPlayerName(player) }}
                                        <span v-if="player.score !== null"> ({{ player.score }})</span>
                                        <span v-if="player.is_winner" class="ml-1 text-accent">✓</span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    v-if="getUserPlayerResult(play)"
                                    :class="[
                                        'font-bold',
                                        getUserPlayerResult(play).is_winner ? 'text-accent' : 'text-primary',
                                    ]"
                                >
                                    {{ getUserPlayerResult(play).is_winner ? 'Won' : 'Lost' }}
                                    <span v-if="getUserPlayerResult(play).score !== null">
                                        ({{ getUserPlayerResult(play).score }})
                                    </span>
                                </span>
                                <span v-else class="text-text-muted-dark">N/A</span>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ play.creator?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <a
                                    v-if="play.bgg_play_id"
                                    :href="`https://boardgamegeek.com/play/details/${play.bgg_play_id}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-accent underline hover:no-underline"
                                >
                                    BGG
                                </a>
                                <span v-else class="text-text-muted-dark">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="userPlaysPaginator.data && userPlaysPaginator.data.length > 0"
                class="mt-4 flex flex-wrap items-center justify-between gap-2"
            >
                <span class="text-sm text-text-muted-dark">
                    Page {{ userPlaysPaginator.current_page }} of {{ userPlaysPaginator.last_page }}
                    ({{ userPlaysPaginator.total }} total)
                </span>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-surface-darker bg-surface-dark px-3 py-1.5 text-sm text-text-dark disabled:cursor-not-allowed disabled:opacity-50 hover:bg-surface-darker"
                        :disabled="!userPlaysPaginator.prev_page_url"
                        @click="goToUserPlaysPage(userPlaysPaginator.current_page - 1)"
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-surface-darker bg-surface-dark px-3 py-1.5 text-sm text-text-dark disabled:cursor-not-allowed disabled:opacity-50 hover:bg-surface-darker"
                        :disabled="!userPlaysPaginator.next_page_url"
                        @click="goToUserPlaysPage(userPlaysPaginator.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
            <p v-else class="py-4 text-text-muted-dark">No games played yet.</p>
        </section>

        <!-- Last Games Played by Group Section -->
        <section>
            <h2 class="mb-4 text-lg font-medium text-text-dark">Group Recent Games</h2>
            <div
                v-if="lastGroupPlays.length > 0"
                class="overflow-x-auto rounded-xl border border-surface-darker"
            >
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-surface-darker bg-surface-darker">
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Date</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Game</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Location</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Duration</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Players</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Winners</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Logged by</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">BGG</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="play in lastGroupPlays"
                            :key="play.id"
                            class="border-b border-surface-darker bg-surface-dark text-text-dark"
                        >
                            <td class="px-4 py-3 text-sm">{{ formatDate(play.played_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <img
                                        v-if="play.board_game?.thumbnail_url"
                                        :src="play.board_game.thumbnail_url"
                                        :alt="(play.board_game?.name || 'Game') + ' thumbnail'"
                                        class="h-10 w-10 rounded object-cover"
                                    />
                                    <span>{{ play.board_game?.name || 'Unknown Game' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ play.location || 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">{{ play.game_length_minutes ? play.game_length_minutes + ' min' : 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div v-for="player in play.players" :key="player.id" class="mb-1">
                                    <span
                                        :class="[
                                            player.is_winner ? 'font-bold text-accent' : 'text-text-dark',
                                        ]"
                                    >
                                        {{ getPlayerName(player) }}
                                        <span v-if="player.score !== null"> ({{ player.score }})</span>
                                        <span v-if="player.is_winner" class="ml-1 text-accent">✓</span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    v-for="(winner, index) in getWinners(play)"
                                    :key="winner.id"
                                    class="font-bold text-accent"
                                >
                                    {{ getPlayerName(winner) }}<span v-if="index < getWinners(play).length - 1">, </span>
                                </span>
                                <span v-if="getWinners(play).length === 0" class="text-text-muted-dark">No winners</span>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ play.creator?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <a
                                    v-if="play.bgg_play_id"
                                    :href="`https://boardgamegeek.com/play/details/${play.bgg_play_id}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-accent underline hover:no-underline"
                                >
                                    BGG
                                </a>
                                <span v-else class="text-text-muted-dark">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="py-4 text-text-muted-dark">No group games played yet.</p>
        </section>

        <!-- Games Table -->
        <section>
            <h2 class="mb-4 text-lg font-medium text-text-dark">Random Games</h2>
            <div class="overflow-x-auto rounded-xl border border-surface-darker">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-surface-darker bg-surface-darker">
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Thumbnail</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Game Name</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Players</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Playing Time</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Year Published</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Publisher</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">BGG Rating</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Complexity</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-text-dark">Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="game in games"
                            :key="game.id"
                            class="border-b border-surface-darker bg-surface-dark text-text-dark"
                        >
                            <td class="px-4 py-3">
                                <img
                                    v-if="game.thumbnail_url"
                                    :src="game.thumbnail_url"
                                    :alt="game.name + ' thumbnail'"
                                    class="h-12 w-12 rounded object-cover"
                                />
                                <span v-else class="text-text-muted-dark">N/A</span>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ game.name }}</td>
                            <td class="px-4 py-3 text-sm">{{ game.min_players }}-{{ game.max_players }}</td>
                            <td class="px-4 py-3 text-sm">{{ game.playing_time_minutes ? game.playing_time_minutes + ' min' : 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">{{ game.year_published || 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">{{ game.publisher || 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span v-if="game.bgg_rating !== null">
                                    {{ parseFloat(game.bgg_rating).toFixed(3) }}
                                </span>
                                <span v-else class="text-text-muted-dark">N/A</span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span v-if="game.complexity_rating !== null">
                                    {{ parseFloat(game.complexity_rating).toFixed(3) }}
                                </span>
                                <span v-else class="text-text-muted-dark">N/A</span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    v-if="game.is_expansion"
                                    class="rounded bg-brand-yellow-dark px-2 py-0.5 text-xs font-medium text-brand-ink"
                                >
                                    Expansion
                                </span>
                                <span v-else class="text-text-muted-dark">Base Game</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    games: {
        type: Array,
        required: true,
    },
    userStatistics: {
        type: Object,
        required: true,
    },
    userPlaysPaginator: {
        type: Object,
        required: true,
        default: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            total: 0,
            prev_page_url: null,
            next_page_url: null,
        }),
    },
    lastGroupPlays: {
        type: Array,
        required: true,
    },
    currentUserId: {
        type: Number,
        required: true,
    },
});

const goToUserPlaysPage = (page) => {
    if (page < 1 || page > props.userPlaysPaginator.last_page) return;
    router.get(route('dashboard'), { user_plays_page: page }, { preserveState: true });
};

const logout = () => {
    router.post(route('logout'));
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
};

const getPlayerName = (player) => {
    if (player.user) {
        return player.user.name;
    }
    if (player.board_game_geek_username) {
        return player.board_game_geek_username;
    }
    if (player.guest_name) {
        return player.guest_name;
    }
    return 'Unknown Player';
};

const getUserPlayerResult = (play) => {
    return play.players?.find(p => p.user_id === props.currentUserId) || null;
};

const getWinners = (play) => {
    return play.players?.filter(p => p.is_winner) || [];
};
</script>


