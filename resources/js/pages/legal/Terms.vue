<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AuthBase from '@/layouts/AuthLayout.vue';

defineProps<{
    contactEmail?: string | null;
}>();

const sections = [
    'service',
    'accounts',
    'content',
    'availability',
    'changes',
] as const;
</script>

<template>
    <AuthBase
        :title="$t('legal.terms.title')"
        :description="$t('legal.terms.description')"
    >
        <Head :title="$t('legal.terms.page_title')" />

        <div class="flex flex-col gap-6 text-sm" data-testid="terms-of-service">
            <section
                v-for="section in sections"
                :id="section"
                :key="section"
                class="flex flex-col gap-1"
            >
                <h2 class="font-semibold">
                    {{ $t(`legal.terms.sections.${section}.title`) }}
                </h2>
                <p class="text-muted-foreground">
                    {{ $t(`legal.terms.sections.${section}.body`) }}
                </p>
            </section>

            <section id="contact" class="flex flex-col gap-1">
                <h2 class="font-semibold">
                    {{ $t('legal.terms.sections.contact.title') }}
                </h2>
                <p class="text-muted-foreground">
                    {{ $t('legal.terms.sections.contact.body') }}
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
