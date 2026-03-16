<template>
    <Head :title="group.friendly_name || 'Group'" />
    <div class="space-y-8">
        <!-- Row 1: Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex min-w-0 flex-1 items-start gap-3">
                <Link
                    :href="route('groups.index')"
                    class="flex-shrink-0 rounded-lg p-2 text-text-muted-dark hover:bg-surface-darker hover:text-text-dark"
                    aria-label="Back to groups"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-semibold text-text-dark m-0 truncate">
                            {{ group.friendly_name }}
                        </h1>
                        <span
                            :class="[
                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                currentUserRole === 'group_admin'
                                    ? 'bg-primary/20 text-primary'
                                    : 'bg-surface-darker text-text-muted-dark',
                            ]"
                        >
                            {{ currentUserRole === 'group_admin' ? 'Admin' : 'Member' }}
                        </span>
                    </div>
                    <p v-if="group.description" class="mt-1 text-sm text-text-muted-dark m-0">
                        {{ group.description }}
                    </p>
                    <p class="mt-1 text-xs text-text-muted-dark m-0">
                        Established {{ formatDate(group.created_at) }}
                    </p>
                </div>
            </div>
            <div class="flex flex-shrink-0 items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-border bg-primary px-4 py-2 text-sm font-medium text-text-primary hover:bg-primary-hover"
                    @click="showInvitePanel = true"
                >
                    Invite members
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-surface-darker bg-surface-darker px-4 py-2 text-sm font-medium text-text-dark hover:bg-surface-hover"
                    @click="showSettingsModal = true"
                >
                    Group Settings
                </button>
            </div>
        </div>

        <!-- Row 2: Invite panel -->
        <div
            v-if="showInvitePanel"
            class="rounded-xl border border-surface-darker bg-surface-dark p-4"
        >
            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0 flex-1 space-y-3">
                    <p v-if="inviteLoading" class="text-sm text-text-muted-dark m-0">Loading…</p>
                    <template v-else-if="inviteUrl">
                        <label class="block text-sm font-medium text-text-dark">Invitation link</label>
                        <div class="flex flex-wrap items-center gap-2">
                            <input
                                :value="inviteUrl"
                                type="text"
                                readonly
                                class="min-w-0 flex-1 rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-sm text-text-dark"
                            />
                            <button
                                type="button"
                                class="rounded-lg border border-border bg-primary px-3 py-2 text-sm font-medium text-text-primary hover:bg-primary-hover"
                                @click="copyInviteLink"
                            >
                                {{ copyFeedback ? 'Copied!' : 'Copy invitation link' }}
                            </button>
                        </div>
                    </template>
                    <template v-else>
                        <p class="text-sm text-text-muted-dark m-0">
                            No active invitation link.
                            <span v-if="currentUserRole === 'group_admin'">
                                Create one below to invite members.
                            </span>
                        </p>
                        <button
                            v-if="currentUserRole === 'group_admin'"
                            type="button"
                            class="rounded-lg border border-border bg-primary px-3 py-2 text-sm font-medium text-text-primary hover:bg-primary-hover disabled:opacity-50"
                            :disabled="inviteCreating"
                            @click="createInviteLink"
                        >
                            {{ inviteCreating ? 'Creating…' : 'Generate invitation link' }}
                        </button>
                    </template>
                </div>
                <button
                    type="button"
                    class="flex-shrink-0 text-sm text-text-muted-dark hover:text-text-dark"
                    @click="showInvitePanel = false"
                >
                    Close
                </button>
            </div>
        </div>

        <!-- Row 3: Stats cards -->
        <section>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-surface-darker bg-surface-dark p-4">
                    <div class="text-2xl font-bold text-text-dark">{{ groupSummary.total_plays }}</div>
                    <div class="mt-1 text-sm text-text-muted-dark">Total Plays</div>
                </div>
                <div class="rounded-xl border border-surface-darker bg-surface-dark p-4">
                    <div class="text-2xl font-bold text-text-dark">{{ groupSummary.total_hours_played }}</div>
                    <div class="mt-1 text-sm text-text-muted-dark">Total Hours played</div>
                </div>
                <div class="rounded-xl border border-surface-darker bg-surface-dark p-4">
                    <div class="text-2xl font-bold text-text-dark">{{ groupSummary.group_members_count }}</div>
                    <div class="mt-1 text-sm text-text-muted-dark">Group Members</div>
                </div>
                <div class="rounded-xl border border-surface-darker bg-surface-dark p-4">
                    <div class="text-2xl font-bold text-text-dark">{{ groupSummary.unique_games_played }}</div>
                    <div class="mt-1 text-sm text-text-muted-dark">Unique Games Played</div>
                </div>
            </div>
        </section>

        <!-- Row 4: Tabs -->
        <section>
            <div class="border-b border-surface-darker">
                <nav class="-mb-px flex gap-4" aria-label="Tabs">
                    <button
                        type="button"
                        :class="[
                            'border-b-2 py-3 text-sm font-medium transition',
                            activeTab === 'overview'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-text-muted-dark hover:border-surface-darker hover:text-text-dark',
                        ]"
                        @click="activeTab = 'overview'"
                    >
                        Overview
                    </button>
                    <button
                        type="button"
                        :class="[
                            'border-b-2 py-3 text-sm font-medium transition',
                            activeTab === 'members'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-text-muted-dark hover:border-surface-darker hover:text-text-dark',
                        ]"
                        @click="activeTab = 'members'"
                    >
                        Members
                    </button>
                    <button
                        type="button"
                        :class="[
                            'border-b-2 py-3 text-sm font-medium transition',
                            activeTab === 'games'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-text-muted-dark hover:border-surface-darker hover:text-text-dark',
                        ]"
                        @click="activeTab = 'games'"
                    >
                        Games
                    </button>
                </nav>
            </div>
            <div class="mt-4">
                <div v-if="activeTab === 'overview'" class="space-y-6 rounded-xl border border-surface-darker bg-surface-dark p-6">
                    <p v-if="overviewLoading" class="text-sm text-text-muted-dark">Loading overview…</p>
                    <p v-else-if="overviewError" class="text-sm text-red-500">{{ overviewError }}</p>
                    <template v-else-if="overviewData">
                        <section>
                            <h3 class="mb-3 text-sm font-medium text-text-dark">Monthly activity (last 3 years)</h3>
                            <div class="h-64">
                                <canvas ref="monthlyActivityCanvas" width="400" height="256"></canvas>
                            </div>
                        </section>
                        <section class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <h3 class="mb-3 text-sm font-medium text-text-dark">Distribution of game categories</h3>
                                <div class="h-48">
                                    <canvas ref="categoryDonutCanvas" width="200" height="192"></canvas>
                                </div>
                            </div>
                            <div>
                                <h3 class="mb-3 text-sm font-medium text-text-dark">Distribution of locations</h3>
                                <div class="h-48">
                                    <canvas ref="locationDonutCanvas" width="200" height="192"></canvas>
                                </div>
                            </div>
                        </section>
                        <section>
                            <h3 class="mb-3 text-sm font-medium text-text-dark">Most played games by time (top 10)</h3>
                            <ul class="space-y-2">
                                <li
                                    v-for="game in (overviewData.top_games_by_time || [])"
                                    :key="game.board_game_id"
                                    class="flex items-center gap-3 rounded-lg border border-surface-darker p-2"
                                >
                                    <img
                                        v-if="game.thumbnail_url"
                                        :src="game.thumbnail_url"
                                        :alt="game.name"
                                        class="h-12 w-12 flex-shrink-0 rounded object-cover"
                                    />
                                    <span v-else class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded bg-surface-darker text-xs text-text-muted-dark">—</span>
                                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-text-dark">{{ game.name }}</span>
                                    <span class="text-sm text-text-muted-dark">{{ formatMinutes(game.total_minutes) }}</span>
                                </li>
                            </ul>
                            <p v-if="!(overviewData.top_games_by_time || []).length" class="text-sm text-text-muted-dark">No plays yet.</p>
                        </section>
                    </template>
                </div>
                <div v-if="activeTab === 'members'" class="rounded-xl border border-surface-darker bg-surface-dark p-6">
                    <p v-if="membersStatsLoading" class="text-sm text-text-muted-dark">Loading member statistics…</p>
                    <p v-else-if="membersError" class="text-sm text-red-500">{{ membersError }}</p>
                    <div v-else-if="membersStats?.length" class="overflow-x-auto">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-surface-darker text-left">
                                    <th class="py-2 pr-4 font-medium text-text-dark">Member</th>
                                    <th class="py-2 px-2 font-medium text-text-dark">Games played</th>
                                    <th class="py-2 px-2 font-medium text-text-dark">Won</th>
                                    <th class="py-2 px-2 font-medium text-text-dark">Win %</th>
                                    <th class="py-2 px-2 font-medium text-text-dark">Unique games</th>
                                    <th class="py-2 px-2 font-medium text-text-dark">Time played</th>
                                    <th class="py-2 px-2 font-medium text-text-dark">Last active</th>
                                    <th class="py-2 px-2 font-medium text-text-dark">H-index (games)</th>
                                    <th class="py-2 pl-2 font-medium text-text-dark">H-index (players)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="m in membersStats"
                                    :key="m.group_member_id"
                                    class="border-b border-surface-darker"
                                >
                                    <td class="py-2 pr-4 font-medium text-text-dark">{{ m.display_name || m.user_name }}</td>
                                    <td class="py-2 px-2 text-text-muted-dark">{{ m.total_games_played }}</td>
                                    <td class="py-2 px-2 text-text-muted-dark">{{ m.total_games_won }}</td>
                                    <td class="py-2 px-2 text-text-muted-dark">{{ m.win_percentage }}%</td>
                                    <td class="py-2 px-2 text-text-muted-dark">{{ m.unique_games_played }}</td>
                                    <td class="py-2 px-2 text-text-muted-dark">{{ formatMinutes(m.total_minutes_played) }}</td>
                                    <td class="py-2 px-2 text-text-muted-dark">{{ m.last_active_at ? formatDate(m.last_active_at) : '—' }}</td>
                                    <td class="py-2 px-2 text-text-muted-dark">{{ m.h_index_games }}</td>
                                    <td class="py-2 pl-2 text-text-muted-dark">{{ m.h_index_players }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-text-muted-dark">No members or no data yet.</p>
                </div>
                <div v-if="activeTab === 'games'" class="rounded-xl border border-surface-darker bg-surface-dark p-6">
                    <div class="mb-4">
                        <label for="games-search" class="sr-only">Filter games by name</label>
                        <input
                            id="games-search"
                            v-model="gamesSearchQuery"
                            type="search"
                            placeholder="Search games by name…"
                            class="w-full rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-sm text-text-dark placeholder:text-text-muted-dark focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            @input="onGamesSearchInput"
                        />
                    </div>
                    <p v-if="gamesLoading" class="text-sm text-text-muted-dark">Loading games…</p>
                    <p v-else-if="gamesError" class="text-sm text-red-500">{{ gamesError }}</p>
                    <template v-else>
                        <ul class="space-y-2">
                            <li
                                v-for="game in (gamesData?.data || [])"
                                :key="game.board_game_id"
                                class="flex items-center gap-3 rounded-lg border border-surface-darker p-2 transition hover:border-primary/50 hover:bg-surface-darker/50"
                            >
                                <Link
                                    :href="route('boardgames.show', { id: game.board_game_id })"
                                    class="flex min-w-0 flex-1 items-center gap-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-surface-dark"
                                >
                                    <img
                                        v-if="game.thumbnail_url"
                                        :src="game.thumbnail_url"
                                        :alt="game.name"
                                        class="h-12 w-12 flex-shrink-0 rounded object-cover"
                                    />
                                    <span v-else class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded bg-surface-darker text-xs text-text-muted-dark">—</span>
                                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-text-dark">{{ game.name }}</span>
                                </Link>
                                <span class="text-sm text-text-muted-dark">{{ game.play_count }} plays</span>
                                <span class="text-sm text-text-muted-dark">{{ formatMinutes(game.total_minutes) }}</span>
                            </li>
                        </ul>
                        <p v-if="!(gamesData?.data || []).length && !gamesLoading" class="text-sm text-text-muted-dark">No games played yet.</p>
                        <div v-if="gamesData?.last_page > 1" class="mt-4 flex items-center justify-between">
                            <button
                                type="button"
                                class="rounded border border-surface-darker px-3 py-2 text-sm disabled:opacity-50"
                                :disabled="(gamesData?.current_page || 1) <= 1"
                                @click="loadGamesPage((gamesData?.current_page || 1) - 1)"
                            >
                                Previous
                            </button>
                            <span class="text-sm text-text-muted-dark">Page {{ gamesData?.current_page || 1 }} of {{ gamesData?.last_page || 1 }}</span>
                            <button
                                type="button"
                                class="rounded border border-surface-darker px-3 py-2 text-sm disabled:opacity-50"
                                :disabled="(gamesData?.current_page || 1) >= (gamesData?.last_page || 1)"
                                @click="loadGamesPage((gamesData?.current_page || 1) + 1)"
                            >
                                Next
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <!-- Settings modal -->
        <div
            v-if="showSettingsModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-background-dark/80 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="settings-title"
        >
            <div class="max-h-[90vh] w-full max-w-2xl overflow-auto rounded-xl border border-surface-darker bg-surface-dark p-6">
                <h2 id="settings-title" class="text-lg font-semibold text-text-dark m-0">Group Settings</h2>

                <!-- Visibility & invitation (admin) -->
                <section v-if="currentUserRole === 'group_admin'" class="mt-6 space-y-3">
                    <h3 class="text-sm font-medium text-text-dark">Visibility & invitation</h3>
                    <div class="flex flex-wrap items-center gap-3">
                        <select
                            v-model="settingsVisibility"
                            class="rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-sm text-text-dark"
                        >
                            <option value="private">Private</option>
                            <option value="viewable">Viewable</option>
                            <option value="publicly_joinable">Publicly joinable</option>
                        </select>
                        <button
                            type="button"
                            class="rounded-lg border border-border bg-primary px-3 py-2 text-sm font-medium text-text-primary hover:bg-primary-hover disabled:opacity-50"
                            :disabled="settingsSaving"
                            @click="saveVisibility"
                        >
                            {{ settingsSaving ? 'Saving…' : 'Save visibility' }}
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-border bg-primary px-3 py-2 text-sm font-medium text-text-primary hover:bg-primary-hover disabled:opacity-50"
                            :disabled="settingsRegenerating"
                            @click="regenerateInviteLink"
                        >
                            {{ settingsRegenerating ? 'Regenerating…' : 'Regenerate invitation link' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-500/20 disabled:opacity-50"
                            :disabled="settingsRevoking"
                            @click="revokeInviteLink"
                        >
                            {{ settingsRevoking ? 'Disabling…' : 'Disable invitation link' }}
                        </button>
                    </div>
                </section>

                <!-- Manage members (admin) -->
                <section class="mt-6">
                    <h3 class="text-sm font-medium text-text-dark">Members</h3>
                    <ul class="mt-2 divide-y divide-surface-darker">
                        <li
                            v-for="member in (group.group_members || [])"
                            :key="member.id"
                            class="flex flex-wrap items-center justify-between gap-2 py-2"
                        >
                            <span class="text-sm text-text-dark">{{ member.user?.name || member.display_name || 'Unknown' }}</span>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-text-muted-dark">{{ member.role === 'group_admin' ? 'Admin' : 'Member' }}</span>
                                <template v-if="currentUserRole === 'group_admin'">
                                    <button
                                        v-if="member.role === 'group_member'"
                                        type="button"
                                        class="text-xs text-primary hover:underline"
                                        @click="promoteMember(member.user?.id)"
                                    >
                                        Make admin
                                    </button>
                                    <button
                                        v-if="member.role === 'group_admin'"
                                        type="button"
                                        class="text-xs text-text-muted-dark hover:underline"
                                        @click="demoteMember(member.user?.id)"
                                    >
                                        Demote
                                    </button>
                                    <button
                                        type="button"
                                        class="text-xs text-red-600 hover:underline"
                                        @click="removeMember(member.user?.id)"
                                    >
                                        Remove
                                    </button>
                                </template>
                            </div>
                        </li>
                    </ul>
                </section>

                <!-- Location aliases (admin) -->
                <section v-if="currentUserRole === 'group_admin'" class="mt-6">
                    <h3 class="text-sm font-medium text-text-dark">Group locations for statistics</h3>
                    <p class="mt-1 text-xs text-text-muted-dark">Map location names to a display name for charts.</p>
                    <div class="mt-2 space-y-2">
                        <div
                            v-for="(alias, index) in settingsLocationAliases"
                            :key="index"
                            class="flex flex-wrap items-center gap-2 rounded-lg border border-surface-darker p-2"
                        >
                            <input
                                v-model="alias.display_name"
                                type="text"
                                placeholder="Display name"
                                class="min-w-[120px] rounded border border-surface-darker bg-surface-darker px-2 py-1 text-sm"
                            />
                            <input
                                v-model="alias.raw_locations_str"
                                type="text"
                                placeholder="Raw locations (comma-separated)"
                                class="min-w-[200px] flex-1 rounded border border-surface-darker bg-surface-darker px-2 py-1 text-sm"
                            />
                            <button
                                type="button"
                                class="text-sm text-red-600 hover:underline"
                                @click="removeLocationAlias(index)"
                            >
                                Remove
                            </button>
                        </div>
                        <button
                            type="button"
                            class="rounded border border-dashed border-surface-darker px-3 py-2 text-sm text-text-muted-dark hover:bg-surface-darker hover:text-text-dark"
                            @click="addLocationAlias"
                        >
                            + Add location group
                        </button>
                    </div>
                    <button
                        type="button"
                        class="mt-2 rounded-lg border border-border bg-primary px-3 py-2 text-sm font-medium text-text-primary hover:bg-primary-hover disabled:opacity-50"
                        :disabled="settingsSaving"
                        @click="saveGroupSettings"
                    >
                        Save location groups
                    </button>
                </section>

                <!-- Game groups (admin) -->
                <section v-if="currentUserRole === 'group_admin'" class="mt-6">
                    <h3 class="text-sm font-medium text-text-dark">Group games for statistics</h3>
                    <p class="mt-1 text-xs text-text-muted-dark">Group board games for statistics (e.g. "Catan family").</p>
                    <div class="mt-2 space-y-2">
                        <div
                            v-for="(grp, index) in settingsGameGroups"
                            :key="index"
                            class="flex flex-wrap items-center gap-2 rounded-lg border border-surface-darker p-2"
                        >
                            <input
                                v-model="grp.name"
                                type="text"
                                placeholder="Group name"
                                class="min-w-[120px] rounded border border-surface-darker bg-surface-darker px-2 py-1 text-sm"
                            />
                            <input
                                v-model="grp.board_game_ids_str"
                                type="text"
                                placeholder="Board game IDs (comma-separated)"
                                class="min-w-[160px] flex-1 rounded border border-surface-darker bg-surface-darker px-2 py-1 text-sm"
                            />
                            <button
                                type="button"
                                class="text-sm text-red-600 hover:underline"
                                @click="removeGameGroup(index)"
                            >
                                Remove
                            </button>
                        </div>
                        <button
                            type="button"
                            class="rounded border border-dashed border-surface-darker px-3 py-2 text-sm text-text-muted-dark hover:bg-surface-darker hover:text-text-dark"
                            @click="addGameGroup"
                        >
                            + Add game group
                        </button>
                    </div>
                    <button
                        type="button"
                        class="mt-2 rounded-lg border border-border bg-primary px-3 py-2 text-sm font-medium text-text-primary hover:bg-primary-hover disabled:opacity-50"
                        :disabled="settingsSaving"
                        @click="saveGroupSettings"
                    >
                        Save game groups
                    </button>
                </section>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="rounded-lg border border-surface-darker bg-surface-darker px-4 py-2 text-sm font-medium text-text-dark hover:bg-surface-hover"
                        @click="closeSettingsModal"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, nextTick } from 'vue';
import axios from 'axios';
import { Chart, registerables } from 'chart.js';
import AppLayout from '@/layouts/AppLayout.vue';

Chart.register(...registerables);

defineOptions({ layout: AppLayout });

const props = defineProps({
    group: {
        type: Object,
        required: true,
    },
    currentUserRole: {
        type: String,
        required: true,
        validator: (v) => ['group_admin', 'group_member'].includes(v),
    },
    groupSummary: {
        type: Object,
        default: () => ({
            total_plays: 0,
            total_hours_played: 0,
            group_members_count: 0,
            unique_games_played: 0,
        }),
    },
});

const activeTab = ref('overview');
const showInvitePanel = ref(false);
const showSettingsModal = ref(false);
const inviteUrl = ref(null);
const inviteLoading = ref(false);
const inviteCreating = ref(false);
const copyFeedback = ref(false);

const settingsVisibility = ref(props.group.visibility || 'private');
const settingsLocationAliases = ref([]);
const settingsGameGroups = ref([]);
const settingsSaving = ref(false);
const settingsRegenerating = ref(false);
const settingsRevoking = ref(false);

const overviewLoading = ref(false);
const overviewData = ref(null);
const overviewError = ref(null);
const membersStatsLoading = ref(false);
const membersStats = ref(null);
const membersError = ref(null);
const gamesLoading = ref(false);
const gamesData = ref(null);
const gamesError = ref(null);
const gamesSearchQuery = ref('');
let gamesSearchDebounceTimer = null;
const monthlyActivityCanvas = ref(null);
const categoryDonutCanvas = ref(null);
const locationDonutCanvas = ref(null);
let monthlyActivityChart = null;
let categoryDonutChart = null;
let locationDonutChart = null;

watch(showInvitePanel, (visible) => {
    if (visible) {
        fetchInvite();
    }
});

watch(showSettingsModal, (visible) => {
    if (visible) {
        settingsVisibility.value = props.group.visibility || 'private';
        const gs = props.group.group_settings || {};
        settingsLocationAliases.value = (gs.location_aliases || []).map((a) => ({
            display_name: a.display_name || '',
            raw_locations: Array.isArray(a.raw_locations) ? a.raw_locations : [],
            raw_locations_str: Array.isArray(a.raw_locations) ? a.raw_locations.join(', ') : '',
        }));
        settingsGameGroups.value = (gs.game_groups || []).map((g) => ({
            name: g.name || '',
            board_game_ids: Array.isArray(g.board_game_ids) ? g.board_game_ids : [],
            board_game_ids_str: Array.isArray(g.board_game_ids) ? g.board_game_ids.join(', ') : '',
        }));
    }
});

watch(activeTab, (tab) => {
    if (tab === 'overview') {
        if (!overviewData.value) {
            fetchOverview();
        } else {
            // Canvases are re-created when tab is shown again; redraw charts on the new elements.
            nextTick(() => drawOverviewCharts());
        }
    }
    if (tab === 'members' && !membersStats.value) {
        fetchMembersStats();
    }
    if (tab === 'games' && !gamesData.value) {
        fetchGames(1, gamesSearchQuery.value);
    }
}, { immediate: true });

watch(overviewData, (data) => {
    if (data) {
        nextTick(() => {
            drawOverviewCharts();
        });
    }
}, { flush: 'post' });

async function fetchInvite() {
    inviteLoading.value = true;
    inviteUrl.value = null;
    try {
        const { data } = await axios.get(route('api.groups.invites.index', { id: props.group.id }), {
            headers: { Accept: 'application/json' },
        });
        if (data.data?.invite_url) {
            inviteUrl.value = data.data.invite_url;
        }
    } finally {
        inviteLoading.value = false;
    }
}

async function createInviteLink() {
    if (props.currentUserRole !== 'group_admin') return;
    inviteCreating.value = true;
    try {
        const { data } = await axios.post(route('api.groups.invites.store', { id: props.group.id }), {}, {
            headers: { Accept: 'application/json' },
        });
        if (data.data?.invite_url) {
            inviteUrl.value = data.data.invite_url;
        }
    } finally {
        inviteCreating.value = false;
    }
}

async function copyInviteLink() {
    if (!inviteUrl.value) return;
    try {
        await navigator.clipboard.writeText(inviteUrl.value);
        copyFeedback.value = true;
        setTimeout(() => {
            copyFeedback.value = false;
        }, 2000);
    } catch (_) {
        // fallback or ignore
    }
}

async function saveVisibility() {
    settingsSaving.value = true;
    try {
        await axios.patch(route('api.groups.update', { id: props.group.id }), { visibility: settingsVisibility.value }, { headers: { Accept: 'application/json' } });
        router.reload({ only: ['group'] });
    } finally {
        settingsSaving.value = false;
    }
}

async function regenerateInviteLink() {
    settingsRegenerating.value = true;
    try {
        const { data } = await axios.post(route('api.groups.invites.regenerate', { id: props.group.id }), {}, { headers: { Accept: 'application/json' } });
        if (data.data?.invite_url) inviteUrl.value = data.data.invite_url;
        router.reload({ only: ['group'] });
    } finally {
        settingsRegenerating.value = false;
    }
}

async function revokeInviteLink() {
    if (!confirm('Disable the current invitation link? New joins will need a new link.')) return;
    settingsRevoking.value = true;
    try {
        await axios.post(route('api.groups.invites.revoke', { id: props.group.id }), {}, { headers: { Accept: 'application/json' } });
        inviteUrl.value = null;
        router.reload({ only: ['group'] });
    } finally {
        settingsRevoking.value = false;
    }
}

async function promoteMember(userId) {
    if (!userId) return;
    try {
        await axios.patch(route('api.groups.members.update', { id: props.group.id, userId }), { role: 'group_admin' }, { headers: { Accept: 'application/json' } });
        router.reload({ only: ['group'] });
    } catch (_) {}
}

async function demoteMember(userId) {
    if (!userId) return;
    try {
        await axios.patch(route('api.groups.members.update', { id: props.group.id, userId }), { role: 'group_member' }, { headers: { Accept: 'application/json' } });
        router.reload({ only: ['group'] });
    } catch (_) {}
}

async function removeMember(userId) {
    if (!userId || !confirm('Remove this member from the group?')) return;
    try {
        await axios.delete(route('api.groups.members.destroy', { id: props.group.id, userId }), { headers: { Accept: 'application/json' } });
        router.reload({ only: ['group'] });
    } catch (_) {}
}

function addLocationAlias() {
    settingsLocationAliases.value.push({ display_name: '', raw_locations: [], raw_locations_str: '' });
}

function removeLocationAlias(index) {
    settingsLocationAliases.value.splice(index, 1);
}

function addGameGroup() {
    settingsGameGroups.value.push({ name: '', board_game_ids: [], board_game_ids_str: '' });
}

function removeGameGroup(index) {
    settingsGameGroups.value.splice(index, 1);
}

async function saveGroupSettings() {
    settingsSaving.value = true;
    try {
        const location_aliases = settingsLocationAliases.value
            .filter((a) => a.display_name.trim())
            .map((a) => ({
                display_name: a.display_name.trim(),
                raw_locations: a.raw_locations_str.split(',').map((s) => s.trim()).filter(Boolean),
            }));
        const game_groups = settingsGameGroups.value
            .filter((g) => g.name.trim())
            .map((g) => ({
                name: g.name.trim(),
                board_game_ids: g.board_game_ids_str.split(',').map((s) => parseInt(s.trim(), 10)).filter((n) => !Number.isNaN(n)),
            }));
        await axios.patch(route('api.groups.update', { id: props.group.id }), {
            group_settings: { location_aliases, game_groups },
        }, { headers: { Accept: 'application/json' } });
        router.reload({ only: ['group'] });
    } finally {
        settingsSaving.value = false;
    }
}

function closeSettingsModal() {
    showSettingsModal.value = false;
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
}

function formatMinutes(minutes) {
    if (minutes < 60) return `${minutes} min`;
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return m ? `${h}h ${m}m` : `${h}h`;
}

async function fetchOverview() {
    overviewLoading.value = true;
    overviewData.value = null;
    overviewError.value = null;
    try {
        const { data } = await axios.get(route('api.groups.overview', { id: props.group.id }), { headers: { Accept: 'application/json' } });
        overviewData.value = data.data || data;
    } catch (err) {
        overviewError.value = err.response?.data?.message || err.message || 'Failed to load overview.';
    } finally {
        overviewLoading.value = false;
    }
}

async function fetchMembersStats() {
    membersStatsLoading.value = true;
    membersStats.value = null;
    membersError.value = null;
    try {
        const { data } = await axios.get(route('api.groups.members.statistics', { id: props.group.id }), { headers: { Accept: 'application/json' } });
        membersStats.value = (data.data?.members || data.members || []);
    } catch (err) {
        membersError.value = err.response?.data?.message || err.message || 'Failed to load member statistics.';
    } finally {
        membersStatsLoading.value = false;
    }
}

async function fetchGames(page, search = '') {
    gamesLoading.value = true;
    gamesError.value = null;
    try {
        const params = { page, per_page: 15 };
        if (search && search.trim()) {
            params.search = search.trim();
        }
        const { data } = await axios.get(route('api.groups.games.index', { id: props.group.id }), { params, headers: { Accept: 'application/json' } });
        gamesData.value = data.data || data;
    } catch (err) {
        gamesError.value = err.response?.data?.message || err.message || 'Failed to load games.';
    } finally {
        gamesLoading.value = false;
    }
}

function onGamesSearchInput() {
    if (gamesSearchDebounceTimer) clearTimeout(gamesSearchDebounceTimer);
    gamesSearchDebounceTimer = setTimeout(() => {
        gamesSearchDebounceTimer = null;
        fetchGames(1, gamesSearchQuery.value);
    }, 300);
}

function loadGamesPage(page) {
    fetchGames(page, gamesSearchQuery.value);
}

function drawOverviewCharts() {
    const data = overviewData.value;
    if (!data) return;

    const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    if (monthlyActivityCanvas.value && data.monthly_activity) {
        if (monthlyActivityChart) monthlyActivityChart.destroy();
        const years = Object.entries(data.monthly_activity);
        const datasets = years.map(([year, monthCounts], i) => {
            const values = Array.from({ length: 12 }, (_, j) => monthCounts[j + 1] ?? 0);
            const colors = ['#6366f1', '#22c55e', '#f59e0b'];
            return {
                label: year,
                data: values,
                borderColor: colors[i % colors.length],
                backgroundColor: colors[i % colors.length] + '20',
                tension: 0.2,
                fill: false,
            };
        });
        monthlyActivityChart = new Chart(monthlyActivityCanvas.value, {
            type: 'line',
            data: { labels: monthLabels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                plugins: { legend: { position: 'top' } },
            },
        });
    }

    if (categoryDonutCanvas.value && data.category_distribution?.length) {
        if (categoryDonutChart) categoryDonutChart.destroy();
        categoryDonutChart = new Chart(categoryDonutCanvas.value, {
            type: 'doughnut',
            data: {
                labels: data.category_distribution.map((d) => d.name),
                datasets: [{ data: data.category_distribution.map((d) => d.count), backgroundColor: ['#6366f1', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6'].slice(0, data.category_distribution.length) }],
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
        });
    }

    if (locationDonutCanvas.value && data.location_distribution?.length) {
        if (locationDonutChart) locationDonutChart.destroy();
        locationDonutChart = new Chart(locationDonutCanvas.value, {
            type: 'doughnut',
            data: {
                labels: data.location_distribution.map((d) => d.name),
                datasets: [{ data: data.location_distribution.map((d) => d.count), backgroundColor: ['#6366f1', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6'].slice(0, data.location_distribution.length) }],
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
        });
    }
}
</script>
