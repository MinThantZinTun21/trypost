<script setup lang="ts">
import type { ComboboxGroupProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { ComboboxGroup, ComboboxLabel } from "reka-ui"

import { cn } from "@/lib/utils"

const props = defineProps<
  ComboboxGroupProps & {
    class?: HTMLAttributes["class"]
    heading?: string
  }
>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <ComboboxGroup
    data-slot="combobox-group"
    v-bind="delegatedProps"
    :class="
      cn('overflow-y-auto max-h-[240px] p-1.5 text-foreground', props.class)
    "
  >
    <ComboboxLabel
      v-if="heading"
      class="px-3 py-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
    >
      {{ heading }}
    </ComboboxLabel>
    <slot />
  </ComboboxGroup>
</template>
