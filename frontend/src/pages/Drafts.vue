<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { draftsApi, type Draft, type Tone, type Length } from '@/api/drafts'
import { useStream } from '@/composables/useStream'
import { formatRelative } from '@/lib/utils'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Select from '@/components/ui/Select.vue'
import Label from '@/components/ui/Label.vue'
import { Mail, Wand2, StopCircle, Send } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const router = useRouter()

const drafts = ref<Draft[]>([])
const goal = ref('')
const recipient = ref('')
const context = ref('')
const tone = ref<Tone>('friendly')
const length = ref<Length>('medium')
const provider = ref<'openai' | 'anthropic'>('openai')
const model = ref('gpt-4o-mini')
const liveOutput = ref('')
const liveDraftId = ref<number | null>(null)

const stream = useStream({
  onText(delta) {
    liveOutput.value += ''  // satisfy linter; real append happens via composable's text ref
  },
  onEvent(e) {
    if (e.event === 'start') liveDraftId.value = (e.data as any)?.id ?? null
    if (e.event === 'delta') liveOutput.value += String((e.data as any)?.text ?? '')
    if (e.event === 'done') {
      load()
      if (liveDraftId.value) router.push(`/drafts/${liveDraftId.value}`)
    }
    if (e.event === 'error') toast.error(String((e.data as any)?.message ?? 'Stream error'))
  },
})

async function load() {
  drafts.value = await draftsApi.list()
}

onMounted(load)

async function generate() {
  if (!goal.value.trim()) return
  liveOutput.value = ''
  liveDraftId.value = null
  await stream.start('/api/drafts', {
    goal: goal.value,
    recipient: recipient.value || null,
    context: context.value || null,
    tone: tone.value,
    length: length.value,
    provider: provider.value,
    model: model.value,
  })
}
</script>

<template>
  <div class="p-6 space-y-6 max-w-5xl">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">Email drafts</h1>
      <p class="text-sm text-muted-foreground">Generate and iterate on professional emails.</p>
    </div>

    <Card class="p-4">
      <h2 class="font-semibold mb-3 flex items-center gap-2"><Wand2 class="h-4 w-4" /> New draft</h2>
      <div class="grid gap-3 md:grid-cols-2">
        <div class="md:col-span-2 space-y-1">
          <Label>Goal (what should the email accomplish?)</Label>
          <Textarea v-model="goal" rows="3" placeholder="e.g. Politely follow up on a missed deadline and propose a new timeline" />
        </div>
        <div class="space-y-1">
          <Label>Recipient (optional)</Label>
          <Input v-model="recipient" placeholder="Sarah, our project lead" />
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div class="space-y-1">
            <Label>Tone</Label>
            <Select v-model="tone" class="w-full">
              <option value="friendly">Friendly</option>
              <option value="formal">Formal</option>
              <option value="direct">Direct</option>
              <option value="empathetic">Empathetic</option>
            </Select>
          </div>
          <div class="space-y-1">
            <Label>Length</Label>
            <Select v-model="length" class="w-full">
              <option value="short">Short</option>
              <option value="medium">Medium</option>
              <option value="long">Long</option>
            </Select>
          </div>
        </div>
        <div class="md:col-span-2 space-y-1">
          <Label>Existing thread or notes (optional)</Label>
          <Textarea v-model="context" rows="3" placeholder="Paste the previous email or any context the model should consider" />
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div class="space-y-1">
            <Label>Provider</Label>
            <Select v-model="provider" class="w-full">
              <option value="openai">OpenAI</option>
              <option value="anthropic">Anthropic</option>
            </Select>
          </div>
          <div class="space-y-1">
            <Label>Model</Label>
            <Select v-model="model" class="w-full">
              <template v-if="provider === 'openai'">
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
        <div class="md:col-span-2 flex gap-2">
          <Button v-if="!stream.isStreaming.value" @click="generate" :disabled="!goal.trim()">
            <Send class="h-4 w-4" /> Generate
          </Button>
          <Button v-else variant="destructive" @click="stream.stop()">
            <StopCircle class="h-4 w-4" /> Stop
          </Button>
        </div>
      </div>

      <div v-if="liveOutput || stream.isStreaming.value" class="mt-4 rounded-md border bg-muted/30 p-3 text-sm whitespace-pre-wrap">
        {{ liveOutput || '…' }}
      </div>
    </Card>

    <Card class="p-4">
      <h2 class="font-semibold mb-3">Recent drafts</h2>
      <div v-if="!drafts.length" class="text-center py-12 text-muted-foreground">
        <Mail class="h-8 w-8 mx-auto mb-2 opacity-50" />
        <p class="text-sm">No drafts yet.</p>
      </div>
      <ul v-else class="space-y-1">
        <li v-for="d in drafts" :key="d.id">
          <button
            class="w-full text-left rounded-md px-3 py-2 hover:bg-accent flex items-center justify-between gap-3"
            @click="router.push(`/drafts/${d.id}`)"
          >
            <span class="truncate">{{ d.goal }}</span>
            <span class="shrink-0 text-xs text-muted-foreground">{{ formatRelative(d.created_at) }}</span>
          </button>
        </li>
      </ul>
    </Card>
  </div>
</template>
