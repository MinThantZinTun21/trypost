<script setup lang="ts">
import type { PrimitiveProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { ButtonGroupVariants } from "."
import { Primitive } from "reka-ui"
import { cn } from "@/lib/utils"

interface Props extends PrimitiveProps {
  class?: HTMLAttributes["class"]
  orientation?: ButtonGroupVariants["orientation"]
}

const props = withDefaults(defineProps<Props>(), {
  as: "div",
})
</script>

<template>
  <Primitive
    role="group"
    data-slot="button-group"
    :data-orientation="props.orientation"
    :as="as"
    :as-child="asChild"
    :class="cn('flex items-center gap-2 rounded-none border border-transparent border-b-input bg-transparent px-2.5 text-xs font-semibold uppercase group-has-[>[data-variant=outline]]/button-group:border-border [&_svg]:pointer-events-none [&_svg:not([class*=\'size-\'])]:size-3.5', props.class)"
  >
    <slot />
  </Primitive>
</template>
