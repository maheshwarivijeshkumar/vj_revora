# Implementation Plan — Overview

This directory turns the [master system prompt](../AI_Omnichannel_Lead_Generation_CRM_SaaS_Master_System_Prompt_UPDATED.md)
into an executable plan. The master document stays the specification; these
documents are how it gets built.

| Document                                   | Covers                                                                      |
| ------------------------------------------ | --------------------------------------------------------------------------- |
| [`01-ARCHITECTURE.md`](01-ARCHITECTURE.md) | Stack, tenancy model, domain layout, queues, integration hub, security      |
| [`02-DATA-MODEL.md`](02-DATA-MODEL.md)     | Central and tenant schema, indexes, isolation guarantees                    |
| [`03-MODULES.md`](03-MODULES.md)           | Every module: scope, routes, permissions, API surface                       |
| [`04-UI-SYSTEM.md`](04-UI-SYSTEM.md)       | Design tokens, layout shell, component library, DataTable standard, theming |
| [`05-ROADMAP.md`](05-ROADMAP.md)           | Phases, deliverables, exit criteria                                         |
| [`06-DECISIONS.md`](06-DECISIONS.md)       | Where this plan diverges from the spec, and why                             |

Brand assets are documented separately in [`resources/brand/README.md`](../../resources/brand/README.md).

---

## The product in one line

A multi-tenant **Lead Operating System**: connect authorized lead sources,
normalize and score what arrives, work it through a CRM, engage across WhatsApp
/ email / SMS / social, automate the follow-up, and attribute the revenue back
to source — while remaining usable as a layer _on top of_ a customer's existing
CRM rather than a replacement for it.

```
CONNECT → CAPTURE → NORMALIZE → DEDUPLICATE → ENRICH → SCORE → QUALIFY
        → ASSIGN → ENGAGE → NURTURE → BOOK → CONVERT → ATTRIBUTE → ANALYZE
```

## Dual-mode requirement (§102)

This is a core product requirement, not an integration nicety. Every module must
work in both modes:

- **Option A — built-in CRM.** The customer has no CRM; ours is the system of record.
- **Option B — existing CRM.** Salesforce/HubSpot/Zoho/custom stays the system of
  record; we are acquisition + AI qualification + omnichannel + automation, and
  we push/pull through the connector framework using external IDs.

Mode B is the reason every entity carries `external_system` / `external_record_type`
/ `external_record_id`, and the reason the REST API is a first-class surface
rather than an afterthought.

## The rule that shapes the product (§2)

The platform integrates through **official, authorized APIs only**. It does not
log into personal accounts, scrape arbitrary profiles, bypass CAPTCHAs or rate
limits, or collect private data without authorization.

The UI must visibly distinguish four states on every lead and every source:

| State                  | Meaning                                       |
| ---------------------- | --------------------------------------------- |
| Connected / authorized | OAuth-connected provider, live sync           |
| API-supported data     | Field came from an authorized provider API    |
| User-imported data     | Customer-owned data, CSV/API ingestion        |
| Unsupported source     | Provider has no authorized path for this data |

This is a compliance boundary. It gets enforced in the ingestion layer, not by
convention.

## Non-negotiables (§101)

Carried forward verbatim as build constraints. Every PR is checked against these:

1. Multi-tenant from day one; no tenant can reach another's data.
2. Everything permission-aware; everything important auditable.
3. Every integration isolated behind a provider interface; OAuth tokens encrypted.
4. All external events idempotent; long-running work queued.
5. Dashboard widget-driven, not hard-coded. Subscription limits not hard-coded.
6. Forms validated server-side — never relying on HTML `required`.
7. Public API versioned; webhooks signed, retryable, observable.
8. No secret or token values in logs, ever.
9. AI provider-agnostic; human-approval and autonomous modes both exist.

## Current repository state

|              |                                                                       |
| ------------ | --------------------------------------------------------------------- |
| Framework    | Laravel 13 + Inertia v3 + Vue 3 + TypeScript + Tailwind 4 + Wayfinder |
| Database     | SQLite (starter default — **must move to PostgreSQL**, see Phase 0)   |
| Domain code  | None yet. `app/` holds the starter skeleton only.                     |
| Migrations   | Starter only: users, cache, jobs                                      |
| Brand assets | Complete — 8 identities in `resources/brand/` and `public/brand/`     |

Phase 0 in the [roadmap](05-ROADMAP.md) exists to close the gap between this
starter kit and the foundation the rest of the plan assumes.
