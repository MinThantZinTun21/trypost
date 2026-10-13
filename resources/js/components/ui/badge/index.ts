import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Badge } from "./Badge.vue"

// Sera badge: a bare uppercase label, no fill or border, coloured by variant.
export const badgeVariants = cva(
  "group/badge inline-flex w-fit shrink-0 items-center justify-center gap-1.5 overflow-hidden whitespace-nowrap rounded-none border-0 bg-transparent px-0 py-0 text-[0.625rem] font-semibold uppercase tracking-widest transition-colors focus-visible:ring-2 focus-visible:ring-ring/30 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 has-data-[icon=inline-end]:pr-0 has-data-[icon=inline-start]:pl-0 [&>svg]:pointer-events-none [&>svg]:size-3!",
  {
    variants: {
      variant: {
        default:
          "text-foreground [a&]:hover:text-foreground/70",
        secondary:
          "text-muted-foreground [a&]:hover:text-foreground",
        destructive:
          "text-destructive [a&]:hover:text-destructive/70 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40",
        success:
          "text-(color:--success) [a&]:hover:text-(color:--success)/70",
        warning:
          "text-(color:--warning) [a&]:hover:text-(color:--warning)/70",
        outline:
          "text-foreground [a&]:hover:text-foreground/70",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)
export type BadgeVariants = VariantProps<typeof badgeVariants>
