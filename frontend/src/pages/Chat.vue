<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { conversationsApi, type Conversation } from '@/api/conversations'
import { documentsApi, type Document } from '@/api/documents'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import Select from '@/components/ui/Select.vue'
import Label from '@/components/ui/Label.vue'
import { MessageSquare, Plus } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const router = useRouter()
const conversations = ref<Conversation[]>([])
const documents = ref<Document[]>([])
const selectedDocs = ref<number[]>([])
const provider = ref<'openai' | 'anthropic'>('openai')
const model = ref('gpt-4o-mini')
const loading = ref(true)

onMounted(async () => {
  loading.value = true
  try {
    const [c, d] = await Promise.all([conversationsApi.list(), documentsApi.list()])
    conversations.value = c
    documents.value = d.filter((doc) => doc.status === 'ready')
  } finally {
    loading.value = false
  }
})

function toggleDoc(id: number) {
  const i = selectedDocs.value.indexOf(id)
  if (i === -1) selectedDocs.value.push(id)
  else selectedDocs.value.splice(i, 1)
}

const grouped = computed(() => {
  const today: Conversation[] = []
  const week: Conversation[] = []
  const older: Conversation[] = []
  const now = Date.now()
  for (const c of conversations.value) {
    const age = now - new Date(c.updated_at).getTime()
    if (age < 24 * 3600_000) today.push(c)
    else if (age < 7 * 24 * 3600_000) week.push(c)
    else older.push(c)
  }
  return { today, week, older }
})

async function startChat() {
  try {
    const c = await conversationsApi.create({
      document_ids: selectedDocs.value,
      provider: provider.value,
      model: model.value,
    })
    router.push(`/chat/${c.id}`)
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Could not start chat.')
  }
}
</script>

<template>
  <div class="p-6 max-w-5xl">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Chat</h1>
        <p class="text-sm text-muted-foreground">Ask questions across your documents.</p>
      </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
      <Card class="p-4">
        <h2 class="font-semibold mb-3 flex items-center gap-2"><Plus class="h-4 w-4" /> New conversation</h2>

        <div class="space-y-3">
          <div>
            <Label>Documents</Label>
            <div v-if="!documents.length" class="mt-1 text-sm text-muted-foreground">
              No ready documents yet.
              <RouterLink to="/documents" class="underline">Upload one first.</RouterLink>
            </div>
            <div v-else class="mt-1 space-y-1 max-h-48 overflow-y-auto pr-1">
              <label v-for="d in documents" :key="d.id" class="flex items-center gap-2 rounded-md p-2 text-sm hover:bg-accent cursor-pointer">
                <input type="checkbox" :checked="selectedDocs.includes(d.id)" @change="toggleDoc(d.id)" />
                <span class="truncate">{{ d.title }}</span>
              </label>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <Label>Provider</Label>
              <Select v-model="provider" class="mt-1 w-full">
                <option value="openai">OpenAI</option>
                <option value="anthropic">Anthropic</option>
              </Select>
            </div>
            <div>
              <Label>Model</Label>
              <Select v-model="model" class="mt-1 w-full">
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

          <Button class="w-full" @click="startChat" :disabled="!documents.length">Start chat</Button>
        </div>
      </Card>

      <Card class="p-4">
        <h2 class="font-semibold mb-3">Recent</h2>
        <div v-if="loading" class="text-sm text-muted-foreground">Loading…</div>
        <div v-else-if="!conversations.length" class="text-center py-12 text-muted-foreground">
          <MessageSquare class="h-8 w-8 mx-auto mb-2 opacity-50" />
          <p class="text-sm">No conversations yet.</p>
        </div>
        <div v-else class="space-y-4">
          <template v-for="(group, label) in grouped" :key="label">
            <div v-if="group.length">
              <div class="mb-1 text-xs font-medium text-muted-foreground capitalize">{{ label }}</div>
              <div class="space-y-1">
                <RouterLink
                  v-for="c in group"
                  :key="c.id"
                  :to="`/chat/${c.id}`"
                  class="flex items-center justify-between rounded-md px-2 py-2 text-sm hover:bg-accent"
                >
                  <span class="truncate">{{ c.title }}</span>
                  <span class="text-xs text-muted-foreground">{{ c.message_count ?? 0 }}</span>
                </RouterLink>
              </div>
            </div>
          </template>
        </div>
      </Card>
    </div>
  </div>
</template>
