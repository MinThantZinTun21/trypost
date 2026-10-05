<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { useTemplateRef } from 'vue';

import ProfileController from '@/actions/App/Http/Controllers/App/Settings/ProfileController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const passwordInput = useTemplateRef('passwordInput');

const focusFirstInput = () => {
    passwordInput.value?.$el?.focus();
};
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall
            :title="$t('settings.delete_account.heading')"
            :description="$t('settings.delete_account.description')"
        />
        <div
            class="space-y-4 rounded-xl border-2 border-foreground bg-rose-50 p-4 shadow-2xs"
        >
            <div class="relative space-y-0.5 text-rose-700">
                <p class="font-bold">
                    {{ $t('settings.delete_account.warning') }}
                </p>
                <p class="text-sm font-medium">
                    {{ $t('settings.delete_account.warning_message') }}
                </p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button
                        variant="destructive"
                        data-test="delete-user-button"
                    >
                        {{ $t('settings.delete_account.button') }}
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        @error="focusFirstInput"
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        v-slot="{ errors, processing, reset, clearErrors }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle>{{
                                $t('settings.delete_account.modal_title')
                            }}</DialogTitle>
                            <DialogDescription>
                                {{
                                    $t(
                                        'settings.delete_account.modal_description_password',
                                    )
                                }}
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only">
                                {{ $t('settings.delete_account.password') }}
                            </Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                ref="passwordInput"
                                :placeholder="
                                    trans(
                                        'settings.delete_account.password_placeholder',
                                    )
                                "
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                                data-test="confirm-delete-user-button"
                            >
                                {{ $t('settings.delete_account.confirm') }}
                            </Button>

                            <DialogClose as-child>
                                <Button
                                    variant="secondary"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    {{ $t('settings.delete_account.cancel') }}
                                </Button>
                            </DialogClose>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
