<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import { documentsApi, type Document } from '@/api/documents'
import { formatBytes, formatRelative } from '@/lib/utils'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Input from '@/components/ui/Input.vue'
import { Upload, FileText, Loader2, AlertCircle, CheckCircle2, Trash2, Search, MessageSquare } from 'lucide-vue-next'

const router = useRouter()
const documents = ref<Document[]>([])
const loading = ref(true)
const uploading = ref(false)
const uploadProgress = ref(0)
const dragOver = ref(false)
const filter = ref('')
let pollHandle: number | null = null

async function load() {
  loading.value = true
  try { documents.value = await documentsApi.list() } finally { loading.value = false }
}

function startPolling() {
  if (pollHandle) return
  pollHandle = window.setInterval(async () => {
    if (!documents.value.some((d) => d.status === 'pending' || d.status === 'processing')) return
    documents.value = await documentsApi.list()
  }, 2000)
}

onMounted(async () => { await load(); startPolling() })
onBeforeUnmount(() => { if (pollHandle) clearInterval(pollHandle) })

async function handleUpload(file: File) {
  uploading.value = true; uploadProgress.value = 0
  try {
    const doc = await documentsApi.upload(file, undefined, (pct) => (uploadProgress.value = pct))
    toast.success(`Uploaded "${doc.title}" — processing started.`)
    await load()
  } catch (e: any) {
    toast.error(e?.response?.data?.errors?.file?.[0] || e?.response?.data?.message || 'Upload failed.')
  } finally {
    uploading.value = false; uploadProgress.value = 0
  }
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) handleUpload(file)
  input.value = ''
}

function onDrop(e: DragEvent) {
  e.preventDefault(); dragOver.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) handleUpload(file)
}

async function destroy(doc: Document) {
  if (!confirm(`Delete "${doc.title}"? This cannot be undone.`)) return
  try {
    await documentsApi.destroy(doc.id)
    documents.value = documents.value.filter((d) => d.id !== doc.id)
    toast.success('Deleted.')
  } catch { toast.error('Could not delete.') }
}

const filtered = computed(() => {
  const q = filter.value.trim().toLowerCase()
  if (!q) return documents.value
  return documents.value.filter((d) => d.title.toLowerCase().includes(q) || d.original_filename.toLowerCase().includes(q))
})

const statusCopy: Record<string, string> = {
  pending: 'In queue',
  processing: 'Processing',
  ready: 'Ready',
  failed: 'Failed',
}
</script>

<template>
  <div class="p-6 lg:p-8 space-y-5">
    <div class="flex items-end justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-3xl font-semibold tracking-tight">Documents</h1>
        <p class="mt-1 text-sm text-muted-foreground">Upload PDFs to chat with them or generate AI summaries.</p>
      </div>
      <label class="cursor-pointer">
        <input type="file" accept="application/pdf" class="hidden" @change="onFileChange" />
        <span class="inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium px-4 py-2 brand-gradient text-white shadow-sm hover:opacity-90 transition-opacity">
          <Upload class="h-4 w-4" />
          {{ uploading ? `Uploading… ${uploadProgress}%` : 'Upload PDF' }}
        </span>
      </label>
    </div>

    <!-- Compact dropzone (only large when there are no docs) -->
    <Card
      class="card-elevated p-6 transition-colors"
      :class="[dragOver ? 'ring-2 ring-primary/50' : '', documents.length ? 'p-4' : '']"
      @dragover.prevent="dragOver = true"
      @dragleave.prevent="dragOver = false"
      @drop="onDrop"
    >
      <div v-if="!documents.length" class="text-center py-8">
        <div class="mx-auto h-14 w-14 grid place-items-center rounded-full bg-primary/10 text-primary">
          <Upload class="h-6 w-6" />
        </div>
        <p class="mt-3 text-base font-medium">Drag a PDF here</p>
        <p class="text-sm text-muted-foreground">or use the button above. Max 20 MB.</p>
      </div>
      <div v-else class="flex items-center gap-3 text-sm text-muted-foreground">
        <Upload class="h-4 w-4" />
        Drop a PDF anywhere on this card to upload it.
        <div class="flex-1" />
        <div class="relative w-64">
          <Search class="absolute left-2 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input v-model="filter" placeholder="Filter by name…" class="pl-8 h-8" />
        </div>
      </div>
    </Card>

    <div v-if="loading" class="text-center py-12 text-muted-foreground"><Loader2 class="h-6 w-6 mx-auto animate-spin" /></div>

    <div v-else-if="!documents.length" />

    <ul v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <li v-for="doc in filtered" :key="doc.id">
        <Card class="card-elevated overflow-hidden hover:ring-soft transition-shadow group">
          <button class="w-full text-left p-4" @click="router.push(`/documents/${doc.id}`)">
            <div class="flex items-start gap-3">
              <div class="rounded-md bg-primary/10 text-primary p-2 shrink-0"><FileText class="h-5 w-5" /></div>
              <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                  <div class="font-medium truncate text-balance">{{ doc.title }}</div>
                  <Badge
                    :variant="doc.status === 'ready' ? 'success' : doc.status === 'failed' ? 'destructive' : 'warning'"
                    class="shrink-0"
                  >
                    <span v-if="doc.status === 'pending' || doc.status === 'processing'" class="pulse-dot mr-1.5" />
                    <CheckCircle2 v-else-if="doc.status === 'ready'" class="h-3 w-3 mr-1" />
                    <AlertCircle v-else class="h-3 w-3 mr-1" />
                    {{ statusCopy[doc.status] }}
                  </Badge>
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                  {{ formatBytes(doc.size_bytes) }} · {{ doc.page_count ?? '?' }} {{ doc.page_count === 1 ? 'page' : 'pages' }} · {{ formatRelative(doc.created_at) }}
                </div>
                <p v-if="doc.error_message" class="mt-2 text-xs text-destructive line-clamp-2">{{ doc.error_message }}</p>
              </div>
            </div>
          </button>
          <div class="px-4 pb-3 flex items-center justify-between border-t/0">
            <Button variant="ghost" size="sm" :disabled="doc.status !== 'ready'" @click="router.push(`/documents/${doc.id}`)">
              <MessageSquare class="h-3.5 w-3.5" /> Open
            </Button>
            <Button variant="ghost" size="icon" @click.stop="destroy(doc)" title="Delete">
              <Trash2 class="h-4 w-4" />
            </Button>
          </div>
        </Card>
      </li>
    </ul>
  </div>
</template>
