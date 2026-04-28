<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { toast } from 'vue-sonner'
import { useAuthStore } from '@/stores/auth'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Label from '@/components/ui/Label.vue'
import Card from '@/components/ui/Card.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const remember = ref(false)
const loading = ref(false)
const error = ref<string | null>(null)

async function submit() {
  error.value = null
  loading.value = true
  try {
    await auth.login(email.value, password.value, remember.value)
    const redirect = (route.query.redirect as string) || '/'
    router.push(redirect)
  } catch (e: any) {
    error.value = e?.response?.data?.message || e?.message || 'Login failed.'
  } finally {
    loading.value = false
  }
}

async function demo() {
  loading.value = true
  try {
    await auth.loginAsDemo()
    router.push('/')
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Demo seeding required first.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-full grid place-items-center p-6 bg-background">
    <Card class="w-full max-w-sm p-6">
      <div class="mb-6 text-center">
        <div class="mx-auto h-10 w-10 rounded-md bg-primary text-primary-foreground grid place-items-center text-sm font-bold mb-3">ID</div>
        <h1 class="text-xl font-semibold">Welcome back</h1>
        <p class="text-sm text-muted-foreground">Log in to InsightDeck</p>
      </div>

      <form class="space-y-3" @submit.prevent="submit">
        <div class="space-y-1">
          <Label for="email">Email</Label>
          <Input id="email" v-model="email" type="email" autocomplete="email" required />
        </div>
        <div class="space-y-1">
          <Label for="password">Password</Label>
          <Input id="password" v-model="password" type="password" autocomplete="current-password" required />
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" v-model="remember" /> Remember me
        </label>
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        <Button type="submit" class="w-full" :loading="loading">Log in</Button>
        <Button type="button" variant="outline" class="w-full" @click="demo" :disabled="loading">Try the demo</Button>
      </form>

      <div class="mt-4 flex items-center justify-between text-sm">
        <RouterLink to="/forgot-password" class="text-muted-foreground hover:text-foreground">Forgot password?</RouterLink>
        <RouterLink to="/register" class="font-medium underline-offset-4 hover:underline">Create account</RouterLink>
      </div>
    </Card>
  </div>
</template>
