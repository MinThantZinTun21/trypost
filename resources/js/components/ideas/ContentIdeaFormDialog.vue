<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { RouteFormDefinition } from '@/wayfinder';

defineProps<{
    action: RouteFormDefinition<'post'>;
    title: string;
    description: string;
    initialTitle?: string;
    initialDetails?: string | null;
    reset?: string[];
}>();

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-2xl">
            <Form
                v-bind="action"
                :options="{ preserveScroll: true, reset: reset ?? [] }"
                reset-on-success
                class="space-y-6"
                data-testid="idea-form"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription>{{ description }}</DialogDescription>
                </DialogHeader>

                <Field class="gap-2">
                    <FieldLabel for="idea_title">{{
                        $t('ideas.form.title')
                    }}</FieldLabel>
                    <Input
                        id="idea_title"
                        name="title"
                        :default-value="initialTitle ?? ''"
                        :placeholder="trans('ideas.form.title_placeholder')"
                        data-testid="idea-title-input"
                    />
                    <FieldError :errors="[errors.title]" />
                </Field>

                <Field class="gap-2">
                    <FieldLabel for="idea_details">{{
                        $t('ideas.form.details')
                    }}</FieldLabel>
                    <Textarea
                        id="idea_details"
                        name="details"
                        class="max-h-[50vh] min-h-48 font-mono text-sm"
                        :default-value="initialDetails ?? ''"
                        :placeholder="trans('ideas.form.details_placeholder')"
                        data-testid="idea-details-input"
                    />
                    <FieldError :errors="[errors.details]" />
                </Field>

                <DialogFooter class="gap-2">
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-testid="idea-save"
                    >
                        {{ $t('ideas.form.save') }}
                    </Button>
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            {{ $t('ideas.form.cancel') }}
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
