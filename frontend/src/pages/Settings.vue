<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { api } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { toast } from 'vue-sonner'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Label from '@/components/ui/Label.vue'
import Card from '@/components/ui/Card.vue'
import Select from '@/components/ui/Select.vue'
import Badge from '@/components/ui/Badge.vue'
import { CheckCircle2, KeyRound, ShieldCheck, AlertTriangle, Loader2, Eye, EyeOff } from 'lucide-vue-next'

type Provider = 'openai' | 'anthropic'

const auth = useAuthStore()

const name = ref('')
const email = ref('')
const default_provider = ref<Provider>('openai')
const default_model = ref('gpt-4o-mini')

const draftKey = ref<Record<Provider, string>>({ openai: '', anthropic: '' })
const showKey = ref<Record<Provider, boolean>>({ openai: false, anthropic: false })
const savingKey = ref<Record<Provider, boolean>>({ openai: false, anthropic: false })
const removingKey = ref<Record<Provider, boolean>>({ openai: false, anthropic: false })
const testing = ref<Record<Provider, boolean>>({ openai: false, anthropic: false })
const testResult = ref<Record<Provider, 'ok' | 'fail' | null>>({ openai: null, anthropic: null })

const savingProfile = ref(false)
const savingPrefs = ref(false)

const hasKey = computed<Record<Provider, boolean>>(() => ({
  openai: !!auth.user?.has_byo_openai_key,
  anthropic: !!auth.user?.has_byo_anthropic_key,
}))

const placeholders: Record<Provider, string> = { openai: 'sk-…', anthropic: 'sk-ant-…' }
const labels: Record<Provider, string> = { openai: 'OpenAI', anthropic: 'Anthropic' }

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

async function savePrefs() {
  savingPrefs.value = true
  try {
    await api.patch('/api/settings', {
      default_provider: default_provider.value,
      default_model: default_model.value,
    })
    await auth.fetchMe()
    toast.success('Preferences saved.')
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Save failed.')
  } finally {
    savingPrefs.value = false
  }
}

async function saveKey(p: Provider) {
  const value = draftKey.value[p].trim()
  if (!value) {
    toast.error('Paste a key first.')
    return
  }
  savingKey.value[p] = true
  try {
    await api.patch('/api/settings', { [`byo_${p}_key`]: value })
    draftKey.value[p] = ''
    showKey.value[p] = false
    testResult.value[p] = null
    await auth.fetchMe()
    toast.success(`${labels[p]} key saved — encrypted at rest.`)
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Save failed.')
  } finally {
    savingKey.value[p] = false
  }
}

async function removeKey(p: Provider) {
  if (!confirm(`Remove your saved ${labels[p]} key? Future calls will fall back to the platform key.`)) return
  removingKey.value[p] = true
  try {
    await api.patch('/api/settings', { [`byo_${p}_key`]: null })
    testResult.value[p] = null
    await auth.fetchMe()
    toast.success(`${labels[p]} key removed.`)
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Remove failed.')
  } finally {
    removingKey.value[p] = false
  }
}

async function testConnection(p: Provider) {
  testing.value[p] = true
  testResult.value[p] = null
  try {
    const r = await api.post<{ ok: boolean; error?: string }>('/api/settings/test-connection', { provider: p })
    testResult.value[p] = r.data.ok ? 'ok' : 'fail'
    if (r.data.ok) toast.success(`${labels[p]}: connected`)
    else toast.error(`${labels[p]}: ${r.data.error || 'failed'}`)
  } catch (e: any) {
    testResult.value[p] = 'fail'
    toast.error(e?.response?.data?.error || `${labels[p]} test failed`)
  } finally {
    testing.value[p] = false
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
  <div class="p-6 lg:p-8 space-y-6 max-w-3xl">
    <div>
      <h1 class="text-3xl font-semibold tracking-tight">Settings</h1>
      <p class="mt-1 text-sm text-muted-foreground">Manage your profile, AI preferences, and provider keys.</p>
    </div>

    <!-- Profile -->
    <Card class="card-elevated p-5 space-y-4">
      <div>
        <h2 class="font-semibold">Profile</h2>
        <p class="text-xs text-muted-foreground">Used for sign-in and display.</p>
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        <div class="space-y-1.5">
          <Label>Name</Label>
          <Input v-model="name" />
        </div>
        <div class="space-y-1.5">
          <Label>Email</Label>
          <Input v-model="email" type="email" />
        </div>
      </div>
      <div class="flex justify-end">
        <Button @click="saveProfile" :loading="savingProfile">Save profile</Button>
      </div>
    </Card>

    <!-- AI Preferences -->
    <Card class="card-elevated p-5 space-y-4">
      <div>
        <h2 class="font-semibold">AI preferences</h2>
        <p class="text-xs text-muted-foreground">Defaults applied to new conversations and drafts.</p>
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        <div class="space-y-1.5">
          <Label>Default provider</Label>
          <Select v-model="default_provider" class="w-full">
            <option value="openai">OpenAI</option>
            <option value="anthropic">Anthropic</option>
          </Select>
        </div>
        <div class="space-y-1.5">
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
      <div class="flex justify-end">
        <Button @click="savePrefs" :loading="savingPrefs">Save preferences</Button>
      </div>
    </Card>

    <!-- BYO API keys -->
    <Card class="card-elevated p-5 space-y-5">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="font-semibold flex items-center gap-2"><KeyRound class="h-4 w-4" /> Your API keys</h2>
          <p class="mt-1 text-xs text-muted-foreground">
            When a key is on file, calls are made with your key and don't count against your daily token budget.
            <span class="inline-flex items-center gap-1 ml-1"><ShieldCheck class="h-3 w-3" /> Encrypted at rest with AES-256.</span>
          </p>
        </div>
      </div>

      <div v-for="p in (['openai','anthropic'] as Provider[])" :key="p" class="space-y-2">
        <div class="flex items-center justify-between gap-2">
          <Label>{{ labels[p] }} API key</Label>
          <div class="flex items-center gap-1.5">
            <Badge v-if="hasKey[p]" variant="success">
              <CheckCircle2 class="h-3 w-3 mr-1" /> Key on file
            </Badge>
            <Badge v-else variant="outline">Not set</Badge>
            <Badge v-if="testResult[p] === 'ok'" variant="success">
              <CheckCircle2 class="h-3 w-3 mr-1" /> Verified
            </Badge>
            <Badge v-else-if="testResult[p] === 'fail'" variant="destructive">
              <AlertTriangle class="h-3 w-3 mr-1" /> Failed
            </Badge>
          </div>
        </div>

        <div class="flex gap-2">
          <div class="relative flex-1">
            <Input
              :model-value="draftKey[p]"
              @update:model-value="(v: string) => draftKey[p] = v"
              :type="showKey[p] ? 'text' : 'password'"
              :placeholder="hasKey[p] ? '•••••••••••••••• (a key is on file — paste a new one to replace)' : placeholders[p]"
              class="pr-10"
            />
            <button
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-muted-foreground hover:text-foreground"
              @click="showKey[p] = !showKey[p]"
              :title="showKey[p] ? 'Hide' : 'Show'"
            >
              <Eye v-if="!showKey[p]" class="h-3.5 w-3.5" />
              <EyeOff v-else class="h-3.5 w-3.5" />
            </button>
          </div>
          <Button
            variant="default"
            :disabled="!draftKey[p].trim() || savingKey[p]"
            :loading="savingKey[p]"
            @click="saveKey(p)"
          >
            {{ hasKey[p] ? 'Replace' : 'Save' }}
          </Button>
        </div>

        <div class="flex items-center gap-2 text-xs">
          <Button variant="ghost" size="sm" :disabled="testing[p]" @click="testConnection(p)" class="h-7 px-2 text-xs">
            <Loader2 v-if="testing[p]" class="h-3 w-3 animate-spin" />
            Test connection
          </Button>
          <Button
            v-if="hasKey[p]"
            variant="ghost" size="sm"
            class="h-7 px-2 text-xs text-destructive hover:text-destructive"
            :loading="removingKey[p]"
            @click="removeKey(p)"
          >
            Remove key
          </Button>
          <span class="ml-auto text-muted-foreground">
            <span v-if="hasKey[p]">Once saved, the value is never sent back to the browser.</span>
            <span v-else>Get one from
              <a v-if="p === 'openai'" href="https://platform.openai.com/api-keys" target="_blank" rel="noopener" class="underline underline-offset-2 hover:text-foreground">platform.openai.com</a>
              <a v-else href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener" class="underline underline-offset-2 hover:text-foreground">console.anthropic.com</a>.
            </span>
          </span>
        </div>
      </div>
    </Card>

    <!-- Danger zone -->
    <Card class="card-elevated p-5 space-y-2 border-destructive/40">
      <h2 class="font-semibold text-destructive flex items-center gap-2"><AlertTriangle class="h-4 w-4" /> Danger zone</h2>
      <p class="text-sm text-muted-foreground">Permanently delete your account and everything associated with it — documents, chats, drafts, usage history.</p>
      <div class="flex justify-end pt-1">
        <Button variant="destructive" @click="deleteAccount">Delete account</Button>
      </div>
    </Card>
  </div>
</template>
