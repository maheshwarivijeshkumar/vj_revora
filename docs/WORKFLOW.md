# How Revora CRM works

The complete workflow for Berg Media Digital and Vijesh Kumar Maheshwari: where leads and
opportunities come from, what the system does with them automatically, what a person does, and how
to set each part up.

```text
 SOURCES                         THE CRM DOES AUTOMATICALLY                   YOU DO
 ───────                         ──────────────────────────                   ──────
 Facebook & Instagram lead ads ─┐
 LinkedIn Lead Gen Forms ───────┼─► capture → dedupe → score → assign ─┐
 Website forms / API ───────────┘                                      │
                                                                       ▼
                              Email 1 at once ─► Email 2 at 6h ─► Email 3 at 24h
                              WhatsApp welcome (if the lead consented)
                                       │                     │
                                 lead replies           no reply by 72h
                                       ▼                     ▼
                         reply scored 1–10 by AI      scored 0/10, → Nurturing
                         sequence stops, status moves          │
                                       ▼                         ▼
                                Qualified / Contacted ──► call, proposal, won / lost

 Pakistan PPRA tenders ─┐
 Upwork jobs ───────────┼─► fetch → dedupe → fit score vs your services ─► Opportunities page
 Remotive / Remote OK /─┘                                                   │
 Arbeitnow job boards                                    AI drafts proposal ─┤
                                                         you review & submit ┘ (Upwork / EPADS / email)
```

---

## 1. The two businesses (Settings → Services)

Everything is matched and written as one of two **service profiles**:

| Profile | Type | Voice | Used for |
| --- | --- | --- | --- |
| **Berg Media Digital** (default) | Agency, UK (Watford) & UAE (Dubai) | "we" | Website, software, mobile, AI, e-commerce, UI/UX, SEO, cloud, branding work; signs the automatic follow-ups |
| **Vijesh Kumar Maheshwari** | Freelancer, London | "I" | Laravel / Vue / React engineering: SaaS, ERP, POS, accounting, CRM, marketplaces; preferred for Upwork |

Each profile holds its services (with the **keywords** clients use), technologies, portfolio,
markets, pricing and sign-off. They were created from bergmediadigital.com and vijeshmaheshwari.com
only. **To do:** paste your full resume into the Vijesh profile's "About" box. Proposals are written
only from what the profile says, so anything missing there is never claimed.

Changing a profile's keywords re-scores every untouched opportunity straight away.

---

## 2. Where leads come from

| Source | How it arrives | Consent | Automatic follow-up? |
| --- | --- | --- | --- |
| Facebook & Instagram lead ads | Synced hourly from each connected Facebook Page (Instagram lead ads belong to the Page) | The person submitted your form | Email: yes. WhatsApp: only if the form had a consent box they ticked |
| LinkedIn Lead Gen Forms | Synced hourly per connected ad account | The person submitted your form | Email: yes |
| Website forms / your own sites | POST to `/api/v1/inbound/leads` with an API key (Settings → API) | The person submitted your form | Email: yes |
| Opportunities → "Add to leads" | You click it on a tender or job | Nobody asked to be contacted | **No**: you contact them by hand |
| Imported lists (source type "Imported") | Not built yet (CSV import is on the roadmap) | A list, not a request | **Never** emailed automatically |

Every lead goes through one pipeline: **normalise → deduplicate (email, then phone) → score → verify
→ assign**. A second submission from the same person enriches the existing lead instead of
creating a duplicate. Each lead keeps its campaign, ad and form name, so you can filter and export
by campaign (Leads → filter by Source → **Export to Excel**).

> Threads has no lead ads or lead API, so it cannot be a source. The CRM does not scrape
> profiles from any network or from web searches: leads are people who asked to hear from you.

---

## 3. What happens to a new lead (automatic)

| When | What | Template |
| --- | --- | --- |
| Within a minute | **Email 1** from your default mailbox | `Berg Media · 1. First reply` |
| Within a minute (if consented + phone) | **WhatsApp welcome** template | your approved `lead_welcome` |
| 6 hours after Email 1 | **Email 2**, threaded under Email 1 | `Berg Media · 2. Follow-up (6 hours)` |
| 24 hours after Email 1 | **Email 3**, last note | `Berg Media · 3. Last follow-up (24 hours)` |
| 72 hours after Email 1, still no reply | Sequence ends, **reply score 0/10**, status → Nurturing | — |

The schedule, the emails and the no-reply window are editable under **Settings → Follow-up**.

### When the lead replies (email or WhatsApp)

1. The reply is filed on the lead (inboxes are read every 5 minutes; WhatsApp arrives instantly).
2. The remaining follow-ups **stop**.
3. The reply is **scored 1–10** by AI (Claude, OpenAI or Gemini), or by keyword rules when no AI key
   is set:

| Score | Meaning | Status becomes |
| --- | --- | --- |
| 10 | Ready to buy or book now | Qualified |
| 8–9 | Asks for a call, demo, quote or pricing | Qualified |
| 6–7 | Interested, asking questions | Contacted (Qualified at 7) |
| 4–5 | Neutral or unclear | Contacted |
| 2–3 | Not now, maybe later | Nurturing |
| 1 | Not interested, wrong person, or "stop" | Lost |
| 0 | Never replied (set by the sequence) | Nurturing |

Automatic moves only touch the early stages. Once **you** set Appointment, Proposal, Won, Lost or
Archived, the automation never overrides it.

### Opt-outs

- Every email has an **unsubscribe link** (and the one-click header Gmail and Yahoo require). One
  click stops all emails to that lead.
- Replying **STOP** on WhatsApp opts the lead out of WhatsApp only.
- A reply like "unsubscribe" or "remove me" opts out of that channel.
- Opted-out leads cannot be messaged by any path: sequence, manual email or WhatsApp.

---

## 4. What you do with a lead (by hand)

Open the lead → **Messages** tab. You see the whole email and WhatsApp thread, the reply score and
the follow-up status. From there you can send an email from any template, chat on WhatsApp (free
text within 24 hours of their last message, otherwise the approved template), or stop the
follow-ups.

| Stage | Your action | Template |
| --- | --- | --- |
| Qualified | Book a call | `4. Book a discovery call` |
| Appointment | Hold the call, write the proposal | — |
| Proposal | Send it | `5. Proposal sent` |
| 3 days later | Chase it | `6. Proposal follow-up (3 days)` |
| Nurturing | Check in after ~30 days | `7. Check-in (nurture)` |
| Won | Kick off | `8. Welcome & kickoff (won)` |
| Lost | Ask why, leave the door open | `9. Close the loop (lost)` |

Each template exists twice: `Berg Media · …` (we) and `Vijesh · …` (I). Placeholders:
`{{first_name}}`, `{{company_name}}`, `{{my_company}}`, `{{my_website}}`, `{{my_phone}}`,
`{{my_signature}}` and more (listed in the template editor).

---

## 5. Opportunities: tenders and jobs (Opportunities page)

| Source | How often | Notes |
| --- | --- | --- |
| **Pakistan PPRA tenders** (EPMS) | 8 AM and 4 PM PKT | All ~2,000 active federal tenders. Also listed in full on the **Tenders** page with issue date and submission deadline |
| **Upwork** | Every 2 hours | Official API, read-only, after Upwork approves your API key. One search per profile from its service keywords |
| **Remotive, Remote OK, Arbeitnow** | 9 AM and 5 PM PKT | Public APIs. Companies hiring for work you can deliver as a contractor. Each post links back to its board, as their terms require |

**Fit score (0–100)**, every point explained on the card:

- service keywords in the title (up to 60; tender titles weigh more) and in the description/tags (up to 20)
- your technologies named in the post (up to 15)
- signals: posted in the last 3 days, a budget given, Upwork payment verified, client spend ≥ $1,000; services tenders
- marked down: goods purchases (laptops, switches), closing in under 3 days, 50+ applicants, sales or
  support hires, AI-trainer gigs, unpaid/equity-only, German-language roles, posts over a month old

The page shows **strong fits (60+)** by default. "Good fit (45+)" and "Everything matched" widen it.
The same job on two boards is kept once.

### Working an opportunity

1. Open the card → read the reasons → **Open on [source]** to see the original.
2. **Draft proposal / Draft letter**: AI writes it in the matched profile's voice, from the profile's
   facts only (template draft when no AI key is set).
   - Upwork: a 150–220 word cover letter
   - Job board: a short pitch offering to deliver the role's work as a contractor
   - Tender: a formal expression of interest plus a "Before bidding" checklist (FBR active-taxpayer,
     NTN/STN, EPADS registration, experience evidence, bid security)
3. **Edit, copy, and submit it yourself** on Upwork, on EPADS, or by email. Nothing is submitted
   automatically: Upwork bans automated proposals and can suspend accounts that use them, and
   tenders are bid on EPADS.
4. **Mark applied** → later **Won** or **Lost**. **Add to leads** puts it in the pipeline.
   **Not a fit** dismisses it.

---

## 6. Daily routine

| When | Who | What |
| --- | --- | --- |
| Morning | Sales | Leads → filter Status = Qualified, sort by Reply. Call or book everyone at 7+ |
| Morning | Sales | Opportunities → strong fits → draft, review and submit the best 3–5 |
| Midday | Sales | Leads → Messages on anyone who replied; send proposals; chase proposals at 3 days |
| Weekly | Owner | Tenders page: anything closing in 7 days you want to bid on |
| Weekly | Owner | Settings → Setup: everything green? Reconnect anything amber or red |
| Monthly | Owner | Settings → Services: refresh keywords from the jobs you won and lost |

---

## 7. Setup (Settings → Setup shows live progress)

Production address: `https://crm.vijeshmaheshwari.com`. Sign-ins to Facebook, LinkedIn, Google,
Microsoft and Upwork happen on their own pages; the CRM never sees those passwords.

| # | Item | What to do | Register this callback / webhook |
| --- | --- | --- | --- |
| 1 | Services | Settings → Services → *Set up from both websites*; paste your resume | — |
| 2 | Email | Settings → Email → Gmail, Microsoft 365, or SMTP + IMAP | `/settings/email/google/callback`, `/settings/email/microsoft/callback` |
| 3 | Facebook & Instagram | Meta app with Facebook Login; App Review for `leads_retrieval` and `pages_manage_ads`; app Live | `/settings/integrations/meta/callback` |
| 4 | LinkedIn | LinkedIn app + Lead Sync API access (`r_marketing_leadgen_automation`) | `/settings/integrations/linkedin/callback` |
| 5 | WhatsApp | Meta Business → WhatsApp Cloud API: phone number ID, permanent system-user token, approved template | Webhook `/api/v1/webhooks/whatsapp`, field `messages` |
| 6 | Upwork | Request an API key with "Read marketplace Job Postings" | `/settings/upwork/callback` |
| 7 | AI | One key: Anthropic, OpenAI or Gemini | — |
| 8 | Scheduler | `php artisan schedule:work` (or cron `* * * * * php artisan schedule:run`) | — |
| 9 | Queue worker | `php artisan queue:work --queue=verification,webhooks,integrations,messaging,bulk,default` | — |

Server `.env` keys: `META_APP_ID`, `META_APP_SECRET`, `LINKEDIN_CLIENT_ID`, `LINKEDIN_CLIENT_SECRET`,
`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `MICROSOFT_CLIENT_ID`, `MICROSOFT_CLIENT_SECRET`,
`WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN`, `UPWORK_CLIENT_ID`, `UPWORK_CLIENT_SECRET`,
`AI_PROVIDER` (claude | openai | gemini), `AI_REPLY_PROVIDER`, and the matching
`ANTHROPIC_API_KEY` / `OPENAI_API_KEY` / `GEMINI_API_KEY`.

First pulls, after deploying: `php artisan migrate`, `php artisan tenders:fetch`,
`php artisan opportunities:fetch`.

### WhatsApp templates to submit to Meta for approval

| Name | Category | Body |
| --- | --- | --- |
| `lead_welcome` | Utility | Hi {{1}}, thanks for your enquiry with Berg Media Digital. We've received your details and will be in touch shortly. Reply here with any questions, or STOP to opt out. |
| `lead_followup` | Marketing | Hi {{1}}, just checking you received our email about your project. Would a quick call this week help? Reply STOP to opt out. |

Put the approved name (e.g. `lead_welcome`) in Settings → WhatsApp. `{{1}}` is filled with the
lead's first name.

### Gmail note

Reading Gmail is a restricted Google permission. An **External** Google app left in testing mode
signs you out every 7 days (the CRM shows "Reconnect needed"). Use an **Internal** app on Google
Workspace, or SMTP + IMAP with a Gmail app password, to avoid that.

---

## 8. Schedule

| Job | When |
| --- | --- |
| Follow-up emails due | every minute |
| Read inboxes for replies | every 5 minutes |
| Facebook / Instagram / LinkedIn lead sync | hourly |
| Upwork job search | every 2 hours |
| PPRA tenders | 8 AM and 4 PM PKT |
| Job boards | 9 AM and 5 PM PKT |
| Remove deleted documents | 3:30 AM daily |
