# Data Model

PostgreSQL 16. Single database, row-level tenant isolation (see
[ADR-002](06-DECISIONS.md)).

Conventions throughout:

- `id` — `bigint` identity primary key. Public-facing identifiers use a
  separate `uuid` column, so internal IDs never leak record counts.
- `tenant_id` — on **every** tenant-owned table, FK to `tenants`, indexed, and
  the leading column of every composite index.
- `created_at` / `updated_at` / `deleted_at` — soft deletes on anything a user
  can destroy. §18 requires that data is never silently destroyed.
- `metadata jsonb` — provider-specific and extension fields. Never widen the
  core schema for one provider (§17).
- Money as `numeric(15,4)` plus an ISO `currency` column. Never floats.

---

## 1. Central schema

No `tenant_id`. Reachable only from the platform admin context.

### Tenancy

```
tenants              id, uuid, name, slug, status, plan_id, trial_ends_at,
                     data jsonb, created_at
tenant_domains       id, tenant_id, domain (unique), is_primary
platform_users       id, uuid, name, email, password, two_factor_*, status
platform_roles       id, name, guard
platform_permissions id, name, guard
```

`tenants.status`: `provisioning | active | suspended | cancelled`.
A tenant stuck in `provisioning` is the signal that §7 step N failed — the
provisioning job is idempotent and resumable from any step.

### Plans, billing, usage

```
plans                id, key, name, interval, price, currency, trial_days,
                     is_public, sort_order
plan_features        id, plan_id, feature_key, value (jsonb), is_unlimited
feature_catalog      id, key, name, type (boolean|limit|metered), description
subscriptions        id, tenant_id, plan_id, status, provider, provider_id,
                     current_period_start/end, trial_ends_at, cancels_at,
                     grace_ends_at, scheduled_plan_id
subscription_items   id, subscription_id, feature_key, quantity
subscription_events  id, subscription_id, type, payload jsonb, occurred_at
billing_customers    id, tenant_id, provider, provider_customer_id
billing_transactions id, tenant_id, subscription_id, type, amount, currency,
                     status, provider_reference, invoice_url, occurred_at
coupons              id, code (unique), type, value, max_redemptions,
                     redeemed_count, valid_from, valid_until
referrals            id, referrer_tenant_id, referred_tenant_id, code, status,
                     qualified_at, reward_amount, paid_at
usage_meters         id, tenant_id, meter_key, period_start, period_end,
                     used, limit, overage_allowed
usage_records        id, tenant_id, meter_key, quantity, occurred_at,
                     source_type, source_id, idempotency_key
```

`plan_features.value` is `jsonb` so a feature can be a boolean, a numeric
limit, or a structured grant without schema changes. **No plan name or limit is
ever hard-coded in application code** (§8, §101.28).

`usage_records.idempotency_key` is uniquely indexed per tenant — a retried job
must not double-count usage.

### Platform operations

```
integration_providers id, key, name, category, is_enabled, api_version,
                      config jsonb, docs_url
platform_templates    id, key, channel, name, subject, body, variables jsonb,
                      version, is_active
system_settings       id, key (unique), value jsonb
feature_flags         id, key, is_enabled, rollout_percentage,
                      tenant_allowlist jsonb
audit_logs            id, tenant_id (nullable), actor_type, actor_id, action,
                      entity_type, entity_id, before jsonb, after jsonb,
                      ip, user_agent, created_at
```

`audit_logs` is central with a nullable `tenant_id` so platform-level and
tenant-level actions share one queryable trail. Partition by month —
it becomes the largest table in the system.

---

## 2. Tenant schema

Every table below carries `tenant_id`.

### Identity and access

```
users                 id, uuid, tenant_id, name, email, password, avatar_path,
                      timezone, locale, status, last_login_at, two_factor_*
roles / permissions   spatie/laravel-permission, team-scoped by tenant
teams                 id, tenant_id, name, parent_id, timezone, business_hours jsonb
team_members          id, team_id, user_id, role
```

### Leads

```
leads            id, uuid, tenant_id, first_name, last_name, full_name, email,
                 email_normalized, phone, phone_normalized, country,
                 company_name, job_title, website, status, stage,
                 score, score_band, owner_id, team_id, source_id,
                 campaign_id, form_id, external_system, external_record_type,
                 external_record_id, consent, consent_source, consent_at,
                 privacy_policy_version, first_touch jsonb, last_touch jsonb,
                 utm jsonb, landing_page, referrer, metadata jsonb,
                 last_activity_at, next_follow_up_at, converted_at,
                 merged_into_id, created_at, updated_at, deleted_at

lead_sources         id, tenant_id, key, name, type, provider, is_active
lead_source_accounts id, tenant_id, lead_source_id, oauth_connection_id,
                     external_account_id, name, config jsonb, status,
                     last_synced_at, last_cursor
lead_events          id, tenant_id, lead_id, type, payload jsonb,
                     provider_event_id, occurred_at
lead_scores          id, tenant_id, lead_id, score, band, method,
                     reasons jsonb, computed_at
lead_score_rules     id, tenant_id, name, condition jsonb, points,
                     is_active, sort_order
lead_assignments     id, tenant_id, lead_id, user_id, team_id, rule_id,
                     assigned_at, assigned_by
lead_merges          id, tenant_id, master_lead_id, merged_lead_id,
                     strategy, diff jsonb, merged_by, merged_at
```

**`email_normalized` / `phone_normalized`** are generated columns (lowercased
email; E.164 phone) carrying unique-ish indexes. Deduplication (§18) matches on
these rather than on raw input, so `John@Example.com ` and `john@example.com`
collide as intended.

**`merged_into_id`** preserves duplicates as tombstones rather than deleting
them. `lead_merges.diff` records exactly what was combined, satisfying "never
silently destroy data".

**`score_band`** is stored, not derived at read time, because thresholds are
tenant-configurable (§119) and historical scores must not shift when a tenant
retunes its bands.

### CRM

```
contacts        id, uuid, tenant_id, first_name, last_name, email, phone,
                job_title, owner_id, lead_id, external_*, metadata jsonb
companies       id, uuid, tenant_id, name, domain, industry, size, country,
                website, owner_id, external_*, metadata jsonb
contact_company  contact_id, company_id, role, is_primary
deals           id, uuid, tenant_id, title, value, currency, pipeline_id,
                stage_id, probability, status, expected_close_date, closed_at,
                lost_reason, owner_id, lead_id, contact_id, company_id,
                external_*, metadata jsonb
pipelines       id, tenant_id, name, is_default, entity_type
pipeline_stages id, tenant_id, pipeline_id, name, key, probability, sort_order,
                is_won, is_lost, required_fields jsonb, automation jsonb
activities      id, tenant_id, type, subject, body, entity_type, entity_id,
                user_id, occurred_at, duration_minutes, outcome
tasks           id, tenant_id, title, description, due_at, completed_at,
                priority, status, assignee_id, entity_type, entity_id
notes           id, tenant_id, body, entity_type, entity_id, user_id, is_pinned
tags            id, tenant_id, name, color, category
taggables        tag_id, taggable_type, taggable_id
products        id, tenant_id, sku, name, description, price, currency, is_active
quotes          id, tenant_id, deal_id, number, status, subtotal, tax, total,
                currency, valid_until, sent_at, accepted_at
quote_items     id, quote_id, product_id, description, quantity, unit_price, total
sales           id, tenant_id, deal_id, amount, currency, closed_at,
                attribution jsonb
attachments     id, tenant_id, entity_type, entity_id, filename, mime_type,
                size, storage_key, uploaded_by, scanned_at, scan_result
```

`activities`, `tasks`, `notes`, `attachments` and `tags` are **polymorphic**
across leads / contacts / companies / deals / conversations. One timeline
implementation serves every entity (§86).

### Conversations and messaging

```
conversations              id, uuid, tenant_id, channel, status, subject,
                           lead_id, contact_id, assignee_id, team_id,
                           external_thread_id, is_unread, last_message_at,
                           first_response_at, sla_due_at, sla_breached_at,
                           ai_mode, closed_at
conversation_participants  id, conversation_id, participant_type,
                           participant_id, identifier, role
messages                   id, uuid, tenant_id, conversation_id, direction,
                           channel, body, body_html, status, provider,
                           provider_message_id, sent_by, sent_at,
                           delivered_at, read_at, failed_reason,
                           is_ai_generated, ai_metadata jsonb, metadata jsonb
message_attachments        id, message_id, filename, mime_type, size, storage_key
message_templates          id, tenant_id, channel, name, category, subject,
                           body, variables jsonb, language, is_active,
                           provider_template_id, approval_status, version
email_accounts             id, tenant_id, user_id, provider, email, config jsonb,
                           oauth_connection_id, is_shared, status
email_sequences            id, tenant_id, name, status, settings jsonb
email_sequence_steps       id, sequence_id, step, delay_minutes, template_id,
                           condition jsonb
consents                   id, tenant_id, identifier, channel, status, source,
                           occurred_at, policy_version
opt_outs                   id, tenant_id, identifier, channel, reason, occurred_at
```

`message_templates` covers WhatsApp, email and SMS in one table with a `channel`
discriminator — three near-identical tables (§6 lists them separately) would
triple the template UI and the variable-validation logic for no gain.

**`consents` and `opt_outs` are keyed on `identifier`** (email or E.164 phone),
not on `lead_id`. Opt-out follows the person across every duplicate, merge and
re-import — which is the only way §88 actually holds.

### Automation

```
automation_workflows  id, uuid, tenant_id, name, description, trigger_type,
                      trigger_config jsonb, mode (manual|approval|autonomous),
                      status, limits jsonb, quiet_hours jsonb,
                      retry_policy jsonb, is_enabled, version
automation_nodes      id, workflow_id, type (trigger|condition|action|delay|
                      branch), config jsonb, parent_id, branch_key, position jsonb
automation_runs       id, uuid, tenant_id, workflow_id, entity_type, entity_id,
                      status, context jsonb, started_at, completed_at,
                      error, idempotency_key
automation_actions    id, run_id, node_id, status, input jsonb, output jsonb,
                      approved_by, approved_at, executed_at, error
```

`automation_actions.approved_by` is what makes approval mode work: in that mode
the action is persisted with everything needed to execute, and waits. Manual /
approval / autonomous are the same engine with different gates (§29).

### Campaigns, forms, appointments

```
campaigns          id, uuid, tenant_id, name, type, channel, status, budget,
                   currency, starts_at, ends_at, external_campaign_id, settings jsonb
campaign_members   id, campaign_id, entity_type, entity_id, status, joined_at
campaign_metrics   id, campaign_id, date, sent, delivered, opened, clicked,
                   replied, qualified, converted, revenue, cost
forms              id, uuid, tenant_id, name, type, slug, schema jsonb,
                   settings jsonb, redirect_url, is_active, submission_count
form_submissions   id, tenant_id, form_id, lead_id, payload jsonb, utm jsonb,
                   landing_page, referrer, ip, user_agent, submitted_at
appointments       id, uuid, tenant_id, title, type, status, starts_at, ends_at,
                   timezone, location, meeting_url, lead_id, contact_id,
                   deal_id, host_id, external_event_id, calendar_connection_id,
                   reminder_sent_at, cancelled_at, cancel_reason
calendar_connections id, tenant_id, user_id, provider, oauth_connection_id,
                   external_calendar_id, sync_token, status
booking_links      id, tenant_id, user_id, slug, name, duration_minutes,
                   availability jsonb, buffer_minutes, blackout_dates jsonb
```

### Integrations and API

```
oauth_connections    id, uuid, tenant_id, provider, external_account_id,
                     access_token (encrypted), refresh_token (encrypted),
                     expires_at, scopes jsonb, provider_api_version,
                     connected_by, connected_at, last_verified_at, status
integration_events   id, tenant_id, provider, provider_event_id, event_type,
                     payload jsonb, signature_valid, status, received_at,
                     processed_at, error
integration_syncs    id, tenant_id, oauth_connection_id, type, status,
                     cursor, items_total, items_ok, items_failed,
                     started_at, finished_at
integration_errors   id, tenant_id, oauth_connection_id, sync_id, code,
                     message, is_recoverable, action_required, payload jsonb,
                     occurred_at, resolved_at
crm_connections      id, tenant_id, system, config jsonb, field_map jsonb,
                     sync_direction, conflict_strategy, status, last_synced_at
api_keys             id, uuid, tenant_id, name, prefix, hash, scopes jsonb,
                     rate_limit, last_used_at, expires_at, revoked_at, created_by
webhooks             id, uuid, tenant_id, url, events jsonb, secret (encrypted),
                     is_active, failure_count, disabled_at
webhook_deliveries   id, tenant_id, webhook_id, event, payload jsonb,
                     attempt, status_code, response_body, duration_ms,
                     delivered_at, next_retry_at
```

**`api_keys` stores only `prefix` + `hash`.** The secret is shown once at
creation and is unrecoverable afterwards (§48). Lookup is by `prefix`, then a
hash comparison.

**`integration_events` carries a unique index on
`(tenant_id, provider, provider_event_id)`** — this single constraint is what
makes webhook replay safe across the whole platform (§58).

### Dashboards, reporting, notifications

```
dashboards            id, uuid, tenant_id, user_id (null = shared), name,
                      is_default, role_key, settings jsonb
dashboard_layouts     id, dashboard_id, widget_key, x, y, width, height,
                      config jsonb, sort_order
dashboard_widgets     id, key, name, type, permission, category, configurable,
                      refreshable, date_filter, default_size jsonb
custom_fields         id, tenant_id, entity_type, key, label, type, options jsonb,
                      is_required, is_searchable, permission, sort_order
custom_field_values   id, tenant_id, custom_field_id, entity_type, entity_id,
                      value jsonb
reports               id, uuid, tenant_id, name, type, config jsonb, schedule,
                      recipients jsonb, last_run_at
saved_views           id, tenant_id, user_id, entity_type, name, filters jsonb,
                      columns jsonb, sort jsonb, density, is_shared
analytics_events      id, tenant_id, event, entity_type, entity_id,
                      properties jsonb, occurred_at
notifications         Laravel notifications table + tenant_id
notification_preferences id, tenant_id, user_id, event_key, channels jsonb
sla_policies          id, tenant_id, name, applies_to jsonb, target_minutes,
                      escalation jsonb, is_active
sla_violations        id, tenant_id, entity_type, entity_id, policy_id,
                      due_at, breached_at, escalated_to
```

`dashboard_widgets` is a **catalog table**, not per-tenant rows. It is the
registry the "+ Add Widget" picker reads, and it is why the dashboard is
widget-driven rather than hard-coded (§36, §80, §101.29).

`saved_views` persists the DataTable state described in §111 — filters, visible
columns, sort and density — per user per entity.

`analytics_events` is append-only and partitioned by month; it backs attribution
and the funnel reports (§90) without querying live CRM tables.

---

## 3. Indexing

The ones that matter under load:

```sql
-- Tenant-scoped list pages. tenant_id leads every composite index.
CREATE INDEX ON leads (tenant_id, status, created_at DESC);
CREATE INDEX ON leads (tenant_id, owner_id, next_follow_up_at);
CREATE INDEX ON leads (tenant_id, score DESC) WHERE deleted_at IS NULL;

-- Deduplication lookups (§18)
CREATE INDEX ON leads (tenant_id, email_normalized) WHERE email_normalized IS NOT NULL;
CREATE INDEX ON leads (tenant_id, phone_normalized) WHERE phone_normalized IS NOT NULL;
CREATE UNIQUE INDEX ON leads (tenant_id, external_system, external_record_id)
  WHERE external_record_id IS NOT NULL;

-- Idempotency (§58) — the constraint that makes webhook replay safe
CREATE UNIQUE INDEX ON integration_events (tenant_id, provider, provider_event_id);
CREATE UNIQUE INDEX ON usage_records (tenant_id, idempotency_key);

-- Inbox: unread + SLA queues
CREATE INDEX ON conversations (tenant_id, status, last_message_at DESC);
CREATE INDEX ON conversations (tenant_id, assignee_id, is_unread);
CREATE INDEX ON messages (tenant_id, conversation_id, sent_at DESC);

-- Opt-out enforcement, checked before every send
CREATE UNIQUE INDEX ON opt_outs (tenant_id, identifier, channel);

-- Full-text search (§91)
CREATE INDEX ON leads USING gin (
  to_tsvector('simple',
    coalesce(full_name,'') || ' ' || coalesce(email,'') || ' ' ||
    coalesce(phone,'')     || ' ' || coalesce(company_name,''))
);

-- JSONB containment for custom fields and metadata filters
CREATE INDEX ON custom_field_values USING gin (value);
CREATE INDEX ON leads USING gin (metadata);
```

Partition by month: `audit_logs`, `analytics_events`, `integration_events`,
`webhook_deliveries`, `messages`. These are the append-heavy tables; everything
else stays unpartitioned until measurement says otherwise.

---

## 4. Isolation guarantees

Three enforcement layers, because the trait alone will eventually be forgotten
on some new model:

1. **`BelongsToTenant` global scope** on every tenant-owned model.
2. **PostgreSQL row-level security** on every tenant table, keyed to a session
   variable set from the tenant context.
3. **A schema test that fails the build** when a tenant-owned table is missing
   `tenant_id`, its FK or its index — or when a model in a tenant domain lacks
   the trait.

Layer 3 is written in Phase 0, before there is any data to protect. It is the
only layer that catches the mistake at the moment it is introduced.
