<template>
    <Head title="Browse Groups" />
    <div class="space-y-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-text-dark m-0">Browse Groups</h1>
                <p class="mt-1 text-sm text-text-muted-dark m-0">Find and join groups in your area</p>
            </div>
            <Link
                :href="route('groups.index')"
                class="text-sm font-medium text-text-muted-dark hover:text-text-dark"
            >
                Back to my groups
            </Link>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="group in groupsPaginator?.data || []"
                :key="group.id"
                class="flex flex-col rounded-xl border border-surface-darker bg-surface-dark overflow-hidden"
            >
                <div class="p-4">
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
                                {{ (group.description || '').slice(0, 80) }}{{ (group.description || '').length > 80 ? '…' : '' }}
                            </p>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-text-muted-dark">
                        {{ group.group_members_count ?? 0 }} members
                        <span v-if="group.visibility === 'publicly_joinable'" class="ml-2 rounded bg-surface-darker px-2 py-0.5 text-xs">Joinable</span>
                    </p>
                    <div class="mt-4">
                        <button
                            v-if="group.visibility === 'publicly_joinable'"
                            type="button"
                            :disabled="joiningId === group.id"
                            class="rounded-lg border border-border bg-primary px-3 py-1.5 text-sm font-medium text-text-primary hover:bg-primary-hover disabled:opacity-50"
                            @click="joinGroup(group.id)"
                        >
                            {{ joiningId === group.id ? 'Joining...' : 'Join' }}
                        </button>
                        <span v-else class="text-sm text-text-muted-dark">Join by invite only</span>
                    </div>
                </div>
            </div>
        </div>

        <p v-if="groupsPaginator?.data?.length === 0" class="py-8 text-center text-text-muted-dark">No groups to browse right now.</p>

        <div
            v-if="groupsPaginator?.data?.length && (groupsPaginator.prev_page_url || groupsPaginator.next_page_url)"
            class="flex flex-wrap items-center justify-between gap-2"
        >
            <Link
                v-if="groupsPaginator.prev_page_url"
                :href="groupsPaginator.prev_page_url"
                class="rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-sm font-medium text-text-dark hover:bg-surface-hover"
                preserve-scroll
            >
                Previous
            </Link>
            <span class="text-sm text-text-muted-dark">
                Page {{ groupsPaginator.current_page }} of {{ groupsPaginator.last_page }}
            </span>
            <Link
                v-if="groupsPaginator.next_page_url"
                :href="groupsPaginator.next_page_url"
                class="rounded-lg border border-surface-darker bg-surface-darker px-3 py-2 text-sm font-medium text-text-dark hover:bg-surface-hover"
                preserve-scroll
            >
                Next
            </Link>
        </div>
    </div>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const props = defineProps({
    groupsPaginator: {
        type: Object,
        default: () => ({ data: [], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null }),
    },
});

const joiningId = ref(null);

async function joinGroup(groupId) {
    joiningId.value = groupId;
    try {
        await axios.post(route('api.groups.join', { id: groupId }), {}, {
            headers: { Accept: 'application/json' },
        });
        router.visit(route('groups.index'), { preserveScroll: true });
    } catch (e) {
        joiningId.value = null;
    }
}
</script>
