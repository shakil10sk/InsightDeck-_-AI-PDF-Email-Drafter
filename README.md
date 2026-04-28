# InsightDeck — Chat with your PDFs (and draft emails)

> A Laravel 11 + Vue 3 SPA that lets you upload PDFs, generate AI summaries, chat with them via streaming RAG, and draft emails — with provider switching between OpenAI and Anthropic, per-user token budgets, and bring-your-own-key support.

**Stack**: Laravel 11 · Vue 3 + TypeScript · Pinia · Vue Router · Tailwind + shadcn-vue · PostgreSQL + pgvector · Sanctum (SPA cookies) · OpenAI + Anthropic APIs · Server-Sent Events streaming.

---

## Features

| Area | Status |
|---|---|
| Email + password auth (Sanctum SPA cookies) | ✅ |
| Multi-tenancy via `BelongsToUser` global scope | ✅ |
| PDF upload + parse + chunk + embed pipeline (queued) | ✅ |
| Vector search with pgvector (cosine similarity) | ✅ |
| Streaming chat with citations to page numbers | ✅ |
| Provider abstraction — OpenAI + Anthropic, runtime swap | ✅ |
| Bring your own API key (encrypted with Laravel Crypt) | ✅ |
| Map-reduce summarization (short / medium / long) | ✅ |
| Email Drafting Studio (tone, length, recipient, iterate) | ✅ |
| Per-user daily token budget + per-route rate limits | ✅ |
| Usage dashboard (today, 14-day series, cost breakdown) | ✅ |
| Conversation auto-titling | ✅ |
| Dark mode | ✅ |

---

## Architecture

```
Vue 3 SPA  ──HTTPS──>  Laravel 11 API  ──>  PostgreSQL + pgvector
   │                       │  │  │
   │                       │  │  └──>  Queue worker (ProcessDocument, Title)
   │                       │  └─────>  AI provider strategy (OpenAI · Anthropic)
   └────────SSE────────────┘            both stream chat completions
```

Two senior-engineer signals to highlight in code review:

1. **`App\Domain\Ai\Contracts\AiProvider`** — a single interface implemented by `OpenAiProvider` and `AnthropicProvider`, with a `ProviderManager` that picks the right one per user (incl. their BYO key). Adding a third provider takes ~50 lines.

2. **`App\Domain\Rag\Services\ChatStreamer`** — coordinates the entire RAG round-trip: embed → vector search → build prompt with context → stream completion → log usage → trigger title-generation job. Streams chunks back to the client over SSE; persists partial responses on client disconnect.

See [docs/architecture.md](docs/architecture.md) for the deep dive.

---

## Quickstart

Requirements: PHP 8.3+, Composer, Node 20+, Docker (for Postgres + pgvector).

```bash
# 1. Clone and install
composer install
cp .env.example .env
php artisan key:generate

# 2. Start Postgres + pgvector
docker compose up -d postgres

# 3. Run migrations and seed the demo account
php artisan migrate
php artisan db:seed --class=DemoSeeder

# 4. Set your API keys in .env (or leave empty if every user will BYO)
# OPENAI_API_KEY=sk-...
# ANTHROPIC_API_KEY=sk-ant-...

# 5. Start the API and the queue worker
php artisan serve            # http://localhost:8000
php artisan queue:work       # in a second terminal

# 6. Start the Vue SPA
cd frontend
npm install
npm run dev                  # http://localhost:5173
```

Visit `http://localhost:5173/login` and click **Try the demo** to log in as the seeded user.

Demo credentials (only seeded if you ran `db:seed`):
- email: `demo@insightdeck.app`
- password: `demo-password`

---

## Tests

```bash
# Backend
php artisan test               # PHPUnit + RefreshDatabase against Postgres test DB

# Frontend
cd frontend && npm run test    # Vitest (jsdom)
cd frontend && npm run build   # Vue + TS type check + production build
```

The test suite covers:

- Unit: `Chunker`, `CitationFormatter` token boundaries and citation parsing.
- Feature: Sanctum SPA auth, document tenancy isolation (User A cannot read User B's docs), upload validation, streaming chat (with `Http::fake`), rate limiting, daily token budget enforcement, BYO-key encryption.

---

## API surface

All routes are prefixed `/api`. Auth is Sanctum SPA cookies — call `GET /sanctum/csrf-cookie` first, then send the `XSRF-TOKEN` cookie value in the `X-XSRF-TOKEN` header on every state-changing request (the bundled Vue `api/client.ts` does this automatically).

| Route | Purpose |
|---|---|
| `POST /api/auth/register · /login · /logout` | Auth lifecycle |
| `POST /api/demo-login` | One-click demo session |
| `GET /api/me` · `PATCH /api/me` · `DELETE /api/me` | Profile |
| `PATCH /api/settings` · `POST /api/settings/test-connection` | AI prefs + BYO keys |
| `POST /api/documents` · `GET /api/documents` · `GET /api/documents/{id}` · `DELETE /api/documents/{id}` | Documents |
| `POST /api/documents/{id}/summary` (SSE) | Streamed summarization |
| `POST /api/conversations` · `GET /api/conversations/{id}` · ... | Conversation CRUD |
| `POST /api/conversations/{id}/messages` (SSE) | Streamed chat |
| `POST /api/conversations/{id}/messages/{msg}/regenerate` (SSE) | Regenerate |
| `POST /api/drafts` (SSE) · `POST /api/drafts/{id}/iterate` (SSE) | Email drafting |
| `GET /api/usage/today · /timeseries · /breakdown` | Usage dashboard |

Streaming responses are `text/event-stream` with these event types: `delta` (token chunk), `citations` (chunk manifest), `done` (final message + usage), `error` (error message).

---

## Project layout

```
app/
├── Domain/
│   ├── Ai/                  Provider abstraction, DTOs, cost calculator
│   ├── Rag/                 Chunker, Retriever, PromptBuilder, ChatStreamer, Summarizer
│   └── Drafting/            DraftStreamer
├── Http/Controllers/Api/    Action-style controllers (one HTTP action per method)
├── Jobs/                    ProcessDocument, GenerateConversationTitle
├── Models/Concerns/         BelongsToUser trait (auto-tenant on create + read)
└── Models/Scopes/           BelongsToUserScope (global query filter)
config/
├── ai.php                   Providers, defaults, retrieval, daily caps
└── ai-pricing.php           USD per 1k tokens by provider/model
frontend/
├── src/api/                 Typed API clients
├── src/composables/useStream.ts   SSE reader (POST + AbortController)
├── src/pages/               Login · Dashboard · Documents · Chat · Drafts · Settings
└── src/components/ui/       Button · Card · Input · Select · Badge · Textarea
docker-compose.yml          Postgres 16 + pgvector for local dev
```

---

## Deployment

The app is built to deploy on Railway (web + worker + Postgres) with the SPA on Vercel. See [`docs/deployment.md`](docs/deployment.md) for step-by-step.

---

## License

MIT — feel free to fork and learn from it.
