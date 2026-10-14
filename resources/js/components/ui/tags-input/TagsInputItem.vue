<script setup lang="ts">
import type { TagsInputItemProps } from "reka-ui"
import type { HTMLAttributes } from "vue"

import { reactiveOmit } from "@vueuse/core"
import { TagsInputItem, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<TagsInputItemProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <TagsInputItem v-bind="forwardedProps" :class="cn('bg-muted text-foreground flex h-5.5 w-fit items-center justify-center gap-1 rounded-none text-xs font-medium whitespace-nowrap data-[state=active]:ring-2 data-[state=active]:ring-ring/30', props.class)">
    <slot />
  </TagsInputItem>
</template>
