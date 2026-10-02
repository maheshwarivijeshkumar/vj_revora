# UI System

Implements §35–46 and §106–128.

The product must feel like a mature SaaS application used daily by sales,
marketing, support and management teams — clean enterprise information
architecture, compact but readable density, strong whitespace, rounded cards,
subtle borders, soft elevation, clear hierarchy, accessible contrast and fast
interaction feedback.

---

## 1. Design tokens

Two layers, deliberately separate (§126, §106.3):

- **Foundation** — the product's Deep Navy + Electric Indigo system. Stable.
- **Brand accent** — swappable per selected brand, and later per tenant for
  white-label (§95), without touching component code.

Tailwind 4 defines these with `@theme`, so every token is simultaneously a CSS
custom property and a utility class.

```css
/* resources/css/tokens.css */
@theme {
    /* --- Foundation: brand-neutral product palette (§106.2) --- */
    --color-primary-500: #4f46e5;
    --color-primary-600: #4338ca;
    --color-primary-700: #3730a3;
    --color-accent-blue: #2563eb;
    --color-accent-cyan: #06b6d4;

    --color-success: #10b981;
    --color-warning: #f59e0b;
    --color-danger: #ef4444;
    --color-info: #0ea5e9;

    --color-text-strong: #0f172a;
    --color-text-body: #334155;
    --color-text-muted: #64748b;
    --color-text-soft: #94a3b8;

    --color-border: #e2e8f0;
    --color-border-soft: #f1f5f9;
    --color-surface: #ffffff;
    --color-surface-alt: #f8fafc;
    --color-page: #f6f8fc;

    /* --- Typography (§107) --- */
    --font-sans: 'Inter', system-ui, sans-serif; /* application UI */
    --font-display:
        'Poppins', 'Inter', system-ui, sans-serif; /* marketing only */

    --radius-card: 12px;
    --radius-control: 8px;
    --radius-pill: 999px;

    --shadow-card:
        0 1px 2px rgb(15 23 42 / 0.04), 0 1px 3px rgb(15 23 42 / 0.06);
    --shadow-pop: 0 10px 24px rgb(15 23 42 / 0.1);

    --transition-base: 160ms cubic-bezier(0.4, 0, 0.2, 1);
}

/* --- Dark mode: a designed system, not an inversion (§123) --- */
[data-theme='dark'] {
    --color-text-strong: #f8fafc;
    --color-text-body: #cbd5e1;
    --color-text-muted: #94a3b8;
    --color-text-soft: #64748b;

    --color-border: #24364d;
    --color-border-soft: #1a2a3f;
    --color-surface: #0f1b2d;
    --color-surface-alt: #12233a;
    --color-page: #08111f;
}
```

**Never pure `#000000`.** Deep navy surfaces are what create hierarchy in dark
mode; black flattens it.

### Brand accent layer

Swapped independently of the foundation. Values from
[`resources/brand/README.md`](../../resources/brand/README.md):

```css
:root {
    /* example: Revora */
    --brand-primary: #059669;
    --brand-secondary: #10b981;
    --brand-accent: #2563eb;
    --brand-gradient: linear-gradient(135deg, #059669, #2563eb);
}
```

Components reference `--brand-*` for identity (logo lockup, sign-in, marketing,
primary CTA) and `--color-primary-*` for product chrome. Keeping them separate
is what lets the brand change without a UI rebuild, and what makes tenant
white-labelling a settings change rather than a fork.

### Type scale (§107)

| Token   | Size / Line / Weight |
| ------- | -------------------- |
| Display | 48 / 56 / 700        |
| H1      | 32 / 40 / 700        |
| H2      | 24 / 32 / 700        |
| H3      | 20 / 28 / 600        |
| H4      | 18 / 26 / 600        |
| Body L  | 16 / 24 / 400        |
| Body    | 14 / 22 / 400        |
| Small   | 13 / 20 / 400        |
| Caption | 12 / 18 / 500        |
| Table   | 13–14 / 20 / 400–500 |

Inter is the application typeface. **Poppins is marketing-only** — hero
headings, brand statements, landing-page headings, promotional sections. Using
it in the app costs readability at the densities a CRM needs.

---

## 2. Application shell (§108)

```
┌─────────────────────────────────────────────────────────────┐
│ Topbar: Search  Quick Create  Integrations  Bell  ?  ◐  👤 │
├───────────────┬─────────────────────────────────────────────┤
│               │ Breadcrumb / Page title / Page actions      │
│ Sidebar       ├─────────────────────────────────────────────┤
│ (collapsible) │                                             │
│               │ Main content                                │
└───────────────┴─────────────────────────────────────────────┘
```

**Sidebar:** collapsed/expanded, icon + label, active indicator, nested
navigation, badge counts, pinned favorites, workspace switcher, tooltips when
collapsed. Items are both **permission-aware and subscription-aware** — a
feature the plan does not include is not rendered as a dead link.

**Topbar:** global search, `⌘K` hint, quick-create, integration status
indicator, notifications, help, theme switcher, language, profile menu.

The integration status indicator in the topbar is deliberate: customers should
notice a disconnected source before they notice missing leads.

**Mobile (§71, §122):** sidebar becomes a drawer, cards stack, tables scroll or
become cards, filters move to a bottom sheet, modals become full-screen sheets,
conversations go full-screen single-pane. Primary actions stay reachable.

### Navigation

**Platform Admin** — Dashboard, Tenants, Users, Plans, Subscriptions, Billing,
Usage, Integrations, AI Providers, Message Providers, Templates, Coupons,
Referrals, System Logs, Audit Logs, API, Settings.

**Tenant** — Dashboard, Leads, Contacts, Companies, Deals, Pipelines, Inbox,
Campaigns, Automation, Appointments, Tasks, Reports, AI, Integrations, Forms,
Templates, Users, Roles, API, Billing, Settings.

---

## 3. Iconography (§40, §118)

**Lucide only.** One library, no mixing, no emoji as interface icons.

| Concept    | Icon                           |     | Concept      | Icon                  |
| ---------- | ------------------------------ | --- | ------------ | --------------------- |
| Dashboard  | `LayoutDashboard`              |     | Analytics    | `ChartNoAxesCombined` |
| Leads      | `UsersRound` / `UserRoundPlus` |     | Integrations | `PlugZap`             |
| Contacts   | `ContactRound`                 |     | API          | `Braces`              |
| Companies  | `Building2`                    |     | Settings     | `Settings2`           |
| Deals      | `Handshake`                    |     | Search       | `Search`              |
| Pipeline   | `GitBranch`                    |     | Filter       | `ListFilter`          |
| Campaigns  | `Megaphone`                    |     | Add          | `Plus`                |
| Automation | `Workflow`                     |     | Edit         | `Pencil`              |
| AI         | `Sparkles`                     |     | Delete       | `Trash2`              |
| Inbox      | `Inbox`                        |     | View         | `Eye`                 |
| WhatsApp   | `MessageCircle`                |     | More         | `MoreHorizontal`      |
| Email      | `Mail`                         |     | Export       | `Download`            |
| SMS        | `Smartphone`                   |     | Import       | `Upload`              |
| Calendar   | `CalendarDays`                 |     | Sync         | `RefreshCw`           |
| Tasks      | `CheckSquare`                  |     | Assign       | `UserRoundCog`        |

Icons are semantically consistent across every module — `RefreshCw` means sync
everywhere, never "reset".

---

## 4. Component library (§125)

```
components/
  ui/          Button Input Select MultiSelect Combobox Checkbox Radio Switch
               Textarea DatePicker DateRangePicker TagInput Avatar AvatarGroup
               Badge Tooltip Popover DropdownMenu ContextMenu Tabs Accordion
               Progress Stepper Skeleton Alert Separator

  layout/      AppShell Sidebar Topbar Breadcrumbs PageHeader

  data/        DataTable DataTableToolbar DataTableFilters DataTablePagination
               ColumnSelector BulkActionBar SavedViews DensityToggle
               EmptyState ErrorState

  charts/      LineChart AreaChart BarChart StackedBar DonutChart FunnelChart
               GaugeChart Heatmap Sparkline ChartCard

  dashboard/   WidgetContainer WidgetPicker MetricCard DashboardGrid

  overlay/     Modal Drawer ConfirmationDialog Toast CommandPalette

  domain/      StatusBadge ScoreBadge LeadCard PipelineBoard Timeline
               ActivityFeed ConversationThread MessageComposer FileUploader
               RichTextEditor WorkflowCanvas FieldMapper
```

Every component supports light and dark mode and meets accessibility
requirements. Giant monolithic components are the failure mode to avoid (§77).

---

## 5. DataTable standard (§110–115)

Used by every listing page: leads, contacts, companies, deals, tasks, campaigns,
conversations, messages, appointments, users, roles, integrations, API keys,
webhooks, subscriptions, invoices, usage records, workflows, templates, audit
logs.

```
┌────┬──────────────────┬──────────┬───────────┬───────┬─────────┬─────────┐
│ ☐  │ Lead / Name ↑    │ Source   │ Status    │ Score │ Updated │ Actions │
├────┼──────────────────┼──────────┼───────────┼───────┼─────────┼─────────┤
│ ☐  │ John Smith       │ Facebook │ Qualified │ 92    │ 2m ago  │ ⋮       │
└────┴──────────────────┴──────────┴───────────┴───────┴─────────┴─────────┘
```

**First column is always row selection** where bulk actions exist — with select
-all-page, an indeterminate state, selection cleared after each bulk action, and
an optional "select all N matching records" for large sets.

**Last column is row actions.** Icon buttons for View / Edit / Delete, then a
`MoreHorizontal` menu for the rest (duplicate, archive, restore, assign,
convert, send, call, WhatsApp, email, add task, add note, export, sync, retry,
run workflow, view logs, view history, view API payload). Not a row of text
buttons.

**Toolbar (§111):** debounced server-side search; a Filters button with a count
badge across ~20 filter types; saved views; column show/hide, reorder and reset
persisted per user; density (comfortable / default / compact); permission-gated
export to CSV/XLSX/PDF with large exports queued.

**Footer (§112):** `Showing 1–25 of 12,482 records`, rows-per-page
(10/25/50/100/250), first/previous/next/last with disabled states, loading
state and selection count. Page size persists per user per table.

**Bulk actions (§113):** a contextual bar replacing the toolbar on selection.
Respects permissions, subscription limits and tenant isolation; confirms
destructive actions **showing the exact affected record count**; shows progress;
runs large operations through queues; returns a success/failure summary; writes
audit events.

**States (§114):** skeleton rows while loading; an explained empty state with a
CTA; a distinct no-search-results state with Clear Filters; an error state with
Retry; and no unauthorized fields or actions in partial states.

**Server-side pagination is mandatory for large datasets.** Never load thousands
of records into the browser to paginate them (§72, §112).

**Accessibility (§115):** keyboard navigation, labelled checkboxes, visible
focus, tooltips on icon-only actions, screen-reader-friendly headings, sort
indicators, visible selected rows, accessible menus, destructive confirmations.

On small screens: horizontal scroll for dense tables, priority columns stay
visible, secondary columns move to a row-details drawer, actions stay reachable.
**Never shrink text until the table is unusable.**

---

## 6. Charts (§37, §109)

Apache ECharts via `vue-echarts`. Line, area, bar, stacked bar, donut, funnel,
gauge, scatter, heatmap, KPI cards, progress, sparkline, cohort.

Every chart supports loading, empty and error states, date range, filters,
tooltips, legend control, responsive resize, accessible labels and export where
appropriate.

**One semantic colour per metric across the entire application and all reports.**
If "Qualified" is green in the funnel, it is green in every report and every
export. Avoid excessive saturation — brand colour marks primary actions and
important states; neutral surfaces carry the interface.

**Do not overload dashboards with charts** (§37).

---

## 7. Status, score and badges (§119)

| Status      | Colour     |     | Score  | Band      |
| ----------- | ---------- | --- | ------ | --------- |
| New         | Blue       |     | 0–39   | Low       |
| Contacted   | Cyan       |     | 40–69  | Medium    |
| Qualified   | Green      |     | 70–89  | High      |
| Nurturing   | Violet     |     | 90–100 | Very High |
| Appointment | Indigo     |     |        |           |
| Proposal    | Amber      |     |        |           |
| Won         | Green      |     |        |           |
| Lost        | Red / Gray |     |        |           |
| Archived    | Gray       |     |        |           |

Thresholds stay tenant-configurable. **Never rely on colour alone** — every
badge carries text, and icons where it helps.

---

## 8. Forms, modals and drawers (§42, §43, §116)

**Do not rely on browser HTML `required` validation** (§101.16). Client-side
validation is UX; **server-side Laravel validation is authoritative**, and every
required field is validated there.

Messages are human-friendly and centrally defined:

```
First name is required.
Please enter a valid email address.
Phone number is invalid.
Pipeline stage is required.
```

Modal/drawer CRUD for create lead, edit lead, add contact, add note, assign
owner, create task, add tag, create API key, add integration, create webhook.
Full-page wizards only where a form genuinely cannot fit — import, workflow
builder, onboarding.

All forms: field-level errors, summary errors where useful, async submit states,
duplicate-submit prevention, unsaved-change protection, accessible labels,
helper text, input formatting.

---

## 9. Toasts (§41, §117)

Global, accessible, queued. Success / Info / Warning / Error / Loading.

```
✓ Lead created successfully.
ℹ Sync started. You can continue working.
⚠ Your monthly message quota is almost exhausted.
✕ The integration connection failed. Please reconnect.   [Reconnect]
```

Non-blocking, dismissible, auto-dismissing for normal messages and persistent
for critical errors, with action-button support, deduplication and
mobile-friendly positioning.

**Toasts never replace inline form errors**, and never hide validation errors.
Destructive operations use a confirmation modal, not a toast.

---

## 10. Motion (§121)

150–200ms hover transitions, smooth dropdowns, drawer and modal transitions,
skeleton shimmer, chart transitions, success confirmation.

No excessive bouncing, no long transitions, no decorative animation on every
component, nothing that slows a CRM workflow. **Respect
`prefers-reduced-motion`.**

---

## 11. Theming (§39, §123)

Light / Dark / System, persisted per user and applied before first paint to
avoid a flash. Configurable at platform level and optionally per tenant.

Dark mode defines its own tokens for page background, sidebar, header, cards,
inputs, tables, borders, text, charts, status badges, tooltips, modals and
dropdowns — it is a designed system, not an inversion.

---

## 11b. Brand token layer

All eight candidate identities from §105 ship as swappable token sets in
`resources/css/brands.css`, selected by `data-brand` on `<html>` from
`config/brand.php`.

Each brand contributes a full 50–900 ramp. Which _step_ fills which semantic
role is declared once and is theme-dependent — light mode takes 600 for
primary, dark mode takes 400, because a 600 that reads confident on white goes
muddy on deep navy. Adding a ninth brand means adding a ramp and nothing else.

The palette ramps themselves (blue, emerald, amber, red, slate) come from the
"UI Color Palettes" block of the supplied brand board, which agrees with
§106.2 on every semantic colour.

`/branding` previews any candidate live, per session, without changing the
configured default. This is also the hook §95 white-labelling will use: a
tenant's custom colours become another block of the same variables.

---

## 12. Marketing site (§124)

Shares the design language but may be more expressive. Sections: hero, product
overview, lead capture, AI features, CRM, omnichannel inbox, automation,
integrations, analytics, security, pricing, testimonials, FAQ, CTA, footer.

Poppins for major headings, Inter for supporting text and UI. Product
screenshots must match the actual dashboard.

**No fake customer logos, fake testimonials or fabricated performance claims.**

The landing page follows this strictly. Common SaaS landing patterns that were
deliberately _not_ copied from reference sites: customer logo walls, testimonial
carousels, and headline statistics ("120+ founders", "$40K saved"). An unlaunched
product has no such evidence, and inventing it is exactly what §124 prohibits.

What replaces them: qualitative problem framing, pricing read live from the
`plans` table, and abstract product visuals built from the design tokens rather
than stock imagery. Each stays true as the product evolves.

### Avoiding a generated look

Patterns that make a marketing page read as machine-produced, and what the site
does instead:

| Tell                                                | Instead                                                                                                             |
| --------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Every section centred with eyebrow, title, subtitle | Left-aligned section labels with a rule and a two-digit index; centring reserved for the rare section that earns it |
| Uniform grids of identical cards                    | A bento grid with deliberately uneven cells, and tables where the content is genuinely tabular                      |
| An icon in a soft rounded square on every card      | Numbers, hairline dividers and plain headings; icons used where they carry meaning                                  |
| Identical vertical padding throughout               | Varied section rhythm, with one deep-navy band breaking the run of light surfaces                                   |
| Gradient blobs behind everything                    | One hand-drawn underline in the hero; no ambient blur                                                               |
| Uniform heading sizes                               | A wide display range with tight tracking (`-0.03em`) at large sizes                                                 |

The intent is that a reader cannot predict the next section from the previous
one.

---

## 13. QA checklist (§127)

Every UI module is checked against this before acceptance:

- [ ] Light mode · [ ] Dark mode · [ ] Mobile · [ ] Tablet
- [ ] Keyboard navigation · [ ] Visible focus states
- [ ] Empty state · [ ] Loading state · [ ] Error state
- [ ] Permission restrictions respected
- [ ] Subscription restrictions respected
- [ ] Toast feedback exists
- [ ] Destructive actions require confirmation
- [ ] Table supports search / filter / sort / pagination
- [ ] First column supports row selection where applicable
- [ ] Actions accessible via icons and/or menu
- [ ] Total record count displayed · [ ] Per-page selector
- [ ] Bulk actions work · [ ] Export respects permissions
- [ ] Server-side pagination for large data
- [ ] Forms do not rely on browser `required` validation
- [ ] API errors presented clearly
- [ ] No tenant data leaks across boundaries
