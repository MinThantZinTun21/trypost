<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <p
    data-slot="empty-description"
    :class="cn(
      'text-muted-foreground mt-0.5 text-sm/relaxed [&>a]:underline [&>a]:underline-offset-4 [&>a:hover]:text-foreground',
      props.class,
    )"
  >
    <slot />
  </p>
</template>
