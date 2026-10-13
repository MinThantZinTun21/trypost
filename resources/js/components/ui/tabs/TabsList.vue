<script setup lang="ts">
import type { TabsListProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { TabsList } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<TabsListProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <TabsList
    data-slot="tabs-list"
    v-bind="delegatedProps"
    :class="cn(
      'group/tabs-list bg-muted text-muted-foreground inline-flex w-fit max-w-full items-center overflow-x-auto rounded-none p-1 group-data-[orientation=horizontal]/tabs:min-h-10 group-data-[orientation=vertical]/tabs:h-fit group-data-[orientation=vertical]/tabs:flex-col [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
      props.class,
    )"
  >
    <slot />
  </TabsList>
</template>
