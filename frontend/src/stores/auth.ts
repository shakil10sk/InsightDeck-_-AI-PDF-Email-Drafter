import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi, type User } from '@/api/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)
  const initialized = ref(false)

  const isAuthenticated = computed(() => user.value !== null)

  async function fetchMe() {
    loading.value = true
    try {
      user.value = await authApi.me()
    } catch {
      user.value = null
    } finally {
      loading.value = false
      initialized.value = true
    }
  }

  async function login(email: string, password: string, remember = false) {
    user.value = await authApi.login(email, password, remember)
  }

  async function register(payload: { name: string; email: string; password: string; password_confirmation: string }) {
    user.value = await authApi.register(payload)
  }

  async function logout() {
    try {
      await authApi.logout()
    } finally {
      user.value = null
    }
  }

  async function loginAsDemo() {
    user.value = await authApi.demo()
  }

  return { user, loading, initialized, isAuthenticated, fetchMe, login, register, logout, loginAsDemo }
})
