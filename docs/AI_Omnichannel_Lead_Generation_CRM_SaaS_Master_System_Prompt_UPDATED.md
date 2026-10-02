# AI Omnichannel Lead Generation, CRM & Sales Automation SaaS

## Complete Master System Development Prompt & Technical Specification

**Document purpose:** This document is the master implementation specification for building a multi-tenant SaaS platform that connects authorized business accounts and lead sources, captures leads, normalizes and scores them, manages CRM pipelines, automates WhatsApp/email/SMS/social follow-up, provides AI-assisted or autonomous sales conversations, exposes APIs/webhooks for existing CRMs, and includes a configurable interactive admin dashboard.

**Reference UI:** The supplied Mediline dashboard image is a visual direction only. Recreate the overall UX principles—clean left sidebar, top header, metric cards, charts, calendar, light/dark modes, spacious cards, rounded controls, strong hierarchy—but DO NOT copy the medical/hospital-specific content or create a pixel-for-pixel clone. The final UI must be purpose-built for lead generation, CRM, marketing automation and sales.

---

# 1. Product Vision

Build an **AI-Powered Omnichannel Lead Generation, CRM and Sales Automation SaaS**.

The platform must allow a business to:

1. Connect authorized social-media, advertising, messaging, website and third-party accounts.
2. Receive leads through supported official APIs, lead forms, webhooks, customer-owned data, website forms, imports and integrations.
3. Normalize all incoming leads into one common lead model.
4. Detect duplicates.
5. Enrich leads where lawful and technically supported.
6. Score and qualify leads using configurable rules and AI.
7. Route leads to the correct sales representative/team.
8. Manage leads, contacts, companies, opportunities and deals in a CRM.
9. Communicate through WhatsApp, email, SMS and supported social messaging channels.
10. Let users write and save their own email/WhatsApp templates.
11. Let AI draft replies, emails and follow-ups.
12. Support manual, approval-based and autonomous automation modes.
13. Build visual automation workflows.
14. Book appointments.
15. Track conversions and revenue back to lead source/campaign.
16. Integrate with an existing CRM/ERP through REST APIs and webhooks.
17. Provide a complete public API/developer portal.
18. Operate as a multi-tenant SaaS with plans, subscriptions, quotas, usage billing and feature entitlements.
19. Provide platform-level Super Admin and tenant-level administration.
20. Provide configurable dashboards with widgets rather than hard-coded layouts.

---

# 2. Critical Product Rule: Official Integrations First

DO NOT design the product around unrestricted scraping of arbitrary social-media users.

The platform must distinguish:

### A. Supported/authorized lead acquisition

- Facebook/Meta lead-generation products where API access and permissions allow.
- Instagram/Meta business integrations where API access and permissions allow.
- TikTok Business/Lead Generation APIs where access is approved.
- LinkedIn Lead Sync for supported Lead Gen Forms and approved use cases.
- Google Ads and supported Google lead sources.
- WhatsApp Business Platform.
- Website forms.
- Landing pages.
- Customer-owned databases.
- CSV/Excel imports.
- REST API.
- Webhooks.
- Approved third-party connectors.

### B. Unsupported/restricted behavior

Do not implement functionality that:

- logs into a user's personal social account to scrape arbitrary profiles;
- bypasses CAPTCHA, rate limits, access controls or anti-bot systems;
- collects private data without authorization;
- extracts arbitrary personal profiles where the platform does not provide an authorized API;
- circumvents platform permission or consent requirements.

The UI should clearly communicate the difference between:

- Connected/authorized source
- API-supported data
- User-imported data
- Unsupported source

The system should never promise "scrape every social-media user."

---

# 3. Target Users

## Platform-level

- Platform Owner
- Super Admin
- Platform Administrator
- Billing Administrator
- Integration Administrator
- Support Administrator

## Tenant-level

- Owner
- Tenant Admin
- Sales Manager
- Sales Representative
- Marketing Manager
- Marketing Executive
- Customer Support
- Operations
- Viewer
- Custom roles

Every module and action must use permissions.

---

# 4. Recommended Technology Stack

## Backend

- PHP 8.3+
- Latest stable Laravel version available at implementation time
- Laravel REST API
- Laravel Queue
- Laravel Events/Listeners
- Laravel Notifications
- Laravel Scheduler
- Laravel Reverb/WebSockets where real-time UI is required
- Laravel Sanctum for first-party/API token authentication
- OAuth 2.0 for external provider authorization
- Eloquent ORM
- Form Request/custom validation services
- Policies/Gates
- API Resources
- Service classes
- Repository pattern only where it creates genuine reuse/abstraction; do not create repositories mechanically for every model

## Frontend

Use **Vue.js (Option B)**:

- Vue 3
- TypeScript
- Vite
- Tailwind CSS
- Vue Router
- Pinia
- Axios or typed HTTP client
- Composition API
- reusable modal/drawer/form/table components
- chart library such as Apache ECharts or another actively maintained Vue-compatible charting library

## Database

Preferred:

- PostgreSQL

Alternative:

- MySQL 8+

Use relational database design and Eloquent relationships.

Avoid raw SQL unless genuinely required for:

- large reporting queries;
- bulk operations;
- database-specific performance optimization;
- specialized indexing/search;
- analytics workloads.

## Cache / queues

- Redis
- Laravel Queue

At higher scale:

- RabbitMQ can be introduced for integration/event workloads.

Do not force RabbitMQ into the MVP if Redis queues are sufficient.

## Storage

- AWS S3
- optional CloudFront/CDN
- local storage for development only

## Infrastructure

- AWS
- ECS/EC2 initially
- RDS PostgreSQL
- ElastiCache Redis
- S3
- CloudFront
- Load Balancer
- Secrets Manager
- CloudWatch
- automated backups
- monitoring and alerting

## AI

Build a provider abstraction:

- OpenAI
- Anthropic
- Google Gemini
- Azure OpenAI
- future providers

Never hard-code the application to one AI provider.

## Messaging

Provider abstraction for:

- WhatsApp Business Platform
- email providers
- SMS providers
- future messaging providers

Potential email providers:

- Amazon SES
- Postmark
- SendGrid
- Mailgun
- SMTP
- Microsoft 365/Gmail where supported

Potential SMS:

- Twilio
- Vonage
- AWS or regional providers

---

# 5. High-Level Architecture

```text
                         PLATFORM
                            |
                    Super Admin Panel
                            |
          +-----------------+-----------------+
          |                                   |
       SaaS Core                         Integration Hub
          |                                   |
   Plans / Billing                    Social / Ads / Messaging
   Tenants / Users                    Facebook / Instagram
   Features / Usage                   TikTok / LinkedIn
   Security / Audit                   WhatsApp / Google
          |                                   |
          +-----------------+-----------------+
                            |
                     TENANT APPLICATION
                            |
       +--------------------+--------------------+
       |                    |                    |
      CRM              Lead Engine        Automation
       |                    |                    |
 Leads / Contacts      Normalize           Workflows
 Companies             Deduplicate          Triggers
 Deals                 Score                Conditions
 Activities             Enrich              Actions
       |                    |                    |
       +--------------------+--------------------+
                            |
                    Omnichannel Inbox
                            |
            +---------------+---------------+
            |               |               |
         WhatsApp         Email            SMS
            |               |               |
            +---------------+---------------+
                            |
                         AI Layer
                            |
            +---------------+---------------+
            |               |               |
       AI Scoring       AI Replies      AI Sales Agent
                            |
                       Appointments
                            |
                           Sales
                            |
                         Revenue
```

---

# 6. Multi-Tenant Architecture

Use a central platform database plus isolated tenant data.

## Central database

Suggested tables:

```text
tenants
tenant_domains
plans
plan_features
subscriptions
subscription_items
subscription_events
usage_meters
usage_records
platform_users
platform_roles
platform_permissions
feature_catalog
billing_customers
billing_transactions
coupons
referrals
platform_templates
integration_providers
system_settings
audit_logs
```

## Tenant database

Suggested tables:

```text
users
roles
permissions
teams
team_members
leads
lead_sources
lead_source_accounts
lead_events
lead_fields
lead_field_values
lead_scores
lead_score_rules
contacts
companies
contact_company
deals
pipelines
pipeline_stages
activities
tasks
notes
tags
lead_tags
campaigns
campaign_members
automation_workflows
automation_nodes
automation_runs
automation_actions
conversations
conversation_participants
messages
message_templates
email_templates
whatsapp_templates
sms_templates
appointments
calendar_connections
products
quotes
sales
attachments
forms
form_submissions
api_keys
oauth_connections
webhooks
webhook_deliveries
integration_syncs
integration_errors
notifications
notification_preferences
reports
dashboard_widgets
dashboard_layouts
custom_fields
```

The exact database structure may be refined during implementation, but tenancy isolation, foreign-key integrity, indexes, auditability and scalability are mandatory.

---

# 7. Tenant Provisioning

When a new tenant registers:

1. Create central tenant record.
2. Create tenant identifier.
3. Create tenant database/schema according to deployment strategy.
4. Run tenant migrations.
5. Create tenant owner.
6. Create default roles.
7. Create default permissions.
8. Create default pipeline.
9. Create default lead stages.
10. Create default dashboard layout.
11. Create default templates.
12. Create default notification preferences.
13. Create subscription.
14. Apply plan entitlements.
15. Create API credentials only when requested.
16. Send welcome notification.

Provisioning must be idempotent and recoverable.

---

# 8. Subscription & SaaS Billing

Implement complete subscription management.

## Plans

Each plan can define:

```text
users
teams
leads_per_month
contacts
companies
automation_workflows
automation_runs
social_connections
ad_accounts
whatsapp_messages
email_messages
sms_messages
ai_credits
ai_tokens
api_requests
webhooks
storage
custom_fields
pipelines
dashboard_widgets
reports
integrations
ai_agents
```

## Subscription lifecycle

```text
Trial
Active
Past Due
Paused
Cancelled
Expired
Grace Period
```

Support:

- monthly billing
- annual billing
- trials
- upgrades
- downgrades
- scheduled downgrade
- cancellation
- reactivation
- invoices
- receipts
- taxes
- coupons
- discounts
- usage limits
- overage
- feature locking

Never hard-code plan names or limits.

---

# 9. Usage Metering

Track:

```text
lead_created
lead_imported
lead_synced
message_sent
whatsapp_message_sent
email_sent
sms_sent
ai_request
ai_tokens
automation_run
api_request
webhook_delivered
storage_uploaded
appointment_created
```

Each event should be tenant-scoped.

Provide:

- current usage
- plan limit
- remaining usage
- projected usage
- warning threshold
- hard limit behavior
- overage behavior

---

# 10. Integration Hub

Create a reusable provider architecture.

```text
IntegrationProvider
    |
    +-- MetaProvider
    +-- InstagramProvider
    +-- TikTokProvider
    +-- LinkedInProvider
    +-- GoogleProvider
    +-- WhatsAppProvider
    +-- EmailProvider
    +-- SMSProvider
    +-- CustomWebhookProvider
```

Each provider must define:

- authorization URL
- OAuth callback
- scopes
- token storage
- token refresh
- account discovery
- synchronization
- webhook subscription
- webhook verification
- lead retrieval
- message sending where supported
- error handling
- rate limiting
- provider status
- disconnect/revoke logic

---

# 11. Social Connection UX

Tenant goes to:

```text
Settings
 > Integrations
 > Social & Ads
```

Show cards:

```text
Facebook / Meta
Instagram
TikTok
LinkedIn
Google Ads
WhatsApp
```

Each card:

```text
[Connect]
```

After clicking:

```text
1. Explain requested permissions.
2. Redirect to provider OAuth.
3. Provider authenticates user.
4. Provider returns authorization code.
5. Backend exchanges code for token.
6. Encrypt token.
7. Discover business pages/ad accounts/forms.
8. Let tenant choose accounts.
9. Subscribe webhooks where supported.
10. Run initial sync.
11. Display connected status.
```

Never ask users to paste access tokens unless a provider explicitly requires a supported developer workflow.

---

# 12. Meta / Facebook / Instagram Integration

Build Meta integration as a provider rather than separate unrelated code.

Support, where available and approved:

- Facebook Lead Ads
- Page connections
- Instagram business integrations
- supported messaging
- supported lead/comment/conversation events
- campaigns/ad metadata where API access permits

Connection flow:

```text
Tenant
  |
  v
Connect Meta
  |
  v
OAuth
  |
  v
Select Business / Page / Ad Account
  |
  v
Select Lead Forms / supported assets
  |
  v
Save encrypted credentials
  |
  v
Subscribe webhooks
  |
  v
Initial synchronization
```

Lead ingestion:

```text
Meta webhook
      |
      v
Verify signature
      |
      v
Create webhook event
      |
      v
Queue processing
      |
      v
Fetch lead details if required
      |
      v
Normalize
      |
      v
Deduplicate
      |
      v
Score
      |
      v
Trigger automation
```

Do not assume every Instagram/Facebook interaction exposes a person's complete profile information. Store only the fields returned by authorized APIs and permissions.

---

# 13. LinkedIn Integration

Support the current approved LinkedIn Lead Sync model.

Connection:

```text
Connect LinkedIn
      |
OAuth / authorization
      |
Select organization/ad account
      |
Select supported lead forms
      |
Configure sync
      |
Webhook + fallback polling
```

LinkedIn Lead Sync currently supports syncing leads from supported Lead Gen Forms to CRMs/marketing automation platforms, with authorized access and appropriate permissions. Access is subject to LinkedIn's developer program and qualification requirements. Implement the provider so API version changes can be handled without rewriting the application.

Use:

- OAuth
- access-token management
- Lead Sync API
- webhooks where available
- periodic reconciliation/polling as fallback
- deduplication
- form-field mapping
- source/campaign attribution

Do not implement arbitrary LinkedIn profile scraping.

---

# 14. TikTok Integration

Create TikTok provider support using approved APIs/products.

Flow:

```text
Connect TikTok
      |
OAuth
      |
Authorize requested scopes
      |
Select supported business/merchant/ad assets
      |
Select lead sources
      |
Configure sync
      |
Webhook/polling where supported
```

Implement token refresh and provider-specific permissions.

TikTok's current developer documentation requires access tokens and approved scopes for API access. Keep provider configuration versioned because TikTok APIs and product access can change.

Do not implement arbitrary user-profile scraping.

---

# 15. WhatsApp Integration

Implement a dedicated WhatsApp provider.

Features:

- connect business account
- select phone number
- templates
- approved message templates
- inbound messages
- outbound messages
- delivery status
- read status where available
- media
- conversation history
- assignment to agents
- AI-assisted replies
- automation
- opt-out handling
- business-hours handling

Architecture:

```text
WhatsApp
   |
Webhook
   |
Verify webhook
   |
Queue event
   |
Normalize message
   |
Conversation
   |
AI / Automation / Human
   |
Send response
```

Never send unsolicited bulk WhatsApp messages without the appropriate customer/business permissions and provider rules.

---

# 16. Website Lead Capture

Provide a form builder.

Users can create:

```text
Contact Form
Lead Form
Demo Request
Quote Request
Appointment Form
Newsletter Form
Custom Form
```

Fields:

- text
- email
- phone
- number
- select
- multi-select
- checkbox
- radio
- textarea
- country
- file
- date
- date/time
- hidden tracking fields

Each form generates:

```text
Embed Code
JavaScript Snippet
REST Endpoint
Webhook
```

Capture:

- UTM source
- UTM medium
- UTM campaign
- UTM content
- UTM term
- landing page
- referrer
- timestamp
- form ID
- campaign ID

---

# 17. Lead Normalization Engine

Every source maps into a common schema.

Example:

```text
first_name
last_name
full_name
email
phone
country
company_name
job_title
website
source
source_type
provider
external_id
campaign
campaign_id
form
form_id
landing_page
utm_source
utm_medium
utm_campaign
utm_content
utm_term
raw_payload
consent
consent_timestamp
created_at
```

Never force provider-specific fields into the core schema.

Store provider-specific fields in structured metadata/custom fields.

---

# 18. Lead Deduplication

Use layered matching:

1. provider external ID
2. normalized email
3. normalized phone
4. company + email domain
5. name + phone
6. configurable fuzzy matching
7. AI-assisted similarity only where useful

When duplicate:

```text
Keep master lead
Attach source event
Merge non-conflicting information
Preserve history
Preserve source attribution
Create audit record
```

Never silently destroy data.

---

# 19. Lead Scoring

Allow each tenant to configure scoring rules.

Example:

```text
Email provided               +10
Phone provided               +10
Requested demo               +25
Budget provided              +15
Visited pricing page         +10
Replied to WhatsApp          +10
High purchase intent         +20
```

Score categories:

```text
Cold
Nurture
Warm
Hot
Qualified
```

Allow:

- manual scoring
- rule-based scoring
- AI scoring
- hybrid scoring

Keep the score explainable.

Example:

```text
Lead Score: 82

Reasons:
+25 Demo requested
+20 High purchase intent
+15 Budget provided
+10 Phone provided
+12 Recent response
```

---

# 20. AI Lead Qualification

AI should be able to analyze:

- lead source
- form answers
- conversation
- email content
- company information where legitimately available
- requested product/service
- budget
- timing
- intent
- objections

Return structured data:

```json
{
    "score": 82,
    "intent": "high",
    "qualification": "qualified",
    "buying_stage": "evaluation",
    "budget_signal": "positive",
    "urgency": "medium",
    "recommended_action": "sales_call",
    "reasoning": [
        "Requested demo",
        "Provided budget",
        "Asked implementation timeline"
    ]
}
```

Do not expose hidden chain-of-thought. Store concise, user-facing reasons instead.

---

# 21. CRM

The system must work as a complete CRM if the customer does not have an existing CRM.

Modules:

```text
Leads
Contacts
Companies
Deals
Pipelines
Activities
Tasks
Notes
Calls
Meetings
Emails
Conversations
Appointments
Products
Quotes
Documents
Sales
```

---

# 22. Pipeline Management

Tenant can create multiple pipelines.

Example:

```text
New Lead
Contacted
Qualified
Demo Scheduled
Proposal Sent
Negotiation
Won
Lost
```

Support:

- drag and drop
- stage probability
- stage rules
- required fields
- automation triggers
- stage-specific tasks
- stage-specific notifications
- pipeline permissions

---

# 23. Lead Assignment

Rules:

```text
Round Robin
Least Assigned
Territory
Country
Product
Lead Source
Campaign
Lead Score
Team
Business Hours
```

Example:

```text
IF country = UAE
AND score >= 70
THEN assign UAE Sales Team
```

---

# 24. Omnichannel Inbox

Single interface for supported conversations:

```text
All
WhatsApp
Email
Facebook
Instagram
SMS
```

Layout:

```text
--------------------------------------------------
| Conversations | Conversation | Lead Details   |
|---------------|--------------|----------------|
| John          | Messages     | Lead Score     |
| Sarah         |              | Company        |
| Ahmed         |              | Deal           |
| Maria         |              | Tasks          |
--------------------------------------------------
```

Features:

- search
- filters
- tags
- assignment
- internal notes
- AI suggested response
- template insertion
- attachments
- conversation status
- unread state
- SLA
- customer timeline

---

# 25. Email Module

Support:

```text
Email accounts
SMTP
Provider connections
Templates
Signatures
Sequences
Campaigns
Tracking where legally/technically appropriate
Inbound email
Reply detection
Attachments
Scheduled email
```

Email composer must allow:

```text
Use Template
AI Draft
Write Manually
```

---

# 26. User Email Drafts

Allow users to create their own templates.

Example:

```text
Template Name:
New Lead Follow-up

Subject:
Thanks for contacting {{company_name}}

Body:
Hi {{first_name}},

Thank you for your interest in {{product_name}}.

Would you like to schedule a quick call?

{{meeting_link}}

Regards,
{{sales_rep_name}}
```

Variables must be tenant-safe and validated.

---

# 27. AI Email Composer

Modes:

```text
Professional
Friendly
Short
Detailed
Sales
Follow-up
Proposal
Re-engagement
Appointment
```

User can:

```text
Generate
Regenerate
Edit
Save as Template
Send
Schedule
```

AI must never automatically send unless tenant automation explicitly permits it.

---

# 28. Automation Workflow Builder

Create visual workflow builder.

### Trigger examples

```text
Lead Created
Lead Updated
Lead Score Changed
Lead Qualified
Form Submitted
Message Received
Email Received
Deal Created
Deal Stage Changed
Appointment Created
Appointment Missed
No Response
Tag Added
Campaign Joined
Subscription Event
```

### Conditions

```text
score
source
country
industry
product
campaign
email
phone
lead stage
deal stage
message content
response status
business hours
date/time
subscription plan
```

### Actions

```text
Assign Lead
Send Email
Send WhatsApp
Send SMS
Create Task
Create Appointment
Add Tag
Remove Tag
Update Field
Move Pipeline Stage
Notify User
Notify Team
Start Sequence
Stop Sequence
Call Webhook
Invoke AI
Create Deal
Create Quote
```

---

# 29. Automation Modes

### Manual

System recommends the action.

### Approval

System prepares action and waits for approval.

### Autonomous

System executes automatically according to tenant rules.

Every autonomous workflow must have:

- enable/disable
- limits
- quiet hours
- retry policy
- error handling
- audit logs
- kill switch

---

# 30. AI Sales Agent

Tenant can configure an AI Sales Agent.

Configuration:

```text
Name
Description
Business
Products
Services
Pricing
FAQs
Knowledge Sources
Tone
Languages
Business Hours
Sales Rules
Qualification Rules
Escalation Rules
Human Handoff Rules
```

AI agent should:

- answer questions
- qualify leads
- collect missing information
- recommend products/services
- send approved information
- schedule appointments
- create/update CRM records
- escalate to humans
- summarize conversations

Do not allow the AI to invent prices, policies, guarantees or business facts.

---

# 31. AI Human Handoff

Escalate when:

```text
Customer asks for human
Low AI confidence
Pricing exception
Refund/legal complaint
Sensitive issue
Complex negotiation
Angry customer
High-value opportunity
Configured keyword
```

Create a task and notify the assigned team.

---

# 32. Appointment & Calendar

Support:

```text
Google Calendar
Microsoft Calendar where supported
Internal calendar
Availability
Meeting types
Booking links
Time zones
Working hours
Blackout dates
Reminders
Rescheduling
Cancellation
```

Lead conversion can trigger appointment booking.

---

# 33. Campaign Management

Campaign types:

```text
Lead Generation
Email
WhatsApp
SMS
Nurture
Retargeting
Re-engagement
Product Promotion
Event
```

Track:

```text
sent
delivered
opened where supported
clicked where supported
replied
qualified
converted
revenue
```

---

# 34. Attribution

Every lead should retain source attribution.

Track:

```text
Source
Medium
Campaign
Ad Account
Campaign ID
Ad ID
Form ID
Landing Page
UTM values
Referrer
First Touch
Last Touch
Conversion Source
```

Reports:

```text
Leads by Source
Qualified Leads by Source
Deals by Source
Revenue by Source
Conversion Rate
Cost per Lead
Cost per Qualified Lead
ROI
```

---

# 35. Dashboard UI

The uploaded Mediline dashboard is the visual reference.

Create a modern SaaS dashboard with:

## Left sidebar

- collapsible
- icon + label
- active state
- nested menu
- badge counts
- permission-aware menu items

## Top bar

- global search
- command/search shortcut
- date range
- notifications
- help
- theme switcher
- language
- user menu

## Dashboard cards

Examples:

```text
Total Leads
New Leads
Qualified Leads
Hot Leads
Open Deals
Pipeline Value
Revenue
Conversion Rate
Appointments
AI Conversations
Messages
Response Rate
```

Each card:

- icon
- value
- trend
- comparison period
- tooltip
- click-through
- permission-aware

---

# 36. Dashboard Widgets

Dashboard must NOT be hard-coded.

Provide:

```text
+ Add Widget
```

Widgets:

```text
Lead Overview
Lead Funnel
Lead Sources
Lead Trend
Conversion Rate
Revenue
Pipeline
Deals
Sales Leaderboard
Recent Leads
Recent Conversations
Upcoming Appointments
Calendar
Tasks
Campaign Performance
WhatsApp Performance
Email Performance
AI Performance
Usage
Subscription
API Usage
```

Allow:

- drag/drop
- resize
- hide
- duplicate
- reset
- save layout
- role-based default layouts

---

# 37. Charts & Graphs

Use a professional chart library such as Apache ECharts.

Use:

- line charts
- area charts
- bar charts
- stacked bars
- donut charts
- funnel charts
- radar only where useful
- heatmaps
- KPI cards
- conversion funnels
- cohort charts
- source comparison
- pipeline charts

Every chart must support:

- loading state
- empty state
- error state
- date range
- filters
- tooltip
- responsive behavior
- export where appropriate

Do not overload dashboards with charts.

---

# 38. Calendar Widget

Dashboard calendar should show:

- appointments
- demos
- follow-ups
- tasks
- reminders

Click date:

- show events
- create appointment
- create task

---

# 39. Light/Dark Theme

The supplied image demonstrates both.

Implement:

- Light
- Dark
- System

Use design tokens instead of hard-coded colors.

Primary palette can use:

- modern green/teal accent
- neutral background
- white cards in light mode
- dark slate cards in dark mode

Make the theme configurable at platform level and optionally tenant level.

---

# 40. Icons

Use one consistent icon library.

Recommended:

- Lucide
  or
- Heroicons

Do not mix many icon libraries unnecessarily.

Every menu item, action and important status should have a meaningful icon.

Do not use random emoji as interface icons.

---

# 41. Toasts & Notifications

Implement global toast system.

Types:

```text
Success
Info
Warning
Error
Loading
```

Examples:

```text
Lead created successfully.
Lead imported: 238 records.
Facebook connection completed.
WhatsApp template approved.
Email sent successfully.
Workflow activated.
API key revoked.
Subscription upgraded.
```

Toasts must:

- auto-dismiss where appropriate
- support action button
- be accessible
- never hide important validation errors

For critical operations, use confirmation modal.

---

# 42. Forms

Important rule:

**Do not rely on browser HTML `required` validation as the application's validation system.**

Use Vue form validation + Laravel server validation.

All required fields must be validated server-side.

Validation messages should be human-friendly:

```text
First name is required.
Please enter a valid email address.
Phone number is invalid.
Pipeline stage is required.
```

Centralize labels and validation terminology.

---

# 43. Modal-Based CRUD

For small/medium forms:

```text
Create Lead
Edit Lead
Create Contact
Create Task
Create Tag
Create Template
Create API Key
```

Open in:

- modal
- large modal
- side drawer

Do not navigate away unnecessarily.

For complex multi-step forms, use a full-page wizard only when the form cannot reasonably fit a modal.

---

# 44. Lead Details UI

Use a tabbed or sectioned detail screen:

```text
Overview
Activity
Conversations
Emails
WhatsApp
Deals
Tasks
Appointments
Notes
Documents
Timeline
AI Analysis
Source
Audit
```

Right side:

- lead score
- stage
- assigned user
- company
- contact details
- next action

---

# 45. Global Search

Search:

```text
Leads
Contacts
Companies
Deals
Messages
Tasks
Appointments
Campaigns
```

Use debounced search.

Show:

- recent searches
- keyboard navigation
- categorized results
- permission-aware results

---

# 46. Notifications

In-app notification center:

```text
New lead
Hot lead
Lead assigned
Customer replied
Appointment booked
Appointment cancelled
Deal won
Automation failed
Integration disconnected
Subscription issue
API limit warning
```

Optional:

- email
- WhatsApp
- push/browser notifications

---

# 47. Public REST API

Version API:

```text
/api/v1/
```

Endpoints:

```text
POST   /leads
GET    /leads
GET    /leads/{id}
PATCH  /leads/{id}
DELETE /leads/{id}

POST   /contacts
GET    /contacts

POST   /companies
GET    /companies

POST   /deals
GET    /deals

POST   /activities
POST   /tasks

POST   /messages
GET    /conversations

POST   /appointments

GET    /pipelines
GET    /users

POST   /webhooks/test
GET    /usage
```

Use API Resources and consistent response structures.

---

# 48. API Authentication

Support:

- tenant API keys
- scoped keys
- OAuth applications
- bearer tokens
- webhook signatures

API key scopes:

```text
leads.read
leads.write
contacts.read
contacts.write
deals.read
deals.write
messages.send
appointments.write
webhooks.manage
```

Never return secret keys again after creation.

---

# 49. Webhooks

Tenant can subscribe to:

```text
lead.created
lead.updated
lead.qualified
lead.assigned
lead.converted
contact.created
deal.created
deal.won
deal.lost
message.received
message.sent
appointment.created
appointment.cancelled
automation.completed
automation.failed
subscription.updated
```

Webhook delivery must support:

- signature
- retry
- exponential backoff
- delivery logs
- response status
- replay
- disable after repeated failure

---

# 50. API Developer Portal

Provide:

```text
API Overview
Authentication
API Keys
OAuth Apps
Endpoints
Webhooks
Schemas
Examples
Rate Limits
Logs
Usage
Sandbox
Changelog
```

Generate OpenAPI documentation.

---

# 51. API Rate Limiting

Use:

- tenant limits
- API key limits
- endpoint limits
- IP throttling where appropriate

Return standard rate-limit headers.

---

# 52. Import/Export

Support:

- CSV
- XLSX
- JSON

Import wizard:

```text
Upload
 ↓
Detect columns
 ↓
Map fields
 ↓
Validate
 ↓
Preview
 ↓
Duplicate strategy
 ↓
Import
 ↓
Report
```

Export:

- current view
- filtered records
- selected records
- scheduled reports

---

# 53. Custom Fields

Tenants can create custom fields for:

- leads
- contacts
- companies
- deals
- products

Types:

- text
- textarea
- number
- currency
- date
- datetime
- boolean
- select
- multi-select
- URL
- email
- phone

Custom fields must be permission-aware and available to automation.

---

# 54. Audit Logs

Track:

```text
login
logout
lead created
lead edited
lead deleted
assignment
stage change
message sent
template changed
automation changed
integration connected
integration disconnected
API key created
API key revoked
subscription changed
permission changed
```

Audit:

- actor
- tenant
- action
- entity
- entity ID
- before
- after
- IP
- user agent
- timestamp

---

# 55. Security

Mandatory:

- encrypted secrets
- encrypted OAuth tokens
- HTTPS
- CSRF protection
- XSS protection
- SQL injection protection
- rate limiting
- authorization policies
- tenant isolation
- signed webhooks
- API scopes
- audit logs
- secure file uploads
- virus/malware scanning where required
- secrets manager
- backups
- disaster recovery

Never log:

- access tokens
- refresh tokens
- API secrets
- passwords
- private keys

---

# 56. Queue Architecture

Use asynchronous processing for:

```text
lead synchronization
lead enrichment
AI analysis
email sending
WhatsApp sending
SMS sending
webhooks
imports
exports
campaigns
automation execution
reports
notifications
```

Example:

```text
Webhook
 ↓
Store Event
 ↓
Queue Job
 ↓
Process
 ↓
Persist Result
 ↓
Trigger Automation
 ↓
Queue Message
 ↓
Send
 ↓
Update Status
```

Jobs must be:

- retryable
- idempotent
- observable
- tenant-aware

---

# 57. Integration Sync Engine

Every integration should have:

```text
sync_runs
sync_items
sync_errors
last_cursor
last_synced_at
provider_version
status
```

Support:

- initial sync
- incremental sync
- webhook real-time sync
- periodic reconciliation
- manual sync
- retry failed items

Use cursor-based pagination where provider supports it.

---

# 58. Idempotency

Critical operations must support idempotency.

Examples:

- lead webhook
- message send
- payment webhook
- subscription webhook
- form submission
- import
- automation action

Use:

```text
provider_event_id
idempotency_key
tenant_id
```

---

# 59. Error Handling

Every integration error should show:

```text
What happened
Why it happened
What the user can do
Retry
Reconnect
View logs
```

Example:

```text
LinkedIn synchronization failed.

Reason:
Authorization expired.

Action:
[Reconnect LinkedIn]
[View Details]
```

---

# 60. Admin Dashboard Pages

## Platform Admin

```text
Dashboard
Tenants
Users
Plans
Subscriptions
Billing
Usage
Integrations
AI Providers
Message Providers
Templates
Coupons
Referrals
System Logs
Audit Logs
API
Settings
```

## Tenant Admin

```text
Dashboard
Leads
Contacts
Companies
Deals
Pipelines
Inbox
Campaigns
Automation
Appointments
Tasks
Reports
AI
Integrations
Forms
Templates
Users
Roles
API
Billing
Settings
```

---

# 61. Reporting

Reports:

```text
Lead Source
Lead Funnel
Lead Quality
Conversion
Sales
Revenue
Campaign
Email
WhatsApp
SMS
Salesperson
Team
Pipeline
AI
Automation
API
Usage
```

Allow:

- date range
- filters
- grouping
- export
- scheduled reports

---

# 62. Sales Team Dashboard

Widgets:

```text
My Leads
Hot Leads
Today's Tasks
Upcoming Meetings
Open Deals
Pipeline Value
Conversion
Unread Conversations
Overdue Follow-ups
```

---

# 63. Marketing Dashboard

Widgets:

```text
Campaigns
Leads by Channel
Cost per Lead
Qualified Lead Rate
Campaign Conversion
Revenue Attribution
Email Performance
WhatsApp Performance
```

---

# 64. AI Dashboard

Show:

```text
AI Conversations
AI Qualified Leads
AI Generated Messages
AI Assisted Messages
Human Handoffs
AI Conversion
AI Cost
AI Token Usage
Provider Usage
```

---

# 65. Integration Dashboard

Show:

```text
Connected Accounts
Disconnected Accounts
Sync Status
Last Sync
Webhook Health
API Errors
Token Expiration
Provider Rate Limits
```

---

# 66. Tenant Settings

Sections:

```text
Business
Users
Roles
Permissions
Teams
Lead Settings
Pipeline
Custom Fields
Notifications
Email
WhatsApp
SMS
Social Accounts
AI
Automation
Templates
Calendar
API
Webhooks
Billing
Security
Audit
```

---

# 67. Templates

Platform-level templates:

- welcome email
- password reset
- OTP
- subscription activated
- payment failed
- trial ending
- lead notification

Tenant-level templates:

- lead follow-up
- sales email
- WhatsApp message
- appointment
- quote
- re-engagement

Platform defaults can be cloned by tenants.

---

# 68. Permission Architecture

Permissions must be granular.

Examples:

```text
lead.view
lead.create
lead.update
lead.delete
lead.export
lead.assign

contact.view
contact.create
contact.update
contact.delete

deal.view
deal.create
deal.update
deal.delete

campaign.view
campaign.create
campaign.manage

automation.view
automation.create
automation.execute
automation.manage

conversation.view
conversation.reply
conversation.assign

integration.view
integration.connect
integration.disconnect

billing.view
billing.manage

api.view
api.create
api.revoke
```

---

# 69. UI/UX Rules

The UI must be:

- responsive
- fast
- keyboard friendly
- accessible
- consistent
- mobile-friendly
- tablet-friendly
- desktop optimized

Use:

- cards
- tabs
- drawers
- modals
- dropdowns
- command palette
- skeleton loaders
- empty states
- confirmation dialogs
- inline actions
- bulk actions
- filters
- saved filters

Avoid:

- unnecessary page reloads
- giant forms
- clutter
- inconsistent spacing
- random colors
- random icon libraries

---

# 70. Data Tables

All major list pages should have:

```text
Search
Filters
Advanced Filters
Saved Views
Column Selection
Sort
Pagination
Bulk Selection
Bulk Actions
Export
Import
Refresh
```

Example Lead table:

```text
Lead
Company
Source
Score
Stage
Assigned To
Last Activity
Next Follow-up
Created
Actions
```

---

# 71. Responsive Mobile Experience

On mobile:

- sidebar becomes drawer
- cards become stacked
- tables become responsive
- important actions remain visible
- conversations use full-screen mobile layout
- lead details use tabs
- charts remain readable

---

# 72. Performance Requirements

Target:

- fast initial dashboard load
- lazy-load heavy widgets
- server-side pagination
- database indexes
- queued jobs
- caching
- optimized API responses
- background analytics
- asynchronous imports
- CDN for static assets
- image optimization

Never load thousands of leads into the browser.

---

# 73. Testing

Backend:

- unit tests
- feature tests
- API tests
- authorization tests
- integration tests
- queue tests
- webhook tests

Frontend:

- component tests
- form validation tests
- store tests
- critical workflow tests

Integration:

- OAuth
- webhook
- lead synchronization
- messaging
- subscription webhooks
- API authentication

End-to-end:

- registration
- tenant provisioning
- connect provider
- receive lead
- score
- assign
- automate
- message
- convert
- report

---

# 74. Observability

Implement:

- application logs
- integration logs
- queue monitoring
- webhook logs
- API logs
- audit logs
- error tracking
- metrics
- uptime monitoring

Track:

- lead processing latency
- webhook latency
- message delivery
- automation failures
- API error rate
- queue backlog
- AI usage

---

# 75. Backup & Disaster Recovery

Implement:

- automated database backups
- point-in-time recovery where available
- S3 versioning
- backup retention
- restore testing
- documented recovery procedure

---

# 76. API/Integration Versioning

All provider integrations must be version-aware.

Store:

```text
provider
provider_api_version
integration_version
connected_at
last_verified_at
```

Do not hard-code API versions throughout controllers.

Use provider service classes/configuration.

---

# 77. Developer Coding Rules

### Laravel

- Use Eloquent relationships.
- Use Form Requests or dedicated validation services.
- Use Policies/Gates.
- Use API Resources.
- Use Events/Listeners where appropriate.
- Use Jobs for expensive/asynchronous work.
- Use Services for business logic.
- Use database transactions for multi-record critical operations.
- Use repositories only where abstraction/reuse justifies them.
- Avoid raw SQL unless required for performance or database-specific operations.
- Keep controllers thin.
- Keep provider integrations isolated.
- Never mix tenant and central database logic accidentally.

### Vue

- TypeScript.
- Composition API.
- Reusable components.
- Pinia stores by domain.
- Typed API services.
- Centralized error handling.
- Centralized toast service.
- Permission-aware UI.
- Avoid giant monolithic components.

---

# 78. Suggested Laravel Module Structure

```text
app/
  Domain/
    Tenancy/
    Leads/
    Contacts/
    Companies/
    Deals/
    Campaigns/
    Automation/
    Conversations/
    Messaging/
    Appointments/
    AI/
    Billing/
    Integrations/
    Reporting/
    API/
    Notifications/

  Services/
  Jobs/
  Events/
  Listeners/
  Policies/
  Models/
  Http/
```

The exact structure can follow a pragmatic Laravel architecture, but domain boundaries must remain clear.

---

# 79. Suggested Frontend Structure

```text
resources/js/
  components/
    ui/
    forms/
    modals/
    tables/
    charts/
    dashboard/

  layouts/
  pages/
    dashboard/
    leads/
    contacts/
    companies/
    deals/
    inbox/
    campaigns/
    automation/
    appointments/
    reports/
    integrations/
    settings/

  stores/
  services/
  composables/
  types/
  router/
  utils/
```

---

# 80. Dashboard Widget Data Contract

Each widget should have:

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

Dashboard layout:

```json
{
    "widget": "lead_overview",
    "x": 0,
    "y": 0,
    "width": 3,
    "height": 2
}
```

This allows flexible dashboards.

---

# 81. CRM Integration Strategy

If customer already has a CRM:

```text
Existing CRM
     |
REST API / Webhook
     |
Your SaaS
     |
Lead Engine
     |
AI / Automation
     |
Existing CRM
```

Support:

- push leads to CRM
- receive leads from CRM
- update contacts
- update deal status
- sync activities
- sync appointments
- sync conversion status

Avoid creating duplicate records.

Use external IDs:

```text
external_system
external_record_type
external_record_id
```

---

# 82. CRM Connector Framework

Build:

```text
CrmConnectorInterface
```

Potential connectors:

- Salesforce
- HubSpot
- Zoho
- Pipedrive
- Freshsales
- custom CRM
- custom REST API

Each connector:

- auth
- field mapping
- lead sync
- contact sync
- company sync
- deal sync
- activity sync
- error handling

---

# 83. Field Mapping UI

Example:

```text
Your SaaS Field        External CRM Field

first_name       ->    firstname
last_name        ->    lastname
email            ->    email
phone            ->    phone
company          ->    company
lead_source      ->    lead_source
```

Allow:

- one-way sync
- two-way sync
- conflict strategy
- default values
- transformations

---

# 84. Webhook Ingestion API

Allow external systems to send:

```http
POST /api/v1/inbound/leads
```

Example:

```json
{
    "external_id": "CRM-123",
    "first_name": "John",
    "last_name": "Smith",
    "email": "john@example.com",
    "phone": "+123456789",
    "source": "existing_crm",
    "company": "ABC Ltd"
}
```

Process asynchronously.

Return an ingestion ID.

---

# 85. Lead Lifecycle

```text
Captured
  ↓
Normalized
  ↓
Deduplicated
  ↓
Enriched
  ↓
Scored
  ↓
Assigned
  ↓
Contacted
  ↓
Qualified
  ↓
Opportunity
  ↓
Proposal
  ↓
Won / Lost
  ↓
Customer
```

Every transition should be auditable.

---

# 86. Lead Timeline

Display:

```text
09:30 Lead created
09:31 AI score calculated: 78
09:32 Assigned to Sarah
09:33 WhatsApp sent
09:41 Customer replied
09:42 AI classified as high intent
09:43 Salesperson notified
10:15 Appointment booked
Next day Deal created
```

---

# 87. Notifications & SLA

Allow tenant to define:

```text
Hot lead must be contacted within 5 minutes
Qualified lead within 30 minutes
New lead within 15 minutes
```

If SLA breached:

- notify salesperson
- notify manager
- escalate
- record SLA violation

---

# 88. Consent & Compliance

Store:

- consent status
- consent source
- consent timestamp
- privacy policy version
- communication preferences
- unsubscribe status
- channel-specific opt-out

Every messaging channel must respect opt-out state.

Provide data-management tools appropriate to applicable privacy laws and platform rules.

---

# 89. AI Safety & Business Controls

AI must:

- use tenant-approved knowledge
- never invent business facts
- respect business hours
- respect customer opt-outs
- respect escalation rules
- respect messaging limits
- log actions
- provide concise action reasons
- support human approval

Provide:

```text
AI Kill Switch
```

Tenant can disable all autonomous AI actions immediately.

---

# 90. Analytics Event Model

Capture events:

```text
lead.created
lead.qualified
lead.assigned
message.sent
message.received
email.opened
email.clicked
appointment.created
appointment.completed
deal.created
deal.won
deal.lost
revenue.created
```

Use these events for analytics and attribution.

---

# 91. Search Architecture

Initially:

- PostgreSQL full-text search
- indexed fields

At scale:

- OpenSearch/Elasticsearch can be added.

Search should be tenant-aware.

---

# 92. File Management

Allow attachments on:

- leads
- contacts
- companies
- deals
- conversations
- quotes
- campaigns

Store files in S3.

Metadata:

```text
tenant_id
entity_type
entity_id
filename
mime_type
size
storage_key
uploaded_by
```

---

# 93. Subscription Entitlement Middleware

Every feature must be checked through a central entitlement service.

Example:

```text
can('automation.create')
hasFeature('automation')
withinLimit('automation_runs')
```

Do not scatter plan checks throughout controllers.

---

# 94. Feature Flags

Support platform feature flags:

```text
AI_SALES_AGENT
WHATSAPP
TIKTOK
LINKEDIN
ADVANCED_ANALYTICS
API_V2
WHITE_LABEL
```

Allow gradual rollout.

---

# 95. White Label

Future enterprise feature:

```text
Custom Logo
Custom Domain
Brand Colors
Email Branding
Login Branding
Favicon
Custom Sender
Custom Help URL
```

---

# 96. Referral / Affiliate System

Optional SaaS growth module:

```text
Referral Code
Referral Link
Referred Tenant
Subscription
Qualification Period
Reward
Credit
Payout
```

Support platform-defined qualification period and reward rules.

---

# 97. Billing Provider Abstraction

Do not hard-code billing provider.

Create:

```text
BillingProviderInterface
```

Potential:

- Stripe
- PayPal
- regional gateway
- manual bank transfer
- invoice billing

Support provider webhooks.

---

# 98. Platform Templates

Super Admin can define default templates.

Tenant can:

- use default
- clone
- customize
- disable where permitted

Template versioning is recommended.

---

# 99. Initial MVP Scope

Build MVP in this order:

### MVP 1

- Multi-tenancy
- Auth
- RBAC
- Plans/subscriptions
- Leads
- Contacts
- Companies
- Deals
- Pipeline
- Dashboard
- Website forms
- CSV import
- REST API
- Webhooks

### MVP 2

- Meta/Instagram
- LinkedIn
- TikTok
- Google
- WhatsApp
- Email
- SMS

### MVP 3

- Automation builder
- lead scoring
- omnichannel inbox
- campaigns
- appointments

### MVP 4

- AI qualification
- AI email
- AI WhatsApp
- AI sales agent
- AI dashboard

### MVP 5

- CRM connectors
- developer portal
- advanced analytics
- white label
- marketplace

---

# 100. Final Product Acceptance Criteria

The system is considered complete only when:

1. Tenant isolation is verified.
2. Users/roles/permissions work.
3. Subscription entitlements work.
4. Usage limits work.
5. Lead sources can be connected through approved APIs.
6. Website leads can be captured.
7. Leads are normalized.
8. Duplicates are handled.
9. Lead scoring works.
10. Lead assignment works.
11. CRM pipeline works.
12. Email works.
13. WhatsApp works where approved/configured.
14. SMS works where configured.
15. Automation workflows work.
16. AI qualification works.
17. Human handoff works.
18. Appointments work.
19. Dashboards are configurable.
20. Charts are responsive.
21. Toasts and validation are consistent.
22. Forms do not rely on HTML required validation.
23. API works.
24. Webhooks work.
25. External CRM integration works.
26. Audit logs work.
27. Security controls work.
28. Background jobs are observable.
29. Integration errors are recoverable.
30. Mobile/responsive UI works.
31. Light/dark mode works.
32. Documentation exists.
33. OpenAPI documentation exists.
34. Automated tests cover critical workflows.
35. Backup/recovery is configured.

---

# 101. Non-Negotiable Implementation Principles

1. **API-first architecture.**
2. **Vue 3 + TypeScript frontend.**
3. **Laravel backend.**
4. **Eloquent relationships first.**
5. **No unnecessary raw SQL.**
6. **Multi-tenant from day one.**
7. **Everything permission-aware.**
8. **Everything important auditable.**
9. **Every integration isolated behind a provider interface.**
10. **OAuth tokens encrypted.**
11. **Webhooks preferred for real-time events where available.**
12. **Polling/reconciliation used as fallback where appropriate.**
13. **All external events idempotent.**
14. **Long-running work goes to queues.**
15. **Dashboard is widget-driven, not hard-coded.**
16. **Forms use application/server validation, not HTML required validation.**
17. **Use modal/drawer CRUD wherever practical.**
18. **Use consistent icon library.**
19. **Use professional charts and responsive data visualization.**
20. **AI must be configurable and provider-agnostic.**
21. **Human approval and autonomous modes must both exist.**
22. **Customer consent and platform policies must be respected.**
23. **Do not build unrestricted social-media scraping.**
24. **Public API must be versioned.**
25. **Webhooks must be signed, retryable and observable.**
26. **No secret/token values in logs.**
27. **No tenant can access another tenant's data.**
28. **Do not hard-code subscription limits.**
29. **Do not hard-code dashboard widgets.**
30. **Design every module for future integrations.**

---

# 102. Recommended Build Philosophy

Build the platform as a **Lead Operating System**, not simply a scraper.

The product's competitive flow should be:

```text
CONNECT
  ↓
CAPTURE
  ↓
NORMALIZE
  ↓
DEDUPLICATE
  ↓
ENRICH
  ↓
SCORE
  ↓
QUALIFY
  ↓
ASSIGN
  ↓
ENGAGE
  ↓
NURTURE
  ↓
BOOK
  ↓
CONVERT
  ↓
ATTRIBUTE
  ↓
ANALYZE
```

The system should make it possible for a company to either:

### Option A — Use your built-in CRM

or

### Option B — Keep its existing CRM

while using your platform as:

```text
Lead Acquisition
+
AI Qualification
+
Omnichannel Communication
+
Automation
+
Conversion Intelligence
```

This dual mode is a core product requirement.

---

# 103. Official Integration Documentation References

Before implementing each provider, verify the provider's current API documentation, permissions, review requirements, rate limits and product availability.

LinkedIn Lead Sync:
https://learn.microsoft.com/en-us/linkedin/marketing/lead-sync/leadsync-overview

LinkedIn Lead Sync use cases:
https://learn.microsoft.com/en-us/linkedin/marketing/lead-sync/usecases

LinkedIn API quick start:
https://learn.microsoft.com/en-us/linkedin/marketing/quick-start

TikTok API access tokens:
https://developers.tiktok.com/docs/en/obtain-access-token-for-apis

Do not assume a provider's API availability from an older tutorial. Provider API versions, permissions and approval requirements change. Implement provider adapters so version migrations do not require changes to core CRM/lead logic.

---

# 104. Final Development Instruction

Use this document as the **master prompt/specification** for the complete application.

Do not implement the application as a collection of disconnected CRUD pages.

Build a coherent platform where:

```text
Social/Ad/Website/CRM Source
        ↓
Integration Layer
        ↓
Lead Ingestion
        ↓
Lead Intelligence
        ↓
CRM
        ↓
Automation
        ↓
Omnichannel Communication
        ↓
AI
        ↓
Appointments
        ↓
Deals
        ↓
Revenue
        ↓
Analytics
```

Every module must be:

- multi-tenant
- permission-aware
- subscription-aware
- API-aware
- auditable
- testable
- scalable
- reusable
- responsive

The UI should take visual inspiration from the supplied Mediline reference: modern sidebar, compact metric cards, clean charts, calendar, strong whitespace, rounded cards, light/dark themes and clear visual hierarchy. However, the actual information architecture must be designed specifically for this Lead Generation + CRM + AI Sales Automation product.

**End of Master System Development Prompt**

---

# 105. Brand Identity, Logo, Favicon & Visual Design System

## 105.1 Brand Direction

Create a premium, memorable B2B SaaS identity for the lead-generation, CRM, AI sales automation and omnichannel engagement platform.

The visual language should communicate:

- Intelligent lead management
- Growth and revenue
- Automation
- Trust
- Speed
- Connectivity
- AI assistance
- Data clarity
- Modern SaaS technology

Do not make the identity look like a generic marketing agency, finance application, hospital application, or social-media clone.

Use the supplied generated brand-board image as the visual direction/reference for the eight candidate names below. The final implementation must use clean vector/SVG artwork rather than rasterized screenshots.

### Candidate Brand Names

1. **Leadora** — preferred brand concept for the primary design exploration.
2. **LeadPulse**
3. **LeadMinds**
4. **LeadFlow**
5. **Revora**
6. **LeadForge**
7. **LeadOrbit**
8. **LeadNest**

Before public commercial launch, perform independent trademark, company-name, social-handle and domain availability checks. Do not assume a generated name is legally available.

---

## 105.2 Logo Concepts

Create a complete logo family for every candidate name.

### Leadora

Concept:

- Abstract letter **L** combined with a forward-moving lead/conversion shape.
- Rounded geometric construction.
- Small spark/AI highlight may be used as a secondary detail.
- The mark should remain recognizable at 16–32 px.
- Primary visual direction: electric blue + indigo.

Suggested tagline:

> Turn Every Lead Into Opportunity.

### LeadPulse

Concept:

- Minimal heartbeat/pulse waveform integrated into the wordmark or a compact icon.
- Represents lead activity, engagement and real-time opportunity signals.
- Primary visual direction: teal + blue.

Suggested tagline:

> More Leads. Higher Conversions.

### LeadMinds

Concept:

- Abstract human/AI head silhouette containing connected nodes or a neural network.
- Avoid a literal robot head.
- Primary visual direction: indigo + violet.

Suggested tagline:

> Smarter Leads. Better Decisions.

### LeadFlow

Concept:

- Three/four flowing lines or directional arrows moving toward conversion.
- Should communicate capture → nurture → convert.
- Primary visual direction: blue + cyan + small green accent.

Suggested tagline:

> Capture. Nurture. Convert.

### Revora

Concept:

- Abstract upward revenue bars/arrow integrated into a rounded geometric mark.
- Premium and enterprise-friendly rather than financial/trading styled.
- Primary visual direction: emerald + blue.

Suggested tagline:

> Grow Faster. Generate More Revenue.

### LeadForge

Concept:

- Stylized geometric F/forge symbol suggesting transformation from raw interest into qualified opportunity.
- Stronger, energetic visual language.
- Primary visual direction: orange + red/orange gradient with dark navy text.

Suggested tagline:

> Turn Interest Into Impact.

### LeadOrbit

Concept:

- Abstract orbital ring around a lead/opportunity node.
- Communicates connected channels, continuous engagement and a unified lead ecosystem.
- Primary visual direction: violet + indigo.

Suggested tagline:

> Connect. Engage. Grow.

### LeadNest

Concept:

- Abstract connected-lead/nurturing shape, avoiding literal bird imagery.
- Represents organizing, protecting and nurturing leads.
- Primary visual direction: emerald + teal.

Suggested tagline:

> Your Leads. Our Priority.

---

## 105.3 Logo Deliverables

For each candidate brand produce:

- Primary horizontal logo
- Compact horizontal logo
- Symbol-only logo
- App icon
- Favicon 16×16
- Favicon 32×32
- Favicon 48×48
- Apple touch icon 180×180
- PWA icon 192×192
- PWA icon 512×512
- Light-background version
- Dark-background version
- Monochrome dark version
- Monochrome white/reversed version
- SVG master artwork
- PNG export
- Transparent-background export
- Social/avatar square version
- Email signature version

Maintain a clear-space rule around the logo and never distort, rotate, stretch or apply unapproved effects.

The favicon must use the symbol only. It must not depend on small text being readable.

---

# 106. Master UI Design System

## 106.1 Design Principle

The product must feel like a mature modern SaaS application used daily by sales, marketing, support and management teams.

Use established SaaS patterns as inspiration, but create an original visual system rather than copying any existing product.

The UI should combine:

- Clean enterprise SaaS information architecture
- Compact but readable data density
- Strong whitespace
- Rounded cards and controls
- Subtle borders
- Soft elevation
- Clear hierarchy
- Responsive layouts
- Accessible contrast
- Fast interaction feedback
- Consistent component behavior

Current design-system examples demonstrate the value of reusable dashboards, composable tables, row selection, sorting, filtering, pagination and column visibility controls. The implementation should follow the same component-system philosophy while remaining original to this product. citeturn0search0turn0search5

Use a reusable design-token architecture so colors, typography, spacing, radius, shadows and states are defined centrally rather than duplicated throughout the application.

---

## 106.2 Recommended Primary UI Theme

The default product theme should use a **Deep Navy + Electric Indigo/Blue** foundation.

### Core colors

```text
Primary 500: #4F46E5
Primary 600: #4338CA
Primary 700: #3730A3
Accent Blue: #2563EB
Accent Cyan: #06B6D4
Success:     #10B981
Warning:     #F59E0B
Danger:      #EF4444
Info:        #0EA5E9

Text Strong: #0F172A
Text Body:   #334155
Text Muted:  #64748B
Text Soft:   #94A3B8

Border:      #E2E8F0
Border Soft: #F1F5F9
Surface:     #FFFFFF
Surface Alt: #F8FAFC
Page BG:     #F6F8FC

Dark BG:     #08111F
Dark Surface:#0F1B2D
Dark Card:   #12233A
Dark Border: #24364D
Dark Text:   #F8FAFC
Dark Muted:  #94A3B8
```

Do not overuse saturated colors. Brand color should identify primary actions and important states; neutral surfaces should carry most of the interface. This follows established dashboard/data-visualization guidance to use color meaningfully and avoid excessive saturation. citeturn0search9

---

## 106.3 Brand Accent Palettes

The product must support a brand-token layer so the selected final brand can change its accent without rebuilding the UI.

### Leadora

```text
Primary: #4F46E5
Secondary: #2563EB
Accent: #06B6D4
Gradient: #4F46E5 → #2563EB
```

### LeadPulse

```text
Primary: #0F766E
Secondary: #14B8A6
Accent: #2563EB
Gradient: #14B8A6 → #2563EB
```

### LeadMinds

```text
Primary: #4F46E5
Secondary: #7C3AED
Accent: #A855F7
Gradient: #4F46E5 → #7C3AED
```

### LeadFlow

```text
Primary: #2563EB
Secondary: #0EA5E9
Accent: #10B981
Gradient: #2563EB → #06B6D4
```

### Revora

```text
Primary: #059669
Secondary: #10B981
Accent: #2563EB
Gradient: #059669 → #2563EB
```

### LeadForge

```text
Primary: #EA580C
Secondary: #F97316
Accent: #EF4444
Gradient: #F97316 → #EF4444
```

### LeadOrbit

```text
Primary: #6D28D9
Secondary: #7C3AED
Accent: #4F46E5
Gradient: #7C3AED → #4F46E5
```

### LeadNest

```text
Primary: #059669
Secondary: #0D9488
Accent: #14B8A6
Gradient: #059669 → #14B8A6
```

---

# 107. Typography System

## 107.1 Primary UI Font

Use **Inter** as the primary product font.

Weights:

```text
400 Regular
500 Medium
600 Semibold
700 Bold
```

Use Inter for:

- Navigation
- Dashboard
- Forms
- Tables
- Buttons
- Filters
- Modals
- Notifications
- CRM records
- API/developer screens

## 107.2 Marketing / Brand Font

Use **Poppins** selectively for:

- Marketing website hero headings
- Brand statements
- Major landing-page headings
- Campaign/feature promotional sections

Do not use Poppins everywhere in the application. Inter should remain the dominant application UI typeface for readability and density.

Typography must be centrally tokenized. Brand guidelines should define typefaces, hierarchy, weights and spacing consistently across digital surfaces. citeturn0search2turn0search8

### Type scale

```text
Display: 48 / 56 / 700
H1:      32 / 40 / 700
H2:      24 / 32 / 700
H3:      20 / 28 / 600
H4:      18 / 26 / 600
Body:    14 / 22 / 400
Body L:  16 / 24 / 400
Small:   13 / 20 / 400
Caption: 12 / 18 / 500
Table:   13–14 / 20 / 400–500
```

---

# 108. Application Layout System

## 108.1 Global Layout

Use:

```text
┌─────────────────────────────────────────────────────────────┐
│ Top Header: Search | Quick Create | Notifications | Profile│
├───────────────┬─────────────────────────────────────────────┤
│               │ Breadcrumb / Page title / Page actions     │
│ Collapsible   ├─────────────────────────────────────────────┤
│ Sidebar       │                                             │
│               │ Main content                                │
│               │                                             │
│               │                                             │
└───────────────┴─────────────────────────────────────────────┘
```

Sidebar:

- Collapsed and expanded states
- Icon + label navigation
- Active indicator
- Nested navigation
- Favorites/pinned modules
- Tenant/workspace switcher where applicable
- Permission-aware menu items
- Subscription-aware menu items
- Tooltip in collapsed mode

Top bar:

- Global search
- Command palette shortcut
- Quick-create button
- Integration status indicator
- Notifications
- Help
- Theme switcher
- User profile menu

---

# 109. Dashboard UI/UX

The dashboard must remain **widget-driven and configurable**, not hard-coded.

Users with permission can:

- Add widgets
- Remove widgets
- Hide/show widgets
- Drag and drop
- Resize
- Reorder
- Duplicate
- Configure widget filters
- Choose date range
- Save dashboard layouts
- Reset to default
- Create multiple dashboards
- Set a default dashboard

### Dashboard widget categories

```text
Lead Metrics
Lead Sources
Lead Funnel
Lead Quality
AI Qualification
Pipeline
Deals
Revenue
Conversion
Campaigns
WhatsApp
Email
SMS
Appointments
Tasks
Automation
Team Performance
Response Time
SLA
Attribution
Integration Health
Subscription Usage
API Usage
```

### Dashboard visualizations

Use a professional Vue-compatible chart library such as Apache ECharts or an actively maintained equivalent.

Support:

- Line
- Area
- Bar
- Stacked bar
- Donut
- Funnel
- Gauge
- Scatter where useful
- Heatmap
- KPI cards
- Progress bars
- Sparkline
- Cohort/retention visualization

Charts must support:

- Tooltips
- Legend control
- Responsive resizing
- Date range changes
- Export where appropriate
- Accessible labels
- Consistent color meaning across charts

The same metric/category must use the same semantic color across the application and reports. Avoid using too many saturated colors. citeturn0search9

---

# 110. Enterprise DataTable / DataGrid Standard

Every major listing page must use a reusable, high-quality data-table component.

Examples:

- Leads
- Contacts
- Companies
- Deals
- Tasks
- Campaigns
- Conversations
- Messages
- Appointments
- Users
- Roles
- Integrations
- API keys
- Webhooks
- Subscriptions
- Invoices
- Usage records
- Automation workflows
- Templates
- Audit logs

The table must be optimized for real CRM usage, not just display.

Modern table patterns should support sorting, filtering, pagination, row selection, column visibility and row actions. citeturn0search5turn0search12

## 110.1 Standard Table Structure

```text
┌────┬────────────────────┬────────────┬────────────┬──────────┬──────────┬─────────────┐
│ □  │ Lead / Name ↑      │ Source     │ Status     │ Score    │ Updated  │ Actions     │
├────┼────────────────────┼────────────┼────────────┼──────────┼──────────┼─────────────┤
│ □  │ John Smith         │ Facebook   │ Qualified  │ 92       │ 2m ago   │ ⋮           │
│ □  │ Sarah Wilson       │ Instagram  │ New        │ 81       │ 8m ago   │ ⋮           │
└────┴────────────────────┴────────────┴────────────┴──────────┴──────────┴─────────────┘
```

The **first column must always be row selection** where bulk actions are supported.

### Header checkbox

The first header cell must contain:

- Select all current page
- Indeterminate state when some rows are selected
- Clear selection after bulk action
- Optional “Select all N matching records” capability for large datasets

### Row checkbox

Each row must contain a compact accessible checkbox.

### Actions column

The final column must contain contextual row actions.

Minimum actions:

- View
- Edit
- Delete

Context-dependent actions:

- Duplicate
- Archive
- Restore
- Activate
- Deactivate
- Assign
- Reassign
- Convert
- Send
- Resend
- Call
- WhatsApp
- Email
- Add task
- Add note
- Export
- Import
- Sync
- Retry
- Run workflow
- Test
- View logs
- View history
- View API payload

Use icon buttons for common actions and a `MoreHorizontal`/ellipsis menu for secondary actions.

Do not fill the table with many text buttons. Use consistent icons with accessible tooltips and keyboard support.

---

# 111. DataTable Toolbar

Every table should provide a reusable toolbar with:

### Search

- Prominent search input
- Search icon
- Debounced server-side search for large datasets
- Clear button
- Search across configured fields
- Optional advanced search syntax

Examples:

```text
Search name, email, phone, company...
Search lead ID...
Search invoice number...
```

### Filters

Provide a visible **Filters** button and/or filter bar.

Filter types:

- Text
- Select
- Multi-select
- Date
- Date range
- Number range
- Currency range
- Boolean
- Status
- Source
- Owner
- Team
- Tags
- Score range
- Pipeline
- Campaign
- Integration
- Created by
- Updated by
- Custom fields

Support:

- Clear all
- Individual filter removal
- Saved filters/views
- Filter count badge
- URL/query-state persistence where appropriate

### Column controls

Provide:

- Show/hide columns
- Drag to reorder columns
- Reset columns
- Save column preferences per user

### Density

Support:

- Comfortable
- Default
- Compact

### Export

Allow permission-controlled:

- CSV
- XLSX where supported
- PDF where useful

Large exports must be queued rather than blocking the browser.

---

# 112. DataTable Footer & Pagination

The bottom of every large table must provide:

```text
Showing 1–25 of 12,482 records

Rows per page: [25 ▼]

[First] [Previous] 1 2 3 … 499 [Next] [Last]
```

Required:

- Total records
- Filtered records where different
- Current range
- Rows per page
- Page navigation
- First/last controls
- Disabled states
- Loading state
- Selection count

Suggested page sizes:

```text
10
25
50
100
250
```

Persist the user's preferred page size per table when appropriate.

Use server-side pagination for large datasets.

Do not load thousands of records into the browser simply to paginate them.

---

# 113. Bulk Actions

When rows are selected, replace or expand the toolbar with a contextual bulk-action bar.

Example:

```text
✓ 24 selected

[Assign] [Change Status] [Add Tag] [Send Email]
[Export] [Archive] [Delete] [More]
```

Bulk actions must:

- Respect permissions
- Respect subscription limits
- Respect tenant isolation
- Require confirmation for destructive operations
- Display progress for large operations
- Execute large operations through queues
- Provide success/failure summaries
- Record audit events

For dangerous actions, show the exact affected record count.

---

# 114. Table States

Every table must implement all states:

### Loading

Use skeleton rows rather than a blank page.

### Empty

Explain why the table is empty and provide an appropriate CTA.

Example:

```text
No leads yet
Connect a lead source or create your first lead.
[Connect Source] [Create Lead]
```

### No search results

```text
No leads match your search.
Try a different keyword or clear filters.
[Clear Filters]
```

### Error

```text
We couldn't load these records.
[Retry]
```

### Partial/permission state

Do not expose unauthorized fields or actions.

---

# 115. Table Interaction & Accessibility

All tables must support:

- Keyboard navigation
- Accessible checkbox labels
- Focus states
- Tooltips for icon-only actions
- Screen-reader-friendly column headings
- Sort direction indicators
- Visible selected-row states
- Accessible menus
- Confirmations for destructive actions
- Responsive behavior

On small screens, use a responsive table strategy:

1. Horizontal scrolling for information-dense tables where appropriate.
2. Priority columns remain visible.
3. Secondary columns can move into a row-details drawer.
4. Actions remain accessible.
5. Never simply shrink text until the table becomes unusable.

---

# 116. Forms, Modals & Drawers

Use modals/drawers for quick CRUD operations where practical.

Use full-page forms for complex multi-step processes.

Examples for modal/drawer CRUD:

- Create lead
- Edit lead
- Add contact
- Add note
- Assign owner
- Create task
- Add tag
- Create API key
- Add integration
- Create webhook

All forms must use:

- Client-side UX validation
- Laravel/server-side validation
- Field-level errors
- Summary errors where useful
- Async submit states
- Duplicate-submit prevention
- Unsaved-change protection
- Accessible labels
- Helper text
- Input formatting

**Do not rely on HTML `required` validation.** Server-side validation is authoritative.

---

# 117. Toast / Notification System

Use a consistent global toast system.

Types:

- Success
- Info
- Warning
- Error
- Loading/progress

Examples:

```text
✓ Lead created successfully.
✓ Changes saved.
ℹ Sync started. You can continue working.
⚠ Your monthly message quota is almost exhausted.
✕ The integration connection failed. Please reconnect.
```

Requirements:

- Non-blocking
- Dismissible
- Auto-dismiss for normal messages
- Persistent for critical errors when appropriate
- Action button support
- Queue multiple notifications
- Avoid duplicate toasts
- Accessible live-region behavior
- Mobile-friendly positioning

Use toasts for immediate operation feedback, not as a replacement for important inline form errors.

---

# 118. Iconography

Use **Lucide Icons** or another actively maintained consistent icon library.

Do not mix random icon sets.

Recommended mappings:

```text
Dashboard       LayoutDashboard
Leads           UsersRound / UserRoundPlus
Contacts        ContactRound
Companies       Building2
Deals           Handshake
Pipeline        GitBranch
Campaigns       Megaphone
Automation      Workflow
AI              Sparkles
Inbox           Inbox
WhatsApp        MessageCircle
Email           Mail
SMS             Smartphone
Calendar        CalendarDays
Tasks           CheckSquare
Analytics       ChartNoAxesCombined
Integrations    PlugZap
API             Braces
Settings        Settings2
Search          Search
Filter          ListFilter
Add             Plus
Edit            Pencil
Delete          Trash2
View             Eye
More            MoreHorizontal
Export          Download
Import          Upload
Sync            RefreshCw
Assign          UserRoundCog
Archive         Archive
Restore         ArchiveRestore
```

Icons must be semantically consistent across all modules.

---

# 119. Status, Score & Badge System

Use semantic badges rather than random colors.

Example lead statuses:

```text
New           Blue
Contacted     Cyan
Qualified     Green
Nurturing     Violet
Appointment   Indigo
Proposal      Amber
Won           Green
Lost          Red/Gray
Archived      Gray
```

AI lead score:

```text
0–39     Low
40–69    Medium
70–89    High
90–100   Very High
```

The exact scoring thresholds must remain configurable.

Never rely on color alone. Include text/icons for status meaning.

---

# 120. Global Search & Command Palette

Implement a global command/search interface.

Shortcut:

```text
Ctrl/Cmd + K
```

Search:

- Leads
- Contacts
- Companies
- Deals
- Conversations
- Tasks
- Appointments
- Campaigns
- Help/documentation
- Settings

Quick actions:

- Create lead
- Create contact
- Create deal
- Send message
- Create task
- Open inbox
- Start import
- Connect integration

Results must respect tenant isolation and user permissions.

---

# 121. UI Motion & Micro-interactions

Use subtle motion only where it improves comprehension.

Use:

- 150–200 ms hover transitions
- Smooth dropdowns
- Drawer transitions
- Modal transitions
- Skeleton shimmer
- Chart transitions
- Success confirmation animation

Avoid:

- Excessive bouncing
- Long transitions
- Decorative animation on every component
- Motion that slows CRM workflows

Respect `prefers-reduced-motion`.

---

# 122. Responsive Design

Support:

- Desktop
- Laptop
- Tablet
- Mobile

Breakpoints should be design-token based.

On mobile:

- Sidebar becomes a drawer
- Tables become horizontally scrollable or transform to cards/details
- Filters become a bottom sheet/drawer
- Modals may become full-screen sheets
- Dashboard widgets stack intelligently
- Primary actions remain easy to reach

---

# 123. Dark Mode

Dark mode must be a complete design system, not simply inverted colors.

Define separate dark tokens for:

- Page background
- Sidebar
- Header
- Cards
- Inputs
- Tables
- Borders
- Text
- Charts
- Status badges
- Tooltips
- Modals
- Dropdowns

Avoid pure `#000000` as the main background. Use deep navy/slate surfaces to create hierarchy.

Persist theme preference per user.

Support:

```text
Light
Dark
System
```

---

# 124. Website Marketing UI

The public website must share the same design language but may be more expressive than the application.

Required sections:

- Hero
- Product overview
- Lead capture
- AI features
- CRM
- Omnichannel inbox
- Automation
- Integrations
- Analytics
- Security
- Pricing
- Testimonials where legitimate
- FAQ
- CTA
- Footer

Use Poppins for major marketing headings and Inter for supporting text/UI.

Use product screenshots/illustrations that match the actual dashboard.

Do not create fake customer logos, fake testimonials or fabricated performance claims.

---

# 125. UI Component Library Requirements

Build reusable components for:

```text
AppShell
Sidebar
Topbar
Breadcrumbs
PageHeader
MetricCard
ChartCard
WidgetContainer
DataTable
DataTableToolbar
DataTableFilters
DataTablePagination
ColumnSelector
BulkActionBar
StatusBadge
ScoreBadge
Avatar
AvatarGroup
SearchInput
CommandPalette
Select
MultiSelect
Combobox
DatePicker
DateRangePicker
TagInput
Modal
Drawer
ConfirmationDialog
Toast
Alert
Tabs
Accordion
Timeline
ActivityFeed
KanbanBoard
Calendar
FileUploader
RichTextEditor
EmptyState
ErrorState
Skeleton
Tooltip
DropdownMenu
ContextMenu
Popover
Progress
Stepper
```

Every component must support light/dark mode and accessibility.

---

# 126. Design Tokens

Create a centralized token system.

Example:

```text
colors.primary.*
colors.secondary.*
colors.success.*
colors.warning.*
colors.danger.*
colors.info.*
colors.neutral.*
colors.surface.*
colors.background.*
colors.text.*

font.family.*
font.size.*
font.weight.*
lineHeight.*

spacing.*
radius.*
shadow.*
border.*
zIndex.*
transition.*
```

Do not hard-code visual values inside individual components when a shared token exists.

Brand colors must be replaceable through tenant/platform branding settings without changing component code.

---

# 127. Design-System QA Checklist

Before accepting any UI module, verify:

- [ ] Light mode works.
- [ ] Dark mode works.
- [ ] Mobile works.
- [ ] Tablet works.
- [ ] Keyboard navigation works.
- [ ] Focus states are visible.
- [ ] Empty state exists.
- [ ] Loading state exists.
- [ ] Error state exists.
- [ ] Permission restrictions are respected.
- [ ] Subscription restrictions are respected.
- [ ] Toast feedback exists.
- [ ] Destructive actions require confirmation.
- [ ] Tables support search/filter/sort/pagination.
- [ ] First table column supports row selection where applicable.
- [ ] Actions are accessible through icons and/or menu.
- [ ] Total record count is displayed.
- [ ] Per-page selector exists.
- [ ] Bulk actions work.
- [ ] Export respects permissions.
- [ ] Server-side pagination is used for large data.
- [ ] Forms do not rely on browser `required` validation.
- [ ] API errors are presented clearly.
- [ ] No tenant data leaks across boundaries.

---

# 128. Final Brand/UI Implementation Instruction

The application must not feel like a collection of separately designed pages.

Create one coherent visual language across:

```text
Marketing Website
       ↓
Authentication
       ↓
Onboarding
       ↓
Dashboard
       ↓
CRM
       ↓
Lead Management
       ↓
Automation
       ↓
Omnichannel Inbox
       ↓
AI
       ↓
Campaigns
       ↓
Analytics
       ↓
Billing
       ↓
Settings
       ↓
Developer/API Portal
```

The generated eight-brand visual board should be treated as a creative reference for the candidate identities. The final production system must convert the selected identity into an actual vector logo system, favicon set, design tokens and reusable Vue components.

The UI should take inspiration from the strongest current SaaS design patterns—composable dashboards, reusable data tables, sorting/filtering/pagination, row selection, column visibility, responsive navigation and consistent component primitives—while remaining an original product design. citeturn0search0turn0search5turn0search14

**End of Brand, UI/UX, DataTable & Visual Design Extension.**
