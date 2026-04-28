<script setup lang="ts">
import { onMounted, ref, nextTick, computed, watch } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { conversationsApi, type Conversation, type Message, type Citation } from '@/api/conversations'
import { useStream } from '@/composables/useStream'
import Button from '@/components/ui/Button.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Select from '@/components/ui/Select.vue'
import Badge from '@/components/ui/Badge.vue'
import { ArrowLeft, Send, StopCircle, RefreshCw, Trash2 } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { marked } from 'marked'

const props = defineProps<{ id: string }>()
const router = useRouter()

const conversation = ref<Conversation | null>(null)
const draft = ref('')
const scrollRef = ref<HTMLElement | null>(null)
const liveAssistant = ref<{ content: string; citations: Citation[] } | null>(null)

const stream = useStream({
  onEvent(e) {
    if (e.event === 'citations') {
      const c = (e.data as any)?.citations ?? []
      if (!liveAssistant.value) liveAssistant.value = { content: '', citations: [] }
      liveAssistant.value.citations = c
    } else if (e.event === 'delta') {
      if (!liveAssistant.value) liveAssistant.value = { content: '', citations: [] }
      liveAssistant.value.content += String((e.data as any)?.text ?? '')
      scrollToBottom()
    } else if (e.event === 'done') {
      liveAssistant.value = null
      load()
    } else if (e.event === 'error') {
      toast.error(String((e.data as any)?.message ?? 'Stream error'))
    }
  },
})

async function load() {
  conversation.value = await conversationsApi.get(Number(props.id))
  await nextTick()
  scrollToBottom()
}

onMounted(load)
watch(() => props.id, load)

function scrollToBottom() {
  if (scrollRef.value) scrollRef.value.scrollTop = scrollRef.value.scrollHeight
}

async function send() {
  const content = draft.value.trim()
  if (!content || !conversation.value) return
  draft.value = ''
  liveAssistant.value = { content: '', citations: [] }

  // Optimistic user message
  conversation.value.messages = [
    ...(conversation.value.messages ?? []),
    { id: -1, conversation_id: conversation.value.id, role: 'user', content, citations: [], prompt_tokens: 0, completion_tokens: 0, cost_usd: 0, model: null, status: 'complete', created_at: new Date().toISOString() },
  ]
  await nextTick()
  scrollToBottom()

  await stream.start(`/api/conversations/${conversation.value.id}/messages`, { content })
}

function stop() { stream.stop() }

async function destroy() {
  if (!conversation.value || !confirm('Delete this conversation?')) return
  await conversationsApi.destroy(conversation.value.id)
  router.push('/chat')
}

async function regenerate(m: Message) {
  if (!conversation.value || m.role !== 'assistant') return
  liveAssistant.value = { content: '', citations: [] }
  await stream.start(`/api/conversations/${conversation.value.id}/messages/${m.id}/regenerate`, {})
}

function renderMarkdown(text: string): string {
  // Convert [n] inline tokens to spans we can target with CSS or click handlers later.
  const safe = text.replace(/\[(\d+)\]/g, (_m, n) => ` <sup data-cite="${n}" class="cite">[${n}]</sup>`)
  return marked.parse(safe, { async: false }) as string
}

const orderedMessages = computed(() => conversation.value?.messages ?? [])

async function changeModel(model: string) {
  if (!conversation.value) return
  const [provider, m] = model.includes(':') ? model.split(':') : [conversation.value.provider, model]
  conversation.value = await conversationsApi.update(conversation.value.id, { provider, model: m })
}
</script>

<template>
  <div class="flex h-full flex-col">
    <header class="flex items-center justify-between gap-3 border-b px-4 py-3 bg-background">
      <div class="flex items-center gap-2 min-w-0">
        <Button variant="ghost" size="icon" @click="router.push('/chat')"><ArrowLeft class="h-4 w-4" /></Button>
        <div class="min-w-0">
          <div class="font-semibold truncate">{{ conversation?.title }}</div>
          <div class="text-xs text-muted-foreground">
            {{ conversation?.provider }} / {{ conversation?.model }} ·
            {{ conversation?.documents?.length ?? 0 }} docs
          </div>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <Select :modelValue="`${conversation?.provider}:${conversation?.model}`" @update:modelValue="changeModel">
          <option value="openai:gpt-4o-mini">OpenAI · gpt-4o-mini</option>
          <option value="openai:gpt-4o">OpenAI · gpt-4o</option>
          <option value="anthropic:claude-haiku-4-5">Anthropic · claude-haiku-4-5</option>
          <option value="anthropic:claude-sonnet-4-6">Anthropic · claude-sonnet-4-6</option>
        </Select>
        <Button variant="ghost" size="icon" @click="destroy" title="Delete"><Trash2 class="h-4 w-4" /></Button>
      </div>
    </header>

    <div ref="scrollRef" class="flex-1 overflow-y-auto px-4 py-6 space-y-6">
      <div v-if="!orderedMessages.length && !liveAssistant" class="mx-auto max-w-md text-center text-muted-foreground py-12">
        <p class="text-sm">Ask anything about your selected documents. Answers cite the exact pages used.</p>
      </div>

      <div v-for="m in orderedMessages" :key="m.id" class="mx-auto max-w-3xl">
        <div :class="['rounded-lg px-4 py-3', m.role === 'user' ? 'bg-primary/5 border' : '']">
          <div class="text-xs font-medium text-muted-foreground mb-1">{{ m.role === 'user' ? 'You' : 'Assistant' }}</div>
          <div class="prose-chat" v-html="renderMarkdown(m.content)" />

          <div v-if="m.citations?.length" class="mt-2 flex flex-wrap gap-1.5">
            <Badge v-for="c in m.citations" :key="c.chunk_id" variant="outline" class="cursor-help" :title="`p.${c.page ?? '?'} — ${c.snippet}`">
              [{{ c.n }}] p.{{ c.page ?? '?' }}
            </Badge>
          </div>

          <div v-if="m.role === 'assistant'" class="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
            <span v-if="m.prompt_tokens">{{ m.prompt_tokens + m.completion_tokens }} tok</span>
            <span v-if="m.cost_usd">· ${{ Number(m.cost_usd).toFixed(4) }}</span>
            <Button variant="ghost" size="sm" @click="regenerate(m)" :disabled="stream.isStreaming.value">
              <RefreshCw class="h-3 w-3" /> Regenerate
            </Button>
          </div>
        </div>
      </div>

      <div v-if="liveAssistant" class="mx-auto max-w-3xl">
        <div class="rounded-lg px-4 py-3">
          <div class="text-xs font-medium text-muted-foreground mb-1">Assistant</div>
          <div class="prose-chat" v-html="renderMarkdown(liveAssistant.content || '…')" />
          <div v-if="liveAssistant.citations?.length" class="mt-2 flex flex-wrap gap-1.5">
            <Badge v-for="c in liveAssistant.citations" :key="c.chunk_id" variant="outline" :title="`p.${c.page ?? '?'} — ${c.snippet}`">
              [{{ c.n }}] p.{{ c.page ?? '?' }}
            </Badge>
          </div>
        </div>
      </div>
    </div>

    <footer class="border-t bg-background p-3">
      <div class="mx-auto max-w-3xl flex items-end gap-2">
        <Textarea v-model="draft" rows="2" placeholder="Ask something about your documents…" class="resize-none" @keydown.meta.enter.prevent="send" @keydown.ctrl.enter.prevent="send" />
        <Button v-if="!stream.isStreaming.value" @click="send" :disabled="!draft.trim()">
          <Send class="h-4 w-4" /> Send
        </Button>
        <Button v-else variant="destructive" @click="stop">
          <StopCircle class="h-4 w-4" /> Stop
        </Button>
      </div>
    </footer>
  </div>
</template>

<style scoped>
:deep(.cite) {
  @apply ml-0.5 cursor-help rounded bg-muted px-1 text-[0.7em] text-muted-foreground;
}
</style>
