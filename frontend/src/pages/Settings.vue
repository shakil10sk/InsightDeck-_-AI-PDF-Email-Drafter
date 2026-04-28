<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { toast } from 'vue-sonner'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Label from '@/components/ui/Label.vue'
import Card from '@/components/ui/Card.vue'
import Select from '@/components/ui/Select.vue'

const auth = useAuthStore()

const name = ref('')
const email = ref('')
const default_provider = ref<'openai' | 'anthropic'>('openai')
const default_model = ref('gpt-4o-mini')
const byo_openai_key = ref('')
const byo_anthropic_key = ref('')

const savingProfile = ref(false)
const savingAi = ref(false)
const testing = ref<{ openai: boolean; anthropic: boolean }>({ openai: false, anthropic: false })

onMounted(async () => {
  await auth.fetchMe()
  if (auth.user) {
    name.value = auth.user.name
    email.value = auth.user.email
    default_provider.value = (auth.user.default_provider as any) ?? 'openai'
    default_model.value = auth.user.default_model ?? 'gpt-4o-mini'
  }
})

async function saveProfile() {
  savingProfile.value = true
  try {
    await api.patch('/api/me', { name: name.value, email: email.value })
    await auth.fetchMe()
    toast.success('Profile saved.')
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Save failed.')
  } finally {
    savingProfile.value = false
  }
}

async function saveAi() {
  savingAi.value = true
  try {
    const payload: Record<string, unknown> = {
      default_provider: default_provider.value,
      default_model: default_model.value,
    }
    if (byo_openai_key.value) payload.byo_openai_key = byo_openai_key.value
    if (byo_anthropic_key.value) payload.byo_anthropic_key = byo_anthropic_key.value
    await api.patch('/api/settings', payload)
    byo_openai_key.value = ''
    byo_anthropic_key.value = ''
    await auth.fetchMe()
    toast.success('AI settings saved.')
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Save failed.')
  } finally {
    savingAi.value = false
  }
}

async function testConnection(provider: 'openai' | 'anthropic') {
  testing.value[provider] = true
  try {
    const r = await api.post<{ ok: boolean; error?: string }>('/api/settings/test-connection', { provider })
    if (r.data.ok) toast.success(`${provider}: connected`)
    else toast.error(`${provider}: ${r.data.error || 'failed'}`)
  } catch (e: any) {
    toast.error(e?.response?.data?.error || `${provider} test failed`)
  } finally {
    testing.value[provider] = false
  }
}

async function deleteAccount() {
  const confirmed = prompt('Type DELETE to permanently remove your account and all data.')
  if (confirmed !== 'DELETE') return
  await api.delete('/api/me')
  await auth.logout()
  location.href = '/login'
}
</script>

<template>
  <div class="p-6 space-y-6 max-w-2xl">
    <h1 class="text-2xl font-semibold tracking-tight">Settings</h1>

    <Card class="p-4 space-y-3">
      <h2 class="font-semibold">Profile</h2>
      <div class="space-y-1">
        <Label>Name</Label>
        <Input v-model="name" />
      </div>
      <div class="space-y-1">
        <Label>Email</Label>
        <Input v-model="email" type="email" />
      </div>
      <Button @click="saveProfile" :loading="savingProfile">Save profile</Button>
    </Card>

    <Card class="p-4 space-y-3">
      <h2 class="font-semibold">AI preferences</h2>
      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1">
          <Label>Default provider</Label>
          <Select v-model="default_provider" class="w-full">
            <option value="openai">OpenAI</option>
            <option value="anthropic">Anthropic</option>
          </Select>
        </div>
        <div class="space-y-1">
          <Label>Default model</Label>
          <Select v-model="default_model" class="w-full">
            <template v-if="default_provider === 'openai'">
              <option value="gpt-4o-mini">gpt-4o-mini</option>
              <option value="gpt-4o">gpt-4o</option>
            </template>
            <template v-else>
              <option value="claude-haiku-4-5">claude-haiku-4-5</option>
              <option value="claude-sonnet-4-6">claude-sonnet-4-6</option>
            </template>
          </Select>
        </div>
      </div>

      <h3 class="text-sm font-medium pt-2">Bring your own API keys (optional)</h3>
      <p class="text-xs text-muted-foreground">When set, calls are made with your key and don't count against your daily token budget. Stored encrypted.</p>

      <div class="space-y-1">
        <Label>OpenAI API key {{ auth.user?.has_byo_openai_key ? ' (set)' : '' }}</Label>
        <div class="flex gap-2">
          <Input v-model="byo_openai_key" type="password" placeholder="sk-…" />
          <Button variant="outline" @click="testConnection('openai')" :loading="testing.openai">Test</Button>
        </div>
      </div>
      <div class="space-y-1">
        <Label>Anthropic API key {{ auth.user?.has_byo_anthropic_key ? ' (set)' : '' }}</Label>
        <div class="flex gap-2">
          <Input v-model="byo_anthropic_key" type="password" placeholder="sk-ant-…" />
          <Button variant="outline" @click="testConnection('anthropic')" :loading="testing.anthropic">Test</Button>
        </div>
      </div>

      <Button @click="saveAi" :loading="savingAi">Save AI settings</Button>
    </Card>

    <Card class="p-4 space-y-2 border-destructive/40">
      <h2 class="font-semibold text-destructive">Danger zone</h2>
      <p class="text-sm text-muted-foreground">Permanently delete your account and all associated data — documents, chats, drafts, usage history.</p>
      <Button variant="destructive" @click="deleteAccount">Delete account</Button>
    </Card>
  </div>
</template>
