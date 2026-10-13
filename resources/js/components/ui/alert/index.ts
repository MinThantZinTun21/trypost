import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Alert } from "./Alert.vue"
export { default as AlertDescription } from "./AlertDescription.vue"
export { default as AlertTitle } from "./AlertTitle.vue"

// Sera alert: square card with a 2px accent bar on the left edge (::after).
export const alertVariants = cva(
  "group/alert relative grid w-full gap-1 border bg-background px-4 py-3 text-left text-sm has-data-[slot=alert-action]:relative has-data-[slot=alert-action]:pr-18 has-[>svg]:grid-cols-[auto_1fr] has-[>svg]:gap-x-2.5 *:[svg]:row-span-2 *:[svg]:translate-y-0.5 *:[svg]:text-current *:[svg:not([class*='size-'])]:size-4 after:absolute after:-inset-y-px after:-left-px after:w-0.5",
  {
    variants: {
      variant: {
        default: "bg-card text-card-foreground after:bg-foreground",
        destructive:
          "bg-card text-destructive *:data-[slot=alert-description]:text-destructive/90 *:[svg]:text-current after:bg-destructive",
        warning:
          "bg-card text-(color:--warning) *:data-[slot=alert-description]:text-(color:--warning)/90 *:[svg]:text-current after:bg-(color:--warning)",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)

export type AlertVariants = VariantProps<typeof alertVariants>
