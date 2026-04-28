<script setup lang="ts">
import { cn } from '@/lib/utils'

defineProps<{ modelValue?: string; placeholder?: string; rows?: number | string; class?: string; disabled?: boolean }>()
defineEmits<{ (e: 'update:modelValue', v: string): void }>()
</script>

<template>
  <textarea
    :value="modelValue ?? ''"
    :placeholder="placeholder"
    :rows="rows ?? 4"
    :disabled="disabled"
    @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    :class="cn('flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50', $props.class)"
  />
</template>
