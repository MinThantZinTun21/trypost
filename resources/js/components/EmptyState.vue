<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    icon: Component;
    title: string;
    description: string;
}>();
</script>

<template>
    <div
        class="relative flex flex-1 flex-col items-center justify-center gap-4 overflow-hidden rounded-3xl border border-border bg-card/80 p-12 text-center shadow-sm backdrop-blur"
    >
        <div
            class="pointer-events-none absolute -top-24 left-1/2 size-64 -translate-x-1/2 rounded-full bg-brand opacity-10 blur-3xl"
            aria-hidden="true"
        />
        <div
            class="relative inline-flex size-16 items-center justify-center rounded-2xl bg-brand text-white shadow-lg shadow-primary/30"
        >
            <component :is="icon" class="size-8" stroke-width="1.75" />
        </div>
        <div class="relative space-y-1">
            <h3 class="text-xl font-bold tracking-tight text-foreground">
                {{ title }}
            </h3>
            <p class="max-w-sm text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.action" class="relative mt-2">
            <slot name="action" />
        </div>
    </div>
</template>
