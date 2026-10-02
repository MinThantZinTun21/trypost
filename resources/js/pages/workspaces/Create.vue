<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store as storeWorkspace } from '@/routes/app/workspaces';

const form = useForm({
    name: '',
});

const submit = (): void => {
    form.post(storeWorkspace.url());
};
</script>

<template>
    <Head :title="$t('workspaces.create.page_title')" />

    <AuthLayout
        :title="$t('workspaces.create.title')"
        :description="$t('workspaces.create.description')"
    >
        <form class="flex flex-col space-y-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">{{ $t('workspaces.create.name') }}</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    :placeholder="$t('workspaces.create.name_placeholder')"
                />
                <InputError :message="form.errors.name" />
            </div>

            <Button
                type="submit"
                class="w-full"
                data-testid="workspaces-create-submit"
                :disabled="form.processing"
            >
                {{ $t('workspaces.create.submit') }}
            </Button>
        </form>
    </AuthLayout>
</template>
