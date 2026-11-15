import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-full border px-2 py-0.5 text-xs leading-5 font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/30 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,background-color,border-color] overflow-hidden",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-primary text-primary-foreground [a&]:hover:bg-primary/90",
        secondary:
          "border-transparent bg-surface-secondary text-muted-foreground [a&]:hover:bg-surface-tertiary",
        destructive:
          "border-transparent bg-danger-soft text-danger-soft-foreground [a&]:hover:bg-danger-soft/80",
        success:
          "border-transparent bg-success-soft text-success-soft-foreground [a&]:hover:bg-success-soft/80",
        warning:
          "border-transparent bg-warning-soft text-warning-soft-foreground [a&]:hover:bg-warning-soft/80",
        info:
          "border-transparent bg-primary-soft text-primary [a&]:hover:bg-primary/15",
        outline:
          "border-border text-muted-foreground [a&]:hover:bg-surface-secondary [a&]:hover:text-foreground",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant,
  asChild = false,
  ...props
}: React.ComponentProps<"span"> &
  VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
  const Comp = asChild ? Slot : "span"

  return (
    <Comp
      data-slot="badge"
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  )
}

export { Badge, badgeVariants }
