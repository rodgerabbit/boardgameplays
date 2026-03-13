<template>
    <Head title="Create Group" />
    <div class="space-y-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-text-dark m-0">Create Group</h1>
            <Link
                :href="route('groups.index')"
                class="text-sm font-medium text-text-muted-dark hover:text-text-dark"
            >
                Cancel
            </Link>
        </div>

        <!-- Step indicator -->
        <div class="flex gap-2">
            <span
                v-for="s in 3"
                :key="s"
                :class="[
                    'h-2 flex-1 rounded-full',
                    s <= step ? 'bg-primary' : 'bg-surface-darker',
                ]"
            />
        </div>

        <!-- Step 1: Base info + photo + visibility -->
        <section v-if="step === 1" class="rounded-xl border border-surface-darker bg-surface-dark p-6">
            <h2 class="mb-4 text-lg font-medium text-text-dark">Group details</h2>
            <form @submit.prevent="submitStep1" class="space-y-6 max-w-xl">
                <div>
                    <label for="friendly_name" class="block text-sm font-medium text-text-dark">Group name *</label>
                    <input
                        id="friendly_name"
                        v-model="step1Form.friendly_name"
                        type="text"
                        required
                        maxlength="255"
                        class="mt-1 w-full rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-text-dark focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                    <p v-if="step1Form.errors.friendly_name" class="mt-1 text-sm text-primary">{{ step1Form.errors.friendly_name }}</p>
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-text-dark">Description</label>
                    <textarea
                        id="description"
                        v-model="step1Form.description"
                        rows="3"
                        class="mt-1 w-full rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-text-dark focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                    <p v-if="step1Form.errors.description" class="mt-1 text-sm text-primary">{{ step1Form.errors.description }}</p>
                </div>
                <div>
                    <label for="group_location" class="block text-sm font-medium text-text-dark">Location</label>
                    <input
                        id="group_location"
                        v-model="step1Form.group_location"
                        type="text"
                        maxlength="255"
                        class="mt-1 w-full rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-text-dark focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                </div>
                <div>
                    <label for="website_link" class="block text-sm font-medium text-text-dark">Website</label>
                    <input
                        id="website_link"
                        v-model="step1Form.website_link"
                        type="url"
                        maxlength="255"
                        class="mt-1 w-full rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-text-dark focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                    <p v-if="step1Form.errors.website_link" class="mt-1 text-sm text-primary">{{ step1Form.errors.website_link }}</p>
                </div>
                <div>
                    <label for="discord_link" class="block text-sm font-medium text-text-dark">Discord</label>
                    <input
                        id="discord_link"
                        v-model="step1Form.discord_link"
                        type="url"
                        maxlength="255"
                        class="mt-1 w-full rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-text-dark focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                    <p v-if="step1Form.errors.discord_link" class="mt-1 text-sm text-primary">{{ step1Form.errors.discord_link }}</p>
                </div>
                <div>
                    <label for="slack_link" class="block text-sm font-medium text-text-dark">Slack</label>
                    <input
                        id="slack_link"
                        v-model="step1Form.slack_link"
                        type="url"
                        maxlength="255"
                        class="mt-1 w-full rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-text-dark focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                    <p v-if="step1Form.errors.slack_link" class="mt-1 text-sm text-primary">{{ step1Form.errors.slack_link }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-dark">Group photo</label>
                    <div class="mt-1 flex items-center gap-4">
                        <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-lg border border-surface-darker bg-surface-darker">
                            <img
                                v-if="step1PhotoPreview || step1Form.photo"
                                :src="step1PhotoPreview"
                                alt="Preview"
                                class="h-full w-full object-cover"
                            />
                            <span v-else class="flex h-full w-full items-center justify-center text-text-muted-dark text-sm">No photo</span>
                        </div>
                        <div>
                            <input
                                ref="step1PhotoInputRef"
                                type="file"
                                accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                class="hidden"
                                @change="onStep1PhotoChange"
                            />
                            <button
                                type="button"
                                class="rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-sm font-medium text-text-dark hover:bg-surface-hover"
                                @click="step1PhotoInputRef?.click()"
                            >
                                Choose photo
                            </button>
                        </div>
                    </div>
                    <p v-if="step1Form.errors.photo" class="mt-1 text-sm text-primary">{{ step1Form.errors.photo }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-dark mb-2">Visibility</label>
                    <div class="space-y-2">
                        <label
                            v-for="opt in visibilityOptions"
                            :key="opt.value"
                            class="flex cursor-pointer gap-3 rounded-lg border border-surface-darker p-3 hover:bg-surface-darker/50"
                        >
                            <input
                                v-model="step1Form.visibility"
                                type="radio"
                                :value="opt.value"
                                class="mt-1 h-4 w-4 text-primary focus:ring-primary"
                            />
                            <div>
                                <span class="font-medium text-text-dark">{{ opt.label }}</span>
                                <p class="text-xs text-text-muted-dark">{{ opt.description }}</p>
                            </div>
                        </label>
                    </div>
                </div>
                <button
                    type="submit"
                    :disabled="step1Form.processing"
                    class="rounded-lg border border-border bg-primary px-4 py-2 text-sm font-medium text-text-primary hover:bg-primary-hover disabled:opacity-50"
                >
                    {{ step1Form.processing ? 'Creating...' : 'Continue' }}
                </button>
            </form>
        </section>

        <!-- Step 2: Add members -->
        <section v-if="step === 2 && group" class="rounded-xl border border-surface-darker bg-surface-dark p-6">
            <h2 class="mb-4 text-lg font-medium text-text-dark">Add members (optional)</h2>
            <p class="mb-4 text-sm text-text-muted-dark">Add existing users by ID or BoardGameGeek username. You can skip this and share the invite link in the next step.</p>
            <form @submit.prevent="submitStep2" class="space-y-4 max-w-xl">
                <div
                    v-for="(member, index) in step2Members"
                    :key="index"
                    class="flex flex-wrap items-end gap-2 rounded-lg border border-surface-darker p-3"
                >
                    <div class="flex-1 min-w-[120px]">
                        <label class="block text-xs font-medium text-text-muted-dark">Type</label>
                        <select
                            v-model="member.type"
                            class="mt-1 w-full rounded border border-surface-darker bg-surface-darker px-2 py-1.5 text-sm text-text-dark"
                        >
                            <option value="user">User ID</option>
                            <option value="bgg">BGG username</option>
                        </select>
                    </div>
                    <div v-if="member.type === 'user'" class="flex-1 min-w-[120px]">
                        <label class="block text-xs font-medium text-text-muted-dark">User ID</label>
                        <input
                            v-model.number="member.user_id"
                            type="number"
                            min="1"
                            class="mt-1 w-full rounded border border-surface-darker bg-surface-darker px-2 py-1.5 text-sm text-text-dark"
                            placeholder="User ID"
                        />
                    </div>
                    <div v-else class="flex-1 min-w-[120px]">
                        <label class="block text-xs font-medium text-text-muted-dark">BGG username</label>
                        <input
                            v-model="member.bgg_username"
                            type="text"
                            class="mt-1 w-full rounded border border-surface-darker bg-surface-darker px-2 py-1.5 text-sm text-text-dark"
                            placeholder="BGG username"
                        />
                    </div>
                    <div class="flex-1 min-w-[100px]">
                        <label class="block text-xs font-medium text-text-muted-dark">Display name</label>
                        <input
                            v-model="member.display_name"
                            type="text"
                            class="mt-1 w-full rounded border border-surface-darker bg-surface-darker px-2 py-1.5 text-sm text-text-dark"
                            placeholder="Optional"
                        />
                    </div>
                    <button
                        type="button"
                        class="rounded p-2 text-text-muted-dark hover:bg-surface-darker hover:text-text-dark"
                        aria-label="Remove"
                        @click="step2Members.splice(index, 1)"
                    >
                        ×
                    </button>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-dashed border-surface-darker px-4 py-2 text-sm text-text-muted-dark hover:border-primary hover:text-text-dark"
                    @click="step2Members.push({ type: 'bgg', user_id: null, bgg_username: '', display_name: '' })"
                >
                    + Add member
                </button>
                <div class="flex gap-2 pt-4">
                    <button
                        type="submit"
                        :disabled="step2Form.processing"
                        class="rounded-lg border border-border bg-primary px-4 py-2 text-sm font-medium text-text-primary hover:bg-primary-hover disabled:opacity-50"
                    >
                        {{ step2Form.processing ? 'Saving...' : 'Continue' }}
                    </button>
                    <Link
                        :href="route('groups.create', { step: 3, group_id: group.id })"
                        class="rounded-lg border border-surface-darker bg-surface-darker px-4 py-2 text-sm font-medium text-text-dark hover:bg-surface-hover"
                    >
                        Skip to invite link
                    </Link>
                </div>
            </form>
        </section>

        <!-- Step 3: Invite link -->
        <section v-if="step === 3" class="rounded-xl border border-surface-darker bg-surface-dark p-6">
            <h2 class="mb-4 text-lg font-medium text-text-dark">Invitation link</h2>
            <p class="mb-4 text-sm text-text-muted-dark">Share this link so others can join your group. They must be logged in to join.</p>
            <div class="flex flex-wrap items-center gap-2 max-w-2xl">
                <input
                    :value="inviteUrl"
                    type="text"
                    readonly
                    class="flex-1 min-w-0 rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-sm text-text-dark"
                />
                <button
                    type="button"
                    class="rounded-lg border border-border bg-primary px-4 py-2 text-sm font-medium text-text-primary hover:bg-primary-hover"
                    @click="copyInviteUrl"
                >
                    {{ copyDone ? 'Copied!' : 'Copy link' }}
                </button>
            </div>
            <div class="mt-6">
                <Link
                    :href="route('groups.index')"
                    class="inline-flex rounded-lg border border-border bg-primary px-4 py-2 text-sm font-medium text-text-primary hover:bg-primary-hover"
                >
                    Finish
                </Link>
            </div>
        </section>
    </div>
</template>

<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    step: { type: Number, default: 1 },
    group: { type: Object, default: null },
    inviteUrl: { type: String, default: '' },
    visibilityOptions: { type: Array, default: () => [] },
});

const step1PhotoInputRef = ref(null);
const step1PhotoPreview = ref(null);
const copyDone = ref(false);

const step1Form = useForm({
    friendly_name: '',
    description: '',
    group_location: '',
    website_link: '',
    discord_link: '',
    slack_link: '',
    photo: null,
    visibility: 'private',
});

const step2Members = ref([{ type: 'bgg', user_id: null, bgg_username: '', display_name: '' }]);
const step2Form = useForm({
    group_id: props.group?.id ?? null,
    members: [],
});

watch(() => props.group, (g) => {
    if (g?.id) step2Form.group_id = g.id;
}, { immediate: true });

function onStep1PhotoChange(e) {
    const file = e.target.files?.[0];
    if (file) {
        step1Form.photo = file;
        const reader = new FileReader();
        reader.onload = (ev) => { step1PhotoPreview.value = ev.target?.result; };
        reader.readAsDataURL(file);
    }
}

function submitStep1() {
    step1Form.post(route('groups.store'), {
        forceFormData: true,
        preserveScroll: true,
    });
}

function submitStep2() {
    const members = step2Members.value
        .filter((m) => (m.type === 'user' && m.user_id) || (m.type === 'bgg' && (m.bgg_username || '').trim()))
        .map((m) => ({
            type: m.type,
            user_id: m.type === 'user' ? m.user_id : undefined,
            bgg_username: m.type === 'bgg' ? (m.bgg_username || '').trim() : undefined,
            display_name: (m.display_name || '').trim() || undefined,
        }));
    step2Form.members = members;
    step2Form.group_id = props.group?.id;
    step2Form.post(route('groups.members.store'), {
        preserveScroll: true,
        onSuccess: () => {
            router.visit(route('groups.create', { step: 3, group_id: props.group?.id }));
        },
    });
}

function copyInviteUrl() {
    if (!props.inviteUrl) return;
    navigator.clipboard.writeText(props.inviteUrl).then(() => {
        copyDone.value = true;
        setTimeout(() => { copyDone.value = false; }, 2000);
    });
}
</script>
