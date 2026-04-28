<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { toast } from 'vue-sonner'
import { useAuthStore } from '@/stores/auth'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Label from '@/components/ui/Label.vue'
import Logo from '@/components/brand/Logo.vue'
import { Sparkles, FileText, MessageSquare, Mail, Zap } from 'lucide-vue-next'

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

const features = [
  { icon: FileText, label: 'Drop a PDF, get a streaming summary in seconds.' },
  { icon: MessageSquare, label: 'Chat with your documents — answers cite the exact pages.' },
  { icon: Mail, label: 'Drafting Studio: tone-tuned emails you can iterate on live.' },
  { icon: Zap, label: 'Switch between OpenAI and Anthropic mid-conversation.' },
]
</script>

<template>
  <div class="min-h-full grid lg:grid-cols-[1.05fr_1fr] bg-background bg-dotgrid">
    <!-- Hero panel -->
    <div class="hidden lg:flex relative overflow-hidden flex-col justify-between p-10 text-white brand-gradient">
      <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-white/10 blur-3xl" />
      <div class="absolute bottom-0 left-0 w-72 h-72 rounded-full bg-black/30 blur-3xl" />
      <div class="relative">
        <Logo with-wordmark :size="32" class="text-white" />
        <h1 class="mt-12 text-4xl font-semibold tracking-tight text-balance leading-tight">
          Turn dense PDFs into answers — with citations.
        </h1>
        <p class="mt-3 max-w-md text-white/80">
          InsightDeck embeds your documents, retrieves the right passages, and streams the answer back the moment you ask.
        </p>
      </div>

      <ul class="relative mt-10 space-y-3 max-w-md">
        <li v-for="f in features" :key="f.label" class="flex gap-3">
          <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-md bg-white/15 ring-1 ring-white/20">
            <component :is="f.icon" class="h-4 w-4" />
          </span>
          <span class="text-sm text-white/90">{{ f.label }}</span>
        </li>
      </ul>

      <div class="relative text-xs text-white/60">
        Built with Laravel 11 · Vue 3 · pgvector · OpenAI + Anthropic
      </div>
    </div>

    <!-- Form panel -->
    <div class="flex items-center justify-center p-6">
      <div class="w-full max-w-sm">
        <div class="lg:hidden mb-8 flex flex-col items-center text-center">
          <Logo with-wordmark :size="32" />
        </div>
        <h2 class="text-2xl font-semibold tracking-tight">Welcome back</h2>
        <p class="mt-1 text-sm text-muted-foreground">Log in to keep working with your documents.</p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
          <div class="space-y-1.5">
            <Label for="email">Email</Label>
            <Input id="email" v-model="email" type="email" autocomplete="email" required placeholder="you@example.com" />
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <Label for="password">Password</Label>
              <RouterLink to="/forgot-password" class="text-xs text-muted-foreground hover:text-foreground">Forgot?</RouterLink>
            </div>
            <Input id="password" v-model="password" type="password" autocomplete="current-password" required placeholder="••••••••" />
          </div>
          <label class="flex items-center gap-2 text-sm select-none">
            <input type="checkbox" v-model="remember" class="h-4 w-4 rounded border-input text-primary focus:ring-ring" />
            Remember me
          </label>
          <p v-if="error" class="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ error }}</p>
          <Button type="submit" class="w-full" :loading="loading">Log in</Button>
        </form>

        <div class="my-6 flex items-center gap-3 text-xs text-muted-foreground">
          <div class="h-px flex-1 bg-border" />
          <span>or</span>
          <div class="h-px flex-1 bg-border" />
        </div>

        <button
          @click="demo"
          :disabled="loading"
          type="button"
          class="w-full inline-flex items-center justify-center gap-2 rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-accent transition-colors disabled:opacity-60"
        >
          <Sparkles class="h-4 w-4 text-primary" />
          Try the live demo
        </button>

        <p class="mt-6 text-center text-sm text-muted-foreground">
          New here?
          <RouterLink to="/register" class="font-medium text-foreground underline-offset-4 hover:underline">Create an account</RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>
