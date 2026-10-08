<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AuthBase from '@/layouts/AuthLayout.vue';

defineProps<{
    contactEmail?: string | null;
}>();

const sections = ['collect', 'use', 'storage', 'sharing', 'deletion'] as const;
</script>

<template>
    <AuthBase
        :title="$t('legal.privacy.title')"
        :description="$t('legal.privacy.description')"
    >
        <Head :title="$t('legal.privacy.page_title')" />

        <div class="flex flex-col gap-6 text-sm" data-testid="privacy-policy">
            <section
                v-for="section in sections"
                :id="section"
                :key="section"
                class="flex flex-col gap-1"
            >
                <h2 class="font-semibold">
                    {{ $t(`legal.privacy.sections.${section}.title`) }}
                </h2>
                <p class="text-muted-foreground">
                    {{ $t(`legal.privacy.sections.${section}.body`) }}
                </p>
            </section>

            <section id="contact" class="flex flex-col gap-1">
                <h2 class="font-semibold">
                    {{ $t('legal.privacy.sections.contact.title') }}
                </h2>
                <p class="text-muted-foreground">
                    {{ $t('legal.privacy.sections.contact.body') }}
                    <a
                        v-if="contactEmail"
                        :href="`mailto:${contactEmail}`"
                        class="underline"
                        >{{ contactEmail }}</a
                    >
                </p>
            </section>
        </div>
    </AuthBase>
</template>
