<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { IconChevronRight, IconUser } from '@tabler/icons-vue';

import PageHeader from '@/components/PageHeader.vue';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { edit as profileEdit } from '@/routes/app/profile';
</script>

<template>
    <Head :title="$t('settings.hub.title')" />

    <AppLayout>
        <div class="mx-auto max-w-4xl space-y-8 px-4 py-8">
            <PageHeader
                :title="$t('settings.hub.title')"
                :description="$t('settings.hub.description')"
            />

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Link
                    :href="profileEdit().url"
                    class="group block rounded-none focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <Card
                        class="gap-0 py-0 shadow-2xs transition-all group-hover:shadow-sm"
                    >
                        <CardContent class="flex flex-col gap-4 p-5">
                            <div class="flex items-start justify-between">
                                <div
                                    class="inline-flex size-12 items-center justify-center rounded-none bg-primary text-primary-foreground"
                                >
                                    <IconUser class="size-6" stroke-width="2" />
                                </div>
                                <IconChevronRight
                                    class="size-5 text-foreground/40 transition-transform group-hover:translate-x-0.5 group-hover:text-foreground"
                                />
                            </div>
                            <div>
                                <h2
                                    class="font-display text-lg font-semibold text-foreground"
                                >
                                    {{ $t('settings.hub.profile.title') }}
                                </h2>
                                <p class="mt-1 text-sm text-foreground/70">
                                    {{ $t('settings.hub.profile.description') }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
