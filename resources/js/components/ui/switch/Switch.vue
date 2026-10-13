<script setup lang="ts">
import type { SwitchRootEmits, SwitchRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import {
  SwitchRoot,
  SwitchThumb,
  useForwardPropsEmits,
} from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<SwitchRootProps & { class?: HTMLAttributes["class"] }>()

const emits = defineEmits<SwitchRootEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <SwitchRoot
    v-slot="slotProps"
    data-slot="switch"
    v-bind="forwarded"
    :class="cn(
      'peer group/switch relative inline-flex h-4.5 w-8.25 shrink-0 cursor-pointer items-center rounded-none border transition-all outline-none data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=unchecked]:border-input/50 data-[state=unchecked]:bg-input focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30 aria-invalid:border-destructive aria-invalid:ring-2 aria-invalid:ring-destructive/20 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40 group-has-[:focus-visible]/field-label:ring-0 group-has-[:focus-visible]/field-label:data-[state=unchecked]:border-input/50 group-has-[:focus-visible]/field-label:data-[state=checked]:border-primary disabled:cursor-not-allowed disabled:opacity-50',
      props.class,
    )"
  >
    <SwitchThumb
      data-slot="switch-thumb"
      :class="cn('bg-background pointer-events-none block size-3.5 rounded-none ring-0 transition-transform data-[state=checked]:translate-x-[calc(100%+2px)] data-[state=unchecked]:translate-x-0.25 dark:data-[state=checked]:bg-primary-foreground dark:data-[state=unchecked]:bg-foreground')"
    >
      <slot name="thumb" v-bind="slotProps" />
    </SwitchThumb>
  </SwitchRoot>
</template>
