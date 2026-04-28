<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import { documentsApi, type Document } from '@/api/documents'
import { formatBytes, formatRelative } from '@/lib/utils'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import { Upload, FileText, Loader2, AlertCircle, CheckCircle2, Trash2 } from 'lucide-vue-next'

const router = useRouter()
const documents = ref<Document[]>([])
const loading = ref(true)
const uploading = ref(false)
const uploadProgress = ref(0)
const dragOver = ref(false)
let pollHandle: number | null = null

async function load() {
  loading.value = true
  try {
    documents.value = await documentsApi.list()
  } finally {
    loading.value = false
  }
}

function startPolling() {
  if (pollHandle) return
  pollHandle = window.setInterval(async () => {
    const pending = documents.value.some((d) => d.status === 'pending' || d.status === 'processing')
    if (!pending) return
    documents.value = await documentsApi.list()
  }, 2000)
}

onMounted(async () => {
  await load()
  startPolling()
})

onBeforeUnmount(() => {
  if (pollHandle) clearInterval(pollHandle)
})

async function handleUpload(file: File) {
  uploading.value = true
  uploadProgress.value = 0
  try {
    const doc = await documentsApi.upload(file, undefined, (pct) => (uploadProgress.value = pct))
    toast.success(`Uploaded "${doc.title}" — processing started.`)
    await load()
  } catch (e: any) {
    const msg = e?.response?.data?.errors?.file?.[0] || e?.response?.data?.message || 'Upload failed.'
    toast.error(msg)
  } finally {
    uploading.value = false
    uploadProgress.value = 0
  }
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) handleUpload(file)
  input.value = ''
}

function onDrop(e: DragEvent) {
  e.preventDefault()
  dragOver.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) handleUpload(file)
}

async function destroy(doc: Document) {
  if (!confirm(`Delete "${doc.title}"? This cannot be undone.`)) return
  try {
    await documentsApi.destroy(doc.id)
    documents.value = documents.value.filter((d) => d.id !== doc.id)
    toast.success('Deleted.')
  } catch {
    toast.error('Could not delete.')
  }
}

function statusVariant(s: Document['status']) {
  return s === 'ready' ? 'success' : s === 'failed' ? 'destructive' : 'warning'
}
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Documents</h1>
        <p class="text-sm text-muted-foreground">Upload PDFs to chat with them or generate summaries.</p>
      </div>
      <label>
        <input type="file" accept="application/pdf" class="hidden" @change="onFileChange" />
        <Button :loading="uploading" as="span">
          <Upload class="h-4 w-4" />
          {{ uploading ? `Uploading… ${uploadProgress}%` : 'Upload PDF' }}
        </Button>
      </label>
    </div>

    <Card
      class="border-dashed border-2 p-8 text-center transition-colors"
      :class="{ 'bg-accent': dragOver }"
      @dragover.prevent="dragOver = true"
      @dragleave.prevent="dragOver = false"
      @drop="onDrop"
    >
      <Upload class="h-8 w-8 mx-auto mb-2 opacity-50" />
      <p class="text-sm">Drag a PDF here, or use the button above. Max 20&nbsp;MB.</p>
    </Card>

    <div v-if="loading" class="text-center py-12 text-muted-foreground"><Loader2 class="h-6 w-6 mx-auto animate-spin" /></div>
    <div v-else-if="!documents.length" class="text-center py-16 text-muted-foreground">
      <FileText class="h-12 w-12 mx-auto mb-3 opacity-30" />
      <p>No documents yet.</p>
    </div>

    <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <Card v-for="doc in documents" :key="doc.id" class="p-4 hover:shadow transition-shadow cursor-pointer" @click="router.push(`/documents/${doc.id}`)">
        <div class="flex items-start gap-3">
          <div class="rounded-md bg-muted p-2"><FileText class="h-5 w-5" /></div>
          <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
              <div class="font-medium truncate">{{ doc.title }}</div>
              <Badge :variant="statusVariant(doc.status)">
                <Loader2 v-if="doc.status === 'pending' || doc.status === 'processing'" class="h-3 w-3 mr-1 animate-spin" />
                <CheckCircle2 v-else-if="doc.status === 'ready'" class="h-3 w-3 mr-1" />
                <AlertCircle v-else class="h-3 w-3 mr-1" />
                {{ doc.status }}
              </Badge>
            </div>
            <div class="mt-1 text-xs text-muted-foreground">
              {{ formatBytes(doc.size_bytes) }} · {{ doc.page_count ?? '?' }} pages · {{ formatRelative(doc.created_at) }}
            </div>
            <p v-if="doc.error_message" class="mt-1 text-xs text-destructive">{{ doc.error_message }}</p>
            <div class="mt-3 flex justify-end">
              <Button variant="ghost" size="icon" @click.stop="destroy(doc)" title="Delete">
                <Trash2 class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>
      </Card>
    </div>
  </div>
</template>
