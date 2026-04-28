<script setup lang="ts">
import { cn } from '@/lib/utils'
defineProps<{ class?: string; size?: number; withWordmark?: boolean }>()
</script>

<template>
  <span :class="cn('inline-flex items-center gap-2', $props.class)">
    <svg
      :width="size ?? 28"
      :height="size ?? 28"
      viewBox="0 0 32 32"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-label="InsightDeck"
    >
      <defs>
        <linearGradient :id="`idg-${size ?? 28}`" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="hsl(var(--gradient-start))" />
          <stop offset="55%" stop-color="hsl(var(--gradient-mid))" />
          <stop offset="100%" stop-color="hsl(var(--gradient-end))" />
        </linearGradient>
      </defs>
      <rect x="0.5" y="0.5" width="31" height="31" rx="7.5" :fill="`url(#idg-${size ?? 28})`" />
      <!-- Stacked deck of insight rays -->
      <path d="M8.5 19.5L13 13.2L17.5 17.6L23.5 9" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
      <circle cx="23.5" cy="9" r="1.8" fill="white" />
      <path d="M8 24.5h16" stroke="white" stroke-opacity="0.55" stroke-width="1.5" stroke-linecap="round" />
    </svg>
    <span v-if="withWordmark" class="font-semibold tracking-tight text-[15px]">InsightDeck</span>
  </span>
</template>
