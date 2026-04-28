<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { draftsApi, type Draft, type Tone, type Length } from '@/api/drafts'
import { useStream } from '@/composables/useStream'
import { formatRelative } from '@/lib/utils'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Label from '@/components/ui/Label.vue'
import Badge from '@/components/ui/Badge.vue'
import { ArrowLeft, Wand2, Copy, Trash2 } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{ id: string }>()
const router = useRouter()
const draft = ref<Draft | null>(null)
const instruction = ref('')
const tone = ref<Tone>('friendly')
const length = ref<Length>('medium')
const liveOutput = ref('')

const stream = useStream({
  onEvent(e) {
    if (e.event === 'delta') liveOutput.value += String((e.data as any)?.text ?? '')
    if (e.event === 'start') {
      const id = (e.data as any)?.id
      if (id) router.replace(`/drafts/${id}`)
    }
    if (e.event === 'done') load()
    if (e.event === 'error') toast.error(String((e.data as any)?.message ?? 'Stream error'))
  },
})

async function load() {
  draft.value = await draftsApi.get(Number(props.id))
  liveOutput.value = ''
  if (draft.value) {
    tone.value = draft.value.tone
    length.value = draft.value.length
  }
}

onMounted(load)
watch(() => props.id, load)

async function iterate() {
  if (!instruction.value.trim() || !draft.value) return
  liveOutput.value = ''
  await stream.start(`/api/drafts/${draft.value.id}/iterate`, {
    instruction: instruction.value,
    tone: tone.value,
    length: length.value,
  })
  instruction.value = ''
}

async function copy() {
  if (!draft.value) return
  await navigator.clipboard.writeText(draft.value.output)
  toast.success('Copied to clipboard.')
}

async function destroy() {
  if (!draft.value || !confirm('Delete this draft?')) return
  await draftsApi.destroy(draft.value.id)
  router.push('/drafts')
}
</script>

<template>
  <div class="p-6 space-y-4 max-w-3xl">
    <Button variant="ghost" size="sm" @click="router.push('/drafts')"><ArrowLeft class="h-4 w-4" /> Back to drafts</Button>

    <div v-if="!draft" class="text-muted-foreground">Loading…</div>
    <div v-else class="space-y-4">
      <Card class="p-4">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              <Badge variant="outline">{{ draft.tone }}</Badge>
              <Badge variant="outline">{{ draft.length }}</Badge>
              <Badge variant="outline">{{ draft.provider }}/{{ draft.model }}</Badge>
              <span class="text-xs text-muted-foreground">{{ formatRelative(draft.created_at) }}</span>
            </div>
            <p class="mt-2 text-sm font-medium">{{ draft.goal }}</p>
            <p v-if="draft.recipient" class="text-xs text-muted-foreground">To: {{ draft.recipient }}</p>
          </div>
          <div class="flex gap-1">
            <Button variant="ghost" size="icon" @click="copy" title="Copy"><Copy class="h-4 w-4" /></Button>
            <Button variant="ghost" size="icon" @click="destroy" title="Delete"><Trash2 class="h-4 w-4" /></Button>
          </div>
        </div>
        <div class="mt-4 whitespace-pre-wrap rounded-md border bg-muted/30 p-3 text-sm">{{ liveOutput || draft.output }}</div>
      </Card>

      <Card class="p-4">
        <h2 class="font-semibold mb-3 flex items-center gap-2"><Wand2 class="h-4 w-4" /> Iterate</h2>
        <div class="space-y-2">
          <Label>What should change?</Label>
          <Input v-model="instruction" placeholder="Make it shorter and warmer." />
          <div class="grid grid-cols-2 gap-2">
            <Select v-model="tone">
              <option value="friendly">Friendly</option>
              <option value="formal">Formal</option>
              <option value="direct">Direct</option>
              <option value="empathetic">Empathetic</option>
            </Select>
            <Select v-model="length">
              <option value="short">Short</option>
              <option value="medium">Medium</option>
              <option value="long">Long</option>
            </Select>
          </div>
          <Button @click="iterate" :loading="stream.isStreaming.value" :disabled="!instruction.trim()">Generate revision</Button>
        </div>
      </Card>
    </div>
  </div>
</template>
