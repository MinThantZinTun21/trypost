<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <h3
    data-slot="card-title"
    :class="cn('font-[family-name:var(--font-display)] text-lg leading-none font-semibold tracking-wider uppercase', props.class)"
  >
    <slot />
  </h3>
</template>
