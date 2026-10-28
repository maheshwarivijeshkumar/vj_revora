# Ironbridge Construction CRM — Master Build Prompt

**Audience:** A future engineer or coding agent.  
**Deliverable:** A complete, production-grade, **client-based** (single-company) construction CRM — not multi-tenant SaaS.  
**Demo brand:** Ironbridge Construction (all branding configurable via Settings).  
**Quality bar:** Visually and interactionally **beyond** the Ironbridge + Moorly references. Do not pixel-clone; surpass them.

Read the visual reference pack under `/cursor/stores/self/media/crm-refs/` before implementing UI. Treat those notes (and any binaries dropped beside them) as non-negotiable IA and widget inventory.

---

## 0. How to use this prompt

Build the product end-to-end from this document. Prefer concrete field names, enums, permission keys, and route patterns given here. When a choice is open, follow the **Recommended stack** section. Do not invent unrelated product lines (no marketplace, no insurance underwriting product, no multi-org billing). Construction domain language is primary; Moorly patterns are for desk polish, pipeline depth, detail hierarchy, and dual-theme excellence.

Imperative throughout: **Build …**, **Implement …**, **Enforce …**.

---

## 1. Vision & positioning

Build a **single-tenant CRM** operated by one construction company (general contractor / renovation firm). One deployment = one company. There is no tenant switcher, no per-seat SaaS billing, no org marketplace.

Position Ironbridge Construction as a premium operations desk for:

- Capturing and qualifying **leads**
- Moving work through a construction **pipeline**
- Writing **estimates**, converting winners into **clients** and **projects**
- Scheduling crews, tracking **subcontractors** and **materials**
- Managing **change orders**, **invoices**, **documents**, and **messages**
- Surfacing **attention queues** and **reports** for owners and PMs

**Quality mandate**

- UI must be more beautiful and advanced than the reference screens.
- Dark + light themes required (Moorly-style toggle).
- Motion is first-class: fade-up sections, staggered list reveals, flip-from-top page transitions, micro-interactions — with `prefers-reduced-motion` support.
- Avoid generic AI-slop aesthetics: no purple-on-white defaults, no Inter-only stacks, no emoji decoration, no pill-cluster hero chrome, no multi-layer neon glows.
- Every list is a real data table (filters, sort, pagination, bulk + inline actions). Every entity has complete CRUD forms. Pipeline is drag-drop kanban with server-validated transitions.

---

## 2. Recommended stack (concrete)

Pick this stack unless the implementer has an explicit alternate that preserves all behaviors below:

| Layer | Choice | Justification |
|---|---|---|
| Backend | **Laravel 11+** (PHP 8.3) | Mature auth, validation, queues, Eloquent, policy RBAC |
| API auth | **Laravel Sanctum** (SPA cookie + optional personal access tokens) | First-party SPA; Passport/OAuth server is overkill for single-client CRM. Document Passport only if a future mobile/third-party token server is required. |
| Frontend | **React 19 + TypeScript + Vite** (or Vue 3 + Inertia if the team prefers Laravel-native) | Rich motion + kanban; either is acceptable — React assumed in component notes |
| Styling | **Tailwind CSS v4** + CSS variables for dual themes | Token-driven light/dark |
| Charts | **Recharts** or Chart.js | Sparklines + desk charts |
| DnD | **dnd-kit** (React) or SortableJS | Pipeline kanban |
| Motion | **Motion (Framer Motion)** | Page + list animations |
| DB | **PostgreSQL 16** | Money/`numeric`, JSON, soft deletes, audit |
| Files | Local/S3-compatible disk via Laravel Storage | Documents module |
| Queue | Redis + Laravel queues | Notifications, PDF generation |
| PDF | DomPDF or Browsershot | Estimates/invoices |

**Monolith layout:** `apps/api` (Laravel) + `apps/web` (React) **or** single Laravel + Inertia app. Prefer API + SPA if marketing site is separate; prefer Inertia if one deploy unit is required. Marketing site can be the same frontend with public routes.

---

## 3. Information architecture & navigation

### 3.1 App shell

Build a persistent **left sidebar** + optional **top bar**.

**Sidebar brand block**

- Logo mark + wordmark **Ironbridge Construction**
- Collapse to icon rail on `<lg`
- Company name from `settings.company_name` (default Ironbridge Construction)

**Primary nav (exact order — match Ironbridge inventory)**

| Key | Label | Badge source |
|---|---|---|
| `dashboard` | Dashboard | — |
| `leads` | Leads | count of leads in `new` + `contacted` awaiting owner action (demo: **3**) |
| `pipeline` | Pipeline | — (or count of deals in active stages) |
| `projects` | Projects | count of projects with status `active` (demo: **6**) |
| `schedule` | Schedule | — |
| `clients` | Clients | — |
| `estimates` | Estimates | — |
| `invoices` | Invoices | count of invoices `overdue` or `due_today` (demo: **1**) |
| `crew` | Crew | — |
| `subcontractors` | Subcontractors | — |
| `materials` | Materials | — |
| `change_orders` | Change Orders | optional: pending approval count |
| `documents` | Documents | — |
| `messages` | Messages | unread thread count for current user (demo: **2**) |
| `reports` | Reports | — |
| `settings` | Settings | — |

**Top bar**

- Global command search (`⌘K` / `Ctrl+K`) across leads, clients, projects, invoices, documents
- Theme toggle (light / dark / system)
- Notification bell (attention queue + message digests)
- Current user menu (profile, logout)

**Breadcrumbs**

- Module › Entity › Sub-tab (e.g. `Projects › Rivard Kitchen Remodel › Change Orders`)
- Clickable ancestors; current page plain text

**Desk vs modules**

- **Desk** = Dashboard home: greeting, KPIs + sparklines, primary chart, Today’s schedule, Needs attention / Waiting on you
- **Modules** = everything else in the sidebar; each has list + detail + create/edit routes

### 3.2 Roles (RBAC)

| Role key | Intent |
|---|---|
| `owner` | Full access, settings, financials, user admin |
| `pm` | Projects, schedule, change orders, estimates; limited settings |
| `estimator` | Leads, pipeline, estimates, documents; read projects |
| `accountant` | Invoices, payments, reports (financial); read clients/projects |
| `foreman` | Schedule, crew, materials, project read; limited write |
| `crew` | Own schedule, own timesheets (if present), messages; read assigned projects |
| `readonly` | Reports + list/detail read everywhere |

Map every mutating route to a permission (section 13). Sidebar items hide when the role lacks `*.view`.

---

## 4. User flows & hierarchy

### 4.1 Primary commercial flow

```
Lead capture
  → Qualify (site/visit notes, budget, job type)
  → Pipeline stage progression (kanban)
  → Estimate / scope package
  → Send proposal
  → Negotiation / revision
  → Convert to Client (if new) + Project
  → Schedule crew & subcontractors
  → Materials procurement tracking
  → Change orders during build
  → Progress / milestone invoices
  → Documents archive + Messages
  → Close-out / reports
```

### 4.2 Attention & waiting queues

Build two desk lists (same data, different framing):

1. **Needs attention** (Ironbridge) — system-wide items the company must clear: overdue invoices, estimates past validity, change orders awaiting approval, leads idle > N days, schedule conflicts.
2. **Waiting on you** (Moorly) — subset owned by `auth.user_id`: assigned approvals, @mentions, unread messages, stages where user is `owner_id`.

Each row: type icon, title, entity link, age, severity (`info` | `warn` | `critical`), dismiss/snooze (owner/pm only for dismiss of system items).

### 4.3 Messaging

- Threaded messages tied to an optional `entity_type` + `entity_id` (lead, project, invoice, change_order) or free-form internal.
- Unread badges update sidebar + top bell.

### 4.4 Lead → Client conversion

Transactional conversion (see §12):

1. Validate lead stage allows convert (`proposal` | `negotiation` | `awarded`).
2. Create or link `Client`.
3. Optionally create `Project` from estimate totals.
4. Mark lead `converted`; write audit log; soft-link `lead.converted_client_id` / `converted_project_id`.

---

## 5. Module specifications

For **every** module below: implement list view, create form, edit form, detail/preview, soft delete (where noted), empty state, and permission gates. List behaviors follow §14.

### 5.1 Dashboard (Desk)

**Purpose:** Morning operating picture for the signed-in user.

**Screens**

- `/` or `/dashboard` — single composition desk (not a widget marketplace).

**Widgets (required)**

1. **Greeting** — “Good morning, {first_name}” + date (Montreal timezone default `America/Montreal`).
2. **KPI row (4 cards)** with sparklines (14-day series):
   - `active_projects` — label “Active projects”
   - `open_pipeline_value` — sum of estimated values in active pipeline stages (CAD)
   - `outstanding_ar` — unpaid invoice balance
   - `jobs_this_week` — schedule events this week
   - Each card: label, primary value, delta % vs prior period, sparkline
3. **Primary chart** — “Pipeline value by stage” or “Revenue (paid invoices) last 6 months” (toggle). Soft area chart; teal fill at low opacity.
4. **Today’s schedule** — chronological list: time, job title, site address snippet, assignee avatars, status chip (`scheduled` | `en_route` | `on_site` | `done` | `cancelled`).
5. **Needs attention** + **Waiting on you** — tabbed or dual columns on wide screens.

**Empty states:** friendly copy + CTA (“Add a lead”, “Create schedule block”) — no illustration packs with purple blobs.

### 5.2 Leads

**Purpose:** Capture inbound demand before pipeline depth.

**Statuses:** `new` | `contacted` | `qualified` | `disqualified` | `converted`

**Fields**

| Field | Type | Notes |
|---|---|---|
| `id` | uuid | |
| `code` | string | e.g. `LD-1042` |
| `first_name`, `last_name` | string | required |
| `company_name` | string | nullable |
| `email`, `phone` | string | |
| `job_type` | enum | `residential_reno` `new_build` `commercial` `multi_unit` `other` |
| `source` | enum | `referral` `website` `phone` `walk_in` `partner` `other` |
| `estimated_budget` | money | nullable |
| `address_line1`, `city`, `province`, `postal_code` | string | Quebec-centric demo |
| `description` | text | |
| `owner_id` | fk users | |
| `status` | enum | above |
| `next_follow_up_at` | datetime | |
| `converted_client_id`, `converted_project_id` | fk | nullable |
| `created_at`, `updated_at`, `deleted_at` | timestamps | soft delete |

**Screens:** list, create/edit drawer or page, detail with activity timeline, action **Add to pipeline** (creates pipeline deal linked to lead), **Convert**.

**Badge:** count where `status in (new, contacted)` AND (`owner_id = me` OR role can see all) AND follow-up overdue or never contacted — seed **3**.

### 5.3 Pipeline

**Purpose:** Kanban of deals from intake through award. Primary visual from Moorly Applications Pipeline; domain language is construction.

**Stages (construction primary)**

| Order | Key | Label | Maps from Moorly |
|---|---|---|---|
| 1 | `intake` | Intake | Intake |
| 2 | `qualification` | Qualification | Clearance |
| 3 | `scope_estimate` | Scope & Estimate | Risk review |
| 4 | `proposal` | Proposal | Pre-bind |
| 5 | `negotiation` | Negotiation | Offer |
| 6 | `awarded` | Awarded | Bound |
| — | `lost` | Lost | (sink; not a column or last column collapsed) |

**Deal fields:** `title`, `lead_id?`, `client_id?`, `job_type`, `estimated_value`, `probability`, `owner_id`, `stage`, `stage_entered_at`, `category` (chip), `site_city`, `priority` (`low`|`normal`|`high`), `lost_reason?`.

**Screens:** `/pipeline` kanban (default), `/pipeline/table` alternate, deal detail (entity detail pattern §7).

**Behaviors:** see §6.

### 5.4 Projects

**Purpose:** Execution record after award / conversion.

**Statuses:** `planning` | `active` | `on_hold` | `punch_list` | `completed` | `cancelled`

**Fields:** `code` (`PRJ-006`), `name`, `client_id`, `pipeline_deal_id?`, `lead_id?`, `job_type`, `status`, `contract_value`, `address_*`, `start_date`, `target_end_date`, `pm_id`, `foreman_id`, `description`, soft delete.

**Detail tabs:** Overview · Schedule · Estimates · Change Orders · Invoices · Materials · Crew · Documents · Messages.

**Badge:** active count — seed **6**.

### 5.5 Schedule

**Purpose:** Day/week calendar of jobs, visits, and crew assignments.

**Event types:** `site_visit` | `work_block` | `inspection` | `delivery` | `internal`

**Fields:** `title`, `project_id?`, `lead_id?`, `type`, `starts_at`, `ends_at`, `location`, `assignee_ids[]`, `status`, `notes`, `color_token`.

**Views:** day list (desk widget style), week calendar, filter by crew member / project.

**Conflict detection:** warn when same crew member double-booked (non-blocking warn on save; blocking if `settings.strict_schedule_conflicts`).

### 5.6 Clients

**Purpose:** Account of record for converted / direct clients.

**Fields:** `code`, `display_name`, `legal_name?`, `type` (`individual`|`company`), contacts (`name`,`email`,`phone`,`is_primary`), billing address, site addresses (has many), `payment_terms_days` (default 30), `tax_id?`, `notes`, soft delete.

**Detail:** Overview · Projects · Estimates · Invoices · Documents · Messages.

### 5.7 Estimates

**Purpose:** Scoped pricing packages tied to lead/deal/project.

**Statuses:** `draft` | `sent` | `accepted` | `declined` | `expired` | `superseded`

**Header fields:** `number` (`EST-2026-014`), `client_id?`, `lead_id?`, `project_id?`, `pipeline_deal_id?`, `status`, `issue_date`, `valid_until`, `currency` (`CAD`), `subtotal`, `tax_total`, `total`, `notes`, `version`, `parent_estimate_id?`.

**Line items:** `sort_order`, `kind` (`labor`|`material`|`subcontract`|`allowance`|`other`), `description`, `qty`, `unit`, `unit_price`, `tax_rate`, `line_total`.

**Actions:** duplicate version, send (marks `sent`, logs), accept → optional convert, PDF download, expire job (scheduler).

### 5.8 Invoices

**Purpose:** AR for projects / change orders.

**Statuses:** `draft` | `sent` | `partial` | `paid` | `overdue` | `void`

**Fields:** `number` (`INV-2026-008`), `client_id`, `project_id?`, `change_order_id?`, `estimate_id?`, `status`, `issue_date`, `due_date`, `subtotal`, `tax_total`, `total`, `amount_paid`, `balance`, line items (same shape as estimates), `idempotency_key` on payment create.

**Payments:** `amount`, `method` (`etransfer`|`cheque`|`card`|`cash`|`other`), `paid_at`, `reference`, created inside DB transaction updating invoice totals/status.

**Badge:** overdue + due_today — seed **1**.

### 5.9 Crew

**Purpose:** Internal labor roster.

**Fields:** `user_id?` (link to login), `first_name`, `last_name`, `role_title`, `trade` (`carpenter`|`electrician`|`plumber`|`general`|`other`), `phone`, `email`, `hourly_cost_rate`, `status` (`active`|`inactive`), certifications JSON, soft delete.

**Screens:** list, detail with upcoming schedule, utilization snippet.

### 5.10 Subcontractors

**Purpose:** External trade partners.

**Fields:** `company_name`, `contact_name`, `email`, `phone`, `trades[]`, `wcb_number?`, `insurance_expiry?`, `status`, `rating?`, `notes`, documents (COI), soft delete.

**Link:** assign to project via `project_subcontractor` pivot (`scope_notes`, `contract_value`).

### 5.11 Materials

**Purpose:** Material catalog + project material needs.

**Catalog fields:** `sku`, `name`, `category`, `unit`, `default_unit_cost`, `vendor_name?`, `reorder_level?`, soft delete.

**Project materials:** `project_id`, `material_id?` or free-text, `qty_needed`, `qty_ordered`, `qty_received`, `unit_cost`, `status` (`planned`|`ordered`|`partial`|`received`|`cancelled`).

### 5.12 Change Orders

**Purpose:** Scope/price changes after contract.

**Statuses:** `draft` | `submitted` | `approved` | `rejected` | `invoiced`

**Fields:** `number` (`CO-012`), `project_id`, `title`, `description`, `status`, `amount_delta`, `schedule_days_delta`, `requested_by`, `approved_by?`, `approved_at?`, line items optional, soft delete drafts only.

**Rule:** approving a CO in a transaction updates project `contract_value` and writes audit; optionally spawn invoice draft.

### 5.13 Documents

**Purpose:** File vault with entity binding.

**Fields:** `title`, `entity_type`, `entity_id`, `disk_path`, `mime`, `size_bytes`, `uploaded_by`, `visibility` (`internal`|`client_shareable`), `version`, soft delete.

**Upload rules:** §13. Preview images/PDFs in-app; others download.

### 5.14 Messages

**Purpose:** Internal (and optional client-visible) threads.

**Fields:** thread `subject`, `entity_type?`, `entity_id?`, `participant_ids[]`, messages: `body`, `author_id`, `read_receipts`, attachments via documents.

**Badge:** unread for current user — seed **2**.

### 5.15 Reports

**Purpose:** Read-only analytics.

**Required reports**

1. **Revenue** — paid invoice totals by month
2. **Pipeline value** — sum by stage + weighted by probability
3. **Aging invoices** — buckets 0–30 / 31–60 / 61–90 / 90+
4. **Crew utilization** — scheduled hours / capacity hours by week
5. **Lead conversion** — leads → awarded rate by source
6. **Change order volume** — count and $ by project

Export CSV on each. Date range + owner filters.

### 5.16 Settings

**Sections**

- Company profile (name, logo, address, GST/QST numbers, timezone, currency CAD)
- Branding (primary color override within teal family, logo light/dark)
- Users & roles
- Pipeline stage labels (keys fixed; labels editable)
- Estimate/invoice numbering prefixes
- Tax rates (GST, QST)
- Notification preferences
- Theme default
- Integrations placeholders (email SMTP only for v1)
- Audit log viewer (owner)

---

## 6. Pipeline / kanban deep spec

### 6.1 Board chrome

- Title “Pipeline”
- Search input (title, client, city, code)
- Category chips: All · Residential reno · New build · Commercial · Multi-unit
- Toggle **Only mine** (`owner_id = auth.id`)
- View switch: Board | Table
- “New deal” primary button

### 6.2 Columns

Render stages 1–6 as columns; **Lost** accessible via filter or sink column with muted styling. Each column header: label + count + total estimated value.

### 6.3 Card anatomy

- Title (2-line clamp)
- Category chip
- Estimated value (CAD, compact)
- Owner avatar
- Age in stage (`3d`)
- Priority dot if high
- Attention flag if idle > `settings.pipeline_idle_days` (default 7)

### 6.4 Drag-drop rules

1. Optimistic UI move; show toast “Moving…”
2. PATCH `/api/pipeline/deals/{id}/stage` with `{ stage, from_stage, idempotency_key }`
3. Server validates transition matrix:

| From \ To | intake | qualification | scope_estimate | proposal | negotiation | awarded | lost |
|---|---|---|---|---|---|---|---|
| intake | — | ✓ | ✓ | ✗ | ✗ | ✗ | ✓ |
| qualification | ✓ | — | ✓ | ✗ | ✗ | ✗ | ✓ |
| scope_estimate | ✗ | ✓ | — | ✓ | ✗ | ✗ | ✓ |
| proposal | ✗ | ✗ | ✓ | — | ✓ | ✗ | ✓ |
| negotiation | ✗ | ✗ | ✗ | ✓ | — | ✓ | ✓ |
| awarded | ✗ | ✗ | ✗ | ✗ | ✗ | — | ✗ |
| lost | ✓ (reopen to intake/qualification only) | ✓ | ✗ | ✗ | ✗ | ✗ | — |

4. On failure: revert card, error toast with envelope message.
5. Moving to `awarded` opens modal: Convert to client/project (prefilled).
6. Moving to `lost` requires `lost_reason`.
7. Wrap stage change + side effects in DB transaction; write `pipeline_stage_events` + audit.

### 6.5 Filters persistence

Persist board filters in `localStorage` per user + optional server preference.

---

## 7. Entity detail pages

Apply this shell to **Pipeline deals, Projects, Leads, Clients, Estimates, Invoices, Change Orders**.

### 7.1 Header

- Back link
- Title + code
- Status badge (semantic color)
- Owner + last activity
- Primary actions (context): Edit, Advance stage / Send / Record payment / Approve
- Overflow: Duplicate, Export PDF, Soft delete, Audit

### 7.2 Progress stepper

Horizontal stepper for pipeline-linked entities. Steps = construction stages. Current step filled teal; completed check; upcoming muted. Clicking a prior step is **display only** unless user has `pipeline.force_stage` (owner).

### 7.3 Particulars grid

2–3 column definition list:

Examples for a deal: Client / Contact, Job type, Site address, Estimator, Source lead, Target start, Probability, Category.

### 7.4 Financial summary card

Moorly “Indicated premium” → **Indicated contract value**:

- Estimated value
- Estimate total (latest accepted/sent)
- Change orders net
- Invoiced to date
- Balance remaining  
Use large numeral + quiet supporting rows. Sparkline optional for value history.

### 7.5 Tabs

| Tab | Content |
|---|---|
| Overview | Narrative, particulars, activity feed |
| Quotes / Estimates | Linked estimates table |
| Questions / Tasks | Checklist + open questions (Moorly “questions” pattern) |
| Versions | Estimate/document versions |
| Documents | Bound files |

Projects replace Quotes with deeper financials; keep Tasks + Documents.

### 7.6 Motion

Detail enter: **flip-from-top** (rotateX subtle + fade) 280ms. Tab panels: fade-in 160ms. Lists inside tabs: stagger 30ms per row.

---

## 8. UI/UX design system

### 8.1 Principles

- One composition per major view; avoid dashboard-of-cards clutter on marketing pages.
- Construction warmth + Moorly teal precision.
- Soft surfaces, restrained elevation, no neon.
- Dual theme parity: every component designed for light and dark.

### 8.2 Color tokens

Define CSS variables on `:root` and `[data-theme="dark"]`.

**Light (Ironbridge refined + soft teal)**

```css
--bg-canvas: #F4F7F7;
--bg-surface: #FFFFFF;
--bg-surface-muted: #E8F0EF;
--bg-elevated: #FFFFFF;
--border-subtle: #D5E2E0;
--border-strong: #B7CBC8;
--text-primary: #14201F;
--text-secondary: #4A5C59;
--text-muted: #7A8F8B;
--accent: #0F766E;          /* teal-700 */
--accent-hover: #0D9488;    /* teal-600 */
--accent-subtle: #CCFBF1;   /* teal-100 */
--accent-foreground: #FFFFFF;
--danger: #B42318;
--warning: #B54708;
--success: #067647;
--info: #026AA2;
--chart-1: #0F766E;
--chart-2: #1D4E4A;
--chart-3: #5EEAD4;
--shadow-color: rgba(20, 32, 31, 0.08);
```

**Dark (Moorly charcoal + teal)**

```css
--bg-canvas: #0B1211;
--bg-surface: #121A19;
--bg-surface-muted: #182221;
--bg-elevated: #1A2423;
--border-subtle: #243330;
--border-strong: #334640;
--text-primary: #E7F0EE;
--text-secondary: #A7BDB8;
--text-muted: #6F8681;
--accent: #2DD4BF;          /* teal-400 */
--accent-hover: #5EEAD4;
--accent-subtle: #134E4A;
--accent-foreground: #042F2E;
--danger: #F97066;
--warning: #FDB022;
--success: #47CD89;
--info: #7CD4FD;
--chart-1: #2DD4BF;
--chart-2: #99F6E4;
--chart-3: #0F766E;
--shadow-color: rgba(0, 0, 0, 0.45);
```

**Status chips**

| Status family | Token |
|---|---|
| Neutral / draft | muted surface + secondary text |
| Active / sent / scheduled | `--info` |
| Success / paid / awarded / approved | `--success` |
| Warning / due soon / negotiation | `--warning` |
| Danger / overdue / lost / rejected | `--danger` |
| Accent / current stage | `--accent` |

### 8.3 Typography

Do **not** ship Inter-only.

```css
--font-display: "Fraunces", "Iowan Old Style", Georgia, serif; /* marketing + desk greeting */
--font-sans: "Source Sans 3", "Segoe UI", sans-serif;         /* UI body */
--font-mono: "IBM Plex Mono", ui-monospace, monospace;        /* codes, money tables */
```

Scale: display 32/40, h1 24/32, h2 20/28, body 15/22, small 13/18, micro 11/14. Tabular nums for money.

### 8.4 Spacing, radii, elevation

- Space scale: 4 / 8 / 12 / 16 / 24 / 32 / 48
- Radius: `sm` 6, `md` 10, `lg` 16, `xl` 22 — **no** `rounded-full` pills except avatars and tiny unread dots
- Elevation: soft single shadow using `--shadow-color`; dark mode prefers border + slight lift, not heavy shadows
- Glass/soft: optional `backdrop-filter: blur(8px)` on sticky header over scrolling desk — keep subtle

### 8.5 Charts

- No harsh grid; dashed muted gridlines
- Area fill 16% opacity of `--chart-1`
- Tooltips: elevated surface, mono for values
- Sparklines: 64×24, stroke 1.5px, no axes

### 8.6 Pills / tags rules

- Category chips: soft accent-subtle background, accent text, radius `sm`
- Nav badges: min-width 18px, height 18px, micro bold, accent solid on light / accent on dark
- Avoid chip clusters > 5 visible without +N overflow

---

## 9. Motion / animation

| Pattern | Spec |
|---|---|
| Section fade-up | `opacity 0→1`, `y 12→0`, 320ms, easing `cubic-bezier(0.22, 1, 0.36, 1)` |
| Staggered lists | children delay `index * 30ms`, max 300ms total |
| Page flip-from-top | entering view: `rotateX(-8deg)→0`, `transform-origin: top`, `opacity 0→1`, 280ms; exiting opposite optional |
| Hover micro | row lift 1px + border accent; button scale 1.02; card shadow deepen |
| Kanban drag | card scale 1.03, column highlight accent-subtle |
| Toasts | slide from top-right 200ms |

**Accessibility:** if `prefers-reduced-motion: reduce`, set all durations to 0 / opacity-only fades ≤80ms. No parallax. Focus rings always visible (`outline: 2px solid var(--accent)`).

**Performance:** animate `transform`/`opacity` only; virtualize tables > 100 rows; pause offscreen animations.

---

## 10. Components library

Build a shared package / `components/ui` with:

1. **AppShell** — sidebar, topbar, breadcrumbs
2. **KpiCard** — value, delta, sparkline, loading skeleton
3. **Sparkline**
4. **DeskChart** — area/bar with theme tokens
5. **ScheduleList** / **ScheduleCalendar**
6. **AttentionFeed** — Needs attention / Waiting on you
7. **DataTable** — §14
8. **FilterBar** — search, chips, date range, “only mine”
9. **Form** primitives — TextField, MoneyField, Select, DateTime, TextArea, Repeater (line items)
10. **Stepper**
11. **StatusBadge**
12. **Modal** / **Drawer**
13. **Toast** / **ConfirmDialog**
14. **CommandSearch** (⌘K)
15. **ThemeToggle**
16. **EmptyState**
17. **FileDropzone** (documents)
18. **KanbanBoard** / **KanbanCard**
19. **EntityHeader**
20. **ParticularsGrid**
21. **FinancialSummaryCard**
22. **Pagination**
23. **BulkActionBar**

Every interactive component: keyboard operable, labeled, disabled styles, dark/light.

---

## 11. Marketing website

Public routes (same app or `apps/marketing`):

| Route | Content |
|---|---|
| `/` | Hero: brand **Ironbridge Construction** as dominant signal; one headline; one supporting sentence; CTA “Request a demo” / “Sign in”; full-bleed atmospheric construction site imagery (not inset card collage) |
| `/features` | Feature listing covering **every** module in §5 |
| `/how-it-works` | Lead → project → invoice narrative |
| `/pricing` | Single-company license framing (not SaaS tiers) — “Deployed for your firm” |
| `/demo` | **Demo video placeholder**: 16:9 surface, play affordance, caption “Product walkthrough — coming soon”, `data-demo-video-src` empty until Phase 4 |
| `/contact` | Form → stores `demo_requests` |
| `/login` | Auth |

Feature grid on `/features` must name: Dashboard desk, Leads, Pipeline kanban, Projects, Schedule, Clients, Estimates, Invoices, Crew, Subcontractors, Materials, Change Orders, Documents, Messages, Reports, RBAC security, Dark/Light themes.

Motion on marketing: fade-up sections on scroll; respect reduced motion. No purple gradients.

---

## 12. Data model

### 12.1 Core entities (tables)

`users`, `roles`, `permissions`, `role_permission`, `user_role`  
`leads`, `clients`, `client_contacts`, `pipeline_deals`, `pipeline_stage_events`  
`projects`, `project_subcontractor`, `estimates`, `estimate_items`, `invoices`, `invoice_items`, `payments`  
`schedule_events`, `schedule_event_assignees`  
`crew_members`, `subcontractors`, `materials`, `project_materials`  
`change_orders`, `change_order_items`  
`documents`, `message_threads`, `message_participants`, `messages`, `message_reads`  
`attention_items`, `audit_logs`, `settings`, `demo_requests`, `notifications`

### 12.2 Money

Store as `numeric(14,2)` + `currency char(3)` default `CAD`. Never float. Display with `Intl.NumberFormat('en-CA', { style:'currency', currency:'CAD' })`.

### 12.3 Soft deletes

Soft-delete: leads, clients, projects, estimates (draft), documents, crew, subcontractors, materials, messages (optional).  
Hard constraints: do not hard-delete invoices with payments; void instead. Awarded deals not deleted — archive.

### 12.4 Audit

`audit_logs`: `actor_id`, `action`, `entity_type`, `entity_id`, `before_json`, `after_json`, `ip`, `created_at`.  
Always log: stage moves, conversions, CO approve, payment create, role changes, settings updates.

### 12.5 Enums

Centralize in PHP enums / TS union types mirroring §5 statuses and pipeline stages.

---

## 13. API & backend patterns

### 13.1 Auth

- **Sanctum** SPA authentication (cookie + CSRF) for the web app.
- Optional **personal access tokens** for future mobile — same Sanctum.
- Login, logout, password reset, invite user (owner).
- Session timeout configurable; re-auth for role changes.

**Why Sanctum over Passport:** this is a first-party, single-company CRM. Passport’s OAuth authorization server adds complexity without a third-party client ecosystem. If a partner API appears later, add Passport or OAuth2 server as a Phase 5 — do not block v1.

### 13.2 RBAC permission keys (examples)

`leads.view|create|update|delete|convert`  
`pipeline.view|create|update|move_stage|force_stage`  
`projects.view|create|update|delete`  
`schedule.view|create|update|delete`  
`clients.view|create|update|delete`  
`estimates.view|create|update|send|accept`  
`invoices.view|create|update|send|record_payment|void`  
`crew.view|manage` `subcontractors.view|manage` `materials.view|manage`  
`change_orders.view|create|submit|approve`  
`documents.view|upload|delete` `messages.view|send`  
`reports.view` `settings.manage` `users.manage` `audit.view`

Enforce via Laravel Policies + `can:` middleware on every mutating route. Frontend hides controls but **server is source of truth**.

### 13.3 Validation

Form Requests for every write. Return 422 with `{ message, errors: { field: [..] } }`.

### 13.4 Error envelope & try/catch

```json
{ "ok": false, "error": { "code": "STAGE_TRANSITION_INVALID", "message": "Cannot move from Intake to Proposal." } }
```

Wrap service methods in try/catch; log exceptions; never leak stack traces to clients. HTTP 500 → generic message + `request_id`.

### 13.5 DB transactions (mandatory)

Use `DB::transaction` for:

- Lead → client/project conversion
- Pipeline stage move + side effects
- Invoice creation from estimate / CO
- Payment recording (idempotent)
- Change order approval (updates project value)
- Soft-delete cascades that touch balances

### 13.6 Idempotency

`Idempotency-Key` header (or body) required for `POST /payments`, `POST /invoices`, `POST /pipeline/deals/{id}/stage`. Store keys in `idempotency_keys` with response snapshot for 24h.

### 13.7 Optimistic UI

Client updates immediately; reconcile with server payload; on conflict show toast and refetch entity.

---

## 14. Security

- CSRF on cookie SPA (Sanctum/Axios XSRF)
- Escape all rendered user HTML; Markdown messages sanitized (allowlist)
- Rate limit login (`5/min`), password reset, contact form, API writes
- Documents: mime allowlist (`pdf`, `png`, `jpg`, `jpeg`, `webp`, `docx`, `xlsx`), max 25MB, virus-scan hook placeholder, store outside public root, authorize download by policy
- Secrets in `.env` only; never commit
- Role checks on every mutating route + policy on file access
- HTTPS only in production; secure cookies
- Mass-assignment protection (`$fillable` / DTO)
- SQL via Eloquent/Query Builder only

---

## 15. CRUD & table standards

Every index table implements:

| Feature | Spec |
|---|---|
| Search | Debounced q across primary text fields |
| Filters | Status, owner, date range, type/category |
| Sort | Multi-column: click cycles asc/desc/off; shift-click adds |
| Pagination | Page size 10/25/50; cursor or page; show total |
| Bulk actions | Select rows → assign owner, status change, export, delete (permissioned) |
| Inline actions | Edit, Preview drawer, Delete/Archive |
| Row click | Navigate to detail |
| Export | CSV of current filter set |
| Column visibility | Optional persistence |
| Empty | Illustration-free EmptyState + primary CTA |
| Loading | Skeleton rows matching columns |

Forms: create + edit share schema; dirty-state warn on navigate; server validation mirrored client-side with zod/yup; money inputs; line-item repeaters with drag reorder.

Preview: read-only drawer with Edit button.

---

## 16. Notifications & badges

- Recompute badge counts on login and via Echo/polling every 60s (or websocket).
- Mirror screenshot intent: Leads **3**, Projects **6**, Invoices **1**, Messages **2** in seed data.
- Notification types: `invoice_overdue`, `estimate_expiring`, `co_pending`, `message_unread`, `schedule_reminder`, `pipeline_idle`.
- Bell dropdown groups by day; mark read; deep link to entity.

---

## 17. Reporting details

- All report endpoints read-only, `reports.view`.
- Cache heavy aggregates 5 minutes keyed by filters.
- Aging invoices use `due_date` vs `today` in company timezone.
- Crew utilization: sum scheduled hours / (`crew.active_count * settings.weekly_capacity_hours`).
- Charts use design tokens; print-friendly table alternate.

---

## 18. Seed / demo data

Build a seeder `DemoSeeder` with Montreal/Quebec flavor:

**People**

- Nadia Haddad — PM / estimator
- Patrick Gagnon — Foreman
- Sophie Tremblay — Owner
- Marc Bouchard — Accountant
- Client contacts: Éric Rivard, Clinique Duval, Habitations Lajoie, etc.

**Projects (6 active)**

Examples: Rivard Kitchen Remodel (Plateau), Duval Clinic Fit-out (Laval), Lajoie Duplex Envelope (Verdun), … include addresses with H-postal codes.

**Leads (with 3 badge-worthy)**

Idle new leads waiting on Nadia.

**Pipeline**

Populate all six columns with realistic CAD values ($18k–$420k).

**Invoices**

At least one overdue (badge 1); several paid for revenue chart.

**Schedule today**

Morning site visit Patrick; afternoon delivery; inspection block — matches desk widget.

**Messages**

Two unread threads for demo user.

**Desk copy**

Polished Moorly-like microcopy: “Waiting on you”, “Indicated contract value”, “Only mine”.

Locale: `en-CA` with French names/addresses acceptable in data; UI English unless `settings.locale` set.

---

## 19. Acceptance criteria & phased delivery

### Phase 1 — Shell, auth, IA

- [ ] Laravel + Sanctum + React shell
- [ ] Dual theme toggle working on all shell surfaces
- [ ] Sidebar with all nav items (empty routes OK)
- [ ] Login/logout, roles seeded
- [ ] Marketing landing skeleton
- [ ] Motion primitives + reduced-motion

### Phase 2 — CRM core

- [ ] Leads, Clients, Pipeline kanban (DnD + rules), Projects
- [ ] Schedule day/week
- [ ] Desk dashboard widgets with seed data
- [ ] Entity detail shell (stepper, particulars, tabs)
- [ ] Messages + badges
- [ ] Attention / Waiting on you feeds

### Phase 3 — Financials & field ops

- [ ] Estimates + versions + PDF
- [ ] Invoices + payments (idempotent) + void
- [ ] Change orders approve transaction
- [ ] Crew, Subcontractors, Materials
- [ ] Documents upload secure
- [ ] Reports (4+ required)
- [ ] Audit log
- [ ] Full RBAC matrix enforced in tests

### Phase 4 — Polish, marketing, demo hook

- [ ] Marketing `/features` complete listing
- [ ] Demo video placeholder section wired (`/demo`)
- [ ] Motion pass on all major views
- [ ] Visual QA light + dark vs reference intent (surpass, don’t clone)
- [ ] Seed script one-command demo
- [ ] Short demo video recorded when system functional (placeholder until then)
- [ ] Performance: Lighthouse / profiling on desk + kanban

**Definition of done:** An owner can run Lead → Pipeline → Estimate → Project → CO → Invoice → Payment on seed-like data with dark/light themes, badges matching demo intent, and no unhandled server exceptions on happy + validation paths.

---

## 20. Out of scope

- Multi-tenant SaaS, org switcher, per-seat billing, Stripe subscription management for the CRM itself
- Insurance underwriting, binders, or premium accounting (Moorly is visual reference only)
- Public subcontractor marketplace
- Native mobile apps (API tokens may be prepared; apps not built)
- Accounting GL export beyond CSV (QuickBooks sync later)
- AI auto-estimating
- Offline-first field app
- Pixel-perfect clones of Ironbridge or Moorly

---

## 21. Testing expectations

- Feature tests for conversion, stage transitions, payment idempotency, CO approve, policy denials
- Browser smoke: login → desk → pipeline drag → estimate → invoice payment
- Visual: screenshot light/dark desk, kanban, detail for review
- Accessibility: keyboard nav, focus, contrast on both themes

---

## 22. Appendix — reference media paths

Project-kept reference pack:

| Path | Description |
|---|---|
| `/cursor/stores/self/media/crm-refs/README.md` | Index + expected binary filenames |
| `/cursor/stores/self/media/crm-refs/01-ironbridge-dashboard.md` | Ironbridge light desk / sidebar / KPIs / schedule / needs attention |
| `/cursor/stores/self/media/crm-refs/02-moorly-desk-dark.md` | Moorly dark desk, sparklines, premium chart, waiting on you |
| `/cursor/stores/self/media/crm-refs/03-moorly-pipeline-kanban.md` | Kanban stages + card anatomy + construction mapping |
| `/cursor/stores/self/media/crm-refs/04-moorly-detail-dark.md` | Detail stepper, particulars, indicated value (dark) |
| `/cursor/stores/self/media/crm-refs/05-moorly-detail-light.md` | Same detail in soft teal light theme |

**Expected binaries** (drop beside the notes if/when available):

- `/cursor/stores/self/media/crm-refs/ironbridge-dashboard.jpg`
- `/cursor/stores/self/media/crm-refs/moorly-desk-dark.png`
- `/cursor/stores/self/media/crm-refs/moorly-pipeline-kanban.png`
- `/cursor/stores/self/media/crm-refs/moorly-detail-dark.png`
- `/cursor/stores/self/media/crm-refs/moorly-detail-light.png`

Original source paths (worker environment; may be absent):

1. `/home/ubuntu/.cursor/projects/agent/assets/0d99ca31-46f3-43c1-bf99-472c7a9d351b.jpg`
2. `/home/ubuntu/.cursor/projects/agent/assets/fc9e945e-ec90-4c13-a130-2ca7a79be14f.png`
3. `/home/ubuntu/.cursor/projects/agent/assets/06dd3309-1393-48bb-b891-04f4a51ca28b.png`
4. `/home/ubuntu/.cursor/projects/agent/assets/00c4d7b6-ec90-4c13-a130-2ca7a79be14f.png`
5. `/home/ubuntu/.cursor/projects/agent/assets/30e2b8cf-906e-4555-ba9e-d23e088d762d.png`

---

## 23. Final instruction to the builder

Build Ironbridge Construction CRM as specified. Prefer depth over shortcuts. Every sidebar module ships. Desk must feel as considered as Moorly; modules as complete as Ironbridge’s construction scope; visuals and motion better than both. When uncertain, choose the option that strengthens **single-company construction operations**, **dual-theme craft**, and **transactional integrity** of money and stage changes.
