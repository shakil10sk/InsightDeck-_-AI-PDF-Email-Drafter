<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterView } from 'vue-router'
import { Toaster } from 'vue-sonner'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'

const auth = useAuthStore()
useThemeStore() // initialize theme

onMounted(async () => {
  if (!auth.initialized) await auth.fetchMe()
})
</script>

<template>
  <RouterView />
  <Toaster richColors position="top-right" />
</template>
