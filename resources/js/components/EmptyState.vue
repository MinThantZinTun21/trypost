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
        class="relative flex flex-1 flex-col items-center justify-center gap-4 overflow-hidden rounded-none bg-card p-12 text-center ring-1 ring-foreground/5"
    >
        <div
            class="relative inline-flex size-16 items-center justify-center rounded-none bg-primary text-primary-foreground"
        >
            <component :is="icon" class="size-8" stroke-width="1.75" />
        </div>
        <div class="relative space-y-1">
            <h3
                class="font-display text-xl font-semibold tracking-tight text-foreground"
            >
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
