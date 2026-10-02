# Decision Register

Where this plan diverges from the master specification, or resolves something
the spec leaves open. Each is recorded so it is a deliberate choice rather than
a silent drift — and so it can be revisited with its reasoning intact.

---

## ADR-001 — Inertia v3 for the internal UI, not a standalone SPA

**Status:** Accepted · **Spec reference:** §4, §101.1–2

**Spec says:** Vue 3 + TypeScript + Vite + Tailwind + **Vue Router + Pinia +
Axios**, consuming a Laravel REST API. §101.1 requires "API-first architecture".

**Decision:** Keep the installed Inertia v3 stack for the internal application.
Add Pinia for genuinely cross-page state. Build `/api/v1` as a **first-class
parallel surface**, not a byproduct.

**Reasoning.** Converting to a standalone SPA costs: rebuilding auth as a
Sanctum SPA cookie flow (CSRF, stateful domains, CORS), hand-rolling route
guards that re-check permissions the server already knows, losing Wayfinder's
typed route generation, and adding a loading-state layer per page that Inertia
provides free.

It buys nothing the spec actually needs. The public REST API is required
regardless — for the developer portal, webhooks, inbound ingestion and CRM
connectors (§47, §50, §81–84). That API is built and tested on its own merits.
"API-first" is satisfied by **the Inertia controller and the API controller
calling the same domain action**, which guarantees the UI and the public API
cannot diverge in behaviour — a stronger guarantee than an SPA that merely
happens to call HTTP.

**Consequences.** Pinia holds inbox state, saved filters, notifications, theme
and the command palette — not server state Inertia already delivers. If a native
mobile app or a third-party frontend later becomes a requirement, `/api/v1`
already supports it; nothing here forecloses that.

**Revisit if:** a separately deployed frontend becomes a hard requirement.

---

## ADR-002 — Single database with row-level tenancy

**Status:** Accepted · **Spec reference:** §6, §7.3, §101.27

**Spec says:** "central platform database plus isolated tenant data", and
provisioning step 3 is "create tenant database/schema **according to deployment
strategy**" — explicitly leaving the strategy open.

**Decision:** Single PostgreSQL database, row-level isolation via `tenant_id`,
using `stancl/tenancy` in single-database mode. Central tables on a separate
connection.

**Reasoning.** Database-per-tenant multiplies migration runtime by tenant count,
makes cross-tenant platform analytics require fan-out queries, complicates
connection pooling, and turns a schema change into an operational event. At MVP
scale that cost is paid daily for an isolation guarantee that row-level scoping
plus RLS already provides.

**Consequences.** Isolation is enforced in three layers — global scope,
PostgreSQL row-level security as a backstop, and a schema test that fails the
build (see [`02-DATA-MODEL.md`](02-DATA-MODEL.md#4-isolation-guarantees)). The
third layer is the one that holds over time; it is written in Phase 0 before
there is any data to protect.

`stancl/tenancy` supports both modes, so migrating an individual large customer
to a dedicated database later requires no application change.

**Revisit if:** a customer contract requires physical data isolation, or a
regulatory regime (data residency) demands per-tenant placement.

---

## ADR-003 — One `message_templates` table, not three

**Status:** Accepted · **Spec reference:** §6

**Spec says:** `message_templates`, `email_templates`, `whatsapp_templates`,
`sms_templates` as separate tables.

**Decision:** One `message_templates` table with a `channel` discriminator.

**Reasoning.** The four tables differ only in which optional columns they use.
Splitting them triples the template CRUD UI, the variable-validation logic and
the permission surface, and makes "list all templates" a union query — for no
isolation or performance benefit.

**Consequences.** Channel-specific fields (WhatsApp `provider_template_id` and
`approval_status`, email `subject`) are nullable columns validated per channel.
The spec notes the schema "may be refined during implementation" (§6).

---

## ADR-004 — Consent and opt-out keyed on identifier, not lead

**Status:** Accepted · **Spec reference:** §88

**Spec says:** store consent status, source, timestamp, privacy policy version,
communication preferences, unsubscribe status and per-channel opt-out.

**Decision:** Key `consents` and `opt_outs` on `identifier` (normalized email or
E.164 phone) rather than on `lead_id`.

**Reasoning.** A person opts out once. If opt-out attaches to a lead record, the
same person re-imported from a different source, or created as a duplicate
before dedup runs, arrives with a clean slate and gets messaged. That is a
compliance failure, and the lead-keyed design makes it the _default_ behaviour.
Keying on identifier makes opt-out follow the person across every duplicate,
merge and re-import.

**Consequences.** Opt-out is checked in the dispatch layer against the
identifier immediately before send — after template rendering, after automation,
after AI. There is no path to a send that skips it.

---

## ADR-005 — Phase 0 inserted before MVP 1

**Status:** Accepted · **Spec reference:** §99

**Spec says:** MVP 1 begins with multi-tenancy, auth and RBAC.

**Decision:** Insert a Phase 0 covering database migration, tenancy foundation,
the isolation test, design tokens and the app shell.

**Reasoning.** MVP 1 assumes a foundation the current starter kit lacks — it
still runs on SQLite with no tenancy, no RBAC and no design system. Beginning
feature work first means retrofitting `tenant_id` into models already written
without it, which is where isolation bugs are introduced.

**Consequences.** ~2 weeks before anything is customer-visible. The tenant
isolation test (0.5) is the highest-leverage task in the project: cheap now,
ruinous to retrofit once forty models exist.

---

## ADR-006 — Score band stored, not derived

**Status:** Accepted · **Spec reference:** §19, §119

**Spec says:** score categories (Cold/Nurture/Warm/Hot/Qualified) with
"thresholds must remain configurable".

**Decision:** Persist `score_band` on the lead alongside the numeric score,
rather than deriving the band from thresholds at read time.

**Reasoning.** If the band is derived, a tenant retuning its thresholds silently
rewrites the apparent history of every lead ever scored — reports change
retroactively, and "why did this lead's band change?" has no answer. Storing the
band at scoring time keeps history accurate; re-banding becomes an explicit,
auditable backfill.

---

## ADR-007 — Brand accent tokens separate from foundation tokens

**Status:** Accepted · **Spec reference:** §106.2, §106.3, §126, §95

**Decision:** Two independent token layers — a foundation palette (Deep Navy +
Electric Indigo) and a brand accent layer (`--brand-*`).

**Reasoning.** §106.3 requires the selected brand's accent to change "without
rebuilding the UI", and §95 requires per-tenant white-label brand colors. A
single merged palette makes both a code change. Separating them makes the brand
selection a token swap and white-labelling a settings change.

**Consequences.** Components reference `--brand-*` for identity surfaces (logo,
sign-in, marketing, primary CTA) and `--color-primary-*` for product chrome.
The distinction must be respected in review, or the layers re-merge in practice.

---

---

## ADR-008 — MySQL 8.4 now, PostgreSQL kept as the migration target

**Status:** Accepted · **Spec reference:** §4 · **Supersedes part of ADR-002**

**Spec says:** PostgreSQL preferred, "Alternative: MySQL 8+".

**Decision:** Build on MySQL 8.4, which is installed and running locally. Keep
the schema deliberately portable. The `pdo_pgsql` / `pgsql` PHP extensions have
been enabled so the driver is ready; the PostgreSQL _server_ is not installed.

**Reasoning.** No PostgreSQL server exists in this environment, and MySQL 8.4 is
already running. Choosing MySQL avoids a dev/prod database split, which is a
reliable source of late bugs around JSON semantics and index behaviour.

**Consequences — what is given up.** This is a real reduction in the isolation
story from ADR-002:

- **No row-level security.** RLS is PostgreSQL-only, so isolation layer 2 is
  gone. The global scope (layer 1) and the build-failing schema test (layer 3)
  carry the guarantee. Layer 3 was always the one that actually holds over time,
  but the backstop is genuinely absent — treat `withoutScoping()` call sites as
  security-sensitive and review them accordingly.
- **JSON, not JSONB.** No GIN indexing of JSON documents. Filtering on custom
  fields at scale will need generated columns with conventional indexes.
- **Weaker full-text search.** MySQL FULLTEXT is materially worse than
  `tsvector`. This brings the OpenSearch decision (Phase 5.8) forward if search
  quality becomes a complaint.

**Migration path.** The schema avoids MySQL-only constructs, so the move is a
connection change plus a JSON-column and index review — not a rewrite. Revisit
before the first production deploy, particularly if deploying to RDS.

---

## ADR-009 — Tenancy implemented directly, not via stancl/tenancy

**Status:** Accepted · **Spec reference:** §6 · **Amends ADR-002**

**Decision:** Implement `TenantContext`, `BelongsToTenant` and `TenantScope`
directly (~150 lines) rather than depending on `stancl/tenancy`, which ADR-002
had named.

**Reasoning.** With single-database row-level tenancy chosen, most of what
stancl/tenancy provides — database bootstrapping, per-tenant connection
switching, domain lifecycle — is unused. What remains is a global scope and a
context holder, which is less code to write than to configure. It also keeps the
fail-closed behaviour (see below) fully under our control, and avoids depending
on a v3 package whose Laravel 13 runtime support is unverified.

**Consequences.** The future move of a single large tenant to its own database
is now our work rather than a library feature. Given that move is speculative
and the isolation tests are ours either way, that trade reads correctly.

---

## ADR-010 — The tenant scope fails closed, and authentication is its one exception

**Status:** Accepted · **Spec reference:** §101.27

**Decision:** With no tenant bound, `TenantScope` applies `WHERE 1 = 0` rather
than leaving the query unscoped. Credential lookup is the single sanctioned
exception, implemented in `TenantUserProvider`.

**Reasoning.** An unscoped query returning every tenant's rows turns any
missing-context bug into a cross-tenant data leak — silent, and the worst
failure mode available. Returning nothing makes the same bug loud and harmless.

Authentication cannot obey this: at sign-in there is no bound tenant, and it is
the user who determines which tenant the session belongs to. Left scoped, nobody
could ever sign in — this was caught by end-to-end testing during Phase 0, not
by inspection.

**Consequences.** `TenantUserProvider` lifts the scope for credential lookup
only. Because `users.email` is unique _per tenant_, one address may exist in
several workspaces: where the request identifies a tenant by domain the lookup
is constrained to it, and where it does not and the address is ambiguous,
authentication is refused rather than guessing. Multi-workspace users therefore
need a tenant-qualified sign-in route (custom domain or subdomain) before
production.

---

## ADR-011 — Horizon and Reverb deferred to deployment

**Status:** Accepted · **Spec reference:** §4, §74

**Decision:** Neither package is installed yet.

**Reasoning.**

- **Horizon** requires `ext-pcntl` and `ext-posix`, which do not exist on
  Windows. Installing it with ignored platform requirements would put a package
  in `composer.json` that cannot run locally. Local development uses
  `queue:work`; Horizon is added when a Linux environment exists.
- **Reverb** needs `guzzlehttp/psr7 ^2.6` while the tree is on 3.1.0.
  Downgrading a transitive dependency across the whole project, in Phase 0, for
  a feature first needed in Phase 3 (realtime inbox) is not a trade worth
  making now.

**Consequences.** Queue observability is limited to the failed-jobs table until
deployment. Add both during Phase 2/3 planning, and re-check the psr7 constraint
then — it may have resolved upstream.

## Open questions

Decisions deferred until there is information to make them with.

| #   | Question                                                                                                                                            | Needed by                        |
| --- | --------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------- |
| Q1  | **Which brand ships?** All eight identities exist; none is wired in. Trademark, company-name, handle and domain clearance is unrun (§105.1).        | Phase 0.9                        |
| Q2  | Which billing provider first? Stripe assumed; regional gateways may be required by target market.                                                   | Phase 1.5                        |
| Q3  | Which AI providers, and routing policy — cost-optimized, quality-optimized, or per-tenant choice?                                                   | Phase 4.1                        |
| Q4  | Deployment target. §4 specifies AWS (ECS, RDS, ElastiCache, S3, CloudFront); Laravel Cloud would be materially faster to operate at this team size. | Before Phase 1 ships             |
| Q5  | Data residency requirements? Determines whether ADR-002 holds for EU/UAE customers.                                                                 | Before first enterprise contract |
| Q6  | Primary target market — affects provider priority in Phase 2 and SMS provider choice.                                                               | Phase 2 planning                 |

**Q1 and Q4 are worth resolving early.** Q1 gates the favicon wiring and every
piece of marketing collateral; Q4 changes infrastructure work substantially and
is cheapest to decide before anything is deployed.
