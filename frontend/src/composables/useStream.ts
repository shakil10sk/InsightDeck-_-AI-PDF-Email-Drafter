import { ref, onBeforeUnmount } from 'vue'
import { apiUrl, ensureCsrf } from '@/api/client'

export interface StreamEvent {
  event: string
  data: unknown
}

export interface UseStreamOptions {
  onEvent?: (e: StreamEvent) => void
  onText?: (delta: string) => void
  onError?: (err: unknown) => void
}

export function useStream(opts: UseStreamOptions = {}) {
  const text = ref('')
  const isStreaming = ref(false)
  const error = ref<string | null>(null)
  let controller: AbortController | null = null

  async function start(path: string, body: unknown = {}) {
    text.value = ''
    error.value = null
    isStreaming.value = true
    controller = new AbortController()

    try {
      await ensureCsrf()
      const csrf = decodeURIComponent(
        document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? ''
      )
      const response = await fetch(apiUrl(path), {
        method: 'POST',
        credentials: 'include',
        signal: controller.signal,
        headers: {
          'Content-Type': 'application/json',
          Accept: 'text/event-stream',
          'X-XSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
      })
      if (!response.ok) {
        const t = await response.text().catch(() => '')
        throw new Error(`HTTP ${response.status} ${t}`)
      }
      if (!response.body) throw new Error('No response body')

      const reader = response.body.getReader()
      const decoder = new TextDecoder()
      let buffer = ''

      while (true) {
        const { value, done } = await reader.read()
        if (done) break
        buffer += decoder.decode(value, { stream: true })

        let sep
        while ((sep = buffer.indexOf('\n\n')) !== -1) {
          const block = buffer.slice(0, sep)
          buffer = buffer.slice(sep + 2)
          const evt = parseEventBlock(block)
          if (!evt) continue

          if (evt.event === 'delta' && evt.data && typeof (evt.data as any).text === 'string') {
            const delta: string = (evt.data as any).text
            text.value += delta
            opts.onText?.(delta)
          }
          opts.onEvent?.(evt)
        }
      }
    } catch (e: unknown) {
      if ((e as Error)?.name === 'AbortError') return
      error.value = e instanceof Error ? e.message : String(e)
      opts.onError?.(e)
    } finally {
      isStreaming.value = false
      controller = null
    }
  }

  function stop() {
    controller?.abort()
  }

  onBeforeUnmount(() => stop())

  return { text, isStreaming, error, start, stop }
}

function parseEventBlock(block: string): StreamEvent | null {
  const lines = block.split('\n')
  let eventName = 'message'
  let dataStr = ''
  for (const line of lines) {
    if (line.startsWith('event:')) eventName = line.slice(6).trim()
    else if (line.startsWith('data:')) dataStr += line.slice(5).trim()
  }
  if (!dataStr) return null
  let parsed: unknown = dataStr
  try {
    parsed = JSON.parse(dataStr)
  } catch {
    /* keep raw */
  }
  return { event: eventName, data: parsed }
}
