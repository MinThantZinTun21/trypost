<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { IconBulb, IconRobot } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import {
    index as ideasIndex,
    show as showIdea,
    store as storeIdea,
} from '@/actions/App/Http/Controllers/App/ContentIdeaController';
import EmptyState from '@/components/EmptyState.vue';
import ContentIdeaFormDialog from '@/components/ideas/ContentIdeaFormDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableLoadMore,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    CONTENT_IDEA_STATUSES,
    type ContentIdeaStatus,
    getContentIdeaStatusConfig,
} from '@/composables/useContentIdeaStatus';
import date from '@/date';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ContentIdeaSummary } from '@/types/content-idea';

interface ScrollIdeas {
    data: ContentIdeaSummary[];
    meta: {
        hasNextPage: boolean;
    };
}

const props = defineProps<{
    ideas: ScrollIdeas;
    currentStatus: ContentIdeaStatus | null;
}>();

const filters = computed(() => [
    { name: 'all', label: trans('ideas.filters.all'), href: ideasIndex.url() },
    ...CONTENT_IDEA_STATUSES.map((status) => ({
        name: status,
        label: trans(`ideas.status.${status}`),
        href: ideasIndex.url(status),
    })),
]);

const createDialogOpen = ref(false);
</script>

<template>
    <Head :title="$t('ideas.title')" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 px-6 py-8">
            <PageHeader
                :title="$t('ideas.title')"
                :description="$t('ideas.description')"
            />

            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <Tabs :model-value="props.currentStatus ?? 'all'">
                    <TabsList>
                        <TabsTrigger
                            v-for="filter in filters"
                            :key="filter.name"
                            :value="filter.name"
                            as-child
                        >
                            <Link
                                :href="filter.href"
                                :data-testid="`ideas-filter-${filter.name}`"
                            >
                                {{ filter.label }}
                            </Link>
                        </TabsTrigger>
                    </TabsList>
                </Tabs>

                <Button
                    class="w-full sm:w-auto"
                    data-testid="ideas-new"
                    @click="createDialogOpen = true"
                >
                    {{ $t('ideas.new_idea') }}
                </Button>
            </div>

            <EmptyState
                v-if="ideas.data.length === 0"
                :icon="IconBulb"
                :title="
                    currentStatus
                        ? $t('ideas.empty.filtered_title')
                        : $t('ideas.empty.title')
                "
                :description="
                    currentStatus
                        ? $t('ideas.empty.filtered_description')
                        : $t('ideas.empty.description')
                "
                data-testid="ideas-empty"
            />

            <div v-else data-testid="ideas-list">
                <InfiniteScroll
                    data="ideas"
                    items-element="#ideas-body"
                    preserve-url
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{{
                                    $t('ideas.table.idea')
                                }}</TableHead>
                                <TableHead>{{
                                    $t('ideas.table.status')
                                }}</TableHead>
                                <TableHead>{{
                                    $t('ideas.table.added')
                                }}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody id="ideas-body">
                            <TableRow
                                v-for="idea in ideas.data"
                                :key="idea.id"
                                :data-testid="`idea-row-${idea.id}`"
                                class="cursor-pointer"
                                @click="router.visit(showIdea.url(idea.id))"
                            >
                                <TableCell class="max-w-md py-3">
                                    <div class="space-y-1">
                                        <p
                                            class="truncate font-semibold text-foreground"
                                        >
                                            {{ idea.title }}
                                        </p>
                                        <p
                                            v-if="idea.excerpt"
                                            class="truncate text-sm text-foreground/70"
                                        >
                                            {{ idea.excerpt }}
                                        </p>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            getContentIdeaStatusConfig(
                                                idea.status,
                                            ).variant
                                        "
                                    >
                                        <component
                                            :is="
                                                getContentIdeaStatusConfig(
                                                    idea.status,
                                                ).icon
                                            "
                                            class="size-3"
                                        />
                                        {{
                                            getContentIdeaStatusConfig(
                                                idea.status,
                                            ).label
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <div class="space-y-0.5 text-sm">
                                        <p>
                                            {{
                                                date.formatDate(idea.created_at)
                                            }}
                                        </p>
                                        <p
                                            class="flex items-center gap-1 text-xs text-foreground/60"
                                        >
                                            <IconRobot
                                                v-if="
                                                    idea.created_via === 'mcp'
                                                "
                                                class="size-3"
                                            />
                                            {{
                                                $t(
                                                    `ideas.created_via.${idea.created_via}`,
                                                )
                                            }}
                                        </p>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <template #next="{ loading }">
                        <TableLoadMore v-if="loading" />
                    </template>
                </InfiniteScroll>
            </div>
        </div>
    </AppLayout>

    <ContentIdeaFormDialog
        v-model:open="createDialogOpen"
        :action="storeIdea.form()"
        :title="$t('ideas.form.create_title')"
        :description="$t('ideas.form.create_description')"
        :reset="['ideas']"
    />
</template>
