# Module Specification

Every module in this document is **multi-tenant, permission-aware,
subscription-aware, auditable, testable and responsive** (§104). Those
properties are assumed below rather than restated per module.

Permission keys follow `<entity>.<action>` (§68). Modules are listed in build
order where dependencies exist.

---

## Platform Admin

Reachable only by platform users, on the central connection. Navigation per §60.

### Tenants

Provision, suspend, impersonate (audited), delete. Shows plan, usage, health,
integration status. Provisioning (§7) runs as an **idempotent, resumable job** —
a tenant stuck mid-provision resumes from the failed step rather than starting
over or being hand-repaired.

Steps: tenant record → identifier → schema → migrations → owner → default roles
→ permissions → default pipeline → lead stages → dashboard layout → templates →
notification preferences → subscription → entitlements → (API credentials on
request) → welcome notification.

### Plans & Features

CRUD over `plans` / `plan_features` against the `feature_catalog`. Features are
boolean, limit or metered. Nothing here is hard-coded in application code
(§101.28) — adding a limit is a data change, not a deploy.

### Subscriptions & Billing

Lifecycle: `Trial → Active → Past Due → Paused → Cancelled → Expired`, plus
grace period. Monthly/annual, upgrades, immediate or scheduled downgrades,
cancellation, reactivation, invoices, receipts, taxes, coupons, overage.

Behind `BillingProviderInterface` (§97) — Stripe, PayPal, regional gateways,
manual bank transfer, invoice billing. Provider webhooks are idempotent on
`provider_event_id`.

### Usage

Per-tenant meters (§9) with current, limit, remaining, projected, warning
threshold, hard-limit behaviour and overage behaviour.

### Providers, Templates, Flags

`integration_providers` (enable/disable, API version, config), AI provider
credentials and routing, message provider credentials, platform template library
(§67, §98, versioned and clonable by tenants), feature flags with percentage
rollout and tenant allowlist (§94).

### System & Audit Logs

Central `audit_logs` with actor / tenant / action / entity / before / after / IP
/ UA / timestamp, filterable and exportable.

**Permissions:** `platform.tenants.*`, `platform.plans.*`, `platform.billing.*`,
`platform.integrations.*`, `platform.settings.*`, `platform.audit.view`

---

## Tenant modules

### Dashboard

Widget-driven and configurable (§36, §109, §101.29). Multiple dashboards per
user, role-based defaults, drag/drop, resize, hide, duplicate, reset, per-widget
filters and date range, saved layouts.

Widgets read from the `dashboard_widgets` catalog and honour the §80 contract:

```json
{
    "id": "lead_overview",
    "title": "Lead Overview",
    "type": "kpi",
    "permission": "lead.view",
    "configurable": true,
    "refreshable": true,
    "date_filter": true
}
```

Catalog spans Lead Metrics, Sources, Funnel, Quality, AI Qualification,
Pipeline, Deals, Revenue, Conversion, Campaigns, WhatsApp, Email, SMS,
Appointments, Tasks, Automation, Team Performance, Response Time, SLA,
Attribution, Integration Health, Subscription Usage, API Usage.

Role presets: Sales (§62), Marketing (§63), AI (§64), Integration (§65).

Heavy widgets lazy-load; each has its own loading, empty and error state (§37).

**Permissions:** `dashboard.view`, `dashboard.configure`, `dashboard.share`

---

### Leads

The core module. Everything else exists to feed or act on it.

**Lifecycle (§85):** Captured → Normalized → Deduplicated → Enriched → Scored →
Assigned → Contacted → Qualified → Opportunity → Proposal → Won/Lost → Customer.
Every transition audited.

**Normalization (§17).** All sources map to one common schema. Provider-specific
fields go to `metadata` / custom fields — never into core columns.

**Deduplication (§18).** Layered: provider external ID → normalized email →
normalized phone → company + email domain → name + phone → configurable fuzzy →
AI similarity where useful. On match: keep master, attach the source event,
merge non-conflicting fields, preserve history and attribution, write an audit
record. **Never silently destroy data** — duplicates become tombstones via
`merged_into_id`.

**Scoring (§19).** Manual, rule-based, AI or hybrid — and always _explainable_:

```
Lead Score: 82
  +25 Demo requested       +20 High purchase intent
  +15 Budget provided      +10 Phone provided      +12 Recent response
```

Bands (Cold / Nurture / Warm / Hot / Qualified) and thresholds are
tenant-configurable; the band is stored so retuning does not rewrite history.

**Assignment (§23).** Round robin, least assigned, territory, country, product,
source, campaign, score, team, business hours. Rules compose:
`IF country = UAE AND score >= 70 THEN assign UAE Sales Team`.

**Detail screen (§44).** Tabs: Overview, Activity, Conversations, Emails,
WhatsApp, Deals, Tasks, Appointments, Notes, Documents, Timeline, AI Analysis,
Source, Audit. Right rail: score, stage, owner, company, contact details, next
action.

**List:** full DataTable standard (§110–115) — Lead, Company, Source, Score,
Stage, Assigned To, Last Activity, Next Follow-up, Created, Actions.

**Permissions:** `lead.view|create|update|delete|export|assign|merge`

---

### Contacts / Companies

Standard CRM records with polymorphic activities, tasks, notes, attachments and
tags. Contacts link to companies many-to-many with a primary flag. Both carry
`external_*` columns for Mode B.

**Permissions:** `contact.*`, `company.*`

---

### Deals & Pipelines

Multiple pipelines per tenant (§22). Kanban with drag/drop plus a table view.
Stages carry probability, required fields, entry/exit automation,
stage-specific tasks and notifications, and per-pipeline permissions.

Products, quotes (with line items, validity, accept flow) and recorded sales
with attribution close the revenue loop.

**Permissions:** `deal.*`, `pipeline.view|manage`, `quote.*`, `product.*`

---

### Omnichannel Inbox

Single interface across All / WhatsApp / Email / Facebook / Instagram / SMS
(§24). Three panes: conversation list, thread, lead context.

Search, filters, tags, assignment, internal notes, AI-suggested response,
template insertion, attachments, status, unread state, SLA timers, customer
timeline. Realtime via Reverb. Mobile uses a full-screen single-pane flow (§71).

**Every outbound send checks opt-out and business hours before dispatch** — in
the dispatch layer, not the UI.

**Permissions:** `conversation.view|reply|assign|close`

---

### Messaging

**WhatsApp (§15).** Business account connection, phone number selection,
approved templates with sync of approval status, inbound/outbound, delivery and
read status, media, assignment, AI replies, automation, opt-out, business hours.
No unsolicited bulk messaging.

**Email (§25–27).** Accounts (SMTP / OAuth provider), signatures, templates,
sequences, campaigns, inbound with reply detection, attachments, scheduling,
tracking where legally and technically appropriate.

Composer offers three paths: **Use Template / AI Draft / Write Manually**.
AI modes: Professional, Friendly, Short, Detailed, Sales, Follow-up, Proposal,
Re-engagement, Appointment — with Generate, Regenerate, Edit, Save as Template,
Send, Schedule. **AI never auto-sends unless tenant automation explicitly grants
it** (§27).

**SMS.** Provider abstraction (Twilio, Vonage, regional), templates, delivery
status, opt-out keywords.

**Templates (§26).** User-authored with `{{variable}}` interpolation. Variables
are validated against a tenant-safe allowlist — an unresolvable or unauthorized
variable fails validation at save time, not at send time.

**Permissions:** `message.send`, `template.view|create|update|delete`,
`email_account.manage`

---

### Automation

Visual workflow builder (§28) on a node graph: trigger → conditions → actions,
with delays and branches.

Triggers, conditions and actions are exactly as enumerated in §28 — 15 triggers,
14 condition types, 18 action types.

**Three modes (§29), one engine with different gates:**

| Mode       | Behaviour                                 |
| ---------- | ----------------------------------------- |
| Manual     | Recommends the action                     |
| Approval   | Prepares the action and waits for a human |
| Autonomous | Executes per tenant rules                 |

Every autonomous workflow must have: enable/disable, limits, quiet hours, retry
policy, error handling, audit logs and a **kill switch**.

Runs are idempotent on `(tenant_id, workflow_id, entity, idempotency_key)`.

**Permissions:** `automation.view|create|execute|manage`

---

### AI

Provider-agnostic across OpenAI, Anthropic, Gemini, Azure OpenAI (§4, §101.20).

**Qualification (§20).** Returns structured output — score, intent,
qualification, buying stage, budget signal, urgency, recommended action, and
concise user-facing reasons. **Chain-of-thought is never exposed or stored.**

**Sales Agent (§30).** Configured with name, business, products, services,
pricing, FAQs, knowledge sources, tone, languages, business hours, sales rules,
qualification rules, escalation rules, handoff rules. It answers questions,
qualifies, collects missing information, recommends, sends approved information,
schedules, creates/updates CRM records, escalates, summarizes.

**It must not invent prices, policies, guarantees or business facts.** Enforced
by restricting it to tenant-approved knowledge, not by prompt instruction alone.

**Handoff (§31).** Escalates on: request for human, low confidence, pricing
exception, refund/legal complaint, sensitive issue, complex negotiation, angry
customer, high-value opportunity, configured keyword. Creates a task and
notifies the assigned team.

**Safety (§89).** Business hours, opt-outs, messaging limits and escalation
rules are checked in the dispatch layer. All actions logged with reasons.
Per-tenant **AI kill switch** halts all autonomous actions immediately.

**AI Dashboard (§64):** conversations, qualified leads, generated vs assisted
messages, handoffs, conversion, cost, token usage, provider usage.

**Permissions:** `ai.view|configure|execute`, `ai.agent.manage`, `ai.killswitch`

---

### Campaigns

Types per §33: Lead Generation, Email, WhatsApp, SMS, Nurture, Retargeting,
Re-engagement, Product Promotion, Event.

Tracks sent, delivered, opened, clicked, replied, qualified, converted, revenue
— "where supported" is honest here: open and click tracking are recorded only
where the channel and law permit.

**Permissions:** `campaign.view|create|manage`

---

### Forms & Website Capture

Builder (§16) producing Contact, Lead, Demo Request, Quote Request, Appointment,
Newsletter and Custom forms across 14 field types.

Each form generates an **embed code, JS snippet, REST endpoint and webhook**.

Captures UTM source/medium/campaign/content/term, landing page, referrer,
timestamp, form ID and campaign ID — the raw material for attribution.

**Permissions:** `form.view|create|update|delete`

---

### Appointments & Calendar

Google Calendar, Microsoft Calendar where supported, internal calendar (§32).
Availability, meeting types, booking links, time zones, working hours, blackout
dates, reminders, rescheduling, cancellation. Lead conversion can trigger
booking.

**Permissions:** `appointment.view|create|update|cancel`, `calendar.connect`

---

### Integrations

Cards per provider with explicit connection state (§11): Facebook/Meta,
Instagram, TikTok, LinkedIn, Google Ads, WhatsApp.

Connect flow: explain permissions → provider OAuth → exchange code → encrypt
token → discover assets → tenant selects accounts → subscribe webhooks →
initial sync → connected status. **Users are never asked to paste access
tokens** unless a provider's supported developer workflow requires it.

Per provider, per §12–14: Meta (Lead Ads, Pages, Instagram business, supported
messaging and events), LinkedIn (Lead Sync for approved Lead Gen Forms, webhooks
plus polling reconciliation), TikTok (approved business/ad assets), Google Ads,
WhatsApp Business Platform.

**No arbitrary profile scraping for any provider** (§2). Store only fields the
authorized API returns.

Integration Dashboard (§65): connected/disconnected accounts, sync status, last
sync, webhook health, API errors, token expiration, provider rate limits.

Errors are actionable (§59) — what happened, why, what to do, with Retry,
Reconnect and View logs.

**Permissions:** `integration.view|connect|disconnect|sync`

---

### CRM Connectors (Mode B)

`CrmConnectorInterface` (§82) for Salesforce, HubSpot, Zoho, Pipedrive,
Freshsales and custom REST.

Field mapping UI (§83) with one-way or two-way sync, conflict strategy, default
values and transformations. Duplicate prevention via `external_system` +
`external_record_id`.

**Permissions:** `crm.view|connect|map|sync`

---

### Public API & Developer Portal

Versioned at `/api/v1/` (§47). Endpoints for leads, contacts, companies, deals,
activities, tasks, messages, conversations, appointments, pipelines, users,
webhook tests and usage.

Auth (§48): tenant API keys, scoped keys, OAuth applications, bearer tokens,
webhook signatures. Scopes: `leads.read`, `leads.write`, `contacts.*`,
`deals.*`, `messages.send`, `appointments.write`, `webhooks.manage`.
**Secret keys are never returned after creation.**

Webhooks (§49): 16 event types, signed, retried with exponential backoff,
delivery logs, response status, replay, auto-disable after repeated failure.

Inbound ingestion (§84): `POST /api/v1/inbound/leads` processes asynchronously
and returns an ingestion ID.

Rate limits (§51) at tenant, key, endpoint and IP level, with standard headers.

Portal (§50): overview, authentication, keys, OAuth apps, endpoints, webhooks,
schemas, examples, rate limits, logs, usage, sandbox, changelog — with OpenAPI
generated from the code, not maintained by hand.

**Permissions:** `api.view|create|revoke`, `webhook.view|manage`

---

### Import / Export

Wizard (§52): Upload → Detect columns → Map fields → Validate → Preview →
Duplicate strategy → Import → Report. CSV, XLSX, JSON.

Export of current view, filtered records, selected records, or scheduled
reports. **Large imports and exports run on the `bulk` queue** — never blocking
the browser.

**Permissions:** `lead.import|export` and per-entity equivalents

---

### Reports & Attribution

Reports (§61) across lead source, funnel, quality, conversion, sales, revenue,
campaign, email, WhatsApp, SMS, salesperson, team, pipeline, AI, automation, API
and usage. Date range, filters, grouping, export, scheduled delivery.

Attribution (§34) retains source, medium, campaign, ad account, campaign ID,
ad ID, form ID, landing page, UTM values, referrer, first touch, last touch and
conversion source — producing leads by source, qualified by source, deals by
source, revenue by source, conversion rate, cost per lead, cost per qualified
lead and ROI.

Backed by `analytics_events` (§90), not by live CRM queries.

**Permissions:** `report.view|create|schedule|export`

---

### Settings

Sections per §66: Business, Users, Roles, Permissions, Teams, Lead Settings,
Pipeline, Custom Fields, Notifications, Email, WhatsApp, SMS, Social Accounts,
AI, Automation, Templates, Calendar, API, Webhooks, Billing, Security, Audit.

**Custom fields (§53)** across leads, contacts, companies, deals and products in
12 types — permission-aware and available to automation conditions and actions.

**SLA policies (§87):** e.g. hot lead contacted within 5 minutes, qualified
within 30, new within 15. On breach: notify rep, notify manager, escalate,
record the violation.

**Consent & compliance (§88):** consent status, source and timestamp, privacy
policy version, communication preferences, unsubscribe status and per-channel
opt-out. Every channel respects opt-out state.

**Permissions:** `settings.view|manage`, `user.*`, `role.*`, `team.*`,
`custom_field.manage`, `billing.view|manage`

---

## Cross-cutting

### Global search & command palette (§45, §120)

`Ctrl/Cmd + K`. Debounced, categorized, keyboard-navigable, with recent
searches. Covers leads, contacts, companies, deals, conversations, tasks,
appointments, campaigns, help and settings, plus quick actions (create lead /
contact / deal, send message, create task, open inbox, start import, connect
integration).

**Results respect tenant isolation and user permissions** — a permission-filtered
search is the one place a leak would be least visible, so it is tested directly.

### Notifications (§46)

In-app center plus optional email, WhatsApp and browser push, with per-user
per-event channel preferences. Events: new lead, hot lead, lead assigned,
customer replied, appointment booked/cancelled, deal won, automation failed,
integration disconnected, subscription issue, API limit warning.

### Audit (§54)

Login, logout, lead created/edited/deleted, assignment, stage change, message
sent, template changed, automation changed, integration connected/disconnected,
API key created/revoked, subscription changed, permission changed — each with
actor, tenant, action, entity, before, after, IP, UA and timestamp.
