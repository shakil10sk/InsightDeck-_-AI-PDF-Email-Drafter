import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export type Theme = 'light' | 'dark' | 'system'

const STORAGE_KEY = 'insightdeck-theme'

function applyTheme(theme: Theme) {
  const isDark =
    theme === 'dark' ||
    (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)
  document.documentElement.classList.toggle('dark', isDark)
}

export const useThemeStore = defineStore('theme', () => {
  const theme = ref<Theme>((localStorage.getItem(STORAGE_KEY) as Theme) || 'system')

  applyTheme(theme.value)

  watch(theme, (next) => {
    localStorage.setItem(STORAGE_KEY, next)
    applyTheme(next)
  })

  if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
      if (theme.value === 'system') applyTheme('system')
    })
  }

  function toggle() {
    theme.value = theme.value === 'dark' ? 'light' : 'dark'
  }

  return { theme, toggle }
})
