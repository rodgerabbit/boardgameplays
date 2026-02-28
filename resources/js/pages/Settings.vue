<template>
    <Head title="Settings" />
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-text-dark m-0">Settings</h1>

        <!-- Tabs -->
        <div
            role="tablist"
            aria-label="Settings sections"
            class="flex gap-1 border-b border-surface-darker"
        >
            <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'profile'"
                :class="[
                    'rounded-t-lg border-b-2 px-4 py-2.5 text-sm font-medium transition',
                    activeTab === 'profile'
                        ? 'border-primary bg-surface-dark text-accent'
                        : 'border-transparent text-text-muted-dark hover:bg-surface-darker hover:text-text-dark',
                ]"
                @click="activeTab = 'profile'"
            >
                Profile
            </button>
            <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'boardgamegeek'"
                :class="[
                    'rounded-t-lg border-b-2 px-4 py-2.5 text-sm font-medium transition',
                    activeTab === 'boardgamegeek'
                        ? 'border-primary bg-surface-dark text-accent'
                        : 'border-transparent text-text-muted-dark hover:bg-surface-darker hover:text-text-dark',
                ]"
                @click="activeTab = 'boardgamegeek'"
            >
                BoardGameGeek
            </button>
            <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'notifications'"
                :class="[
                    'rounded-t-lg border-b-2 px-4 py-2.5 text-sm font-medium transition',
                    activeTab === 'notifications'
                        ? 'border-primary bg-surface-dark text-accent'
                        : 'border-transparent text-text-muted-dark hover:bg-surface-darker hover:text-text-dark',
                ]"
                @click="activeTab = 'notifications'"
            >
                Notifications
            </button>
        </div>

        <!-- Tab panels -->
        <div class="rounded-xl border border-surface-darker bg-surface-dark p-6">
            <!-- Profile tab -->
            <div
                v-show="activeTab === 'profile'"
                role="tabpanel"
                aria-labelledby="tab-profile"
                class="space-y-6"
            >
                <form @submit.prevent="submitProfile" class="space-y-6 max-w-xl">
                    <!-- Profile Picture -->
                    <div class="flex flex-col gap-3">
                        <label class="text-sm font-medium text-text-dark">Profile Picture</label>
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-24 w-24 flex-shrink-0 overflow-hidden rounded-full border-2 border-surface-darker bg-surface-darker"
                            >
                                <img
                                    v-if="profilePicturePreview || user.profile_picture_url"
                                    :src="profilePicturePreview || user.profile_picture_url"
                                    alt="Profile"
                                    class="h-full w-full object-cover"
                                />
                                <span
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-2xl font-semibold text-text-muted-dark"
                                >
                                    {{ user.name?.charAt(0)?.toUpperCase() || '?' }}
                                </span>
                            </div>
                            <div class="flex flex-col gap-2">
                                <input
                                    ref="profilePictureInputRef"
                                    type="file"
                                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                    class="hidden"
                                    @change="onProfilePictureChange"
                                />
                                <button
                                    type="button"
                                    class="rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-sm font-medium text-text-dark hover:bg-surface-hover"
                                    @click="profilePictureInputRef?.click()"
                                >
                                    Choose photo
                                </button>
                                <p class="text-xs text-text-muted-dark">
                                    JPEG, PNG, GIF or WebP. Max 2 MB.
                                </p>
                            </div>
                        </div>
                        <p v-if="profileForm.errors.profile_picture" class="text-sm text-primary">
                            {{ profileForm.errors.profile_picture }}
                        </p>
                    </div>

                    <!-- Full Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-text-dark">Full Name</label>
                        <input
                            id="name"
                            v-model="profileForm.name"
                            type="text"
                            required
                            autocomplete="name"
                            class="mt-1 w-full rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-text-dark placeholder-text-muted-dark focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        />
                        <p v-if="profileForm.errors.name" class="mt-1 text-sm text-primary">
                            {{ profileForm.errors.name }}
                        </p>
                    </div>

                    <!-- Email (read-only) -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-text-dark">Email</label>
                        <input
                            id="email"
                            :value="user.email"
                            type="email"
                            readonly
                            class="mt-1 w-full rounded-lg border border-surface-darker bg-surface-darker/70 px-3 py-2 text-text-muted-dark cursor-not-allowed"
                        />
                        <p class="mt-1 text-xs text-text-muted-dark">Email cannot be changed here.</p>
                    </div>

                    <!-- Biography -->
                    <div>
                        <label for="biography" class="block text-sm font-medium text-text-dark">Biography</label>
                        <textarea
                            id="biography"
                            v-model="profileForm.biography"
                            rows="4"
                            class="mt-1 w-full rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-text-dark placeholder-text-muted-dark focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            placeholder="A short bio about yourself..."
                        />
                        <p v-if="profileForm.errors.biography" class="mt-1 text-sm text-primary">
                            {{ profileForm.errors.biography }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            type="submit"
                            :disabled="profileForm.processing"
                            class="rounded-lg border border-border bg-primary px-4 py-2 text-sm font-medium text-text-primary shadow-cartoon transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ profileForm.processing ? 'Saving...' : 'Save profile' }}
                        </button>
                        <p v-if="profileForm.recentlySuccessful" class="text-sm text-success">
                            Profile updated.
                        </p>
                    </div>
                </form>

                <!-- Preferences -->
                <div class="border-t border-surface-darker pt-6">
                    <h2 class="mb-4 text-lg font-medium text-text-dark">Preferences</h2>
                    <form @submit.prevent="submitPreferences" class="space-y-4 max-w-xl">
                        <div>
                            <label for="theme_preference" class="block text-sm font-medium text-text-dark">Theme</label>
                            <select
                                id="theme_preference"
                                v-model="preferencesForm.theme_preference"
                                class="mt-1 w-full rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-text-dark focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            >
                                <option value="system">System preference</option>
                                <option value="dark">Dark</option>
                                <option value="light">Light</option>
                            </select>
                            <p v-if="preferencesForm.errors.theme_preference" class="mt-1 text-sm text-primary">
                                {{ preferencesForm.errors.theme_preference }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <input
                                    id="is_profile_public"
                                    v-model="preferencesForm.is_profile_public"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-surface-darker bg-surface-darker text-primary focus:ring-primary"
                                    :title="publicProfileTooltip"
                                />
                                <label for="is_profile_public" class="text-sm font-medium text-text-dark cursor-pointer">
                                    Public profile
                                </label>
                            </div>
                            <p class="text-xs text-text-muted-dark" :title="publicProfileTooltip">
                                {{ publicProfileTooltip }}
                            </p>
                            <p v-if="preferencesForm.errors.is_profile_public" class="text-sm text-primary">
                                {{ preferencesForm.errors.is_profile_public }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <button
                                type="submit"
                                :disabled="preferencesForm.processing"
                                class="rounded-lg border border-border bg-primary px-4 py-2 text-sm font-medium text-text-primary shadow-cartoon transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ preferencesForm.processing ? 'Saving...' : 'Save preferences' }}
                            </button>
                            <p v-if="preferencesForm.recentlySuccessful" class="text-sm text-success">
                                Preferences updated.
                            </p>
                        </div>
                    </form>
                </div>
            </div>

            <!-- BoardGameGeek tab -->
            <div
                v-show="activeTab === 'boardgamegeek'"
                role="tabpanel"
                aria-labelledby="tab-boardgamegeek"
                class="space-y-6"
            >
                <!-- Flash messages -->
                <div v-if="flash?.success" class="rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
                    {{ flash.success }}
                </div>
                <div v-if="flash?.error" class="rounded-lg border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-primary">
                    {{ flash.error }}
                </div>

                <!-- Connected callout -->
                <div
                    v-if="user.board_game_geek_username"
                    class="rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success"
                >
                    You are connected to BoardGameGeek as <strong>{{ user.board_game_geek_username }}</strong>.
                </div>

                <!-- Sync information box -->
                <div class="rounded-lg border border-surface-darker bg-surface-darker/50 px-4 py-3 text-sm text-text-muted-dark">
                    <p class="font-medium text-text-dark">Sync Information</p>
                    <p class="mt-1">
                        Your BoardGameGeek data is synced automatically. boardgameplays.com maintains its own database
                        and doesn't depend on BoardGameGeek, but we aim to have you choose to sync the information.
                    </p>
                </div>

                <form @submit.prevent="submitBggSettings" class="space-y-6 max-w-xl">
                    <!-- BoardGameGeek username -->
                    <div>
                        <label for="board_game_geek_username" class="block text-sm font-medium text-text-dark">
                            BoardGameGeek username
                        </label>
                        <input
                            id="board_game_geek_username"
                            v-model="bggForm.board_game_geek_username"
                            type="text"
                            autocomplete="username"
                            class="mt-1 w-full rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-text-dark placeholder-text-muted-dark focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            placeholder="Your BGG username"
                        />
                        <p v-if="bggForm.errors.board_game_geek_username" class="mt-1 text-sm text-primary">
                            {{ bggForm.errors.board_game_geek_username }}
                        </p>
                    </div>

                    <!-- Options when username is set -->
                    <template v-if="user.board_game_geek_username">
                        <!-- Sync plays to BGG toggle -->
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <input
                                    id="sync_plays_to_board_game_geek"
                                    v-model="bggForm.sync_plays_to_board_game_geek"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-surface-darker bg-surface-darker text-primary focus:ring-primary"
                                />
                                <label for="sync_plays_to_board_game_geek" class="text-sm font-medium text-text-dark cursor-pointer">
                                    Sync my plays from this site to BoardGameGeek
                                </label>
                            </div>
                            <p class="text-xs text-text-muted-dark">
                                When enabled, plays you log here can be submitted to your BoardGameGeek account.
                            </p>
                        </div>

                        <!-- How to log plays (only when sync to BGG is on) -->
                        <div v-if="bggForm.sync_plays_to_board_game_geek" class="space-y-3 rounded-lg border border-surface-darker p-4">
                            <p class="text-sm font-medium text-text-dark">How to log plays on BoardGameGeek</p>
                            <div class="flex flex-col gap-2">
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input
                                        v-model="bggForm.use_generic_user_for_bgg_plays"
                                        type="radio"
                                        :value="true"
                                        class="h-4 w-4 border-surface-darker text-primary focus:ring-primary"
                                    />
                                    <span class="text-sm text-text-dark">Use a generic account to log plays</span>
                                </label>
                                <p class="ml-6 text-xs text-text-muted-dark">
                                    Plays will be logged under a site-managed BoardGameGeek account. You do not need to enter your BGG password.
                                </p>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input
                                        v-model="bggForm.use_generic_user_for_bgg_plays"
                                        type="radio"
                                        :value="false"
                                        class="h-4 w-4 border-surface-darker text-primary focus:ring-primary"
                                    />
                                    <span class="text-sm text-text-dark">Log plays with my BoardGameGeek account</span>
                                </label>
                                <div v-if="!bggForm.use_generic_user_for_bgg_plays" class="ml-6 space-y-1">
                                    <label for="board_game_geek_password" class="block text-xs text-text-dark">
                                        Your BoardGameGeek password (stored securely, never shown)
                                    </label>
                                    <input
                                        id="board_game_geek_password"
                                        v-model="bggForm.board_game_geek_password"
                                        type="password"
                                        autocomplete="current-password"
                                        class="w-full max-w-sm rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-text-dark placeholder-text-muted-dark focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                        placeholder="Enter your BGG password"
                                    />
                                    <p v-if="bggForm.errors.board_game_geek_password" class="text-sm text-primary">
                                        {{ bggForm.errors.board_game_geek_password }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <button
                                type="submit"
                                :disabled="bggForm.processing"
                                class="rounded-lg border border-border bg-primary px-4 py-2 text-sm font-medium text-text-primary shadow-cartoon transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ bggForm.processing ? 'Saving...' : 'Save BoardGameGeek settings' }}
                            </button>
                            <p v-if="bggForm.recentlySuccessful" class="text-sm text-success">
                                Settings saved.
                            </p>
                        </div>
                    </template>
                    <template v-else>
                        <p class="text-sm text-text-muted-dark">
                            Enter your BoardGameGeek username above and save to connect your account. Your collection and plays will then sync automatically.
                        </p>
                        <button
                            type="submit"
                            :disabled="bggForm.processing"
                            class="rounded-lg border border-border bg-primary px-4 py-2 text-sm font-medium text-text-primary shadow-cartoon transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ bggForm.processing ? 'Saving...' : 'Save username' }}
                        </button>
                    </template>
                </form>

                <!-- Sync status log (when username set) -->
                <div v-if="user.board_game_geek_username" class="border-t border-surface-darker pt-6">
                    <h2 class="mb-4 text-lg font-medium text-text-dark">Sync status</h2>
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="inline font-medium text-text-muted-dark">Collection: </dt>
                            <dd class="inline text-text-dark">
                                <template v-if="boardGameGeek?.last_collection_sync">
                                    {{ formatSyncTime(boardGameGeek.last_collection_sync.synced_at) }}
                                    ({{ boardGameGeek.last_collection_sync.status }})
                                    <span v-if="boardGameGeek.last_collection_sync.error_message" class="text-primary">
                                        — {{ boardGameGeek.last_collection_sync.error_message }}
                                    </span>
                                </template>
                                <span v-else class="text-text-muted-dark">Not synced yet</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="inline font-medium text-text-muted-dark">Plays: </dt>
                            <dd class="inline text-text-dark">
                                <template v-if="boardGameGeek?.last_plays_sync">
                                    {{ formatSyncTime(boardGameGeek.last_plays_sync.synced_at) }}
                                    ({{ boardGameGeek.last_plays_sync.status }})
                                    <span v-if="boardGameGeek.last_plays_sync.error_message" class="text-primary">
                                        — {{ boardGameGeek.last_plays_sync.error_message }}
                                    </span>
                                </template>
                                <span v-else class="text-text-muted-dark">Not synced yet</span>
                            </dd>
                        </div>
                        <div v-if="boardGameGeek?.bgg_manual_sync_requested_at && manualSyncRecentlyRequested" class="mt-2 text-text-muted-dark">
                            Manual sync requested at {{ formatSyncTime(boardGameGeek.bgg_manual_sync_requested_at) }}. Sync may be in queue or running.
                        </div>
                    </dl>

                    <!-- Manual sync button -->
                    <div class="mt-4">
                        <button
                            type="button"
                            :disabled="!boardGameGeek?.manual_sync_allowed || manualSyncForm.processing"
                            :title="!boardGameGeek?.manual_sync_allowed ? 'You can trigger a manual sync once every 24 hours.' : ''"
                            class="rounded-lg border border-border bg-primary px-4 py-2 text-sm font-medium text-text-primary shadow-cartoon transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
                            @click="triggerManualSync"
                        >
                            {{ manualSyncForm.processing ? 'Requesting...' : 'Sync now' }}
                        </button>
                        <p v-if="!boardGameGeek?.manual_sync_allowed" class="mt-1 text-xs text-text-muted-dark">
                            You can request a manual sync once every 24 hours.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Notifications tab (placeholder) -->
            <div
                v-show="activeTab === 'notifications'"
                role="tabpanel"
                aria-labelledby="tab-notifications"
                class="py-4"
            >
                <p class="text-text-muted-dark">Notification preferences will be defined here.</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const THEME_SYSTEM = 'system';
const THEME_DARK = 'dark';
const THEME_LIGHT = 'light';

/** Minutes after manual sync request to show "in queue or running" message */
const MANUAL_SYNC_RECENT_MINUTES = 10;

const props = defineProps({
    activeTab: { type: String, default: 'profile' },
    flash: {
        type: Object,
        default: () => ({}),
    },
    user: {
        type: Object,
        required: true,
        default: () => ({
            id: null,
            name: '',
            email: '',
            profile_picture_url: null,
            biography: null,
            theme_preference: THEME_SYSTEM,
            is_profile_public: false,
            board_game_geek_username: null,
            sync_plays_to_board_game_geek: false,
            use_generic_user_for_bgg_plays: true,
        }),
    },
    boardGameGeek: {
        type: Object,
        default: () => ({}),
    },
});

const activeTab = ref(props.activeTab);
const profilePictureInputRef = ref(null);
const profilePicturePreview = ref(null);

watch(() => props.activeTab, (val) => {
    if (val) activeTab.value = val;
});

const profileForm = useForm({
    name: props.user.name,
    biography: props.user.biography ?? '',
    profile_picture: null,
});

const preferencesForm = useForm({
    theme_preference: props.user.theme_preference ?? THEME_SYSTEM,
    is_profile_public: Boolean(props.user.is_profile_public),
});

const bggForm = useForm({
    board_game_geek_username: props.user.board_game_geek_username ?? '',
    sync_plays_to_board_game_geek: Boolean(props.user.sync_plays_to_board_game_geek),
    use_generic_user_for_bgg_plays: props.user.use_generic_user_for_bgg_plays !== false,
    board_game_geek_password: '',
});

const manualSyncForm = useForm({});

const publicProfileTooltip = 'When enabled, your profile and play statistics are visible to others.';

const manualSyncRecentlyRequested = computed(() => {
    const at = props.boardGameGeek?.bgg_manual_sync_requested_at;
    if (!at) return false;
    const requested = new Date(at);
    const now = new Date();
    return (now - requested) / (60 * 1000) <= MANUAL_SYNC_RECENT_MINUTES;
});

function formatSyncTime(isoString) {
    if (!isoString) return '';
    const d = new Date(isoString);
    return d.toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });
}

function onProfilePictureChange(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    profileForm.profile_picture = file;
    const reader = new FileReader();
    reader.onload = (e) => {
        profilePicturePreview.value = e.target?.result ?? null;
    };
    reader.readAsDataURL(file);
}

function submitProfile() {
    profileForm.post(route('settings.profile.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            profilePicturePreview.value = null;
            profileForm.profile_picture = null;
            if (profilePictureInputRef.value) {
                profilePictureInputRef.value.value = '';
            }
        },
    });
}

function submitPreferences() {
    preferencesForm.put(route('settings.preferences.update'), {
        preserveScroll: true,
    });
}

function submitBggSettings() {
    bggForm.put(route('settings.boardgamegeek.update'), {
        preserveScroll: true,
        onSuccess: () => {
            bggForm.board_game_geek_password = '';
        },
    });
}

function triggerManualSync() {
    manualSyncForm.post(route('settings.boardgamegeek.sync'), {
        preserveScroll: true,
    });
}
</script>
