import { api } from './client'
import type { Document } from './documents'

export type MessageRole = 'system' | 'user' | 'assistant'

export interface Citation {
  n: number
  chunk_id: number
  document_id: number
  page: number | null
  snippet: string
}

export interface Message {
  id: number
  conversation_id: number
  role: MessageRole
  content: string
  citations: Citation[]
  prompt_tokens: number
  completion_tokens: number
  cost_usd: number
  model: string | null
  status: 'streaming' | 'complete' | 'cancelled' | 'failed'
  created_at: string
}

export interface Conversation {
  id: number
  title: string
  provider: string
  model: string
  system_prompt: string | null
  pinned_at: string | null
  created_at: string
  updated_at: string
  documents?: Document[]
  messages?: Message[]
  message_count?: number
}

export const conversationsApi = {
  list: () => api.get<{ data: Conversation[] }>('/api/conversations').then((r) => r.data.data),
  get: (id: number) => api.get<{ data: Conversation }>(`/api/conversations/${id}`).then((r) => r.data.data),
  create: (payload: { title?: string; provider?: string; model?: string; document_ids?: number[]; system_prompt?: string }) =>
    api.post<{ data: Conversation }>('/api/conversations', payload).then((r) => r.data.data),
  update: (id: number, payload: Partial<{ title: string; pinned: boolean; provider: string; model: string; document_ids: number[] }>) =>
    api.patch<{ data: Conversation }>(`/api/conversations/${id}`, payload).then((r) => r.data.data),
  destroy: (id: number) => api.delete(`/api/conversations/${id}`).then(() => undefined),
}
