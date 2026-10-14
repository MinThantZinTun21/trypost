<script setup lang="ts">
interface Props {
    title: string;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <header>
        <h3 class="mb-0.5 font-display text-base font-semibold text-foreground">
            {{ title }}
        </h3>
        <p v-if="description" class="text-sm text-foreground/70">
            {{ description }}
        </p>
    </header>
</template>
