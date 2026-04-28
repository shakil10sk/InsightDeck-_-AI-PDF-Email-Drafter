<script setup lang="ts">
import { computed } from 'vue'
import { RouterView, RouterLink, useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import { FileText, MessageSquare, Mail, Settings, LayoutDashboard, Moon, Sun, LogOut, Sparkles, Plus } from 'lucide-vue-next'
import Button from '@/components/ui/Button.vue'
import Logo from '@/components/brand/Logo.vue'

const auth = useAuthStore()
const theme = useThemeStore()
const router = useRouter()
const route = useRoute()

const items = [
  { to: { name: 'dashboard' }, label: 'Overview', icon: LayoutDashboard },
  { to: { name: 'documents' }, label: 'Documents', icon: FileText },
  { to: { name: 'chat' }, label: 'Chat', icon: MessageSquare },
  { to: { name: 'drafts' }, label: 'Drafts', icon: Mail },
  { to: { name: 'settings' }, label: 'Settings', icon: Settings },
]

const titles: Record<string, string> = {
  dashboard: 'Overview',
  documents: 'Documents',
  'document.show': 'Document',
  chat: 'Chat',
  'chat.show': 'Conversation',
  drafts: 'Email drafts',
  'drafts.show': 'Draft',
  settings: 'Settings',
}
const pageTitle = computed(() => titles[(route.name as string) ?? ''] ?? '')

const planLabel = computed(() => {
  const t = (auth.user?.plan_tier ?? 'free') as string
  return t.charAt(0).toUpperCase() + t.slice(1)
})

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex h-full">
    <aside class="hidden md:flex w-[224px] shrink-0 flex-col border-r bg-card/50 backdrop-blur">
      <div class="px-4 pt-5 pb-3">
        <Logo with-wordmark :size="26" />
      </div>

      <div class="px-3">
        <RouterLink :to="{ name: 'documents' }" class="block">
          <button class="w-full inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium px-3 py-2 brand-gradient text-white shadow-sm hover:opacity-90 transition-opacity">
            <Plus class="h-4 w-4" /> New upload
          </button>
        </RouterLink>
      </div>

      <nav class="flex-1 p-3 space-y-0.5 mt-2">
        <div class="px-2 pb-1 text-[11px] font-medium uppercase tracking-wider text-muted-foreground/70">Workspace</div>
        <RouterLink
          v-for="(item, i) in items"
          :key="item.label"
          :to="item.to"
          class="nav-item"
          :active-class="i === 0 ? '__none__' : 'router-link-active'"
          :exact-active-class="'router-link-active'"
        >
          <component :is="item.icon" class="h-4 w-4" />
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>

      <div class="border-t p-3 space-y-2">
        <RouterLink to="/settings" class="block">
          <div class="flex items-center gap-2 rounded-md p-2 hover:bg-accent transition-colors">
            <div class="h-8 w-8 rounded-full brand-gradient grid place-items-center text-white text-xs font-semibold">
              {{ (auth.user?.name ?? '?').slice(0, 1).toUpperCase() }}
            </div>
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm font-medium">{{ auth.user?.name }}</div>
              <div class="truncate text-[11px] text-muted-foreground">{{ auth.user?.email }}</div>
            </div>
          </div>
        </RouterLink>
        <div class="flex items-center gap-1">
          <Button variant="ghost" size="icon" @click="theme.toggle" :title="`Theme: ${theme.theme}`" class="h-8 w-8">
            <Sun v-if="theme.theme === 'dark'" class="h-4 w-4" />
            <Moon v-else class="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon" @click="logout" title="Sign out" class="h-8 w-8">
            <LogOut class="h-4 w-4" />
          </Button>
          <span class="ml-auto inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
            <Sparkles class="h-3 w-3" /> {{ planLabel }}
          </span>
        </div>
      </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0">
      <header class="hidden md:flex items-center justify-between gap-4 border-b px-6 py-3 bg-background/80 backdrop-blur sticky top-0 z-10">
        <div class="flex items-center gap-2 text-sm">
          <span class="text-muted-foreground">InsightDeck</span>
          <span class="text-muted-foreground/50">/</span>
          <span class="font-medium">{{ pageTitle }}</span>
        </div>
        <div class="flex items-center gap-2 text-xs text-muted-foreground">
          <span class="hidden lg:inline">⌘K to search · ⌘\ sidebar</span>
        </div>
      </header>

      <main class="flex-1 overflow-y-auto">
        <RouterView />
      </main>
    </div>
  </div>
</template>
