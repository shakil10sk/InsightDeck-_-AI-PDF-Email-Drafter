import { api } from './client'

export type Tone = 'friendly' | 'formal' | 'direct' | 'empathetic'
export type Length = 'short' | 'medium' | 'long'

export interface Draft {
  id: number
  parent_draft_id: number | null
  goal: string
  recipient: string | null
  tone: Tone
  length: Length
  context: string | null
  output: string
  provider: string
  model: string
  prompt_tokens: number
  completion_tokens: number
  cost_usd: number
  created_at: string
}

export const draftsApi = {
  list: () => api.get<{ data: Draft[] }>('/api/drafts').then((r) => r.data.data),
  get: (id: number) => api.get<{ data: Draft }>(`/api/drafts/${id}`).then((r) => r.data.data),
  destroy: (id: number) => api.delete(`/api/drafts/${id}`).then(() => undefined),
}
