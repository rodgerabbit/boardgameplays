<template>
    <Head title="Groups" />
    <div class="space-y-8">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-text-dark m-0">Groups</h1>
                <p class="mt-1 text-sm text-text-muted-dark m-0">Manage your board game groups and communities</p>
            </div>
            <Link
                :href="route('groups.create')"
                class="inline-flex items-center gap-2 rounded-lg border border-border bg-primary px-4 py-2 text-sm font-medium text-text-primary shadow-cartoon transition hover:bg-primary-hover"
            >
                <span aria-hidden="true">+</span>
                Create Group
            </Link>
        </div>

        <!-- Empty state: no groups -->
        <section v-if="!groups || groups.length === 0" class="space-y-6">
            <div class="rounded-xl border border-surface-darker bg-surface-dark p-8 text-center">
                <p class="text-text-muted-dark mb-4">You are not a member of any groups yet.</p>
                <div class="flex flex-wrap items-center justify-center gap-4">
                    <Link
                        :href="route('groups.create')"
                        class="rounded-lg border border-border bg-primary px-4 py-2 text-sm font-medium text-text-primary hover:bg-primary-hover"
                    >
                        Create Group
                    </Link>
                    <Link
                        :href="route('groups.browse')"
                        class="rounded-lg border border-surface-darker bg-surface-darker px-4 py-2 text-sm font-medium text-text-dark hover:bg-surface-hover"
                    >
                        Browse Groups
                    </Link>
                </div>
            </div>
            <div class="rounded-xl border border-surface-darker bg-surface-dark p-8">
                <div class="flex flex-col items-center gap-3 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-surface-darker text-text-muted-dark" aria-hidden="true">
                        <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-medium text-text-dark">Join a Group</h2>
                    <p class="max-w-sm text-sm text-text-muted-dark">Connect with other board game enthusiasts in your area</p>
                    <Link
                        :href="route('groups.browse')"
                        class="rounded-lg border border-border bg-primary px-4 py-2 text-sm font-medium text-text-primary hover:bg-primary-hover"
                    >
                        Browse Groups
                    </Link>
                </div>
            </div>
        </section>

        <!-- Member state: group cards -->
        <section v-else>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="group in groups"
                    :key="group.id"
                    class="flex flex-col rounded-xl border border-surface-darker bg-surface-dark overflow-hidden"
                >
                    <div class="relative p-4">
                        <div class="absolute right-2 top-2">
                            <button
                                type="button"
                                class="rounded p-1 text-text-muted-dark hover:bg-surface-darker hover:text-text-dark"
                                aria-label="More options"
                            >
                                <span aria-hidden="true">⋮</span>
                            </button>
                        </div>
                        <div class="flex items-start gap-4">
                            <div class="h-14 w-14 flex-shrink-0 overflow-hidden rounded-lg bg-surface-darker">
                                <img
                                    v-if="group.photo_url"
                                    :src="group.photo_url"
                                    :alt="group.friendly_name + ' photo'"
                                    class="h-full w-full object-cover"
                                />
                                <span
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-xl font-semibold text-text-muted-dark"
                                >
                                    {{ (group.friendly_name || 'G').charAt(0).toUpperCase() }}
                                </span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-medium text-text-dark truncate">{{ group.friendly_name }}</h3>
                                <p class="mt-1 line-clamp-2 text-sm text-text-muted-dark">
                                    {{ shortDescription(group.description) }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <div class="flex -space-x-2">
                                <div
                                    v-for="member in (group.group_members || []).slice(0, 3)"
                                    :key="member.id"
                                    class="h-7 w-7 overflow-hidden rounded-full border-2 border-surface-dark bg-surface-darker"
                                >
                                    <img
                                        v-if="member.user?.profile_picture_url"
                                        :src="member.user.profile_picture_url"
                                        :alt="(member.user?.name || '') + ' avatar'"
                                        class="h-full w-full object-cover"
                                    />
                                    <span
                                        v-else
                                        class="flex h-full w-full items-center justify-center text-xs font-medium text-text-muted-dark"
                                    >
                                        {{ (member.user?.name || '?').charAt(0).toUpperCase() }}
                                    </span>
                                </div>
                            </div>
                            <span class="text-sm text-text-muted-dark">{{ group.group_members_count ?? group.group_members?.length ?? 0 }} members</span>
                        </div>
                        <p class="mt-2 text-xs text-text-muted-dark">
                            Last active {{ formatRelative(group.last_active_at || group.updated_at) }}
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link
                                v-if="group.current_user_is_admin"
                                :href="route('groups.show', { id: group.id })"
                                class="inline-flex items-center gap-1 rounded-lg border border-surface-darker bg-surface-darker px-3 py-1.5 text-sm font-medium text-text-dark hover:bg-surface-hover"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"> <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /> <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /> </svg>
                                Manage
                            </Link>
                            <Link
                                :href="route('groups.show', { id: group.id })"
                                class="inline-flex items-center rounded-lg border border-border bg-primary px-3 py-1.5 text-sm font-medium text-text-primary hover:bg-primary-hover"
                            >
                                View Details
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Group Activity (when user has groups) -->
        <section v-if="groups && groups.length > 0" class="space-y-4">
            <h2 class="text-lg font-medium text-text-dark">Group Activity</h2>
            <p class="text-sm text-text-muted-dark">Recent activity across all your groups</p>
            <div class="rounded-xl border border-surface-darker bg-surface-dark overflow-hidden">
                <ul class="divide-y divide-surface-darker">
                    <li
                        v-for="activity in activityPaginator?.data || []"
                        :key="activity.id"
                        class="flex items-start gap-4 px-4 py-3"
                    >
                        <div class="h-10 w-10 flex-shrink-0 overflow-hidden rounded-full bg-surface-darker">
                            <img
                                v-if="activity.user?.profile_picture_url"
                                :src="activity.user.profile_picture_url"
                                :alt="(activity.user?.name || '') + ' avatar'"
                                class="h-full w-full object-cover"
                            />
                            <span
                                v-else
                                class="flex h-full w-full items-center justify-center text-sm font-medium text-text-muted-dark"
                            >
                                {{ (activity.user?.name || '?').charAt(0).toUpperCase() }}
                            </span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-text-dark">{{ activityDescription(activity) }}</p>
                            <p class="mt-0.5 text-xs text-text-muted-dark">{{ formatRelative(activity.created_at) }}</p>
                        </div>
                    </li>
                </ul>
                <p v-if="!activityPaginator?.data?.length" class="px-4 py-6 text-center text-sm text-text-muted-dark">No recent activity.</p>
            </div>
            <div
                v-if="activityPaginator?.data?.length && (activityPaginator.prev_page_url || activityPaginator.next_page_url)"
                class="flex flex-wrap items-center justify-between gap-2"
            >
                <Link
                    v-if="activityPaginator.prev_page_url"
                    :href="activityPaginator.prev_page_url"
                    class="rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-sm font-medium text-text-dark hover:bg-surface-hover"
                    preserve-scroll
                >
                    Previous
                </Link>
                <span class="text-sm text-text-muted-dark">
                    Page {{ activityPaginator.current_page }} of {{ activityPaginator.last_page }}
                </span>
                <Link
                    v-if="activityPaginator.next_page_url"
                    :href="activityPaginator.next_page_url"
                    class="rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-sm font-medium text-text-dark hover:bg-surface-hover"
                    preserve-scroll
                >
                    Next
                </Link>
            </div>
        </section>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    groups: {
        type: Array,
        default: () => [],
    },
    activityPaginator: {
        type: Object,
        default: () => ({ data: [], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null }),
    },
});

function shortDescription(desc) {
    if (!desc) return 'No description';
    return desc.length > 80 ? desc.slice(0, 80) + '…' : desc;
}

function formatRelative(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    if (diffMins < 1) return 'just now';
    if (diffMins < 60) return `${diffMins} minute${diffMins !== 1 ? 's' : ''} ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
    if (diffDays < 7) return `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
    return date.toLocaleDateString();
}

function activityDescription(activity) {
    const userName = activity.user?.name ?? 'Someone';
    const groupName = activity.group?.friendly_name ?? 'a group';
    const action = activity.action;
    const map = {
        created: `${userName} created the group`,
        member_joined: `${userName} joined ${groupName}`,
        member_left: `${userName} left ${groupName}`,
        play_logged: `${userName} logged a play in ${groupName}`,
        event_created: `${userName} created an event in ${groupName}`,
        member_promoted: `${userName} was promoted to admin in ${groupName}`,
        member_demoted: `${userName} was demoted in ${groupName}`,
        updated: `${userName} updated the group`,
    };
    return map[action] || `${userName} performed an action in ${groupName}`;
}
</script>
