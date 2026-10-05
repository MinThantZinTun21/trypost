<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col gap-4 p-6 md:p-10">
        <div class="flex items-start">
            <img
                src="/images/trypost/logo-light.png"
                alt="TryPost"
                class="h-7"
            />
        </div>

        <div class="flex flex-1 items-center justify-center">
            <div class="w-full max-w-lg">
                <div class="flex flex-col gap-6">
                    <div class="flex flex-col items-center gap-2 text-center">
                        <h1 v-if="title" class="text-2xl font-bold">
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="text-sm text-balance text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>

                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
