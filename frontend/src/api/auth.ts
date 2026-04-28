import { api } from './client'

export interface User {
  id: number
  name: string
  email: string
  plan_tier: string
  default_provider?: string
  default_model?: string
  has_byo_openai_key?: boolean
  has_byo_anthropic_key?: boolean
  email_verified_at?: string | null
  created_at?: string
}

export const authApi = {
  me: () => api.get<{ user: User }>('/api/me').then((r) => r.data.user),
  login: (email: string, password: string, remember = false) =>
    api.post<{ user: User }>('/api/auth/login', { email, password, remember }).then((r) => r.data.user),
  register: (payload: { name: string; email: string; password: string; password_confirmation: string }) =>
    api.post<{ user: User }>('/api/auth/register', payload).then((r) => r.data.user),
  logout: () => api.post('/api/auth/logout').then(() => undefined),
  demo: () => api.post<{ user: User }>('/api/demo-login').then((r) => r.data.user),
  forgot: (email: string) => api.post('/api/auth/forgot-password', { email }).then(() => undefined),
  reset: (payload: { email: string; password: string; password_confirmation: string; token: string }) =>
    api.post('/api/auth/reset-password', payload).then(() => undefined),
}
