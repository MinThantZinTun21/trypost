<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { IconEye, IconEyeOff } from '@tabler/icons-vue';
import { ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePageErrors } from '@/composables/usePageErrors';
import AuthBase from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/login';

defineProps<{
    status?: string;
    email?: string | null;
    devLogin?: { email: string; password: string } | null;
}>();

const showPassword = ref(false);

const pageErrors = usePageErrors();
</script>

<template>
    <AuthBase
        :title="$t('auth.login.title')"
        :description="$t('auth.login.description')"
    >
        <Head :title="$t('auth.login.page_title')" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <div class="flex flex-col gap-6">
            <Form
                v-bind="store.form()"
                :reset-on-success="['password']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
            >
                <div class="grid gap-6">
                    <Field class="gap-2">
                        <FieldLabel for="email">{{
                            $t('auth.login.email')
                        }}</FieldLabel>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            placeholder="email@example.com"
                            :default-value="email ?? devLogin?.email ?? ''"
                        />
                        <FieldError
                            :errors="[errors.email || pageErrors.email]"
                        />
                    </Field>

                    <Field class="gap-2">
                        <FieldLabel for="password">{{
                            $t('auth.login.password')
                        }}</FieldLabel>
                        <InputGroup>
                            <InputGroupInput
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                name="password"
                                :tabindex="2"
                                autocomplete="current-password"
                                :placeholder="$t('auth.login.password')"
                                :default-value="devLogin?.password ?? ''"
                            />
                            <InputGroupAddon align="inline-end">
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <InputGroupButton
                                                type="button"
                                                size="icon-xs"
                                                :tabindex="-1"
                                                :aria-label="
                                                    showPassword
                                                        ? $t(
                                                              'auth.login.hide_password',
                                                          )
                                                        : $t(
                                                              'auth.login.show_password',
                                                          )
                                                "
                                                @click="
                                                    showPassword = !showPassword
                                                "
                                            >
                                                <IconEyeOff
                                                    v-if="showPassword"
                                                    class="size-4"
                                                />
                                                <IconEye
                                                    v-else
                                                    class="size-4"
                                                />
                                            </InputGroupButton>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>
                                                {{
                                                    showPassword
                                                        ? $t(
                                                              'auth.login.hide_password',
                                                          )
                                                        : $t(
                                                              'auth.login.show_password',
                                                          )
                                                }}
                                            </p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </InputGroupAddon>
                        </InputGroup>
                        <FieldError :errors="[errors.password]" />
                    </Field>

                    <div class="flex items-center justify-between">
                        <Label
                            for="remember"
                            class="flex items-center space-x-3"
                        >
                            <Checkbox
                                id="remember"
                                name="remember"
                                :tabindex="3"
                            />
                            <span>{{ $t('auth.login.remember_me') }}</span>
                        </Label>
                    </div>

                    <Button
                        type="submit"
                        data-testid="login-submit"
                        class="mt-4 w-full"
                        :tabindex="4"
                        :disabled="processing"
                        data-test="login-button"
                    >
                        <Spinner v-if="processing" />
                        {{ $t('auth.login.submit') }}
                    </Button>
                </div>
            </Form>
        </div>
    </AuthBase>
</template>
