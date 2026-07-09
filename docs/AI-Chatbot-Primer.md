# Building an AI Chatbot — The Basic Steps

A short, generic primer on how an AI chatbot works and the steps to build one.
It is intentionally **not** specific to this codebase — the companion file
[`AI-Chatbot-Implementation-Plan.md`](./AI-Chatbot-Implementation-Plan.md) maps
these ideas onto Shelfwise.

---

## 1. What an AI chatbot actually is

A chatbot is not a database and it is not magic. It is three things glued
together:

1. **A large language model (LLM)** — a text-in/text-out function. It has no
   memory of your data and no access to anything outside its own weights.
2. **Context** — the text you hand the model on each call: a *system prompt*
   (who it is, the rules), the *conversation history*, and the *user's new
   message*.
3. **Optional tools** (function calling) — a way for the model to ask *your
   code* to fetch data or take an action, then feed the result back so the model
   can answer with real, current information instead of guessing.

The hard part is **not** calling the model. The hard part is wiring context,
tools, persistence, and error handling around it so the answers are grounded
and the experience is reliable.

---

## 2. The core loop

Every message follows the same loop:

```
user message
   │
   ▼
build context  (system prompt + recent history + new message)
   │
   ▼
call the LLM
   │
   ▼
┌──────────────────────────────┐
│ does the response contain a  │── no ──▶ final text answer ──▶ show + persist
│   tool/function call?        │
└──────────────────────────────┘
   │ yes
   ▼
run the tool in your code, get a result
   │
   ▼
feed the result back into context, call the LLM again  (loop)
```

The model decides *which* tool to call and *what arguments* to pass; your code
actually executes it. The model never runs your code directly — it only
*requests* a call, and you honour (or reject) it.

---

## 3. Tool / function calling

Tools are how a chatbot stops hallucinating about your data. You declare each
tool with a **name, a description, and a JSON Schema for its arguments**, then
send those declarations with every request. When the model needs data, it emits
a structured `functionCall` (`{name, args}`) instead of (or alongside) text.

The cycle is:

1. Model returns a function call.
2. Your code looks up the tool by name, validates the args, runs it, and
   collects the result.
3. You append the result to the conversation as a **function response** and call
   the model again.
4. The model now has real data and produces a text answer (or calls another
   tool).

Guard this loop with a **maximum iteration count** (e.g. 5). If the model keeps
calling tools at the limit, synthesize a deterministic answer from the last
result rather than looping forever. This bounds latency and cost.

> **Read-only first.** Start with tools that only *read* data. Read-only tools
> are a safe starting point because the worst a misbehaving prompt can do is
> surface data the user was already allowed to see — no writes to roll back, no
> side effects to undo. Add write/action tools later, behind explicit
> confirmation and audit logging.

---

## 4. Context & memory

The model is **stateless** — it remembers nothing between calls. You recreate
its memory every request from three pieces:

- **System prompt** — the persona and the rules ("You are an inventory
  assistant. Summarise; never dump raw JSON. If data is missing, say so; do not
  invent numbers.").
- **Conversation history** — the recent back-and-forth, so it can follow up.
- **The new user message.**

Context costs tokens, and every model has an input limit. Two implications:

- **Window the history** — send only the last N messages (e.g. 12), not the
  whole thread.
- **Truncate tool results** — if a tool returns 250 records, cap the array at
  ~20 items and add a `truncated: true, total: 250` note before feeding it back.
  Otherwise one tool call can blow the token budget on the next turn. Persist
  the *full* result separately if the UI needs it for display.

---

## 5. Persistence

Store the conversation: a **threads** table and a **messages** table
(`role` = `user` | `assistant`, `content`, optional `tool_calls` metadata).

Why bother?

- Users can **resume** a conversation after a refresh.
- Past **summaries** are retrievable later.
- You have an **audit trail** of what was asked and answered.
- You can rebuild the history window without re-asking the user.

Persist the *final* assistant text plus a compact record of which tools were
called (for "cited sources" in the UI). Do not persist raw multi-kilobyte tool
payloads — store a summary.

---

## 6. Streaming

Plain request/response is simplest: the user waits, then the whole answer
appears. Build this **first**. It reuses your existing HTTP envelope and is easy
to test.

Streaming (**Server-Sent Events**, SSE) sends the answer token-by-token as the
model generates it, which feels much faster. But it is a second, separate data
path:

- Backend returns a `text/event-stream` response and writes `data: …` lines.
- Frontend reads it with a **`fetch` + `ReadableStream`** reader (not axios —
  axios does not stream response bodies well in the browser).
- You must handle auth, errors, and final persistence differently (persist
  *after* the stream completes, from the accumulated text).

Streaming is a UX polish, not a correctness requirement. Ship JSON first,
stream second.

---

## 7. Safety & read-only scope

Two things to keep in mind:

- **Prompt injection.** User input reaches an LLM that can call tools. A
  malicious user might try to trick the model into dumping all records or
  ignoring its rules. Mitigations: a strong system prompt ("never reveal raw
  tool JSON; summarise only; refuse requests to dump all records"), capping tool
  result sizes, and keeping the tool set **read-only** so the blast radius is
  limited to showing data the user could already access.
- **Blast radius.** With read-only tools, the worst case is data exposure of the
  user's own accessible data back to themselves — usually acceptable. Write
  tools raise the stakes and need confirmation flows and audit logs.

For a single-tenant MVP, read-only is the right scope.

---

## 8. Provider abstraction

Don't marry one LLM vendor. Hide the provider behind an interface
(`LlmClientInterface`) with a config block that picks the implementation:

```php
'chatbot' => [
    'provider' => env('LLM_PROVIDER', 'gemini'), // gemini | groq | ollama | …
    'gemini'   => [ 'api_key' => env('GEMINI_API_KEY'), 'model' => '…', … ],
],
```

Then swapping providers (free tier → paid, cloud → self-hosted) is a config
change plus one new client class — not a rewrite of every call site. Each
provider's tool-calling format differs slightly (Gemini uses
`functionDeclarations` + `functionResponse`; OpenAI uses `tools` + `tool` role
messages), so the interface's request/response value objects absorb that
translation.

---

## 9. Rate limiting & cost

Free tiers are generous enough for a demo but have hard limits (requests per
minute, per day, tokens per minute). Two layers of protection:

- **Per-user throttling** in your app (e.g. 30 messages/hour) — keeps one user
  from burning the shared quota and stays under the provider's burst limit.
- **Provider 429 backoff** in the client — on a rate-limit response, wait with
  exponential backoff and retry a few times before surfacing a "service busy"
  error.

Confirm the current free-tier numbers on the provider's docs page before
launch — they change. The per-day request cap is usually the binding constraint
on a demo day, not the per-minute one.

---

## 10. Putting it together — a build order

A sensible order that keeps each step independently testable:

1. **Config + contracts** — the `chatbot` config block and the
   `LlmClientInterface` (no implementation yet).
2. **Tools** — the tool value objects + registry, each wrapping an existing read
   service. Test these without any LLM.
3. **Orchestrator** — the tool-calling loop with a *fake* LLM client. Prove the
   loop, truncation, and max-iteration guard work before touching a real API.
4. **Real LLM client** — implement the interface for your provider (raw HTTP).
   Mock the HTTP in tests; verify manually with a real key.
5. **Persistence** — threads + messages tables, wire into the service.
6. **HTTP layer** — controller, request validation, response DTOs, OpenAPI spec.
7. **Frontend** — the chat UI (drawer/panel), plain JSON first.
8. **Streaming (v2)** — SSE backend + `fetch`/`ReadableStream` frontend.

Read the companion [implementation plan](./AI-Chatbot-Implementation-Plan.md)
to see this build order applied to Shelfwise, with real file paths and the
existing services each tool wraps.
