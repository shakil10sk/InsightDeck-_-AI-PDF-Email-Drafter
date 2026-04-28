<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { authApi } from '@/api/auth'
import { toast } from 'vue-sonner'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Label from '@/components/ui/Label.vue'
import Card from '@/components/ui/Card.vue'

const route = useRoute()
const router = useRouter()

const token = ref('')
const email = ref('')
const password = ref('')
const password_confirmation = ref('')
const loading = ref(false)

onMounted(() => {
  token.value = String(route.query.token ?? '')
  email.value = String(route.query.email ?? '')
})

async function submit() {
  loading.value = true
  try {
    await authApi.reset({ token: token.value, email: email.value, password: password.value, password_confirmation: password_confirmation.value })
    toast.success('Password updated. Please log in.')
    router.push('/login')
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Reset failed.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-full grid place-items-center p-6">
    <Card class="w-full max-w-sm p-6">
      <h1 class="mb-1 text-xl font-semibold">Choose a new password</h1>
      <form class="mt-4 space-y-3" @submit.prevent="submit">
        <div class="space-y-1">
          <Label for="email">Email</Label>
          <Input id="email" v-model="email" type="email" required />
        </div>
        <div class="space-y-1">
          <Label for="password">New password</Label>
          <Input id="password" v-model="password" type="password" required />
        </div>
        <div class="space-y-1">
          <Label for="confirm">Confirm password</Label>
          <Input id="confirm" v-model="password_confirmation" type="password" required />
        </div>
        <Button type="submit" class="w-full" :loading="loading">Update password</Button>
      </form>
      <div class="mt-4 text-sm">
        <RouterLink to="/login" class="text-muted-foreground hover:text-foreground">← Back to log in</RouterLink>
      </div>
    </Card>
  </div>
</template>
