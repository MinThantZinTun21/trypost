<script setup lang="ts">
import type { AcceptableValue } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit, useVModel } from "@vueuse/core"
import { IconChevronDown } from "@tabler/icons-vue"
import { cn } from "@/lib/utils"

defineOptions({
  inheritAttrs: false,
})

const props = defineProps<{ modelValue?: AcceptableValue | AcceptableValue[], class?: HTMLAttributes["class"] }>()

const emit = defineEmits<{
  "update:modelValue": AcceptableValue
}>()

const modelValue = useVModel(props, "modelValue", emit, {
  passive: true,
  defaultValue: "",
})

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <div
    class="group/native-select relative w-fit has-[select:disabled]:opacity-50"
    data-slot="native-select-wrapper"
  >
    <select
      v-bind="{ ...$attrs, ...delegatedProps }"
      v-model="modelValue"
      data-slot="native-select"
      :class="cn(
        'h-10 w-full min-w-0 cursor-pointer appearance-none rounded-none border border-transparent border-b-input bg-transparent py-2 pr-8 pl-0 text-sm transition-[color,border-color] outline-none select-none placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground data-[size=sm]:h-9 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
        'focus-visible:border-b-ring',
        'aria-invalid:border-b-destructive dark:aria-invalid:border-b-destructive/50',
        props.class,
      )"
    >
      <slot />
    </select>
    <IconChevronDown
      class="text-muted-foreground pointer-events-none absolute top-1/2 right-0 size-3.5 -translate-y-1/2 select-none"
      aria-hidden="true"
      data-slot="native-select-icon"
    />
  </div>
</template>
