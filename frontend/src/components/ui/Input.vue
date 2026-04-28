<script setup lang="ts">
import { cn } from '@/lib/utils'

defineProps<{ modelValue?: string | number; type?: string; placeholder?: string; class?: string; disabled?: boolean; autocomplete?: string; required?: boolean }>()
defineEmits<{ (e: 'update:modelValue', v: string): void }>()
</script>

<template>
  <input
    :type="type ?? 'text'"
    :value="modelValue ?? ''"
    :placeholder="placeholder"
    :disabled="disabled"
    :autocomplete="autocomplete"
    :required="required"
    @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    :class="cn('flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50', $props.class)"
  />
</template>
