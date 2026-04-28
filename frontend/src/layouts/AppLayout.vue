<script setup lang="ts">
import { RouterView, RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import { FileText, MessageSquare, Mail, Settings, LayoutDashboard, Moon, Sun, LogOut } from 'lucide-vue-next'
import Button from '@/components/ui/Button.vue'

const auth = useAuthStore()
const theme = useThemeStore()
const router = useRouter()

const items = [
  { to: { name: 'dashboard' }, label: 'Dashboard', icon: LayoutDashboard },
  { to: { name: 'documents' }, label: 'Documents', icon: FileText },
  { to: { name: 'chat' }, label: 'Chat', icon: MessageSquare },
  { to: { name: 'drafts' }, label: 'Drafts', icon: Mail },
  { to: { name: 'settings' }, label: 'Settings', icon: Settings },
]

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex h-full">
    <aside class="hidden md:flex w-60 shrink-0 flex-col border-r bg-card">
      <div class="flex items-center gap-2 px-4 py-4 border-b">
        <div class="h-8 w-8 rounded-md bg-primary text-primary-foreground grid place-items-center text-sm font-bold">ID</div>
        <span class="font-semibold tracking-tight">InsightDeck</span>
      </div>
      <nav class="flex-1 p-2 space-y-1">
        <RouterLink
          v-for="item in items"
          :key="item.label"
          :to="item.to"
          class="flex items-center gap-2 rounded-md px-3 py-2 text-sm hover:bg-accent transition-colors"
          active-class="bg-accent text-accent-foreground"
        >
          <component :is="item.icon" class="h-4 w-4" />
          {{ item.label }}
        </RouterLink>
      </nav>
      <div class="border-t p-3 space-y-2">
        <div class="text-xs text-muted-foreground px-1 truncate">{{ auth.user?.email }}</div>
        <div class="flex gap-2">
          <Button variant="ghost" size="icon" @click="theme.toggle" :title="theme.theme === 'dark' ? 'Light mode' : 'Dark mode'">
            <Sun v-if="theme.theme === 'dark'" class="h-4 w-4" />
            <Moon v-else class="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon" @click="logout" title="Log out">
            <LogOut class="h-4 w-4" />
          </Button>
        </div>
      </div>
    </aside>
    <main class="flex-1 overflow-y-auto">
      <RouterView />
    </main>
  </div>
</template>
