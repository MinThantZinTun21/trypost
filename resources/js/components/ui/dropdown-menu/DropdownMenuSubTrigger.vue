<script setup lang="ts">
import type { DropdownMenuSubTriggerProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { IconChevronRight } from "@tabler/icons-vue"
import {
  DropdownMenuSubTrigger,
  useForwardProps,
} from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<DropdownMenuSubTriggerProps & { class?: HTMLAttributes["class"], inset?: boolean }>()

const delegatedProps = reactiveOmit(props, "class", "inset")
const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <DropdownMenuSubTrigger
    data-slot="dropdown-menu-sub-trigger"
    v-bind="forwardedProps"
    :class="cn(
      'focus:bg-accent focus:text-accent-foreground data-[state=open]:bg-accent data-[state=open]:text-accent-foreground not-data-[variant=destructive]:focus:**:text-accent-foreground flex cursor-pointer items-center gap-2 rounded-none px-3 py-2 text-xs font-medium tracking-wider uppercase outline-hidden select-none data-[inset]:pl-9.5 [&_svg]:shrink-0 [&_svg:not([class*=\'size-\'])]:size-3.5',
      props.class,
    )"
  >
    <slot />
    <IconChevronRight class="ml-auto" />
  </DropdownMenuSubTrigger>
</template>
