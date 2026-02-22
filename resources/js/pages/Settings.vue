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

            <!-- BoardGameGeek tab (placeholder) -->
            <div
                v-show="activeTab === 'boardgamegeek'"
                role="tabpanel"
                aria-labelledby="tab-boardgamegeek"
                class="py-4"
            >
                <p class="text-text-muted-dark">BoardGameGeek settings will be defined here.</p>
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
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const THEME_SYSTEM = 'system';
const THEME_DARK = 'dark';
const THEME_LIGHT = 'light';

const props = defineProps({
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
        }),
    },
});

const activeTab = ref('profile');
const profilePictureInputRef = ref(null);
const profilePicturePreview = ref(null);

const profileForm = useForm({
    name: props.user.name,
    biography: props.user.biography ?? '',
    profile_picture: null,
});

const preferencesForm = useForm({
    theme_preference: props.user.theme_preference ?? THEME_SYSTEM,
});

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
</script>
