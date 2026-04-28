<script setup lang="ts">
import { cn } from '@/lib/utils'
defineProps<{ modelValue: string; class?: string; disabled?: boolean }>()
defineEmits<{ (e: 'update:modelValue', v: string): void }>()
</script>

<template>
  <select
    :value="modelValue"
    :disabled="disabled"
    @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
    :class="cn('h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50', $props.class)"
  >
    <slot />
  </select>
</template>
