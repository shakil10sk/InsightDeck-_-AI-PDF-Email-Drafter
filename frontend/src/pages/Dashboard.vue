<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { usageApi, type UsageToday, type UsageBreakdownRow } from '@/api/usage'
import { documentsApi, type Document } from '@/api/documents'
import { conversationsApi, type Conversation } from '@/api/conversations'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import { FileText, MessageSquare, Coins, Upload } from 'lucide-vue-next'

const today = ref<UsageToday | null>(null)
const breakdown = ref<UsageBreakdownRow[]>([])
const documents = ref<Document[]>([])
const conversations = ref<Conversation[]>([])
const loading = ref(true)

onMounted(async () => {
  loading.value = true
  try {
    const [t, b, d, c] = await Promise.all([
      usageApi.today(),
      usageApi.breakdown(),
      documentsApi.list(),
      conversationsApi.list(),
    ])
    today.value = t
    breakdown.value = b
    documents.value = d
    conversations.value = c
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="p-6 space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
      <p class="text-sm text-muted-foreground">Overview of your documents, chats, and AI usage.</p>
    </div>

    <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
      <Card class="p-4">
        <div class="flex items-center gap-3">
          <div class="rounded-md bg-primary/10 p-2"><FileText class="h-5 w-5" /></div>
          <div>
            <div class="text-xs text-muted-foreground">Documents</div>
            <div class="text-2xl font-semibold">{{ documents.length }}</div>
          </div>
        </div>
      </Card>
      <Card class="p-4">
        <div class="flex items-center gap-3">
          <div class="rounded-md bg-primary/10 p-2"><MessageSquare class="h-5 w-5" /></div>
          <div>
            <div class="text-xs text-muted-foreground">Conversations</div>
            <div class="text-2xl font-semibold">{{ conversations.length }}</div>
          </div>
        </div>
      </Card>
      <Card class="p-4">
        <div class="flex items-center gap-3">
          <div class="rounded-md bg-primary/10 p-2"><Coins class="h-5 w-5" /></div>
          <div>
            <div class="text-xs text-muted-foreground">Tokens used today</div>
            <div class="text-2xl font-semibold">
              {{ today?.used?.toLocaleString() ?? '—' }}
              <span v-if="today?.cap" class="text-sm font-normal text-muted-foreground">/ {{ today.cap.toLocaleString() }}</span>
            </div>
          </div>
        </div>
      </Card>
      <Card class="p-4">
        <div class="flex items-center gap-3">
          <div class="rounded-md bg-primary/10 p-2"><Coins class="h-5 w-5" /></div>
          <div>
            <div class="text-xs text-muted-foreground">Cost today</div>
            <div class="text-2xl font-semibold">${{ (today?.cost_usd ?? 0).toFixed(4) }}</div>
          </div>
        </div>
      </Card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <Card class="p-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="font-semibold">Recent documents</h2>
          <RouterLink to="/documents">
            <Button variant="ghost" size="sm">View all</Button>
          </RouterLink>
        </div>
        <div v-if="!documents.length" class="text-center py-8 text-muted-foreground">
          <Upload class="h-8 w-8 mx-auto mb-2 opacity-50" />
          <p class="text-sm">No documents yet.</p>
          <RouterLink to="/documents"><Button class="mt-2" size="sm">Upload your first PDF</Button></RouterLink>
        </div>
        <ul v-else class="space-y-1">
          <li v-for="doc in documents.slice(0, 5)" :key="doc.id">
            <RouterLink :to="`/documents/${doc.id}`" class="flex items-center justify-between rounded-md px-2 py-2 hover:bg-accent">
              <span class="truncate">{{ doc.title }}</span>
              <span class="text-xs text-muted-foreground capitalize">{{ doc.status }}</span>
            </RouterLink>
          </li>
        </ul>
      </Card>

      <Card class="p-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="font-semibold">Recent conversations</h2>
          <RouterLink to="/chat">
            <Button variant="ghost" size="sm">View all</Button>
          </RouterLink>
        </div>
        <div v-if="!conversations.length" class="text-center py-8 text-muted-foreground">
          <MessageSquare class="h-8 w-8 mx-auto mb-2 opacity-50" />
          <p class="text-sm">No chats yet.</p>
          <RouterLink to="/chat"><Button class="mt-2" size="sm">Start a chat</Button></RouterLink>
        </div>
        <ul v-else class="space-y-1">
          <li v-for="c in conversations.slice(0, 5)" :key="c.id">
            <RouterLink :to="`/chat/${c.id}`" class="flex items-center justify-between rounded-md px-2 py-2 hover:bg-accent">
              <span class="truncate">{{ c.title }}</span>
              <span class="text-xs text-muted-foreground">{{ c.message_count ?? 0 }} msgs</span>
            </RouterLink>
          </li>
        </ul>
      </Card>
    </div>

    <Card v-if="breakdown.length" class="p-4">
      <h2 class="mb-3 font-semibold">Cost breakdown (last 30 days)</h2>
      <table class="w-full text-sm">
        <thead class="text-left text-muted-foreground">
          <tr>
            <th class="py-1">Provider</th>
            <th>Model</th>
            <th class="text-right">Calls</th>
            <th class="text-right">Tokens</th>
            <th class="text-right">Cost</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in breakdown" :key="`${row.provider}-${row.model}`" class="border-t">
            <td class="py-2">{{ row.provider }}</td>
            <td>{{ row.model }}</td>
            <td class="text-right">{{ row.calls }}</td>
            <td class="text-right">{{ row.total_tokens.toLocaleString() }}</td>
            <td class="text-right">${{ Number(row.cost_usd).toFixed(4) }}</td>
          </tr>
        </tbody>
      </table>
    </Card>
  </div>
</template>
