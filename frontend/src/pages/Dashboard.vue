<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { usageApi, type UsageToday, type UsageBreakdownRow } from '@/api/usage'
import { documentsApi, type Document } from '@/api/documents'
import { conversationsApi, type Conversation } from '@/api/conversations'
import { useAuthStore } from '@/stores/auth'
import { formatRelative } from '@/lib/utils'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import { FileText, MessageSquare, ArrowUpRight, Upload, Plus, Mail, Zap } from 'lucide-vue-next'

const auth = useAuthStore()
const today = ref<UsageToday | null>(null)
const breakdown = ref<UsageBreakdownRow[]>([])
const documents = ref<Document[]>([])
const conversations = ref<Conversation[]>([])
const loading = ref(true)

onMounted(async () => {
  loading.value = true
  try {
    const [t, b, d, c] = await Promise.all([
      usageApi.today(), usageApi.breakdown(), documentsApi.list(), conversationsApi.list(),
    ])
    today.value = t; breakdown.value = b; documents.value = d; conversations.value = c
  } finally {
    loading.value = false
  }
})

const usagePct = computed(() => Math.min(100, today.value?.percentage ?? 0))
const usageStrokeOffset = computed(() => {
  // Circumference for r=42 → 2πr ≈ 263.9
  const c = 263.9
  return c - (c * usagePct.value) / 100
})

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 5) return 'Good night'
  if (h < 12) return 'Good morning'
  if (h < 18) return 'Good afternoon'
  return 'Good evening'
})
</script>

<template>
  <div class="p-6 lg:p-8 space-y-6">
    <!-- Header -->
    <div class="flex items-end justify-between gap-4 flex-wrap">
      <div>
        <p class="text-sm text-muted-foreground">{{ greeting }},</p>
        <h1 class="text-3xl font-semibold tracking-tight">{{ auth.user?.name?.split(' ')[0] ?? 'there' }}.</h1>
        <p class="mt-1 text-sm text-muted-foreground">Here's a snapshot of your workspace.</p>
      </div>
      <div class="flex gap-2">
        <RouterLink to="/documents"><Button variant="outline" size="sm"><Upload class="h-4 w-4" /> Upload PDF</Button></RouterLink>
        <RouterLink to="/chat"><Button size="sm"><Plus class="h-4 w-4" /> New chat</Button></RouterLink>
      </div>
    </div>

    <!-- Stats row + token gauge -->
    <div class="grid gap-4 lg:grid-cols-[1fr_1fr_1fr_280px]">
      <Card class="card-elevated p-4 hover:ring-soft transition-shadow">
        <div class="text-xs text-muted-foreground uppercase tracking-wider">Documents</div>
        <div class="mt-1 flex items-end gap-2">
          <span class="text-3xl font-semibold tabular-nums">{{ documents.length }}</span>
          <FileText class="h-5 w-5 text-muted-foreground/60 mb-1.5" />
        </div>
        <p class="mt-1 text-xs text-muted-foreground">{{ documents.filter(d => d.status === 'ready').length }} ready · {{ documents.filter(d => d.status === 'processing' || d.status === 'pending').length }} processing</p>
      </Card>

      <Card class="card-elevated p-4 hover:ring-soft transition-shadow">
        <div class="text-xs text-muted-foreground uppercase tracking-wider">Conversations</div>
        <div class="mt-1 flex items-end gap-2">
          <span class="text-3xl font-semibold tabular-nums">{{ conversations.length }}</span>
          <MessageSquare class="h-5 w-5 text-muted-foreground/60 mb-1.5" />
        </div>
        <p class="mt-1 text-xs text-muted-foreground">across {{ documents.length }} document{{ documents.length === 1 ? '' : 's' }}</p>
      </Card>

      <Card class="card-elevated p-4 hover:ring-soft transition-shadow">
        <div class="text-xs text-muted-foreground uppercase tracking-wider">Cost today</div>
        <div class="mt-1 flex items-end gap-2">
          <span class="text-3xl font-semibold tabular-nums">${{ Number(today?.cost_usd ?? 0).toFixed(4) }}</span>
          <Zap class="h-5 w-5 text-muted-foreground/60 mb-1.5" />
        </div>
        <p class="mt-1 text-xs text-muted-foreground">across all providers</p>
      </Card>

      <!-- Radial token gauge -->
      <Card class="card-elevated p-4 flex items-center gap-4">
        <div class="relative h-24 w-24 shrink-0">
          <svg viewBox="0 0 100 100" class="h-24 w-24 -rotate-90">
            <circle cx="50" cy="50" r="42" fill="none" stroke="hsl(var(--muted))" stroke-width="8" />
            <circle
              cx="50" cy="50" r="42" fill="none"
              stroke="url(#token-gauge-gradient)" stroke-width="8" stroke-linecap="round"
              :stroke-dasharray="263.9" :stroke-dashoffset="usageStrokeOffset"
              class="transition-all duration-700"
            />
            <defs>
              <linearGradient id="token-gauge-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="hsl(var(--gradient-start))" />
                <stop offset="100%" stop-color="hsl(var(--gradient-end))" />
              </linearGradient>
            </defs>
          </svg>
          <div class="absolute inset-0 grid place-items-center">
            <div class="text-center leading-tight">
              <div class="text-base font-semibold tabular-nums">{{ usagePct.toFixed(0) }}%</div>
              <div class="text-[10px] text-muted-foreground">used</div>
            </div>
          </div>
        </div>
        <div class="min-w-0">
          <div class="text-xs text-muted-foreground uppercase tracking-wider">Tokens today</div>
          <div class="mt-0.5 text-sm font-medium tabular-nums">
            {{ (today?.used ?? 0).toLocaleString() }}<span v-if="today?.cap" class="text-muted-foreground">/{{ today.cap.toLocaleString() }}</span>
          </div>
          <div class="mt-1 text-[11px] text-muted-foreground">resets at midnight UTC</div>
        </div>
      </Card>
    </div>

    <!-- Recent activity -->
    <div class="grid gap-4 lg:grid-cols-2">
      <Card class="card-elevated p-5">
        <div class="mb-3 flex items-center justify-between">
          <div>
            <h2 class="font-semibold">Recent documents</h2>
            <p class="text-xs text-muted-foreground">Your latest uploads.</p>
          </div>
          <RouterLink to="/documents"><Button variant="ghost" size="sm">All <ArrowUpRight class="h-3.5 w-3.5" /></Button></RouterLink>
        </div>
        <div v-if="!documents.length" class="text-center py-10">
          <div class="mx-auto h-12 w-12 grid place-items-center rounded-full bg-primary/10 text-primary">
            <Upload class="h-5 w-5" />
          </div>
          <p class="mt-3 text-sm font-medium">No documents yet</p>
          <p class="mt-1 text-xs text-muted-foreground">Upload a PDF to start chatting and summarizing.</p>
          <RouterLink to="/documents"><Button class="mt-3" size="sm">Upload your first PDF</Button></RouterLink>
        </div>
        <ul v-else class="divide-y -mx-2">
          <li v-for="doc in documents.slice(0, 5)" :key="doc.id">
            <RouterLink :to="`/documents/${doc.id}`" class="flex items-center justify-between gap-3 rounded-md px-3 py-2.5 hover:bg-accent transition-colors">
              <div class="min-w-0 flex-1 flex items-center gap-3">
                <FileText class="h-4 w-4 text-muted-foreground shrink-0" />
                <div class="min-w-0">
                  <div class="truncate text-sm font-medium">{{ doc.title }}</div>
                  <div class="text-xs text-muted-foreground">{{ doc.page_count ?? '?' }} {{ doc.page_count === 1 ? 'page' : 'pages' }} · {{ formatRelative(doc.created_at) }}</div>
                </div>
              </div>
              <Badge :variant="doc.status === 'ready' ? 'success' : doc.status === 'failed' ? 'destructive' : 'warning'">
                {{ doc.status }}
              </Badge>
            </RouterLink>
          </li>
        </ul>
      </Card>

      <Card class="card-elevated p-5">
        <div class="mb-3 flex items-center justify-between">
          <div>
            <h2 class="font-semibold">Recent conversations</h2>
            <p class="text-xs text-muted-foreground">Pick up where you left off.</p>
          </div>
          <RouterLink to="/chat"><Button variant="ghost" size="sm">All <ArrowUpRight class="h-3.5 w-3.5" /></Button></RouterLink>
        </div>
        <div v-if="!conversations.length" class="text-center py-10">
          <div class="mx-auto h-12 w-12 grid place-items-center rounded-full bg-primary/10 text-primary">
            <MessageSquare class="h-5 w-5" />
          </div>
          <p class="mt-3 text-sm font-medium">No chats yet</p>
          <p class="mt-1 text-xs text-muted-foreground">Pick a document and ask it a question.</p>
          <RouterLink to="/chat"><Button class="mt-3" size="sm">Start a chat</Button></RouterLink>
        </div>
        <ul v-else class="divide-y -mx-2">
          <li v-for="c in conversations.slice(0, 5)" :key="c.id">
            <RouterLink :to="`/chat/${c.id}`" class="flex items-center justify-between gap-3 rounded-md px-3 py-2.5 hover:bg-accent transition-colors">
              <div class="min-w-0 flex-1 flex items-center gap-3">
                <MessageSquare class="h-4 w-4 text-muted-foreground shrink-0" />
                <div class="min-w-0">
                  <div class="truncate text-sm font-medium">{{ c.title }}</div>
                  <div class="text-xs text-muted-foreground">{{ c.message_count ?? 0 }} message{{ c.message_count === 1 ? '' : 's' }} · {{ formatRelative(c.updated_at) }}</div>
                </div>
              </div>
              <span class="text-[10px] uppercase tracking-wider text-muted-foreground">{{ c.provider }}</span>
            </RouterLink>
          </li>
        </ul>
      </Card>
    </div>

    <Card v-if="breakdown.length" class="card-elevated p-5">
      <div class="mb-3 flex items-center justify-between">
        <div>
          <h2 class="font-semibold">Cost breakdown</h2>
          <p class="text-xs text-muted-foreground">Last 30 days, grouped by model.</p>
        </div>
      </div>
      <div class="overflow-x-auto -mx-2">
        <table class="w-full text-sm">
          <thead class="text-left text-muted-foreground">
            <tr>
              <th class="px-2 py-1 font-normal">Provider</th>
              <th class="px-2 py-1 font-normal">Model</th>
              <th class="px-2 py-1 font-normal text-right">Calls</th>
              <th class="px-2 py-1 font-normal text-right">Tokens</th>
              <th class="px-2 py-1 font-normal text-right">Cost</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in breakdown" :key="`${row.provider}-${row.model}`" class="border-t">
              <td class="px-2 py-2.5">{{ row.provider }}</td>
              <td class="px-2 py-2.5 font-mono text-xs">{{ row.model }}</td>
              <td class="px-2 py-2.5 text-right tabular-nums">{{ row.calls }}</td>
              <td class="px-2 py-2.5 text-right tabular-nums">{{ row.total_tokens.toLocaleString() }}</td>
              <td class="px-2 py-2.5 text-right tabular-nums">${{ Number(row.cost_usd).toFixed(4) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>
  </div>
</template>
