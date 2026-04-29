import axios from 'axios'

// In production we ship under the same origin as the API (Laravel serves the
// built SPA from public/spa/). In dev VITE_API_URL points at php artisan serve.
const baseURL = import.meta.env.VITE_API_URL ?? ''

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
