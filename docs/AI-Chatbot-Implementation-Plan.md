# AI Chatbot — Implementation Plan & Roadmap

A read-only AI assistant for Shelfwise that answers natural-language questions
about upcoming demand, stock, forecasts, and reorders, and generates summaries.
This extends the idea planted in [`Week2_Build_Report.md`](./Week2_Build_Report.md)
§8.2 — *"an AI agent that answers natural-language questions over the
inventory… wrapping the same service layer the UI uses."*

This is a living roadmap. Iterate on it before and during implementation.

For the general concepts, read the companion [`AI-Chatbot-Primer.md`](./AI-Chatbot-Primer.md)
first.

---

## 1. Context & decisions

### Why

The app already computes everything the chatbot needs to *say* — reorder
verdicts, stockout projections, dead-stock flags, 30-day demand forecasts,
dashboard KPIs. The chatbot adds **a natural-language layer over those existing
read services**, not new business logic. The LLM never touches the database
directly; it calls tools that call the same service interfaces the UI uses.

### Locked decisions

| Decision | Choice | Rationale |
|---|---|---|
| LLM provider | **Google Gemini free tier**, behind a provider-agnostic `LlmClientInterface` | Free for the MVP; interface makes swapping to Groq / Ollama / a paid model a config change. Raw HTTP via Laravel's `Http` facade — no composer SDK. |
| Capabilities | **Read-only Q&A + summaries** | Safest MVP scope. No stock adjustments, no `forecast:run` triggers. Worst case is the user seeing their own data summarised. |
| Chat history | **Persistent** — `chat_threads` + `chat_messages` | Users resume conversations and retrieve past summaries. |
| Streaming | **Phased** — v1 plain JSON, v2 SSE later | JSON reuses the existing axios/`ApiResponse` envelope and is easy to test; streaming is a separate, riskier data path. |

### What the codebase already gives us

The tool layer wraps these existing read services (all container-resolved, all
return `BaseData` DTOs with `toArray()`):

- `IntelligenceServiceInterface` → `recommendations(): RecommendationsSummaryDTO`,
  `forProduct(int $id): RecommendationDTO`
- `ForecastReaderInterface` → `summary($now): ForecastSummaryDTO`,
  `chartFor($id, $now): ProductForecastDTO`, `snapshotFor($id, $now): ?ForecastSnapshot`
- `DashboardServiceInterface` → `summary(): DashboardSummaryDTO`,
  `trends($days, $productId?): DashboardTrendsDTO`
- `ProductServiceInterface` → `paginate(ProductFilterData): PaginatedData`,
  `find(int $id): ProductDTO`
- `StockServiceInterface` → `recent(int $limit): StockMovementDTO[]`,
  `history($id, $perPage, $page): PaginatedData`

> **Note on `ForecastReader`:** the 48-hour staleness policy lives *inside* the
> reader (`freshQuery()`). When forecasts are stale or absent, `summary()`
> returns `forecasted_count: 0`, `chartFor()` returns an empty forecast array,
> and `snapshotFor()` returns `null`. Tools must **tolerate** these empties and
> pass them through as structured data — never throw. The system prompt then
> instructs the model to say "forecasts haven't been generated; run
> `php artisan forecast:run`" instead of inventing numbers.

---

## 2. Architecture

A new **`App\Modules\Chatbot\`** module, mirroring the existing module shape
(`Forecast` is the closest template). The LLM is one pluggable client behind an
interface; the tool registry is fixed at provider-bind time and contains only
read-service-backed tools.

```
POST /api/chat/messages  (auth:sanctum, optional thread_id)
   │
   ▼
ChatbotController  ──▶  ApiResponse::item(ChatAnswerDTO)
   │
   ▼
ChatbotService::ask(userId, threadId?, message)
   │  1. RateLimiter (per user / hour) → 429
   │  2. resolve/create ChatThread (verify ownership)
   │  3. persist user ChatMessage
   │  4. load last N messages as history
   ▼
ChatbotOrchestrator::run(history, message)
   │  build system prompt + tool declarations
   ▼
LlmClientInterface::generate(LlmRequest)   ◀── GeminiLlmClient (raw HTTP)
   │
   │  ┌─── loop (max 5 iterations) ───────────────────────────┐
   │  │  functionCall? → ChatbotToolRegistry → existing service │
   │  │  truncate result → append functionResponse → re-call    │
   │  │  text + no calls? → break (final answer)                │
   │  └─────────────────────────────────────────────────────────┘
   ▼
final text + recorded tool_calls
   │
   ▼
ChatbotService:  persist assistant ChatMessage (with tool_calls)
                  update ChatThread.last_message_at
   │
   ▼
ChatAnswerDTO { thread, message }
```

**No writes anywhere in the tool layer.** There is no `adjust_stock` or
`run_forecast` tool. The tool set is fixed at provider-bind time; no runtime
registration. This is the read-only guarantee.

---

## 3. Backend build list (in order)

All new files under `Apps/Backend/app/Modules/Chatbot/`. Patterns to mirror are
named in italics.

### 3.1 Config (edit existing)

- `Apps/Backend/config/services.php` — add the `chatbot` block (see §7).
  *Mirrors the `forecast` block.*
- `Apps/Backend/.env.example` — add `LLM_PROVIDER`, `GEMINI_API_KEY`,
  `GEMINI_MODEL`, `GEMINI_BASE_URL`, `GEMINI_TIMEOUT`, `CHATBOT_MAX_TOKENS`,
  `CHATBOT_RATE_LIMIT_PER_HOUR`.

### 3.2 Contracts & exceptions

- `Services/Contracts/LlmClientInterface.php` —
  `generate(LlmRequest): LlmResponse` (v1) and
  `stream(LlmRequest): \Generator` (v2 SSE; throws `LogicException` until M5).
- `Services/Contracts/ChatbotToolRegistryInterface.php` —
  `all(): array<string, ChatbotTool>`, `get(string): ChatbotTool`, `has(string): bool`.
- `Services/Contracts/ChatbotServiceInterface.php` —
  `ask(int $userId, ?int $threadId, string $message): ChatAnswerDTO`,
  `threads(int $userId): array<ChatThreadDTO>`,
  `thread(int $userId, int $threadId): ChatThreadDTO` (404 if not owner),
  `createThread(int $userId, ?string $title): ChatThreadDTO`.
- `Exceptions/ChatbotServiceUnavailableException.php` — extends `DomainException`,
  `static::gemini(?Throwable $previous = null): self` (HTTP **503**). *Mirrors
  `ForecastServiceUnavailableException`; rendered to JSON for `api/*` by
  `bootstrap/app.php` with zero bootstrap changes.*
- `Exceptions/ChatbotRateLimitException.php` — extends `DomainException`,
  HTTP **429**. Thrown by the per-user `RateLimiter` (distinct from the
  provider's 429, which becomes 503 after backoff).

### 3.3 DTOs (extend `Shared\DTOs\BaseData`, snake_case `toArray()`)

- `DTOs/ChatThreadDTO.php` — `id, user_id, title, message_count, last_message_at, created_at`,
  plus `messages: ?list<ChatMessageDTO>` — `null` on list endpoints, populated
  on the `show` path (M4 thread switching needs the messages; §10's
  "thread + messages" response has nowhere to live without this field).
- `DTOs/ChatMessageDTO.php` — `id, thread_id, role ('user'|'assistant'), content, tool_calls (?array), created_at`.
  `tool_calls` is a truncated `[{name, args, result_summary}]` for UI "cited
  sources" — never the full payload.
- `DTOs/ChatAnswerDTO.php` — `thread: ChatThreadDTO, message: ChatMessageDTO`.
- `DTOs/LlmRequest.php` — plain value object (not `BaseData`): `system_prompt`,
  `messages: list<LlmMessage>`, `tools: list<{name, description, parameters}>`,
  `tool_mode: 'auto'|'none'` (default `auto`; `none` disables function calling
  for the forced-text fallback in §5). Internal only. `LlmMessage` is
  `{role: 'user'|'assistant', parts: list<Part>}` where a part is one of
  `{text}`, `{function_call: {name, args}}`, `{function_response: {name, response}}`
  — a flat `{role, content}` string history **cannot** represent the in-flight
  tool loop (§5 appends `functionCall`/`functionResponse` parts and re-sends).
  The Gemini-specific JSON translation stays inside `GeminiLlmClient`.
- `DTOs/LlmResponse.php` — plain value object: `text: ?string`,
  `function_calls: list<{name, args}>`, `finish_reason: string`. Internal only.

### 3.4 Tool layer (the crux)

- `Services/Tools/ChatbotTool.php` — immutable value object: `name`,
  `description`, `parameters` (JSON Schema), `handler: callable(array $args): array`.
  The handler closes over the resolved service.
- `Services/Tools/ChatbotToolRegistry.php` — implements the interface;
  constructor-injected with the 9 tool instances; builds a `name => ChatbotTool` map.
- The 9 tools (see §4): `GetStoreOverviewTool`, `GetReorderRecommendationsTool`,
  `GetProductRecommendationTool`, `GetForecastSummaryTool`,
  `GetProductForecastTool`, `SearchProductsTool`, `GetProductTool`,
  `GetRecentMovementsTool`, `GetSalesTrendsTool`.

### 3.5 Orchestration

- `Services/ChatbotOrchestrator.php` — the loop (see §5). Builds the
  `LlmRequest` (system prompt + tools + history window capped at
  `max_history_messages`), calls the LLM, resolves function calls through the
  registry, validates args against the tool's JSON Schema, calls the handler,
  **truncates** the result, wraps it as a `functionResponse`, re-calls. Max
  `max_tool_iterations` (5). At the limit, makes **one final LLM call with
  function calling disabled** (`tool_mode: none` →
  `toolConfig.functionCallingConfig.mode: NONE`) to force a text answer from
  the accumulated context; only if that call also fails does it fall back to a
  deterministic one-liner from the last tool result. Returns `{text, tool_calls[]}`.
- `Support/SystemPromptBuilder.php` — builds the system prompt (persona +
  read-only guard + "summarise, don't dump" + "if forecast data is empty, say
  so and suggest `forecast:run`; never invent numbers"). Reads
  `config('services.chatbot.system_prompt')` (optional path override) or falls
  back to an inline constant.

### 3.6 LLM client (Gemini, raw HTTP)

- `Services/Llm/GeminiLlmClient.php` — implements `LlmClientInterface`.
  *Mirrors `ForecastRunner`'s `Http::baseUrl()->timeout()->acceptJson()->post()->throw()->json()`*
  with the `x-goog-api-key` header. Translates `LlmRequest` → Gemini
  `generateContent` payload (`contents`, `systemInstruction`,
  `tools: [{functionDeclarations}]`). Parses `candidates[0].content.parts[]`,
  detecting `text` vs `functionCall`. **429 backoff**: before `->throw()`, if
  `status() === 429` and attempts < 3, `usleep` exponential and retry (*mirrors
  `ShopifyClient`, but the trigger is HTTP 429 status, not a body field*).
  `ConnectionException` + `RequestException` →
  `ChatbotServiceUnavailableException::gemini()`. `stream()` (v2) yields
  SSE-parsed deltas from `:streamGenerateContent?alt=sse`.
- `Services/Llm/LlmClientFactory.php` — reads `provider` config and resolves the
  impl (only `gemini` for now). Bound in the provider so a swap is config + one
  new class.

> **Gemini gotchas (from the design review):**
> - Do **not** set `responseMimeType: application/json` on the conversational
>   path — it breaks function calling in some SDK versions.
> - `functionResponse` parts must be `{name, response: {…}}` (an object), not a
>   bare array. The orchestrator wraps every list result, e.g.
>   `{name: 'get_reorder_recommendations', response: {recommendations: […]}}`.
> - Gemini roles are only `user` and `model`: map the persisted `assistant`
>   role to `model`, and send `functionResponse` parts back under the `user`
>   role. Map `CHATBOT_MAX_TOKENS` to `generationConfig.maxOutputTokens`.

### 3.7 Persistence

- `Models/ChatThread.php` — `#[Fillable(['user_id','title','last_message_at'])]`,
  `belongsTo User`, `hasMany ChatMessage`, cast `last_message_at => datetime`.
- `Models/ChatMessage.php` — `#[Fillable(['thread_id','role','content','tool_calls'])]`,
  `belongsTo ChatThread`, cast `tool_calls => 'array'` (nullable).
- `Database/Migrations/2026_07_08_100000_create_chat_threads_table.php`
- `Database/Migrations/2026_07_08_100001_create_chat_messages_table.php`
- `Database/Factories/ChatThreadFactory.php`, `Database/Factories/ChatMessageFactory.php`

### 3.8 ChatbotService (top level)

- `Services/ChatbotService.php` — implements `ChatbotServiceInterface`.
  Constructor-injects `ChatbotOrchestrator`, the two models, `RateLimiter`.
  `ask()` does **not** wrap the LLM round-trips in a `DB::transaction` — up to
  5 sequential HTTP calls would pin a MySQL connection inside an open
  transaction for minutes, and a rollback on a Gemini failure would silently
  delete the user's message. Instead: rate-limit check → **small transaction
  #1** (resolve/create thread with ownership check; persist user message; on a
  thread's first message, set its title to the first ~50 chars of that
  message) → load last N messages as history → call orchestrator (**outside
  any transaction**) → **small transaction #2** (persist assistant message
  with `tool_calls`; update `last_message_at`) → return `ChatAnswerDTO`. If
  the orchestrator throws, the user message survives and the UI can offer retry.

### 3.9 HTTP layer

- `Requests/SendMessageRequest.php` — `message: required|string|min:1|max:2000`,
  `thread_id: nullable|integer` (**no `exists:` rule** — a 422-vs-404 split
  would leak which thread ids exist globally; the service's ownership check
  404s uniformly for missing *and* foreign threads).
- `Requests/CreateThreadRequest.php` — `title: nullable|string|max:120`.
- `Controllers/ChatbotController.php` — thin, **no `declare(strict_types=1)`**
  (route ID coercion, *mirrors `ForecastController`*). Methods: `index()`
  (user's threads), `store()` (create thread), `show(int $id)` (thread +
  messages), `send(SendMessageRequest)` → `ApiResponse::item($service->ask(...))`.
  `send()` calls `set_time_limit(0)` first (*mirrors `ShopifyController::sync`* —
  the tool loop can outrun PHP's execution limit under Apache/FPM).

### 3.10 Provider & routes

- `Providers/ChatbotServiceProvider.php` — extends `ModuleServiceProvider`.
  `register()`: bind `LlmClientInterface` via `LlmClientFactory`,
  `ChatbotToolRegistryInterface → ChatbotToolRegistry`,
  `ChatbotServiceInterface → ChatbotService`, register the 9 tools.
  `boot()`: `loadApiRoutes(__DIR__.'/../Routes/api.php')` +
  `loadMigrationsFrom(__DIR__.'/../Database/Migrations')`. *Mirrors
  `ForecastServiceProvider`.*
- `Routes/api.php` — under `auth:sanctum`:
  - `GET chat/threads` → `index`
  - `POST chat/threads` → `store`
  - `GET chat/threads/{thread}` → `show` (`.whereNumber('thread')`)
  - `POST chat/messages` → `send` (accepts optional `thread_id`; creates a
    thread when absent — keeps the send path single and explicit)
- `Apps/Backend/bootstrap/providers.php` — append
  `App\Modules\Chatbot\Providers\ChatbotServiceProvider::class`.

### 3.11 OpenAPI (edit existing)

- `Apps/Backend/app/Support/OpenApi/OpenApiSpec.php` — add `Chatbot` tag, 4
  paths, 6 schemas (see §10). Reuse existing `dataResponse`, `listResponse`,
  `idParam`, `messageResponse` helpers.

---

## 4. The 9 tools

Each tool is a thin wrapper over an existing service method. Truncation and
graceful-empty rules are part of the tool, not the orchestrator.

| Tool name | Calls | Returns / rules |
|---|---|---|
| `get_store_overview` | `DashboardServiceInterface::summary()` + `IntelligenceServiceInterface::recommendations()` headline counts only | Compact KPIs + verdict counts. **Does not** include the per-product array. |
| `get_reorder_recommendations` | `IntelligenceServiceInterface::recommendations()` | Optional `verdict` filter (`reorder`\|`overstock`\|`dead_stock`\|`healthy`), `limit` (default 20, max 50). **Truncated** list + counts. |
| `get_product_recommendation` | `IntelligenceServiceInterface::forProduct($id)` | Single product. Catches `ModelNotFoundException` → `{error: 'product not found'}`. |
| `get_forecast_summary` | `ForecastReaderInterface::summary($now)` | Passes through `forecasted_count: 0` as data (not error) when no fresh forecasts. |
| `get_product_forecast` | `ForecastReaderInterface::chartFor($id, $now)` | History + forecast arrays; empty forecast array when stale — returned as-is. |
| `search_products` | `ProductServiceInterface::paginate(ProductFilterData::fromArray($args))` | Page `data` + `meta`. **Reuses `ProductFilterData::fromArray()`** — do not re-implement sanitisation. |
| `get_product` | `ProductServiceInterface::find($id)` | Catches `ModelNotFoundException`. |
| `get_recent_movements` | `StockServiceInterface::recent($limit ?? 10)`; with `product_id`: `StockServiceInterface::history($id, $limit, 1)` | Recent movements — store-wide, or one product's ledger history when `product_id` is given. |
| `get_sales_trends` | `DashboardServiceInterface::trends($days, $productId?)` | Daily in/out totals over a trailing window (`days`: 7–90, default 30) + stock value by category. Answers "how were sales trending?" — without it, §1's promise of `trends()` had no tool behind it. |

**Truncation rule (all list-returning tools):** cap arrays at
`max_tool_result_items` (20); append `{truncated: true, total: N}`. The full
result is *not* sent to the LLM — only the truncated summary feeds back as a
`functionResponse`. (The UI's "cited sources" use the `result_summary`, not the
full payload.)

---

## 5. Orchestration loop

```text
ChatbotOrchestrator::run(history, message):
  system = SystemPromptBuilder::build()
  tools  = registry.all() → [{name, description, parameters}]
  contents = history + user(message)
  recorded_tool_calls = []

  for iteration in 1..max_tool_iterations (5):
      resp = LlmClient.generate(LlmRequest(system, contents, tools))

      if resp.function_calls is empty:
          return { text: resp.text, tool_calls: recorded_tool_calls }   # final answer

      for call in resp.function_calls:
          if not registry.has(call.name):
              append functionResponse {name: call.name, response: {error: 'unknown tool'}}
              continue
          if not valid(call.args, tool.parameters):
              append functionResponse {name: call.name, response: {error: 'invalid args'}}
              continue
          result = tool.handler(call.args)            # calls existing service
          truncated = truncate(result, max=20)        # cap + {truncated, total}
          append functionResponse {name: call.name, response: truncated}
          recorded_tool_calls.append({name, args: call.args, result_summary: summarise(truncated)})

      # loop continues → re-call LLM with the appended functionResponses

  # reached max iterations still calling tools: force a text answer with
  # function calling disabled — no new tool calls are possible
  resp = LlmClient.generate(LlmRequest(system, contents, tools, tool_mode: none))
  if resp.text:
      return { text: resp.text, tool_calls: recorded_tool_calls }

  # that call failed too — deterministic last resort
  return { text: "Based on the data I retrieved: " + summarise(last_result),
           tool_calls: recorded_tool_calls }
```

Guarantees:

- **Max-iteration guard** — never loops past 5; at the cap, one forced-text
  call (`tool_mode: none`), then a deterministic fallback. Bounds latency and
  free-tier cost.
- **No writes** — the registry contains only read tools; enforced at bind time.
- **Graceful empties** — stale/absent forecasts come back as structured data,
  and the system prompt tells the model to say so rather than fabricate.
- **Unknown tool / invalid args** — returned as error `functionResponse`s so the
  model can recover, never thrown.

---

## 6. Migration schema

### `chat_threads`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `user_id` | foreignId | `->constrained()->cascadeOnDelete()` (FK → `users`) |
| `title` | string(120) | default `'New chat'`; overwritten with the first ~50 chars of the thread's first user message (§3.8) so the M4 thread list isn't a wall of identical labels |
| `last_message_at` | dateTime, nullable | updated on each send |
| `created_at` / `updated_at` | timestamps | |

Index: `index(['user_id', 'last_message_at'])` for the thread list (per-column
direction isn't valid schema-builder syntax; MySQL scans the composite index
backwards for the `DESC` ordering anyway).

### `chat_messages`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `thread_id` | foreignId | `->constrained('chat_threads')->cascadeOnDelete()` |
| `role` | string(10) | `'user'` or `'assistant'` |
| `content` | text | the message text |
| `tool_calls` | json, nullable | `[{name, args, result_summary}]` — truncated, display only |
| `created_at` / `updated_at` | timestamps | |

Index: `index(['thread_id', 'created_at'])` for ordered retrieval.

Cascade deletes handle cleanup (delete a user → their threads → their messages).
No soft deletes for the MVP.

---

## 7. Config schema

Add to `Apps/Backend/config/services.php`:

```php
// AI chatbot (read-only Q&A agent) — see app/Modules/Chatbot.
'chatbot' => [
    'provider' => env('LLM_PROVIDER', 'gemini'),
    'gemini' => [
        'api_key'  => env('GEMINI_API_KEY'),
        'model'    => env('GEMINI_MODEL', 'gemini-3.5-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout'  => (int) env('GEMINI_TIMEOUT', 30),
    ],
    'max_tokens'             => (int) env('CHATBOT_MAX_TOKENS', 2048),
    'temperature'            => 0.2,
    'max_history_messages'   => 12,
    'max_tool_iterations'    => 5,
    'max_tool_result_items'  => 20,
    'rate_limit_per_hour'    => (int) env('CHATBOT_RATE_LIMIT_PER_HOUR', 30),
    'system_prompt'          => env('CHATBOT_SYSTEM_PROMPT'), // optional path override
],
```

**Model choice (checked 2026-07):** `gemini-3.5-flash` is Google's current
recommended flash model with no announced shutdown. Do **not** default to
`gemini-2.0-flash` (shut down 2026-06-01 — requests to it now fail) or
`gemini-2.5-flash` (retires 2026-10-16); `gemini-3.1-flash-lite` is the
higher-throughput alternative if free-tier quota bites.

`temperature: 0.2` for grounded, low-hallucination answers. `GEMINI_TIMEOUT`
is 30s per call — a flash-tier model that hasn't answered in 30s isn't going
to, and 5 iterations × 60s was an unbounded 5-minute worst case.
`rate_limit_per_hour: 30` stays well under the provider's per-day cap and the
per-minute burst cap.

---

## 8. Frontend

### New files (`Apps/Frontend/src/`)

- `api/chat.ts` — `chatApi`: `createThread(title?)`, `getThreads()`,
  `getThread(id)`, `sendMessage(threadId, text)`. Uses the shared `api` axios +
  `ApiItem` envelope (*mirrors `api/forecast.ts`*). v1 only.
- `types.ts` (edit) — add `ChatThread`, `ChatMessage`
  (`id, thread_id, role: 'user'|'assistant', content, tool_calls, created_at`),
  `ChatToolCall` (`name, args, result_summary`), `ChatAnswer`
  (`thread: ChatThread, message: ChatMessage`). **String-literal unions, no TS
  enums** (tsconfig `erasableSyntaxOnly`).
- `components/chat/ChatPanel.tsx` — `React.lazy`-loaded. Owns thread state,
  message list, composer. *Mirrors the charts lazy pattern
  (`lazy()` + `<Suspense fallback={<Skeleton/>}>`).*
- `components/chat/MessageList.tsx` — renders messages, auto-scrolls on new
  message (`useRef` + `useEffect`; no `ScrollArea` primitive exists — build
  minimal). User bubbles `bg-white border`, assistant `bg-brand-50`.
- `components/chat/MessageBubble.tsx` — role-aware styling, `Avatar`, timestamp,
  renders `tool_calls` as `Badge`-tagged cited-source `Card`s.
- `components/chat/ChatComposer.tsx` — `Textarea` (from `ui/field.tsx`) + send
  `Button` (from `ui/button.tsx`). Enter to send, Shift+Enter newline. Disabled
  while awaiting response.
- `components/chat/TypingIndicator.tsx` — three-dot animation (reuse `Spinner`).
- Tests: `api/chat.test.ts`, `components/chat/ChatPanel.test.tsx` (Vitest +
  testing-library, mock `chatApi`).

### Mounting (edit existing)

- `components/Layout.tsx` — add `const [chatOpen, setChatOpen] = useState(false)`.
  Floating button `fixed bottom-4 right-4 z-40` (`Button` + `MessageCircle`
  lucide icon). Right-side `<Drawer side="right" title="Shelfwise Assistant">`
  containing `<Suspense><ChatPanel/></Suspense>`. *Mirrors the existing
  `mobileNavOpen` / left-Drawer pattern exactly.*
- `App.tsx` — optional `<Route path="/chat">` full-page view (M2 stretch). The
  Drawer is the primary entry.

### v1 vs v2 streaming split

- **v1 (M1–M4):** `chatApi.sendMessage` is a plain axios POST returning
  `ApiItem<ChatAnswer>`. Composer shows `TypingIndicator` for the full
  round-trip (5–30s with tool calls). This ships first.
- **v2 (M5):** add `chatApi.sendMessageStream(threadId, text, onChunk, onDone,
  onError)` using **native `fetch`** (axios can't stream response bodies in
  browser). Manually attach `Authorization: Bearer ${tokenStore.get()}` (from
  `lib/api.ts`) and handle 401 (clear token + redirect, *mirroring the axios
  interceptor*). Read `response.body` as a `ReadableStream`, decode SSE `data:`
  lines, call `onChunk(textDelta)`. Backend: a `stream()` controller method
  returns `StreamedResponse` (`Content-Type: text/event-stream`) calling
  `GeminiLlmClient::stream()`, echoing `data: {json}\n\n` per delta plus a final
  `data: {"type":"done","message":{...}}\n`. **Persist after the stream
  completes** (accumulate text, then one DB write). **Streaming and the tool
  loop don't mix mid-flight**: intermediate generations end in `functionCall`s,
  not text — run the tool loop non-streamed and stream only the *final*
  generation, optionally emitting typed interim events
  (`data: {"type":"tool","name":"get_forecast_summary"}`) so the UI can show
  "checking your forecasts…".

---

## 9. Phased roadmap

### M1 — Backend module skeleton + tool layer (no LLM yet)
- [ ] `config/services.php` `chatbot` block + `.env.example` vars
- [ ] Migrations: `chat_threads`, `chat_messages`
- [ ] Models: `ChatThread`, `ChatMessage` + factories
- [ ] DTOs: `ChatThreadDTO`, `ChatMessageDTO`, `ChatAnswerDTO`, `LlmRequest`, `LlmResponse`
- [ ] Exceptions: `ChatbotServiceUnavailableException`, `ChatbotRateLimitException`
- [ ] `ChatbotTool` value object + 9 tool classes + `ChatbotToolRegistry`
- [ ] Unit tests: registry resolution, each tool's handler against mocked services, JSON Schema validation, truncation
- [ ] `ChatbotServiceProvider` registered in `bootstrap/providers.php`
- [ ] Routes (`auth:sanctum`) + controller stubs returning dummy data

### M2 — Gemini client + orchestrator + send-message E2E
- [ ] `LlmClientInterface` + `GeminiLlmClient` (`generateContent`, 429 backoff)
- [ ] `LlmClientFactory`
- [ ] `SystemPromptBuilder`
- [ ] `ChatbotOrchestrator` (loop, max-iteration guard, `functionResponse` wrapping)
- [ ] `ChatbotService` (rate limit, persistence, transaction)
- [ ] `SendMessageRequest`, `CreateThreadRequest`
- [ ] Feature tests: `Http::fake()` Gemini (text-only, single call, multi-call, 429 retry, 500 → 503), thread ownership 404, auth 401
- [ ] Manual curl verification against real Gemini (real key, outside CI)

### M3 — Frontend drawer (v1, no streaming)
- [ ] `types.ts` additions
- [ ] `api/chat.ts` + test
- [ ] `ChatPanel`, `MessageList`, `MessageBubble`, `ChatComposer`, `TypingIndicator` + tests
- [ ] `Layout.tsx` floating button + Drawer mount
- [ ] Manual click-through: ask "What should I reorder?" → see tool-cited answer

### M4 — Persistence UX + rate-limiting + error UX
- [ ] Thread list (`GET /chat/threads`) in a sidebar within the drawer
- [ ] Thread switching (`GET /chat/threads/{id}`)
- [ ] New-thread action
- [ ] 429 toast ("You've sent too many messages this hour") via `react-hot-toast`
- [ ] 503 Gemini-down error state in the panel
- [ ] `EmptyState` for first visit with suggested prompts
- [ ] Feature test: rate limiter trips at threshold

### M5 — SSE streaming (v2)
- [ ] `GeminiLlmClient::stream()` (`streamGenerateContent?alt=sse`)
- [ ] Controller `stream()` returning `StreamedResponse`
- [ ] Orchestrator streaming variant (tool loop non-streamed; stream only the final generation; persist at end)
- [ ] Frontend `sendMessageStream` with `fetch` + `ReadableStream`
- [ ] Composer renders deltas token-by-token
- [ ] Handle 401 mid-stream
- [ ] Tests: `Http::fake()` streamed response; assert persistence after stream

---

## 10. OpenAPI spec update

In `Apps/Backend/app/Support/OpenApi/OpenApiSpec.php`:

**Tag:** `['name' => 'Chatbot', 'description' => 'Read-only AI assistant over inventory data.']`

**Paths:**
- `POST /api/chat/messages` — request `SendMessageInput`; `200 → dataResponse('ChatAnswer')`, `401`, `422`, `429`, `503`.
- `GET /api/chat/threads` — `200 → listResponse('ChatThread')`, `401`.
- `POST /api/chat/threads` — request `CreateThreadInput`; `201 → dataResponse('ChatThread')`, `401`, `422`.
- `GET /api/chat/threads/{thread}` — `idParam('thread')`; `200 → dataResponse('ChatThread')` (thread + messages), `401`, `404`.

**Schemas:** `ChatThread` (incl. nullable `messages` array, populated on the
show path), `ChatMessage` (role enum `[user, assistant]`),
`ChatToolCall` (`name, args: object, result_summary: string`), `ChatAnswer`
(`thread, message`), `SendMessageInput` (`message` required, `thread_id`
nullable), `CreateThreadInput` (`title` nullable).

---

## 11. Verification

### Backend unit (`tests/Unit/Chatbot/`)
- `ToolRegistryTest.php` — all 9 tools registered; `has()`/`get()`; unknown → false.
- `Tools/*ToolTest.php` — mock the service interface; assert truncation at the cap and verdict filtering.
- `GeminiLlmClientTest.php` — `Http::fake()` text-only → `LlmResponse.text`; `functionCall` → parsed; 429 → retry then success; 500 → `ChatbotServiceUnavailableException`. Use `Http::sequence()` for multi-call loops.
- `OrchestratorTest.php` — fake `LlmClientInterface` (function call on turn 1, text on turn 2); assert `functionResponse` fed back; assert at the 5-iteration cap a final `tool_mode: none` call is made (and the deterministic fallback fires only when that call fails too); assert unknown-tool/invalid-args → error responses. Also: assert the user message survives when the orchestrator throws (§3.8's two-transaction split).

### Backend feature (`tests/Feature/Chatbot/`)
- `ChatbotApiTest.php` — `Sanctum::actingAs($user)`, `Http::fake()` Gemini,
  `RefreshDatabase`, SQLite `:memory:`, `QUEUE_CONNECTION=sync` (all in
  `phpunit.xml`). Cases: send without `thread_id` → thread created + assistant
  message persisted; send with `thread_id` → appends; thread ownership (user B →
  404 on user A's thread); no token → 401; 31st message/hour → 429; Gemini 500
  → 503. To answer demand questions, seed `ProductForecast` rows directly
  (*mirror `ForecastApiTest::storedForecast()`*) — no sidecar needed.

### Manual backend (curl)
1. `php artisan serve`; seed a user + products + a stored forecast.
2. `POST /api/auth/login` (`demo@retail.test` / `password`) → token.
3. `POST /api/chat/messages` with `Authorization: Bearer $T`, body
   `message=What should I reorder this week?` → expect
   `{data:{thread, message:{content, tool_calls:[{name:'get_reorder_recommendations',…}]}}}`.
4. Ask "How much cash is tied up in dead stock?" → expect `get_store_overview`.
5. Use an invalid key → expect 503.

### Frontend (Vitest + manual)
- `npm test` — `chat.test.ts` (API mapping), `ChatPanel.test.tsx` (renders messages, sends on Enter, typing indicator while loading, renders cited sources).
- Manual: log in, click the floating button, ask a question, watch the assistant bubble populate with cited tool cards. Switch threads. Hit the rate limit → toast. Kill the backend → 503 error state.

### Sidecar note
The chatbot does **not** require the Python forecast sidecar to be running.
But to answer "what's my upcoming demand?" meaningfully, stored forecasts must
exist (from a prior `php artisan forecast:run`). If none exist, forecast tools
return empty and the assistant says so.

---

## 12. Risks & open questions

| Risk | Severity | Mitigation / status |
|---|---|---|
| **Token budget from large tool results** (`recommendations()` returns 250 products) | High | Truncate to 20 items in `functionResponse`; persist only the truncated `result_summary` (§4 — the full payload is never stored *or* sent). System prompt forbids dumping. |
| **Model deprecations** — `gemini-2.0-flash` shut down 2026-06-01; `gemini-2.5-flash` retires 2026-10-16 | High (resolved) | Default is `gemini-3.5-flash` (§7); model is env-configurable, so future rotations are a config change. |
| **Single-tenancy** — tools return global data, not per-user | Medium (MVP ok) | Auth gates *who* can chat; all users see the one store's data. **Add `user_id`/`store_id` scoping to the service layer before going multi-tenant** (ties into the earlier scaling discussion). |
| **Gemini free-tier limits** | Medium | Per-user `RateLimiter` at 30/hour; 429 backoff in client. Google no longer publishes fixed numbers on the docs page — **check the AI Studio rate-limit view for the project before launch**; the per-day request cap is the demo-day binding constraint. |
| **Prompt injection** — user input reaches an LLM with tool access | Low (read-only, single tenant) | All tools read-only; blast radius = the tenant's own data shown back to them. System-prompt guard against raw JSON dumps. Acceptable for MVP. |
| **Function-calling reliability** on free tier / Flash | Medium | Max-iteration guard prevents infinite loops; fallback synthesizes from last tool result. |
| **`responseMimeType: json` breaks function calling** | Medium | Do **not** set it on the conversational path. |
| **`functionResponse` shape** must be `{name, response:{…}}` | Medium | Orchestrator wraps all results; unit-test the wrapping. |
| **Data use on the free tier** | Medium (pitch narrative) | Per Google's terms, unpaid-tier prompts *and* responses are **used to train Google's models** and may be human-reviewed. Fine for the demo (the data is a public dataset), but script the committee answer: production runs the paid tier (contractually excluded from training) or a self-hosted model — which is exactly what `LlmClientInterface` is for. |
| **SSE through Laravel/Sanctum (v2)** | Medium | `StreamedResponse` works; long streams may hit PHP `max_execution_time` — document tuning. |
| **Open:** should cited sources deep-link to app pages (e.g. `/products/5`)? | Open | M4 stretch — `tool_calls.result_summary` could carry a deep link. |

---

## 13. Critical files to mirror

| File | Reuse for |
|---|---|
| `Apps/Backend/app/Modules/Forecast/Services/ForecastRunner.php` | `Http` facade client + `DomainException`-with-status pattern → `GeminiLlmClient` |
| `Apps/Backend/app/Modules/Shopify/Support/ShopifyClient.php` | 429 retry/backoff pattern → Gemini rate-limit handling (trigger = HTTP 429, not a body field) |
| `Apps/Backend/app/Modules/Forecast/Providers/ForecastServiceProvider.php` | Module provider template (bind + `loadApiRoutes` + `loadMigrationsFrom`) |
| `Apps/Backend/app/Support/OpenApi/OpenApiSpec.php` | Extend with `Chatbot` tag / paths / schemas |
| `Apps/Frontend/src/components/Layout.tsx` | Mount the floating button + right `Drawer` (mirror `mobileNavOpen`) |
| `Apps/Frontend/src/api/forecast.ts` | axios + `ApiItem` envelope pattern → `api/chat.ts` |
| `Apps/Frontend/src/lib/api.ts` | `tokenStore.get()` for the v2 `fetch`-based SSE auth header |
