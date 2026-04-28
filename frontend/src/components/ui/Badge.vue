<script setup lang="ts">
import { cn } from '@/lib/utils'
import { computed } from 'vue'

const props = defineProps<{ variant?: 'default' | 'secondary' | 'outline' | 'success' | 'warning' | 'destructive'; class?: string }>()

const klass = computed(() => {
  const v = props.variant ?? 'default'
  const map = {
    default: 'bg-primary text-primary-foreground',
    secondary: 'bg-secondary text-secondary-foreground',
    outline: 'border text-foreground',
    success: 'bg-emerald-600/15 text-emerald-700 dark:text-emerald-300 border border-emerald-600/30',
    warning: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30',
    destructive: 'bg-destructive/15 text-destructive border border-destructive/30',
  }
  return cn('inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', map[v], props.class)
})
</script>

<template>
  <span :class="klass"><slot /></span>
</template>
