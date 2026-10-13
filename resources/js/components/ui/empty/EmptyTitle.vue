<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="empty-title"
    :class="cn('font-[family-name:var(--font-display)] text-lg font-semibold tracking-wider uppercase', props.class)"
  >
    <slot />
  </div>
</template>
