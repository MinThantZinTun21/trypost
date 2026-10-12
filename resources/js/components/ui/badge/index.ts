import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Badge } from "./Badge.vue"

// Soft pill badge: tinted fill, no border, semibold label.
export const badgeVariants = cva(
  "inline-flex items-center justify-center w-fit shrink-0 whitespace-nowrap rounded-full border border-transparent px-2.5 py-0.5 text-xs font-semibold gap-1 [&>svg]:size-3 [&>svg]:pointer-events-none focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 transition-[color,box-shadow] overflow-hidden",
  {
    variants: {
      variant: {
        default:
          "bg-brand text-primary-foreground [a&]:hover:brightness-110",
        secondary:
          "bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90",
        destructive:
          "bg-destructive text-white [a&]:hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40",
        success:
          "bg-emerald-100 text-emerald-800 [a&]:hover:bg-emerald-200",
        warning:
          "bg-amber-100 text-amber-800 [a&]:hover:bg-amber-200",
        outline:
          "border-border bg-card text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)
export type BadgeVariants = VariantProps<typeof badgeVariants>
