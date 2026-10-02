# Architecture

## 1. Stack

| Layer            | Choice                                     | Notes                                                       |
| ---------------- | ------------------------------------------ | ----------------------------------------------------------- |
| Runtime          | PHP 8.3+                                   | Per §4                                                      |
| Framework        | Laravel 13                                 | Installed                                                   |
| Internal UI      | Inertia v3 + Vue 3 + TypeScript            | Installed; see [ADR-001](06-DECISIONS.md)                   |
| Cross-page state | Pinia                                      | Inbox, saved filters, notifications, theme, command palette |
| Styling          | Tailwind CSS 4                             | Token layer via `@theme`, not config-file extension         |
| Typed routes     | Laravel Wayfinder                          | Installed; generates typed route helpers from Laravel       |
| Charts           | Apache ECharts via `vue-echarts`           | §37, §109                                                   |
| Icons            | Lucide (`lucide-vue-next`)                 | §40, §118 — single library, no mixing                       |
| Database         | PostgreSQL 16                              | §4 preferred; JSONB and full-text search both matter here   |
| Cache / queue    | Redis + Laravel Queue                      | RabbitMQ deferred until integration volume needs it (§4)    |
| Realtime         | Laravel Reverb                             | Inbox, notifications, sync status                           |
| Auth (internal)  | Laravel session + Inertia                  |                                                             |
| Auth (API)       | Sanctum tokens + tenant API keys           | §48                                                         |
| Provider auth    | OAuth 2.0 per provider                     | Tokens encrypted at rest                                    |
| Storage          | S3 (`local` in dev)                        | §92                                                         |
| Search           | PostgreSQL full-text → OpenSearch at scale | §91                                                         |

### Packages to add

```
stancl/tenancy            multi-tenancy (single-database mode)
spatie/laravel-permission RBAC, tenant-scoped
laravel/sanctum           API tokens
laravel/reverb            websockets
laravel/horizon           queue observability
spatie/laravel-activitylog audit trail substrate
league/flysystem-aws-s3-v3 storage
maatwebsite/excel          CSV/XLSX import/export
dedoc/scramble             OpenAPI generation from the API layer
```

---

## 2. Tenancy model

**Single database, row-level isolation, `stancl/tenancy` in single-database
mode.** See [ADR-002](06-DECISIONS.md) for why this over database-per-tenant.

Isolation is enforced in three layers, not one:

1. **`BelongsToTenant` trait** — a global Eloquent scope on every tenant-owned
   model, plus automatic `tenant_id` population on create.
2. **PostgreSQL row-level security** as a backstop, so a query that somehow
   escapes the scope still returns nothing.
3. **A test that fails the build** if any tenant-owned table lacks a
   `tenant_id` foreign key, or any tenant model lacks the trait.

Layer 3 is the one that actually holds the line over time. Write it in Phase 0,
before there is anything to protect.

```php
// Every tenant-owned model
final class Lead extends Model
{
    use BelongsToTenant;  // global scope + auto-fill + tenant_id guard
}
```

**Central vs tenant.** Central tables (`tenants`, `plans`, `subscriptions`,
`platform_users`, …) carry no `tenant_id` and are reachable only from the
platform admin context. Two separate connections keep the boundary explicit and
make "never mix tenant and central database logic accidentally" (§77) a
structural property rather than a code-review habit.

**Migration path.** If a large customer later needs physical isolation,
`stancl/tenancy` moves that single tenant to its own database without
application changes. Designing for row-level first does not close that door.

---

## 3. Domain layout

Per §78, with domain boundaries as the primary organising axis:

```
app/
  Domain/
    Tenancy/        provisioning, tenant context, entitlements
    Identity/       users, roles, permissions, teams
    Leads/          normalization, dedup, scoring, assignment, lifecycle
    Contacts/
    Companies/
    Deals/          pipelines, stages, quotes, products
    Campaigns/
    Automation/     workflow engine, triggers, conditions, actions
    Conversations/  omnichannel inbox, threading, SLA
    Messaging/      WhatsApp / email / SMS provider abstraction
    Appointments/   calendars, availability, booking links
    AI/             provider abstraction, qualification, agent, safety
    Billing/        plans, subscriptions, usage metering, entitlements
    Integrations/   provider framework, OAuth, sync engine, CRM connectors
    Forms/          form builder, embeds, submissions
    Reporting/      reports, attribution, analytics events
    Api/            public REST surface, keys, scopes, rate limits
    Notifications/
    Audit/

  Http/             controllers (thin), requests, resources, middleware
  Models/
  Jobs/  Events/  Listeners/  Policies/  Services/
```

Each `Domain/<Name>/` holds its own `Actions/`, `Services/`, `DTOs/`,
`Events/` and `Contracts/`. Models stay in `app/Models/` so Eloquent
relationships and factories remain conventional.

**Repositories only where they earn their place** (§4, §77). Do not generate one
per model. The integration and CRM-connector layers genuinely need the
abstraction; `Lead` does not.

---

## 4. Request flow

```
Browser ──► Inertia page  ──► Controller (thin)
                                  │
                                  ├─ FormRequest         validation
                                  ├─ Policy              authorization
                                  ├─ Entitlements        plan + quota check
                                  └─ Domain Action       business logic
                                          │
                                          ├─ Model / Eloquent
                                          ├─ Event ──► Listener ──► Job (queued)
                                          └─ AuditLog

External ──► /api/v1/*  ──► ApiKey auth ──► Scope check ──► same Domain Action
```

The critical property: **the Inertia controller and the API controller call the
same domain action.** Behaviour cannot drift between the UI and the public API,
and §101's "API-first" is satisfied structurally rather than by building the UI
on top of HTTP calls to ourselves.

---

## 5. Lead ingestion pipeline

Every source — webhook, form, import, API, polling — converges on one pipeline.
There is no per-provider ingestion code path.

```
Source event
     │
     ▼
 Store raw payload  ──────────────► integration_events (idempotency key)
     │                              duplicate key ⇒ ack and stop
     ▼
 Queue: ProcessLeadIngestion
     │
     ├─► Normalize       provider payload ──► common lead schema (§17)
     │                   provider-specific fields ──► metadata / custom fields
     ├─► Deduplicate     layered match (§18); never destroys data
     ├─► Enrich          where lawful and supported
     ├─► Score           rules + AI, explainable (§19)
     ├─► Assign          routing rules (§23)
     └─► Emit lead.created / lead.qualified
                │
                ├─► Automation triggers
                ├─► Notifications + SLA timers
                ├─► Outbound webhooks (signed, retried)
                └─► CRM connector push (Mode B)
```

**Idempotency** (§58) is keyed on `(tenant_id, provider, provider_event_id)`
with a unique index. Replayed webhooks are cheap no-ops. Ingestion returns an
ingestion ID immediately and processes asynchronously (§84).

---

## 6. Integration Hub

```php
interface IntegrationProvider
{
    public function authorizationUrl(TenantConnection $c): string;
    public function handleCallback(Request $r): ProviderToken;
    public function refreshToken(ProviderToken $t): ProviderToken;
    public function discoverAccounts(ProviderToken $t): Collection;
    public function subscribeWebhooks(ProviderAccount $a): void;
    public function verifyWebhook(Request $r): bool;
    public function fetchLeads(ProviderAccount $a, ?Cursor $c): LeadPage;
    public function disconnect(ProviderAccount $a): void;
    public function status(ProviderAccount $a): ProviderStatus;
}
```

Implementations: `Meta`, `Instagram`, `TikTok`, `LinkedIn`, `Google`,
`WhatsApp`, `CustomWebhook`.

Rules that keep this maintainable:

- **Version-aware** (§76). `provider_api_version` is stored per connection and
  read from provider config — never hard-coded in controllers.
- **Webhook-first, polling as reconciliation** (§101.11–12). Both paths land in
  the same ingestion pipeline.
- **Tokens encrypted at rest**; never logged, never returned to the client,
  never pasted by users unless the provider's supported developer workflow
  requires it (§11).
- **Sync state is explicit**: `sync_runs`, `sync_items`, `sync_errors`,
  `last_cursor`, `last_synced_at`, `status` (§57).
- **Errors are actionable** (§59): what happened, why, what to do, plus Retry /
  Reconnect / View logs.

### CRM connectors

Parallel framework for Mode B, same shape:

```php
interface CrmConnector
{
    public function pushLead(Lead $l): ExternalRecord;
    public function pullLeads(?Cursor $c): LeadPage;
    public function syncContact(Contact $c): ExternalRecord;
    public function syncDeal(Deal $d): ExternalRecord;
    public function fieldMap(): FieldMap;
}
```

Targets: Salesforce, HubSpot, Zoho, Pipedrive, Freshsales, custom REST.
Duplicate prevention is via `external_system` + `external_record_id`, with a
configurable conflict strategy per field map (§83).

---

## 7. Messaging and AI abstraction

Both follow the same shape, for the same reason: the spec forbids hard-coding a
single vendor (§4, §101.20).

```php
interface MessageChannel {  // WhatsApp, Email, SMS
    public function send(OutboundMessage $m): DeliveryReceipt;
    public function templates(): Collection;
    public function supports(Capability $c): bool;
}

interface AiProvider {      // OpenAI, Anthropic, Gemini, Azure OpenAI
    public function complete(Prompt $p, AiOptions $o): AiResponse;
    public function stream(Prompt $p, AiOptions $o): Generator;
    public function costOf(AiResponse $r): TokenCost;
}
```

**AI safety controls are architectural, not prompt-level** (§89):

- Tenant-approved knowledge only; the agent cannot invent prices or policies.
- Business hours, opt-out state and messaging limits checked _before_ send, in
  the dispatch layer — not left to the model.
- Every AI action logged with a concise user-facing reason. Chain-of-thought is
  never exposed or stored (§20).
- **AI kill switch** per tenant, halting all autonomous actions immediately.
- Autonomous sends require an explicit tenant automation grant (§27).

---

## 8. Queues

Redis-backed, supervised by Horizon.

| Queue          | Work                                 | Priority |
| -------------- | ------------------------------------ | -------- |
| `realtime`     | Inbound messages, webhook receipt    | Highest  |
| `ingestion`    | Lead normalization, dedup, scoring   | High     |
| `messaging`    | WhatsApp / email / SMS dispatch      | High     |
| `automation`   | Workflow execution                   | Normal   |
| `ai`           | Qualification, drafting, agent turns | Normal   |
| `integrations` | Sync runs, reconciliation, CRM push  | Normal   |
| `bulk`         | Imports, exports, campaigns, reports | Low      |

Every job is **retryable, idempotent, observable and tenant-aware** (§56).
Tenant context is serialized onto the job and restored on handle — a job that
cannot resolve its tenant fails loudly rather than running unscoped.

---

## 9. Entitlements

One service, checked everywhere, hard-coded nowhere (§93, §101.28).

```php
$entitlements->hasFeature('automation');              // plan grants the feature
$entitlements->withinLimit('automation_runs', $n);    // quota has headroom
$entitlements->remaining('whatsapp_messages');        // for UI display
```

Surfaces:

- `EnsureFeature` / `EnsureWithinLimit` middleware on routes.
- Inertia shares an entitlement map so the UI hides and disables consistently.
- Usage recorded through `usage_meters` / `usage_records` (§9) with warning
  thresholds, hard-limit and overage behaviour configured per plan.

Plan checks must never appear inline in a controller.

---

## 10. Security

Mandatory controls from §55, with the non-obvious ones called out:

- Encrypted OAuth tokens and provider secrets; secrets in AWS Secrets Manager.
- Signed inbound webhooks (verify before queuing) and signed outbound webhooks.
- API key scopes (§48); **secret shown once at creation and never again**.
- Tenant isolation verified by automated test, not by inspection.
- Rate limiting at tenant, API-key, endpoint and IP level (§51).
- Secure uploads: type allowlist, size caps, out-of-webroot storage, malware
  scanning where required.
- Full audit trail: actor, tenant, action, entity, before/after, IP, UA,
  timestamp (§54).

**Never logged:** access tokens, refresh tokens, API secrets, passwords, private
keys. Enforce with a log processor that redacts known-sensitive keys, so this
survives a careless `Log::info($payload)`.

---

## 11. Observability

Horizon for queues; structured application logs; Sentry (or equivalent) for
error tracking.

Tracked metrics (§74) — these are the ones that predict customer-visible
failure:

- Lead processing latency (source event → lead visible in UI)
- Webhook receipt and delivery latency; outbound delivery failure rate
- Message delivery rate per channel
- Automation failure rate; queue backlog depth per queue
- API error rate per tenant; provider rate-limit headroom
- AI token usage and cost per tenant

Integration health, token expiry and webhook health surface in the tenant-facing
Integration Dashboard (§65) — customers should see a broken connection before
they see missing leads.
