<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { documentsApi, type Document, type Summary } from '@/api/documents'
import { conversationsApi } from '@/api/conversations'
import { formatBytes, formatRelative } from '@/lib/utils'
import { useStream } from '@/composables/useStream'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Select from '@/components/ui/Select.vue'
import { ArrowLeft, MessageSquare, Wand2, ExternalLink } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { marked } from 'marked'

function renderMarkdown(text: string): string {
  return marked.parse(text || '', { async: false }) as string
}

const props = defineProps<{ id: string }>()
const router = useRouter()
const document = ref<Document | null>(null)
const summary = ref<Summary | null>(null)
const length = ref<'short' | 'medium' | 'long'>('medium')
const summaryStream = useStream()

async function load() {
  document.value = await documentsApi.get(Number(props.id))
  summary.value = await documentsApi.getSummary(Number(props.id), length.value)
}

onMounted(load)

async function summarize() {
  if (!document.value || document.value.status !== 'ready') {
    toast.error('Document is not ready yet.')
    return
  }
  await summaryStream.start(`/api/documents/${document.value.id}/summary`, { length: length.value })
  // Reload summary after stream completes
  setTimeout(load, 300)
}

async function startChat() {
  if (!document.value) return
  const c = await conversationsApi.create({ document_ids: [document.value.id], title: `Chat: ${document.value.title}` })
  router.push(`/chat/${c.id}`)
}
</script>

<template>
  <div class="p-6 space-y-6 max-w-4xl">
    <div>
      <Button variant="ghost" size="sm" @click="router.back()"><ArrowLeft class="h-4 w-4" /> Back</Button>
    </div>

    <div v-if="!document" class="text-muted-foreground">Loading…</div>
    <div v-else>
      <div class="flex items-start justify-between gap-4 mb-4">
        <div class="min-w-0">
          <h1 class="text-2xl font-semibold tracking-tight truncate">{{ document.title }}</h1>
          <div class="text-sm text-muted-foreground">
            {{ document.original_filename }} · {{ formatBytes(document.size_bytes) }} · {{ document.page_count ?? '?' }} {{ document.page_count === 1 ? 'page' : 'pages' }} · {{ formatRelative(document.created_at) }}
          </div>
        </div>
        <Badge :variant="document.status === 'ready' ? 'success' : document.status === 'failed' ? 'destructive' : 'warning'">{{ document.status }}</Badge>
      </div>

      <div class="flex flex-wrap gap-2">
        <Button @click="startChat" :disabled="document.status !== 'ready'"><MessageSquare class="h-4 w-4" /> Chat with this document</Button>
        <a :href="documentsApi.fileUrl(document.id)" target="_blank">
          <Button variant="outline"><ExternalLink class="h-4 w-4" /> Open PDF</Button>
        </a>
      </div>

      <Card class="mt-6 p-4">
        <div class="flex items-center justify-between mb-3">
          <h2 class="font-semibold">Summary</h2>
          <div class="flex items-center gap-2">
            <Select v-model="length" class="h-8" @update:modelValue="load">
              <option value="short">Short</option>
              <option value="medium">Medium</option>
              <option value="long">Long</option>
            </Select>
            <Button size="sm" @click="summarize" :loading="summaryStream.isStreaming.value" :disabled="document.status !== 'ready'">
              <Wand2 class="h-4 w-4" />
              {{ summary ? 'Regenerate' : 'Generate' }}
            </Button>
          </div>
        </div>

        <div v-if="summaryStream.isStreaming.value" class="prose-chat text-sm typing-cursor" v-html="renderMarkdown(summaryStream.text.value)" />
        <div v-else-if="summary" class="prose-chat text-sm" v-html="renderMarkdown(summary.content)" />
        <p v-else class="text-sm text-muted-foreground">No summary yet — click Generate to create one.</p>
        <p v-if="summaryStream.error.value" class="mt-2 text-sm text-destructive">{{ summaryStream.error.value }}</p>
      </Card>
    </div>
  </div>
</template>
