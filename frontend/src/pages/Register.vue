<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Label from '@/components/ui/Label.vue'
import Card from '@/components/ui/Card.vue'

const auth = useAuthStore()
const router = useRouter()

const name = ref('')
const email = ref('')
const password = ref('')
const password_confirmation = ref('')
const loading = ref(false)
const errors = ref<Record<string, string[]>>({})
const error = ref<string | null>(null)

async function submit() {
  errors.value = {}
  error.value = null
  loading.value = true
  try {
    await auth.register({ name: name.value, email: email.value, password: password.value, password_confirmation: password_confirmation.value })
    router.push('/')
  } catch (e: any) {
    errors.value = e?.response?.data?.errors ?? {}
    error.value = e?.response?.data?.message || e?.message || 'Registration failed.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-full grid place-items-center p-6 bg-background">
    <Card class="w-full max-w-sm p-6">
      <h1 class="mb-1 text-xl font-semibold">Create your account</h1>
      <p class="mb-4 text-sm text-muted-foreground">Free during beta — 50K tokens/day included.</p>
      <form class="space-y-3" @submit.prevent="submit">
        <div class="space-y-1">
          <Label for="name">Name</Label>
          <Input id="name" v-model="name" required />
          <p v-if="errors.name" class="text-xs text-destructive">{{ errors.name[0] }}</p>
        </div>
        <div class="space-y-1">
          <Label for="email">Email</Label>
          <Input id="email" v-model="email" type="email" autocomplete="email" required />
          <p v-if="errors.email" class="text-xs text-destructive">{{ errors.email[0] }}</p>
        </div>
        <div class="space-y-1">
          <Label for="password">Password</Label>
          <Input id="password" v-model="password" type="password" autocomplete="new-password" required />
          <p v-if="errors.password" class="text-xs text-destructive">{{ errors.password[0] }}</p>
        </div>
        <div class="space-y-1">
          <Label for="confirm">Confirm password</Label>
          <Input id="confirm" v-model="password_confirmation" type="password" autocomplete="new-password" required />
        </div>
        <p v-if="error && Object.keys(errors).length === 0" class="text-sm text-destructive">{{ error }}</p>
        <Button type="submit" class="w-full" :loading="loading">Create account</Button>
      </form>

      <div class="mt-4 text-center text-sm">
        Already have an account?
        <RouterLink to="/login" class="font-medium underline-offset-4 hover:underline">Log in</RouterLink>
      </div>
    </Card>
  </div>
</template>
