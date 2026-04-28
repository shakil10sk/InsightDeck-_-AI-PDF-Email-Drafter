<script setup lang="ts">
import { computed, type HTMLAttributes } from 'vue'
import { cva } from 'class-variance-authority'
import { cn } from '@/lib/utils'

type Variant = 'default' | 'secondary' | 'outline' | 'ghost' | 'destructive' | 'link'
type Size = 'default' | 'sm' | 'lg' | 'icon'

const button = cva(
  'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50',
  {
    variants: {
      variant: {
        default: 'bg-primary text-primary-foreground hover:bg-primary/90',
        secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
        outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
        ghost: 'hover:bg-accent hover:text-accent-foreground',
        destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
        link: 'text-primary underline-offset-4 hover:underline',
      },
      size: {
        default: 'h-9 px-4 py-2',
        sm: 'h-8 rounded-md px-3 text-xs',
        lg: 'h-10 rounded-md px-6',
        icon: 'h-9 w-9',
      },
    },
    defaultVariants: { variant: 'default', size: 'default' },
  }
)

const props = withDefaults(
  defineProps<{
    variant?: Variant
    size?: Size
    type?: 'button' | 'submit' | 'reset'
    disabled?: boolean
    loading?: boolean
    class?: HTMLAttributes['class']
  }>(),
  { type: 'button', disabled: false, loading: false, variant: 'default', size: 'default' }
)

const klass = computed(() => cn(button({ variant: props.variant, size: props.size }), props.class))
</script>

<template>
  <button :type="type" :disabled="disabled || loading" :class="klass">
    <span v-if="loading" class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent" />
    <slot />
  </button>
</template>
