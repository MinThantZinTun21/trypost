<script setup lang="ts">
import { Head, InfiniteScroll } from '@inertiajs/vue3';
import {
    IconBooks,
    IconBrandFacebook,
    IconBrandTiktok,
    IconBrandYoutube,
    IconCalendarPlus,
    IconDots,
    IconPencil,
    IconPhoto,
    IconPlus,
    IconTrash,
} from '@tabler/icons-vue';
import { transChoice } from 'laravel-vue-i18n';
import { ref } from 'vue';

import { destroy as destroyItem } from '@/actions/App/Http/Controllers/App/LibraryItemController';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import LibraryItemFormDialog from '@/components/library/LibraryItemFormDialog.vue';
import LibraryMediaThumbnail from '@/components/library/LibraryMediaThumbnail.vue';
import ScheduleLibraryItemDialog from '@/components/library/ScheduleLibraryItemDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { TableLoadMore } from '@/components/ui/table';
import date from '@/date';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LibraryItem, LibrarySocialAccount } from '@/types/library';

interface ScrollItems {
    data: LibraryItem[];
    meta: {
        hasNextPage: boolean;
    };
}

defineProps<{
    items: ScrollItems;
    socialAccounts: LibrarySocialAccount[];
}>();

const formOpen = ref(false);
const editing = ref<LibraryItem | null>(null);
const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const openCreate = () => {
    editing.value = null;
    formOpen.value = true;
};

const openEdit = (item: LibraryItem) => {
    editing.value = item;
    formOpen.value = true;
};

const scheduleOpen = ref(false);
const scheduling = ref<LibraryItem | null>(null);

const openSchedule = (item: LibraryItem) => {
    scheduling.value = item;
    scheduleOpen.value = true;
};

const handleDelete = (item: LibraryItem) => {
    deleteModal.value?.open({ url: destroyItem.url(item.id) });
};
</script>

<template>
    <Head :title="$t('library.title')" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 px-6 py-8">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <PageHeader
                    :title="$t('library.title')"
                    :description="$t('library.description')"
                />
                <Button
                    class="w-full sm:w-auto"
                    data-testid="library-new"
                    @click="openCreate"
                >
                    <IconPlus class="size-4" />
                    {{ $t('library.new_item') }}
                </Button>
            </div>

            <EmptyState
                v-if="items.data.length === 0"
                :icon="IconBooks"
                :title="$t('library.empty.title')"
                :description="$t('library.empty.description')"
                data-testid="library-empty"
            />

            <InfiniteScroll
                v-else
                data="items"
                items-element="#library-grid"
                preserve-url
                data-testid="library-list"
            >
                <div
                    id="library-grid"
                    class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
                >
                    <Card
                        v-for="item in items.data"
                        :key="item.id"
                        :data-testid="`library-item-${item.id}`"
                        class="group gap-0 overflow-hidden py-0 transition-all hover:shadow-sm"
                    >
                        <button
                            type="button"
                            class="relative aspect-video cursor-pointer overflow-hidden bg-muted"
                            :data-testid="`library-item-open-${item.id}`"
                            @click="openEdit(item)"
                        >
                            <LibraryMediaThumbnail
                                v-if="item.media.length > 0"
                                :media="item.media[0]"
                            />
                            <span
                                v-else
                                class="flex h-full w-full items-center justify-center text-muted-foreground"
                            >
                                <IconPhoto class="size-8" stroke-width="1.5" />
                            </span>
                            <span
                                v-if="item.media.length > 1"
                                class="absolute right-2 bottom-2 rounded-none bg-black/60 px-2 py-0.5 text-xs font-medium text-white backdrop-blur-sm"
                            >
                                {{
                                    transChoice(
                                        'library.media_count',
                                        item.media.length,
                                    )
                                }}
                            </span>
                        </button>

                        <CardContent class="flex flex-1 flex-col gap-3 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <h3
                                    class="line-clamp-2 font-semibold text-foreground"
                                >
                                    {{ item.name }}
                                </h3>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 shrink-0"
                                            :data-testid="`library-item-menu-${item.id}`"
                                        >
                                            <IconDots class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            :data-testid="`library-item-edit-${item.id}`"
                                            @click="openEdit(item)"
                                        >
                                            <IconPencil class="size-4" />
                                            {{ $t('library.actions.edit') }}
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            variant="destructive"
                                            :data-testid="`library-item-delete-${item.id}`"
                                            @click="handleDelete(item)"
                                        >
                                            <IconTrash class="size-4" />
                                            {{ $t('library.actions.delete') }}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>

                            <div class="flex flex-wrap gap-1.5">
                                <Badge
                                    v-if="item.facebook_text"
                                    variant="secondary"
                                    class="bg-[#1877f2]/10 px-2 font-medium text-[#1877f2] [&>svg]:size-3.5"
                                >
                                    <IconBrandFacebook />
                                    {{ $t('library.texts.facebook') }}
                                </Badge>
                                <Badge
                                    v-if="item.tiktok_caption"
                                    variant="secondary"
                                    class="bg-foreground/10 px-2 font-medium text-foreground [&>svg]:size-3.5"
                                >
                                    <IconBrandTiktok />
                                    {{ $t('library.texts.tiktok') }}
                                </Badge>
                                <Badge
                                    v-if="
                                        item.youtube_title ||
                                        item.youtube_description ||
                                        item.youtube_tags.length > 0
                                    "
                                    variant="secondary"
                                    class="bg-[#ff0000]/10 px-2 font-medium text-[#cc0000] [&>svg]:size-3.5"
                                >
                                    <IconBrandYoutube />
                                    {{ $t('library.texts.youtube') }}
                                </Badge>
                            </div>

                            <div
                                class="mt-auto flex items-end justify-between gap-2 pt-1"
                            >
                                <div
                                    class="space-y-0.5 text-xs text-muted-foreground"
                                >
                                    <p>
                                        {{ date.formatDate(item.created_at) }} ·
                                        {{
                                            $t(
                                                `library.created_via.${item.created_via}`,
                                            )
                                        }}
                                    </p>
                                    <p
                                        :data-testid="`library-item-used-${item.id}`"
                                    >
                                        {{
                                            transChoice(
                                                'library.used',
                                                item.posts_count ?? 0,
                                            )
                                        }}
                                    </p>
                                </div>
                                <Button
                                    size="sm"
                                    :data-testid="`library-item-schedule-${item.id}`"
                                    @click="openSchedule(item)"
                                >
                                    <IconCalendarPlus class="size-4" />
                                    {{ $t('library.schedule.action') }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <template #next="{ loading }">
                    <TableLoadMore v-if="loading" />
                </template>
            </InfiniteScroll>
        </div>
    </AppLayout>

    <LibraryItemFormDialog v-model:open="formOpen" :item="editing" />

    <ScheduleLibraryItemDialog
        v-model:open="scheduleOpen"
        :item="scheduling"
        :social-accounts="socialAccounts"
    />

    <ConfirmDeleteModal
        ref="deleteModal"
        :title="$t('library.delete_modal.title')"
        :description="$t('library.delete_modal.description')"
        :action="$t('library.delete_modal.action')"
        :cancel="$t('library.delete_modal.cancel')"
    />
</template>
