import axios from 'axios'

const baseURL = import.meta.env.VITE_API_URL || 'http://localhost:8000'

export const api = axios.create({
  baseURL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
  },
})

let csrfPromise: Promise<void> | null = null

export async function ensureCsrf(): Promise<void> {
  if (!csrfPromise) {
    csrfPromise = api.get('/sanctum/csrf-cookie').then(() => {
      // The cookie is now set; subsequent requests will use it via XSRF-TOKEN.
    }).catch((err) => {
      csrfPromise = null
      throw err
    })
  }
  await csrfPromise
}

api.interceptors.request.use(async (config) => {
  const method = (config.method || 'get').toLowerCase()
  if (['post', 'put', 'patch', 'delete'].includes(method)) {
    await ensureCsrf()
  }
  return config
})

export function apiUrl(path: string): string {
  return `${baseURL}${path}`
}
