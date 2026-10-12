<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
    total?: number;
}>();
</script>

<template>
    <header class="space-y-1">
        <h1
            class="text-2xl leading-tight font-extrabold tracking-tight text-foreground sm:text-4xl"
        >
            {{ title }}
            <span v-if="total != null && total > 0" class="text-brand"
                >({{ total }})</span
            >
        </h1>
        <p v-if="description" class="max-w-2xl text-sm text-muted-foreground">
            {{ description }}
        </p>
    </header>
</template>
