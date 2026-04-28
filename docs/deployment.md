# Deployment

InsightDeck is built to deploy on **Railway** (API + worker + Postgres) with the
**Vue SPA on Vercel**. Both pieces are independent and you can mix and match.

## Backend on Railway

### 1. Create services
1. Create a new Railway project.
2. Deploy from the GitHub repo (root directory).
3. Add a Postgres plugin (any plan tier — pgvector is auto-installed by the
   `enable_pgvector` migration on first migrate).
4. Once deployed, add a second service from the same repo with the
   start command set to `php artisan queue:work --tries=3 --backoff=10` —
   this is the queue worker.

### 2. Environment variables (web + worker)
```
APP_NAME=InsightDeck
APP_ENV=production
APP_KEY=base64:...                # generate locally with `php artisan key:generate --show`
APP_URL=https://api.insightdeck.app
APP_DEBUG=false

DB_CONNECTION=pgsql
DB_HOST=$RAILWAY_PRIVATE_DOMAIN   # auto-injected
DB_PORT=5432
DB_DATABASE=$PGDATABASE
DB_USERNAME=$PGUSER
DB_PASSWORD=$PGPASSWORD

SESSION_DRIVER=database
SESSION_DOMAIN=.insightdeck.app   # parent domain so api.* and app.* share the cookie
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

SANCTUM_STATEFUL_DOMAINS=insightdeck.app,app.insightdeck.app

CORS_ALLOWED_ORIGINS=https://insightdeck.app,https://app.insightdeck.app

QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local             # or s3 / b2 if you've added storage credentials

OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...

AI_DEFAULT_PROVIDER=openai
AI_DEFAULT_CHAT_MODEL=gpt-4o-mini
AI_FREE_DAILY_TOKENS=50000
AI_PRO_DAILY_TOKENS=500000
AI_MAX_UPLOAD_MB=20
```

### 3. Build / run
Railway uses nixpacks, which detects Laravel automatically. The default start
command works:

```bash
php artisan serve --host=0.0.0.0 --port=$PORT
```

For better throughput, switch to `php-fpm` + nginx via a `Dockerfile` once you
need real production traffic.

### 4. Migrate + seed (one-shot)
Open the Railway shell on the web service:

```bash
php artisan migrate --force
php artisan db:seed --class=DemoSeeder --force
```

## Frontend on Vercel

1. Create a new Vercel project pointing at `/frontend` as the root directory.
2. Build command: `npm run build`. Output: `dist`.
3. Environment:
   ```
   VITE_API_URL=https://api.insightdeck.app
   ```
4. Add a custom domain (e.g. `insightdeck.app` or `app.insightdeck.app`).

> **CORS + cookies**: the frontend domain must be listed in
> `SANCTUM_STATEFUL_DOMAINS` and `CORS_ALLOWED_ORIGINS` on the backend, and
> `SESSION_DOMAIN` must be the **parent** domain that both share. If you put
> the API and the SPA on entirely different parent domains, switch the SPA to
> Sanctum **token** auth instead of cookies — the `User` model already has
> `HasApiTokens`.

## Health check

`GET /api/health` returns `{ ok, db, time, version }` and is suitable for any
load balancer's health probe. Configure both services to hit it.

## Smoke checklist

After first deploy:

- [ ] `curl https://api.insightdeck.app/api/health` returns `{ "ok": true, "db": true }`.
- [ ] Visit the SPA domain → register a new account → login redirects to the dashboard.
- [ ] Upload a small PDF → status moves `pending` → `processing` → `ready`.
- [ ] Start a chat → response streams in token by token.
- [ ] `GET /api/usage/today` shows non-zero `used` after one chat exchange.
- [ ] Hit the chat endpoint 11 times in a minute → 11th returns 429.
