<script setup lang="ts">
import type { PrimitiveProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { ToastVariants, ToastPosition } from "."
import { Primitive } from "reka-ui"
import { cn } from "@/lib/utils"
import { toastVariants, toastPositionVariants } from "."

interface Props extends PrimitiveProps {
  variant?: ToastVariants["variant"]
  position?: ToastPosition["position"]
  class?: HTMLAttributes["class"]
}

const props = withDefaults(defineProps<Props>(), {
  as: "div",
  position: "top-right",
})
</script>

<template>
  <Primitive
    data-slot="toast"
    :as="as"
    :as-child="asChild"
    :class="cn(toastVariants({ variant }), toastPositionVariants({ position }), props.class)"
  >
    <slot />
  </Primitive>
</template>