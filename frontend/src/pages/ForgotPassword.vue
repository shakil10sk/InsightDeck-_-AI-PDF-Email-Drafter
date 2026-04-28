<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { authApi } from '@/api/auth'
import { toast } from 'vue-sonner'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Label from '@/components/ui/Label.vue'
import Card from '@/components/ui/Card.vue'

const email = ref('')
const loading = ref(false)
const sent = ref(false)

async function submit() {
  loading.value = true
  try {
    await authApi.forgot(email.value)
    sent.value = true
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Could not send reset email.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-full grid place-items-center p-6">
    <Card class="w-full max-w-sm p-6">
      <h1 class="mb-1 text-xl font-semibold">Reset password</h1>
      <p class="mb-4 text-sm text-muted-foreground">We'll email you a link to choose a new password.</p>
      <div v-if="sent" class="rounded-md border bg-emerald-500/10 p-3 text-sm">
        If an account exists for <strong>{{ email }}</strong>, a reset link is on its way.
      </div>
      <form v-else class="space-y-3" @submit.prevent="submit">
        <div class="space-y-1">
          <Label for="email">Email</Label>
          <Input id="email" v-model="email" type="email" required />
        </div>
        <Button type="submit" class="w-full" :loading="loading">Send reset link</Button>
      </form>
      <div class="mt-4 text-sm">
        <RouterLink to="/login" class="text-muted-foreground hover:text-foreground">← Back to log in</RouterLink>
      </div>
    </Card>
  </div>
</template>
