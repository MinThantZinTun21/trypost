<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

import { schedule as scheduleItem } from '@/actions/App/Http/Controllers/App/LibraryItemController';
import InputError from '@/components/InputError.vue';
import PlatformLogo from '@/components/PlatformLogo.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { errorForField } from '@/lib/formErrors';
import type { LibraryItem, LibrarySocialAccount } from '@/types/library';

const props = defineProps<{
    item: LibraryItem | null;
    socialAccounts: LibrarySocialAccount[];
}>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm<{ social_account_ids: string[] }>({
    social_account_ids: [],
});

watch(open, (isOpen) => {
    if (!isOpen) return;

    form.clearErrors();
    form.social_account_ids = props.socialAccounts.map((account) => account.id);
});

const toggle = (accountId: string, checked: boolean | 'indeterminate') => {
    form.social_account_ids =
        checked === true
            ? [...form.social_account_ids, accountId]
            : form.social_account_ids.filter((id) => id !== accountId);
};

const errorFor = (field: string): string | undefined =>
    errorForField(form.errors as Record<string, string>, field);

const submit = () => {
    if (!props.item) return;

    form.post(scheduleItem.url(props.item.id));
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <form
                class="space-y-6"
                data-testid="library-schedule-form"
                @submit.prevent="submit"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        $t('library.schedule.title')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{ $t('library.schedule.description') }}
                    </DialogDescription>
                </DialogHeader>

                <p
                    v-if="socialAccounts.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('library.schedule.no_accounts') }}
                </p>

                <div v-else class="grid gap-2">
                    <label
                        v-for="account in socialAccounts"
                        :key="account.id"
                        class="flex cursor-pointer items-center gap-3 rounded-xl border border-border p-3 transition-colors hover:bg-accent has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary/5"
                        :data-testid="`library-schedule-account-${account.id}`"
                    >
                        <Checkbox
                            :model-value="
                                form.social_account_ids.includes(account.id)
                            "
                            @update:model-value="
                                (checked) => toggle(account.id, checked)
                            "
                        />
                        <PlatformLogo
                            :platform="account.platform"
                            size="sm"
                            :tilt="false"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">
                                {{ account.display_name }}
                            </span>
                            <span
                                v-if="account.username"
                                class="block truncate text-xs text-muted-foreground"
                            >
                                @{{ account.username }}
                            </span>
                        </span>
                    </label>
                </div>

                <InputError :message="errorFor('social_account_ids')" />

                <DialogFooter class="gap-2">
                    <Button
                        type="submit"
                        :disabled="
                            form.processing ||
                            form.social_account_ids.length === 0
                        "
                        data-testid="library-schedule-submit"
                    >
                        {{ $t('library.schedule.submit') }}
                    </Button>
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            {{ $t('library.schedule.cancel') }}
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
