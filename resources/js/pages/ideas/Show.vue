<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { IconArrowLeft, IconPencil, IconTrash } from '@tabler/icons-vue';
import { ref } from 'vue';

import {
    destroy as destroyIdea,
    index as ideasIndex,
    update as updateIdea,
} from '@/actions/App/Http/Controllers/App/ContentIdeaController';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import ContentIdeaFormDialog from '@/components/ideas/ContentIdeaFormDialog.vue';
import ContentIdeaSource from '@/components/ideas/ContentIdeaSource.vue';
import ContentIdeaStatusPicker from '@/components/ideas/ContentIdeaStatusPicker.vue';
import { Button } from '@/components/ui/button';
import date from '@/date';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ContentIdeaSummary } from '@/types/content-idea';

interface ContentIdea extends ContentIdeaSummary {
    details: string | null;
    details_html: string;
}

const props = defineProps<{
    idea: ContentIdea;
}>();

const editDialogOpen = ref(false);

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const handleDelete = () => {
    deleteModal.value?.open({ url: destroyIdea.url(props.idea.id) });
};
</script>

<template>
    <Head :title="idea.title" />

    <AppLayout>
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 px-6 py-8">
            <Link
                :href="ideasIndex.url()"
                class="inline-flex items-center gap-1 text-sm font-semibold text-foreground/70 hover:text-foreground"
                data-testid="idea-back"
            >
                <IconArrowLeft class="size-4" />
                {{ $t('ideas.back') }}
            </Link>

            <header class="space-y-3">
                <h1
                    class="text-2xl leading-tight font-semibold break-words text-foreground sm:text-4xl"
                    style="font-family: var(--font-display)"
                    data-testid="idea-title"
                >
                    {{ idea.title }}
                </h1>

                <div
                    class="flex flex-wrap items-center gap-3 text-sm text-foreground/60"
                >
                    <ContentIdeaStatusPicker
                        :idea-id="idea.id"
                        :status="idea.status"
                    />
                    <span>{{ date.formatDateTime(idea.created_at) }}</span>
                    <ContentIdeaSource :created-via="idea.created_via" />
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        data-testid="idea-edit"
                        @click="editDialogOpen = true"
                    >
                        <IconPencil class="size-4" />
                        {{ $t('ideas.actions.edit') }}
                    </Button>
                    <Button
                        variant="outline"
                        data-testid="idea-delete"
                        @click="handleDelete"
                    >
                        <IconTrash class="size-4" />
                        {{ $t('ideas.actions.delete') }}
                    </Button>
                </div>
            </header>

            <article
                v-if="idea.details"
                class="prose max-w-none rounded-xl border-2 border-foreground bg-card p-6 shadow-2xs prose-neutral dark:prose-invert"
                data-testid="idea-details"
                v-html="idea.details_html"
            />
            <p v-else class="text-sm text-foreground/60">
                {{ $t('ideas.no_details') }}
            </p>
        </div>
    </AppLayout>

    <ContentIdeaFormDialog
        v-model:open="editDialogOpen"
        :action="updateIdea.form(idea.id)"
        :title="$t('ideas.form.edit_title')"
        :description="$t('ideas.form.details_hint')"
        :initial-title="idea.title"
        :initial-details="idea.details"
    />

    <ConfirmDeleteModal
        ref="deleteModal"
        :title="$t('ideas.delete_modal.title')"
        :description="$t('ideas.delete_modal.description')"
        :action="$t('ideas.delete_modal.action')"
        :cancel="$t('ideas.delete_modal.cancel')"
    />
</template>
