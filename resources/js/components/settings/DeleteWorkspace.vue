<script setup lang="ts">
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { destroy as destroyWorkspace } from '@/routes/app/workspaces';

const props = defineProps<{
    workspace: {
        id: string;
        name: string;
    };
    otherMemberCount: number;
}>();

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const membersWarning = computed(() => {
    if (props.otherMemberCount <= 0) {
        return null;
    }

    return transChoice(
        'settings.workspace.delete_members_warning',
        props.otherMemberCount,
        { count: String(props.otherMemberCount) },
    );
});

const warningMessage = computed(() => {
    const base = trans('settings.workspace.delete_description');

    return membersWarning.value ? `${base} ${membersWarning.value}` : base;
});

const confirmDescription = computed(() => {
    const base = trans('settings.workspace.delete_confirm_description');

    return membersWarning.value ? `${base} ${membersWarning.value}` : base;
});

const openDeleteModal = () => {
    deleteModal.value?.open({
        url: destroyWorkspace.url(props.workspace.id),
        confirmText: props.workspace.name,
    });
};
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall
            :title="$t('settings.workspace.delete_title')"
            :description="$t('settings.workspace.danger_description')"
        />

        <div
            class="space-y-4 rounded-xl border-2 border-foreground bg-rose-50 p-4 shadow-2xs"
        >
            <div class="relative space-y-0.5 text-rose-700">
                <p class="font-bold">
                    {{ $t('settings.workspace.delete_warning') }}
                </p>
                <p class="text-sm font-medium">
                    {{ warningMessage }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button variant="destructive" @click="openDeleteModal">
                    {{ $t('settings.workspace.delete_action') }}
                </Button>
            </div>
        </div>

        <ConfirmDeleteModal
            ref="deleteModal"
            :title="$t('settings.workspace.delete_confirm_title')"
            :description="confirmDescription"
            :action="$t('settings.workspace.delete_action')"
            :cancel="$t('settings.workspace.delete_cancel')"
        />
    </div>
</template>
