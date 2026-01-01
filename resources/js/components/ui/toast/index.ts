import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Toast } from "./Toast.vue"

export const toastVariants = cva(
  "pointer-events-auto relative flex items-center justify-between space-x-4 overflow-hidden rounded-md border p-4 pr-6 shadow-lg transition-all w-fit",
  {
    variants: {
      variant: {
        default:
          "bg-background border-border text-foreground",
        success:
          "bg-green-50 border-green-200 text-green-900 dark:bg-green-950 dark:border-green-800 dark:text-green-100",
        destructive:
          "bg-destructive border-destructive text-white dark:bg-destructive/60 dark:border-destructive",
        warning:
          "bg-yellow-50 border-yellow-200 text-yellow-900 dark:bg-yellow-950 dark:border-yellow-800 dark:text-yellow-100",
        info:
          "bg-blue-50 border-blue-200 text-blue-900 dark:bg-blue-950 dark:border-blue-800 dark:text-blue-100",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)

export const toastPositionVariants = cva(
  "fixed z-50",
  {
    variants: {
      position: {
        "top-right": "top-4 right-4",
        "top-left": "top-4 left-4",
        "top-center": "top-4 left-1/2 -translate-x-1/2",
        "bottom-right": "bottom-4 right-4",
        "bottom-left": "bottom-4 left-4",
        "bottom-center": "bottom-4 left-1/2 -translate-x-1/2",
      },
    },
    defaultVariants: {
      position: "top-right",
    },
  },
)

export type ToastVariants = VariantProps<typeof toastVariants>
export type ToastPosition = VariantProps<typeof toastPositionVariants>