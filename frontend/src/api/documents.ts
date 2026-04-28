import { api } from './client'

export interface Document {
  id: number
  title: string
  original_filename: string
  mime_type: string | null
  size_bytes: number
  page_count: number | null
  status: 'pending' | 'processing' | 'ready' | 'failed'
  error_message: string | null
  created_at: string
  summaries?: Summary[]
}

export interface Summary {
  id: number
  document_id: number
  length: 'short' | 'medium' | 'long'
  content: string
  model: string | null
  token_cost: number
  created_at: string
}

export const documentsApi = {
  list: () => api.get<{ data: Document[] }>('/api/documents').then((r) => r.data.data),
  get: (id: number) => api.get<{ data: Document }>(`/api/documents/${id}`).then((r) => r.data.data),
  upload: (file: File, title?: string, onProgress?: (pct: number) => void) => {
    const fd = new FormData()
    fd.append('file', file)
    if (title) fd.append('title', title)
    return api
      .post<{ data: Document }>('/api/documents', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (e) => {
          if (e.total && onProgress) onProgress(Math.round((e.loaded / e.total) * 100))
        },
      })
      .then((r) => r.data.data)
  },
  destroy: (id: number) => api.delete(`/api/documents/${id}`).then(() => undefined),
  fileUrl: (id: number) => `${api.defaults.baseURL}/api/documents/${id}/file`,
  getSummary: (id: number, length: 'short' | 'medium' | 'long' = 'medium') =>
    api
      .get<{ summary: Summary | null }>(`/api/documents/${id}/summary`, { params: { length } })
      .then((r) => r.data.summary),
}
