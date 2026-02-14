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

        <!-- Overall Statistics Section -->
        <div class="row" style="margin-bottom: 2rem;">
            <div class="twelve columns">
                <h2>Your Statistics</h2>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px; padding: 1rem; background-color: #f5f5f5; border-radius: 4px;">
                        <div style="font-size: 2rem; font-weight: bold; color: #333;">
                            {{ userStatistics.total_games_played }}
                        </div>
                        <div style="color: #666; margin-top: 0.5rem;">Total Games Played</div>
                    </div>
                    <div style="flex: 1; min-width: 200px; padding: 1rem; background-color: #f5f5f5; border-radius: 4px;">
                        <div style="font-size: 2rem; font-weight: bold; color: #333;">
                            {{ userStatistics.total_games_won }}
                        </div>
                        <div style="color: #666; margin-top: 0.5rem;">Games Won</div>
                    </div>
                    <div style="flex: 1; min-width: 200px; padding: 1rem; background-color: #f5f5f5; border-radius: 4px;">
                        <div style="font-size: 2rem; font-weight: bold; color: #333;">
                            {{ userStatistics.total_games_played > 0 
                                ? ((userStatistics.total_games_won / userStatistics.total_games_played) * 100).toFixed(1) 
                                : '0' }}%
                        </div>
                        <div style="color: #666; margin-top: 0.5rem;">Win Rate</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Last Games Played by User Section -->
        <div class="row" style="margin-bottom: 2rem;">
            <div class="twelve columns">
                <h2>Your Recent Games</h2>
                <p v-if="userPlaysPaginator.total > 0" class="recent-games-hint" style="color: #666; font-size: 0.875rem; margin-bottom: 0.75rem;">
                    Duplicate plays are shown greyed out and are not counted in your statistics.
                </p>
                <table class="u-full-width" v-if="userPlaysPaginator.data && userPlaysPaginator.data.length > 0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Game</th>
                            <th>Location</th>
                            <th>Duration</th>
                            <th>Players</th>
                            <th>Your Result</th>
                            <th>Logged by</th>
                            <th>BGG</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="play in userPlaysPaginator.data"
                            :key="play.id"
                            :class="{ 'play-row-excluded': play.is_excluded }"
                        >
                            <td>
                                <span v-if="play.is_excluded" class="duplicate-label" title="Duplicate play (not counted in statistics)">⊕ </span>
                                {{ formatDate(play.played_at) }}
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <img 
                                        v-if="play.board_game?.thumbnail_url" 
                                        :src="play.board_game.thumbnail_url" 
                                        :alt="play.board_game.name + ' thumbnail'"
                                        style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;"
                                    />
                                    <span>{{ play.board_game?.name || 'Unknown Game' }}</span>
                                </div>
                            </td>
                            <td>{{ play.location || 'N/A' }}</td>
                            <td>{{ play.game_length_minutes ? play.game_length_minutes + ' min' : 'N/A' }}</td>
                            <td>
                                <div style="font-size: 0.875rem;">
                                    <div v-for="player in play.players" :key="player.id" style="margin-bottom: 0.25rem;">
                                        <span :style="{ fontWeight: player.is_winner ? 'bold' : 'normal', color: player.is_winner ? '#4caf50' : '#333' }">
                                            {{ getPlayerName(player) }}
                                            <span v-if="player.score !== null"> ({{ player.score }})</span>
                                            <span v-if="player.is_winner" style="color: #4caf50; margin-left: 0.25rem;">✓</span>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span v-if="getUserPlayerResult(play)" :style="{ 
                                    fontWeight: 'bold',
                                    color: getUserPlayerResult(play).is_winner ? '#4caf50' : '#f44336'
                                }">
                                    {{ getUserPlayerResult(play).is_winner ? 'Won' : 'Lost' }}
                                    <span v-if="getUserPlayerResult(play).score !== null">
                                        ({{ getUserPlayerResult(play).score }})
                                    </span>
                                </span>
                                <span v-else style="color: #999;">N/A</span>
                            </td>
                            <td>{{ play.creator?.name ?? '—' }}</td>
                            <td>
                                <a
                                    v-if="play.bgg_play_id"
                                    :href="`https://boardgamegeek.com/play/details/${play.bgg_play_id}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    style="color: #1976d2;"
                                >
                                    BGG
                                </a>
                                <span v-else style="color: #999;">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="userPlaysPaginator.data && userPlaysPaginator.data.length > 0" class="pagination-controls" style="display: flex; align-items: center; justify-content: space-between; margin-top: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                    <span class="pagination-info" style="color: #666; font-size: 0.875rem;">
                        Page {{ userPlaysPaginator.current_page }} of {{ userPlaysPaginator.last_page }}
                        ({{ userPlaysPaginator.total }} total)
                    </span>
                    <div style="display: flex; gap: 0.5rem;">
                        <button
                            type="button"
                            class="button"
                            :disabled="!userPlaysPaginator.prev_page_url"
                            @click="goToUserPlaysPage(userPlaysPaginator.current_page - 1)"
                        >
                            Previous
                        </button>
                        <button
                            type="button"
                            class="button"
                            :disabled="!userPlaysPaginator.next_page_url"
                            @click="goToUserPlaysPage(userPlaysPaginator.current_page + 1)"
                        >
                            Next
                        </button>
                    </div>
                </div>
                <p v-else style="color: #666; padding: 1rem;">No games played yet.</p>
            </div>
        </div>

        <!-- Last Games Played by Group Section -->
        <div class="row" style="margin-bottom: 2rem;">
            <div class="twelve columns">
                <h2>Group Recent Games</h2>
                <table class="u-full-width" v-if="lastGroupPlays.length > 0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Game</th>
                            <th>Location</th>
                            <th>Duration</th>
                            <th>Players</th>
                            <th>Winners</th>
                            <th>Logged by</th>
                            <th>BGG</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="play in lastGroupPlays" :key="play.id">
                            <td>{{ formatDate(play.played_at) }}</td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <img 
                                        v-if="play.board_game?.thumbnail_url" 
                                        :src="play.board_game.thumbnail_url" 
                                        :alt="play.board_game.name + ' thumbnail'"
                                        style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;"
                                    />
                                    <span>{{ play.board_game?.name || 'Unknown Game' }}</span>
                                </div>
                            </td>
                            <td>{{ play.location || 'N/A' }}</td>
                            <td>{{ play.game_length_minutes ? play.game_length_minutes + ' min' : 'N/A' }}</td>
                            <td>
                                <div style="font-size: 0.875rem;">
                                    <div v-for="player in play.players" :key="player.id" style="margin-bottom: 0.25rem;">
                                        <span :style="{ fontWeight: player.is_winner ? 'bold' : 'normal', color: player.is_winner ? '#4caf50' : '#333' }">
                                            {{ getPlayerName(player) }}
                                            <span v-if="player.score !== null"> ({{ player.score }})</span>
                                            <span v-if="player.is_winner" style="color: #4caf50; margin-left: 0.25rem;">✓</span>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.875rem;">
                                    <span 
                                        v-for="(winner, index) in getWinners(play)" 
                                        :key="winner.id"
                                        style="color: #4caf50; font-weight: bold;"
                                    >
                                        {{ getPlayerName(winner) }}<span v-if="index < getWinners(play).length - 1">, </span>
                                    </span>
                                    <span v-if="getWinners(play).length === 0" style="color: #999;">No winners</span>
                                </div>
                            </td>
                            <td>{{ play.creator?.name ?? '—' }}</td>
                            <td>
                                <a
                                    v-if="play.bgg_play_id"
                                    :href="`https://boardgamegeek.com/play/details/${play.bgg_play_id}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    style="color: #1976d2;"
                                >
                                    BGG
                                </a>
                                <span v-else style="color: #999;">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else style="color: #666; padding: 1rem;">No group games played yet.</p>
            </div>
        </div>

        <!-- Games Table -->
        <div class="row">
            <div class="twelve columns">
                <h2>Random Games</h2>
                <table class="u-full-width">
                    <thead>
                        <tr>
                            <th>Thumbnail</th>
                            <th>Game Name</th>
                            <th>Players</th>
                            <th>Playing Time</th>
                            <th>Year Published</th>
                            <th>Publisher</th>
                            <th>BGG Rating</th>
                            <th>Complexity</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="game in games" :key="game.id">
                            <td>
                                <img 
                                    v-if="game.thumbnail_url" 
                                    :src="game.thumbnail_url" 
                                    :alt="game.name + ' thumbnail'"
                                    style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;"
                                />
                                <span v-else style="color: #999;">N/A</span>
                            </td>
                            <td>{{ game.name }}</td>
                            <td>{{ game.min_players }}-{{ game.max_players }}</td>
                            <td>{{ game.playing_time_minutes ? game.playing_time_minutes + ' min' : 'N/A' }}</td>
                            <td>{{ game.year_published || 'N/A' }}</td>
                            <td>{{ game.publisher || 'N/A' }}</td>
                            <td>
                                <span v-if="game.bgg_rating !== null">
                                    {{ parseFloat(game.bgg_rating).toFixed(3) }}
                                </span>
                                <span v-else style="color: #999;">N/A</span>
                            </td>
                            <td>
                                <span v-if="game.complexity_rating !== null">
                                    {{ parseFloat(game.complexity_rating).toFixed(3) }}
                                </span>
                                <span v-else style="color: #999;">N/A</span>
                            </td>
                            <td>
                                <span 
                                    v-if="game.is_expansion" 
                                    style="background-color: #ff9800; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem;"
                                >
                                    Expansion
                                </span>
                                <span v-else style="color: #666;">Base Game</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';

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

/* Duplicate (excluded) plays: greyed out, not counted in statistics */
table tbody tr.play-row-excluded {
    background-color: #e8e8e8;
    color: #888;
}

table tbody tr.play-row-excluded:nth-child(even) {
    background-color: #e0e0e0;
}

table tbody tr.play-row-excluded img {
    opacity: 0.7;
}

.duplicate-label {
    color: #888;
    font-size: 0.875rem;
}

.pagination-controls .button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>

