# Development Roadmap

Phases follow the MVP ordering in §99, with a **Phase 0** inserted ahead of it.
Phase 0 exists because §99's MVP 1 assumes a foundation the current starter kit
does not yet have — attempting MVP 1 directly means retrofitting tenancy and
RBAC into code already written against neither.

Each phase lists deliverables and **exit criteria**. A phase is not done when
the code exists; it is done when the exit criteria pass.

Estimates assume a small team (2–3 backend, 1–2 frontend, 1 designer/QA shared).
They are sequencing guidance, not commitments.

---

## Phase 0 — Foundation

_~2 weeks. Nothing customer-visible. Everything downstream depends on it._

| #    | Deliverable                                                                                                                       |
| ---- | --------------------------------------------------------------------------------------------------------------------------------- |
| 0.1  | **PostgreSQL** replaces the SQLite starter default; Redis for cache and queue; Docker Compose for local parity                    |
| 0.2  | Packages installed and configured (see [`01-ARCHITECTURE.md`](01-ARCHITECTURE.md#packages-to-add))                                |
| 0.3  | `app/Domain/*` skeleton with the module boundaries from §78                                                                       |
| 0.4  | **Tenancy foundation**: `BelongsToTenant`, global scope, tenant context resolution, central vs tenant connections, PostgreSQL RLS |
| 0.5  | **The isolation test that fails the build** — see below                                                                           |
| 0.6  | Design tokens (`@theme`, light + dark), brand accent layer, Inter + Poppins                                                       |
| 0.7  | AppShell, Sidebar, Topbar, theme switcher (persisted, applied pre-paint)                                                          |
| 0.8  | Base `ui/` primitives; Lucide wired; global toast service; centralized error handling                                             |
| 0.9  | Selected brand's favicon/app-icon set copied into `public/` and referenced                                                        |
| 0.10 | CI: Pint, PHPStan, Pest, `vue-tsc`, ESLint — all gating merges from day one                                                       |

**Exit criteria**

- `composer ci:check` passes clean.
- A test proving tenant A cannot read tenant B's data — at the model scope layer _and_ with the scope deliberately bypassed (RLS backstop).
- The schema test fails the build when a tenant-owned table lacks `tenant_id`, its FK or its index.
- Light and dark mode render correctly with no flash of wrong theme.

> **0.5 is the highest-leverage task in the entire project.** Tenant isolation
> is trivially cheap to enforce now and ruinously expensive to retrofit once
> forty models exist. Write the failing test before the models.

---

## Phase 1 — Core platform (MVP 1)

_~6–8 weeks. First usable product._

**Status.**

- **1.6 lead engine — done.** Normalization, layered deduplication,
  explainable rule-based scoring, assignment and the capture pipeline.
- **1.7 CRM — done.** Companies, contacts, pipelines, stages and
  deals, with stage rules, terminal-stage outcomes, stage-duration tracking
  and Kanban card ordering.

104 tests cover both. Lead sources, scoring rules and the default deal
pipeline are created per workspace by the seeder today, and move into the
provisioning job in 1.2.

- **1.9 DataTable — done.** Row selection with indeterminate header state,
  sortable columns, debounced server-side search, filters, column visibility,
  density, bulk-action bar and the §112 footer. Leads list built on it.
- **Kanban — done.** Deal board with drag-and-drop, plus a keyboard "move to"
  menu, because native HTML5 drag is pointer-only and §115 does not allow a
  board that needs a mouse.

- **1.14 REST API — done.** 28 endpoints at `/api/v1` covering leads,
  contacts, companies, deals and pipelines, authenticated by prefixed, hashed
  keys with implied read scopes, rate limited per key, and logged per request
  in `api_requests`. Writes go through the same domain actions as the UI, so an
  integration gets the same deduplication, matching, scoring and stage rules
  rather than a parallel code path. Keys are managed at `/settings/api-keys`;
  the secret is shown for exactly one render and the row is revoked, never
  deleted, so the request log stays readable. 102 tests.

    Three decisions worth knowing about:

    - Contacts and companies **upsert** rather than create. Posting the same
      person or organisation twice fills gaps in one record and returns 200 with
      `meta.is_duplicate`, so a retrying client cannot manufacture duplicates.
      Companies match on normalised domain first and exact name second — "Acme"
      and "Acme Holdings" stay separate, because merging them is not something a
      human can undo.
    - An email domain **attaches** a contact to an existing company but never
      creates one, and consumer mailbox providers are excluded outright
      (`PersonalEmailDomain`). Everyone on gmail.com does not work at the same
      place.
    - Employment lives at `/contacts/{id}/companies`, not as a field, because it
      carries a role and decides which employer is current. `ContactCompany` is a
      typed, tenant-scoped pivot model rather than a bare join row.

    The CRM relations also had two latent defects that nothing had exercised
    before: both `belongsToMany` calls relied on Laravel deriving
    `company_contact` while the migration created `contact_company`, and neither
    filled the pivot's `tenant_id`. Both are fixed in the models.

- **1.14 scope honesty.** `ApiScope` still names the whole planned surface, but
  `messages.send`, `appointments.write` and `webhooks.manage` are marked
  unavailable and are neither offered in the UI nor grantable, because a scope
  with no endpoint behind it is a capability claim the product does not honour
  (§124). They become grantable with the phases that build them.

- **1.15 outbound webhooks — done.** Endpoints subscribe to events, deliveries
  are signed, retried with exponential backoff and jitter, logged per attempt,
  replayable by hand, and an endpoint retires itself after 20 consecutive
  failures. Managed at `/settings/webhooks`, with a test send so a subscriber can
  prove their verification before a real event depends on it. 48 tests.

    The decisions that shaped it:

    - **Signing** is `t=<unix>,v1=<hmac-sha256 of "<t>.<raw body>">` in
      `X-Revora-Signature`, with a five-minute tolerance. The timestamp is inside
      the signed string, so a captured request cannot be made to look fresh by
      moving it. `WebhookSignature::verify()` is public because it is the reference
      implementation the docs point at, and a verifier nobody can run is a verifier
      nobody trusts.
    - **One job per endpoint**, so a subscriber that is down cannot delay delivery
      to every other subscriber of the same event. A 4xx other than 408/429 settles
      immediately instead of burning three more attempts on a URL that is wrong.
    - **Replay creates a new row** and re-sends the stored payload. The original
      attempt and its response are evidence of what happened; overwriting them
      would destroy the answer to "did you ever send this".
    - **HTTPS only.** A signature proves who sent a payload, not that nobody read
      it, and these carry personal data (§88).
    - `RunsInTenantContext` restores the workspace inside the job, because a queue
      worker keeps nothing from the request that queued it and the global scope
      would otherwise fail closed on a worker (ADR-010). The worker must process
      the `webhooks` queue.

    Writing the emission rules surfaced a genuine design problem. The capture
    pipeline saves a lead two or three times while scoring and routing it, so
    anything reacting to `updated` fired repeatedly for one logical event, and an
    enriched duplicate was announced twice. `Lead::$isBeingCaptured` — transient,
    never persisted — now marks the window, and `CaptureResult::wasEnriched()`
    distinguishes "we saw this person again" from "we learned something", so a
    repeat touch that changes nothing stays silent.

- **1.16 audit logging — done.** 23 actions across access, records and
  integrations, each row carrying actor, workspace, entity, before, after, IP,
  user agent and time. Read-only at `/settings/audit`, filtered in the database,
  behind `audit.view`. 32 tests.

    - **Credentials never reach it.** `AuditRecorder` redacts by substring
      (`password`, `secret`, `token`, `hash`, …) rather than by an exact-name list,
      because an exact list misses the next secret somebody adds, and it recurses
      into nested arrays. A failed sign-in records the address that was tried and
      not the password it was tried with (§55).
    - **An API key is an actor in its own right**, not the person who created it.
      "The Zapier key deleted this" and "Amara deleted this" are different facts,
      and conflating them makes the trail lie.
    - **No actor at all** for queue, scheduler and console work. A request object
      still resolves there, so the absence of a client address is what
      distinguishes it — inventing an IP would be worse than leaving it blank.
    - **Only the diff** for an edit, the whole record for a deletion. An unchanged
      column is noise that hides the change somebody actually made; "what was in
      the thing that is now gone" is why a deletion audit exists.
    - Assignment and stage moves get their own actions, because both are
      single-column updates that the generic entity audit would file as "edited",
      and they are what the trail is read for. Stage moves record stage _names_: a
      row read six months later must make sense without the pipeline open beside
      it.
    - Written synchronously, inside whatever transaction the audited action runs
      in, so a change and the record of it cannot disagree. `Lead::$isBeingCaptured`
      keeps the capture pipeline's own saves out of the trail, the same flag the
      webhook layer uses.
    - There is **no route to edit or delete an entry**. A trail somebody can tidy
      up is not evidence of anything.

- **1.17 global search and command palette — done.** ⌘/Ctrl-K opens a palette
  over the current page, searching leads, contacts, companies and deals, with
  debounced requests, grouped results, keyboard navigation, recent searches and
  quick navigation. 17 tests.

    - **SQL, not a search engine.** The palette shows five rows per entity and the
      columns involved are indexed; Meilisearch would add an index that can be
      stale and a service that can be down in exchange for relevance nobody has
      asked for. Swapping it later touches only `SearchService`.
    - **An entity the user cannot view is never searched**, rather than searched
      and filtered afterwards — and a user with none of the four view permissions
      gets an empty result rather than an error.
    - Results stay **grouped by entity**: "the Acme company" and "the Acme deal"
      are different answers to the same word, and interleaving them makes the user
      read every row to find which is which. Keyboard navigation runs over one flat
      sequence with per-section offsets, so Up/Down follows reading order.
    - A two-character minimum, `LIKE` wildcards escaped so a literal `%` is not a
      table scan, each request tagged so a slow earlier response cannot overwrite a
      newer answer, and recent searches in `localStorage` with every read and write
      guarded.

- **Contacts and companies list screens — done.** Built as part of 1.17 rather
  than left for later: search results linked to them, and shipping a palette
  whose rows lead nowhere would have been worse than not shipping it. Both follow
  the leads list exactly — server-side search, filter, sort and pagination
  (§112), deferred filter options, sortable `contacts_count` / `deals_count` in
  the database because "our biggest accounts" is why that list gets opened. 28
  tests. This closes 1.7's remaining frontend.

- **Bulk actions (§113) — done.** Assign, change status, add tag, remove tag and
  delete across a selection, with per-action permissions, confirmation naming the
  exact count, real progress for long runs, partial-result summaries and audit.
  23 tests.

    - **Iterates models; never issues a mass UPDATE.** A mass update is faster and
      wrong: it bypasses observers, so the change would reach no audit row, fire no
      webhook and skip the normalised columns the save hooks maintain. A bulk edit
      has to mean the same thing as the same edit made one row at a time, and there
      is a test asserting exactly that.
    - **Permission per action, not per "bulk".** Someone who may reassign leads is
      not thereby allowed to delete them, so the check happens in the controller
      where the action in the body is known rather than on the route.
    - **One record failing does not fail the operation.** Twenty-four rows where two
      have since been deleted updates twenty-two and says so. Ids are re-resolved
      through the tenant scope first, so an id from another workspace is not even
      counted in the total and the summary cannot imply it was touched.
    - **Inline up to 200, queued above it.** A selection that finishes inside the
      request gets its answer immediately rather than a progress bar for something
      that took 80ms; above the threshold `bulk_operations` carries real progress
      the page polls by uuid. The job has `tries = 1`, because a retry would
      reapply the action to records it already changed.
    - The operation itself is audited in addition to the per-record rows, otherwise
      the trail shows fifty edits and not the single decision that caused them.

    The `taggables` pivot had the same two latent defects `contact_company` did —
    no `tenant_id` on attach, and no scoping on the join. Fixed on all four
    taggable models.

- **Create and edit forms — done.** Leads, contacts, companies and deals can
  each be created, edited and deleted from the UI. 70 tests.

    Until this landed, every "New …" button and every row's edit pencil was inert:
    the lists, the bulk actions and the REST API were all built on top of records
    that could only arrive through a seeder or an integration. That was the gap.

    - **A shared `Drawer`** rather than a page per form. Editing happens from a
      list, and losing your filters and scroll position to navigate away is the
      thing CRM users complain about. It is a real modal dialog: focus trapped,
      focus returned on close, Escape, `aria-modal`, body-scroll locked, and it
      refuses to close mid-save.
    - **Every form runs the same domain action its API counterpart does.** A lead
      typed in by a rep goes through `CaptureLead`, so it is deduplicated, scored,
      routed, audited and announced identically to one that arrived over a webhook
      — §5 and §85 list manual entry alongside the other sources for exactly this
      reason. Contacts and companies run their upserts. Deals run `CreateDeal`, so
      the opening stage-change record exists and duration reporting has a start.
    - **"Already on file" is said out loud.** When a create matches an existing
      record the flash explains that the details were added to it, rather than
      leaving the user hunting for a row that was never created.
    - **A deal's stage is shown, not edited.** A move recalculates probability, may
      close the deal and reorders two columns; the field is simply absent from the
      ruleset, as it is over the API. Changing a deal's pipeline is refused for the
      same reason: it would orphan the deal on a stage belonging to the old one.
    - Rows carry the fields their form edits, so a drawer opens on data already in
      hand rather than spending a request on a record the table just delivered.

    **`Rule::exists` does not respect the tenant scope.** It builds its own query
    straight on the table, so Eloquent's global scope never runs — the first
    version of the lead form accepted an `owner_id` from another workspace and
    would have assigned ownership across the boundary. Every `exists` in a form
    request is now scoped explicitly with a comment saying why. This is the one
    place the fail-closed scope cannot help, and it is worth remembering before
    the next form is written.

- **Lead verification — done.** Answers "are these details genuine" for every
  lead, whatever door it came through. Four states (not checked / verified /
  needs a look / not usable), a confidence score, and the findings that produced
  them. 20 tests.

    The pipeline is now `normalize → deduplicate → score → verify → assign → emit`.

    - **Source-independent on purpose.** A lead from an authorized provider feed
      can carry a typo and a hand-typed one can be perfect. Provenance stays a
      separate signal with its own badge (§2).
    - **Checks:** address syntax, MX/A record, throwaway providers, role
      addresses, placeholder values, low-vowel mailbox names, phone length against
      E.164, repeated digits, whole-number digit runs, placeholder names, and
      whether consent was ever recorded (§88).
    - **Risky is kept, not binned.** `info@` is how plenty of small businesses
      genuinely reply, and a free mailbox matters for enterprise sales and not at
      all for B2C — so both are visible reasons rather than rejections. Only a lead
      that cannot be reached at all is excluded, which is what `scopeWorkable`
      means and what the list's "Reachable only" toggle filters on.
    - **Routing skips unworkable leads.** Handing a rep an address that cannot
      receive mail wastes the one resource assignment exists to allocate.
    - **The DNS lookup is off inline, on from the queue.** A form must not wait on
      somebody else's resolver, so capture verifies without it and `VerifyLead`
      re-runs with it immediately after — usable at once, accurate a second later.
    - Every verdict carries words a rep can act on (§59). A flag nobody can
      explain is a flag nobody trusts, and "not usable" with no reason cannot be
      corrected.

    Two heuristics needed tightening against real data while writing the tests:
    gibberish detection is conservative (flagging a real person costs more than
    missing a bot, so `jo@` and `a.okafor@` must pass), and the digit-run check
    requires the run to cover the whole number — the valid UAE mobile
    `+971501234567` contains `1234567`, so anything looser rejects genuine leads.

    **Not built, and deliberately not:** scraping leads from Facebook, Instagram,
    LinkedIn or job boards. §2 is authorized APIs only, and a scraped record has no
    lawful basis to put in `consent_source`, which §88 requires before anyone is
    contacted. The authorized equivalents — Meta Lead Ads API, LinkedIn Lead Gen
    Forms, job-board partner APIs — give the same reach with consent captured at
    source, and `IntegrationProviderSeeder` already anticipates them.

- **Phone validation, every country — done.** Backed by Google's libphonenumber
  metadata (`giggsey/libphonenumber-for-php-lite`, approved as a dependency).
  50 tests, covering valid mobiles across 22 countries.

    - **Validity is judged against each country's numbering plan**, not a digit
      count. `+97141234567` has a valid UAE prefix and twelve digits and is still
      not a UAE number; nothing short of the real metadata can tell you that.
    - **Mobile is distinguished from landline, VoIP, toll-free and premium rate** —
      except where the plan genuinely does not distinguish them. North America is
      `FIXED_LINE_OR_MOBILE`, so `isMobile()` accepts it: insisting on a definite
      MOBILE would reject every American mobile there is. The code says so rather
      than guessing.
    - **A landline warns, it does not fail.** Whole industries answer theirs; it
      just cannot be texted. Premium-rate and shared-cost lines do fail — that is
      not how somebody asks to be contacted.
    - `phone_type` and `phone_country` are stored, because "can this lead be
      texted" decides which channel reaches them and parsing on every send would
      put Google's metadata on the hot path of every message.
    - A region hint is taken in order of how much it deserves trust: the number's
      own prefix, then the record's country, then `verification.default_region`.
      Guessing from the server locale would silently mangle numbers for every
      workspace not in that country, so with no hint a national number stays
      honestly unparseable.

    **This fixed a real deduplication hole.** `phone_normalized` was digits-only,
    so `050 123 4567` and `+971 50 123 4567` never matched — the same person
    entered both ways stayed two leads. It is now E.164, and all four common
    notations collapse to one key. A `CaptureLeadTest` case had been _asserting_
    the old limitation; it now asserts the fix.

    Two bugs surfaced while testing: a leading `00` is the ITU international
    prefix and libphonenumber only reads it as one when it knows the caller's
    country, so `00971…` parsed to nothing until it was rewritten to `+` (`011` is
    handled too, for North America). And the old digit-run heuristic flagged the
    valid UAE mobile `+971501234567`, because it contains `1234567` — deleted
    entirely, since the metadata answers the question properly.

    **`php artisan leads:renormalise-phones` backfills existing rows**, chunked by
    id, tenant by tenant, `--dry-run` first, and quietly — a normalisation is not
    an edit anyone made, so it fires no webhook and writes no audit row. Rows
    written by the old normaliser hold keys that no longer match what a new row
    produces, so **deduplication silently misses them until this has run.**

- **Lead detail screen — done.** `/leads/{lead}`, with five tabs and the right
  rail §44 asks for. 19 tests.

    - **Only tabs with something behind them.** §44 also lists conversations,
      emails, WhatsApp, tasks, appointments, notes and documents; those arrive
      with the modules that produce them. An empty tab implies a feature exists,
      which is worse than its absence (§124).
    - **The score is explained, not just stated** — the matched rules and their
      points, from the stored `LeadScore`. §19's whole point is that a rep who
      cannot see why a lead scored 82 will ignore the number.
    - **Verification findings in words**, each one something that can be acted on
      (§59). "Needs a look" with no reason cannot be corrected.
    - **The timeline is built from `lead_events`**, newest first, each entry
      expandable to its payload. An unlabelled event type degrades to a readable
      form rather than vanishing, because providers may add their own and a gap in
      the timeline is worse than an unpolished line in it.
    - **Provenance is visible** (§2): source, type, and an "authorized provider"
      marker, plus UTM parameters and the write-once first touch (§34).
    - **A merged lead announces that it is a tombstone**, with a link to the
      record it now lives under — otherwise someone works a person who moved.
      A master says how many submissions its history is combined from.
    - The audit tab is deferred: least-opened tab, most expensive query.
    - Click-to-call dials the stored E.164 form, so the link works from any
      country, while still showing the number as it was typed.

    Three things this surfaced. `LeadEvent` had no `casts()`, so `occurred_at`
    came back as a string — the timeline would have been sorting and formatting
    text. The command palette's lead hits pointed at a filtered list, which was
    the stand-in while there was no record to open, and now go straight to it.
    And **`LeadFactory` was generating invalid phone numbers**: `+9715` plus
    random digits produces prefixes like 51, 53 and 57, none of which the UAE
    assigns, so roughly 40% of factory leads failed validation. Fixed in both
    factories — test data that does not validate is a trap for every test written
    after it.

Still open in Phase 1: the deal detail screen (§44), activities, tasks, notes
and attachments (1.8 — bulk actions were a §113 concern and are done, but the
polymorphic activity model this row is really about is not), and the
widget-driven dashboard (1.10). Authorized provider ingestion (Meta Lead Ads,
LinkedIn Lead Gen Forms, job-board partner feeds) is Phase 2's integration work.

| #    | Deliverable                                                                   |
| ---- | ----------------------------------------------------------------------------- |
| 1.1  | Auth: registration, login, password reset, email verification, 2FA            |
| 1.2  | **Tenant provisioning** (§7) as an idempotent, resumable job                  |
| 1.3  | RBAC: roles, permissions, teams, default role set, permission-aware UI        |
| 1.4  | Plans, features, subscriptions, entitlement service, usage metering           |
| 1.5  | Billing provider abstraction + first provider (Stripe)                        |
| 1.6  | **Leads**: model, normalization, dedup, manual scoring, assignment, lifecycle |
| 1.7  | Contacts, Companies, Deals, Pipelines with Kanban drag/drop                   |
| 1.8  | Activities, tasks, notes, tags, attachments (polymorphic)                     |
| 1.9  | **DataTable** component, complete to the §110–115 standard                    |
| 1.10 | Widget-driven dashboard + first widget set                                    |
| 1.11 | Form builder, embed/JS snippet/REST endpoint/webhook, UTM capture             |
| 1.12 | CSV/XLSX import wizard (queued) and export                                    |
| 1.13 | Custom fields across all five entities                                        |
| 1.14 | `/api/v1` for leads, contacts, companies, deals; API keys + scopes — **done** |
| 1.15 | Outbound webhooks: signed, retried, logged, replayable — **done**             |
| 1.16 | Audit logging across all §54 events — **done**                                |
| 1.17 | Global search + `⌘K` command palette — **done**                               |

**Exit criteria**

- A tenant registers, is provisioned, invites a user, and both work leads under distinct roles.
- A website form submission becomes a normalized, deduplicated, assigned lead.
- A 10,000-row CSV import completes on the queue without blocking the browser.
- Every list page meets the DataTable standard; the dashboard is configurable, not hard-coded.
- The public API creates a lead; a signed webhook fires and is retried on failure.
- Exceeding a plan limit is blocked by the entitlement service — not by an inline check.

---

## Phase 2 — Integrations & messaging (MVP 2)

_~8–10 weeks. The competitive moat._

| #    | Deliverable                                                                                          |
| ---- | ---------------------------------------------------------------------------------------------------- |
| 2.1  | Integration Hub: `IntegrationProvider`, OAuth framework, encrypted token storage, refresh, discovery |
| 2.2  | Sync engine: initial, incremental, webhook, reconciliation, manual, retry (§57)                      |
| 2.3  | **Meta** — Facebook Lead Ads, Pages, Instagram business, supported events                            |
| 2.4  | **LinkedIn** — Lead Sync for approved Lead Gen Forms, webhook + polling fallback                     |
| 2.5  | **TikTok** — approved business/ad assets                                                             |
| 2.6  | **Google Ads** and supported Google lead sources                                                     |
| 2.7  | **WhatsApp Business Platform** — templates, inbound/outbound, delivery/read status, media            |
| 2.8  | Email: accounts, SMTP/OAuth, templates, signatures, inbound with reply detection                     |
| 2.9  | SMS provider abstraction + first provider                                                            |
| 2.10 | Consent, opt-out and business-hours enforcement **in the dispatch layer**                            |
| 2.11 | Integration Dashboard (§65) + actionable error UX (§59)                                              |
| 2.12 | Connection UX (§11) — permissions explained, assets selected, no token pasting                       |

**Exit criteria**

- Each provider connects via OAuth, discovers assets, subscribes webhooks and completes an initial sync.
- A provider lead arrives by webhook, is normalized, deduplicated, scored and assigned.
- **Replaying a webhook creates no duplicate** — idempotency verified per provider.
- An expired token surfaces a Reconnect action, not a silent sync failure.
- An opted-out recipient cannot be messaged on that channel through _any_ path, including automation and AI.
- No integration code path scrapes profiles or bypasses a permission model.

> Gate this phase on **provider approval timelines, not engineering time**.
> LinkedIn Lead Sync, TikTok API access and WhatsApp Business all require
> developer-program qualification that can take weeks. Start those applications
> during Phase 1. Verify each provider's current documentation before
> implementing — do not build from tutorials (§103).

---

## Phase 3 — Automation & engagement (MVP 3)

_~6–8 weeks._

| #   | Deliverable                                                                      |
| --- | -------------------------------------------------------------------------------- |
| 3.1 | Visual workflow builder: triggers, conditions, actions, delays, branches (§28)   |
| 3.2 | Execution engine — manual / approval / autonomous, idempotent, with kill switch  |
| 3.3 | Rule-based lead scoring with explainable reasons and configurable bands          |
| 3.4 | **Omnichannel inbox** — three-pane, realtime, assignment, internal notes, SLA    |
| 3.5 | Campaigns across all §33 types, with metrics                                     |
| 3.6 | Email sequences                                                                  |
| 3.7 | Appointments: Google/Microsoft calendars, availability, booking links, reminders |
| 3.8 | SLA policies, breach detection and escalation (§87)                              |
| 3.9 | Notification center with per-user per-event channel preferences                  |

**Exit criteria**

- A workflow triggers on lead creation, evaluates conditions and executes actions in all three modes.
- Approval mode holds an action pending a human, with everything needed to execute preserved.
- The kill switch halts autonomous execution immediately.
- The inbox shows WhatsApp, email and SMS threads in realtime, with correct unread and SLA state.
- An SLA breach notifies, escalates and records the violation.
- A booking link creates an appointment and syncs to the connected calendar.

---

## Phase 4 — AI layer (MVP 4)

_~6–8 weeks._

| #   | Deliverable                                                                                 |
| --- | ------------------------------------------------------------------------------------------- |
| 4.1 | AI provider abstraction + at least two providers, with routing and failover                 |
| 4.2 | Token and cost accounting per tenant, metered against plan limits                           |
| 4.3 | AI lead qualification returning the §20 structured output                                   |
| 4.4 | AI email composer — 9 modes, generate/regenerate/edit/save/send/schedule                    |
| 4.5 | AI WhatsApp replies with suggestion and approval flows                                      |
| 4.6 | **AI Sales Agent** with full §30 configuration and knowledge sources                        |
| 4.7 | Human handoff on all §31 triggers                                                           |
| 4.8 | **AI safety layer**: approved knowledge only, business hours, opt-outs, limits, kill switch |
| 4.9 | AI Dashboard (§64)                                                                          |

**Exit criteria**

- Qualification returns structured output with concise user-facing reasons; **no chain-of-thought is exposed or stored**.
- The agent cannot state a price, policy or guarantee absent from tenant-approved knowledge — verified adversarially, not just by prompt review.
- AI never auto-sends without an explicit tenant automation grant.
- Every AI action is logged with a reason and is attributable.
- Switching provider requires configuration only — no application code change.
- Token cost is metered per tenant and enforced against plan limits.

---

## Phase 5 — Platform maturity (MVP 5)

_~8–10 weeks._

| #   | Deliverable                                                                 |
| --- | --------------------------------------------------------------------------- |
| 5.1 | CRM connectors: Salesforce, HubSpot, Zoho, Pipedrive, custom REST           |
| 5.2 | Field mapping UI with one/two-way sync, conflict strategy, transformations  |
| 5.3 | Developer portal with generated OpenAPI, sandbox and changelog (§50)        |
| 5.4 | Advanced analytics: attribution, cohorts, ROI, cost per qualified lead      |
| 5.5 | Scheduled reports with delivery                                             |
| 5.6 | White label: logo, domain, colors, email branding, favicon, sender (§95)    |
| 5.7 | Referral/affiliate system (§96)                                             |
| 5.8 | OpenSearch, if measurement shows PostgreSQL FTS is the bottleneck           |
| 5.9 | Backup, point-in-time recovery, **tested restore**, documented DR procedure |

**Exit criteria**

- A lead syncs bidirectionally with an external CRM **without creating duplicates**.
- OpenAPI docs are generated from code, not maintained by hand.
- Attribution traces revenue to source, campaign and ad.
- A white-labelled tenant shows no platform branding anywhere.
- **A restore has actually been performed from backup** — not merely configured.

---

## Continuous

Not a phase. These run from Phase 0 onward and are part of every PR.

**Testing (§73):** unit, feature, API, authorization, integration, queue and
webhook tests on the backend; component, form-validation, store and workflow
tests on the frontend; E2E across registration → provisioning → connect →
receive → score → assign → automate → message → convert → report.

**Observability (§74):** Horizon, structured logs, error tracking, and the
metrics in §74 — lead processing latency, webhook latency, message delivery,
automation failures, API error rate, queue backlog, AI usage.

**Security:** dependency scanning, the log redaction processor, periodic
isolation audits, and a secrets review before each release.

**Design QA:** the §127 checklist, applied per module rather than at the end.

---

## Dependency order

```
Phase 0  Foundation
   │
   ├──────────────┐
   ▼              ▼
Phase 1        (provider applications submitted — start during Phase 1)
Core CRM          │
   │              │
   ├──────────────┘
   ▼
Phase 2  Integrations & Messaging
   │
   ▼
Phase 3  Automation & Engagement      ← needs messaging channels to act on
   │
   ▼
Phase 4  AI                           ← needs conversations to qualify
   │
   ▼
Phase 5  Platform Maturity
```

Phases 3 and 4 have limited parallelism: the AI layer needs real conversations
to qualify, and automation needs real channels to act on. Building them against
mocks produces work that gets rewritten.

## Acceptance

The system is complete when all 35 criteria in §100 pass. They are the release
gate, and each maps to a phase exit criterion above.
