# Architecture

## High-level

```
┌──────────────────────────────┐         ┌────────────────────────────┐
│  Vue 3 SPA                   │  HTTPS  │  Laravel 11 API            │
│  - Pinia · Vue Router        │ ◄─────► │  - Sanctum (cookie SPA)    │
│  - shadcn-vue · Tailwind     │   SSE   │  - Action controllers      │
└──────────────────────────────┘ ◄────── │  - BelongsToUser scope     │
                                         │  - Rate limit middleware   │
                                         └──────────┬─────────────────┘
                            ┌───────────────────────┼─────────────────────┐
                            ▼                       ▼                     ▼
                  ┌────────────────┐    ┌──────────────────┐    ┌───────────────┐
                  │ Postgres +     │    │ Queue (database) │    │ AI Providers  │
                  │ pgvector       │    │ - ProcessDocument│    │ - OpenAI      │
                  │ - documents    │    │ - Title          │    │ - Anthropic   │
                  │ - chunks (vec) │    └──────────────────┘    └───────────────┘
                  │ - usage_records│
                  └────────────────┘
```

## Multi-tenancy

Every domain model that belongs to a user uses the `BelongsToUser` trait
(`app/Models/Concerns/BelongsToUser.php`) which:

1. Adds `BelongsToUserScope` as a global Eloquent scope, automatically appending
   `WHERE user_id = auth()->id()` to **every read** when a user is authenticated.
2. Auto-fills `user_id = auth()->id()` on **every create** when the column is empty.

Internal jobs and webhooks call `withoutGlobalScopes()` when they legitimately
need to bypass tenancy.

This means a controller never has to manually check ownership — passing a
`Document $document` route-bound model that doesn't belong to the current user
returns 404 (not 403, to avoid leaking existence).

The `MultiTenancyTest` feature test asserts User A cannot view, list, or
delete User B's resources.

## RAG pipeline

A user message in the chat goes through:

1. `ChatStreamer::stream()` accepts the user message + conversation.
2. `embeddingProvider->embed([userMessage])` → 1536-dim float vector.
   - The cost is logged as a `usage_record` with `action=embed`.
3. `Retriever::topK(embedding, [docIds], k)` runs:
   ```sql
   SELECT id, document_id, page_number, content,
          1 - (embedding <=> $1::vector) AS similarity
   FROM document_chunks
   WHERE document_id = ANY($2) AND embedding IS NOT NULL
   ORDER BY embedding <=> $1::vector ASC
   LIMIT $3
   ```
4. `PromptBuilder` combines:
   - The fixed system prompt (cite-only, no fabrication, format with Markdown).
   - The retrieved chunks numbered `[1] (doc#X p.Y) ...`.
   - The last 10 turns of conversation history.
5. The chosen `AiProvider->streamChat($req)` yields `ChatChunk` instances; each
   non-empty `delta` is forwarded over SSE.
6. After the stream completes, the assistant message is persisted with:
   - The full text.
   - The citation manifest filtered to only the `[n]` numbers the model actually used.
   - Prompt + completion token counts and the resulting cost (from `TokenCostCalculator`).
7. A `usage_record` row is written.
8. If this was the first exchange, `GenerateConversationTitle` is dispatched.

If the client disconnects mid-stream, `connection_aborted()` causes the streamer
to persist whatever was generated so far with `status='cancelled'`.

## Provider abstraction

```
┌──────────────────────┐
│  AiProvider          │  ← interface
│  - streamChat        │
│  - chat              │
│  - embed             │
│  - testConnection    │
│  - usesByoKey        │
└──────────────────────┘
        ▲             ▲
        │             │
┌───────────┐  ┌────────────────────┐
│ OpenAi    │  │ Anthropic          │
│ Provider  │  │ Provider           │
└───────────┘  │  ├ embed delegates │
               │  │  to OpenAi      │
               └────────────────────┘
                        ▲
                        │ resolves per-request
                ┌───────────────┐
                │ ProviderManager│
                └───────────────┘
```

`ProviderManager::resolve($user, $name)` picks the provider:

1. If the user has a `byo_<provider>_key_encrypted` set, decrypt and use that key.
   The provider's `usesByoKey()` returns `true`, and `EnforceTokenBudget` skips
   them in budget calculations.
2. Otherwise use the platform key from `config('ai.providers.X.api_key')`.

Embeddings always go through OpenAI's `text-embedding-3-small` (1536 dims).
Anthropic doesn't ship embeddings, so `AnthropicProvider::embed()` proxies to an
OpenAI provider injected at construction time.

## Streaming over SSE

`StreamWriter::sse(Closure $producer)` returns a Symfony `StreamedResponse`
with `Content-Type: text/event-stream`, `Cache-Control: no-cache, no-transform`,
and `X-Accel-Buffering: no` (so any nginx in front doesn't buffer the stream).
It also clears all output buffers and turns off `zlib.output_compression`.

The producer is given a writer with a single `event(name, payload)` method that
serializes as:

```
event: delta
data: {"text":"Hello"}

event: delta
data: {"text":" world"}

event: done
data: {"message":{"id":42, ...}}
```

The Vue client (`composables/useStream.ts`) uses `fetch` + `getReader()` rather
than `EventSource` because `EventSource` doesn't support POST. It accumulates
the body, splits on `\n\n` event boundaries, and dispatches typed events back
to the caller.

## Rate limiting & token budget

Two independent layers stack:

1. **Per-route throttle** (Laravel `RateLimiter`):
   `chat: 10/min`, `upload: 5/min`, `summary: 6/min`, `draft: 20/min`
   — keyed on user ID. Returns 429 with `Retry-After`.

2. **Daily token budget** (`EnforceTokenBudget` middleware):
   Sums `total_tokens` across `usage_records` for today (excluding BYO-key calls)
   and 429s when the user's plan cap is hit.
   Free tier: 50K tokens/day. Pro: 500K. Unlimited for BYO key.

Both are skipped when `usesByoKey()` is true on the active provider.

## BYO API keys

Stored encrypted at rest with `Illuminate\Support\Facades\Crypt`
(uses `APP_KEY` for the cipher), in `users.byo_openai_key_encrypted` and
`users.byo_anthropic_key_encrypted`. The plaintext value is never returned by
any API endpoint; the User model exposes only `has_byo_openai_key`,
`has_byo_anthropic_key` booleans.

The `POST /api/settings/test-connection` endpoint pings the provider with the
saved key (or the platform key) and returns `{ ok: true|false }` so the UI can
show a clear pass/fail to the user without exposing the key itself.

## Document processing

`POST /api/documents` accepts a multipart PDF up to 20 MB, persists it, and
dispatches `ProcessDocument` to the queue. The job:

1. Parses with `smalot/pdfparser` → `[page_number => text]`.
2. Chunks via `Chunker`: ~500 tokens per chunk with 50-token overlap, splitting
   on sentence boundaries (`(?<=[.!?])\s+(?=[A-Z0-9])`) so chunks don't slice
   through clauses.
3. Embeds each chunk in batches of 100 via OpenAI.
4. Inserts chunks into `document_chunks`. On Postgres the `embedding` column is
   `vector(1536)` and is updated with a literal `[v1,v2,...]::vector` cast. On
   non-Postgres drivers (e.g. SQLite during tests) the embedding is stored as
   JSON and `Retriever` falls back to in-PHP cosine similarity.
5. Logs a single `usage_record` for the embed call.
6. Marks the document `ready` (or `failed` with a friendly message on errors
   like encrypted PDFs, scanned-only PDFs, or upstream embedding failures).

The frontend polls `GET /api/documents/{id}` every 2 seconds while the status
is `pending` or `processing`.
