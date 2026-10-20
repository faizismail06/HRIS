---
name: Operational HRIS — Ruang Tim
description: A clear, open workspace for the team’s day.
colors:
  ink: '#332c36'
  muted: '#716775'
  accent: '#704760'
  accent-hover: '#523048'
  ground: '#f7f6f8'
  line: '#e8e3e9'
  selected: '#f0e8ef'
  urgent: '#97472d'
  surface: '#ffffff'
  focus: '#936082'
  selection: '#e5d2df'
  selection-ink: '#432b3d'
  field-line: '#dcd3df'
  field-icon: '#89748a'
  field-placeholder: '#7d6f81'
  filter-hover: '#f5f1f5'
  row-hover: '#fcfafd'
  context: '#f8f4f8'
  table-heading: '#faf8fb'
  row-line: '#f0ebf1'
  urgent-surface: '#faf0e6'
  urgent-label: '#8b4f28'
  urgent-dot: '#af703c'
  present: '#498369'
  scheduled: '#b6c4b9'
  leave: '#8f9fc0'
  incomplete: '#cdac65'
  absent: '#ac6654'
  avatar-lavender: '#eee7ef'
  avatar-lavender-ink: '#67516d'
  avatar-blue: '#e9edf4'
  avatar-blue-ink: '#4e627c'
  avatar-sand: '#eeeade'
  avatar-sand-ink: '#72613f'
  avatar-green: '#e7eee8'
  avatar-green-ink: '#4b6a50'
typography:
  headline:
    fontFamily: '''Segoe UI'', ui-sans-serif, system-ui, sans-serif'
    fontSize: 34px
    fontWeight: 600
    lineHeight: 1.25
    letterSpacing: -.03em
  title:
    fontFamily: '''Segoe UI'', ui-sans-serif, system-ui, sans-serif'
    fontSize: 18px
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: -.015em
  body:
    fontFamily: '''Segoe UI'', ui-sans-serif, system-ui, sans-serif'
    fontSize: 14px
    fontWeight: 400
    lineHeight: 1.5
  task-title:
    fontFamily: '''Segoe UI'', ui-sans-serif, system-ui, sans-serif'
    fontSize: 13px
    fontWeight: 600
    lineHeight: 1.5
  navigation:
    fontFamily: '''Segoe UI'', ui-sans-serif, system-ui, sans-serif'
    fontSize: 13px
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: '''Segoe UI'', ui-sans-serif, system-ui, sans-serif'
    fontSize: 12px
    fontWeight: 400
    lineHeight: 1.5
  metadata:
    fontFamily: '''Segoe UI'', ui-sans-serif, system-ui, sans-serif'
    fontSize: 11px
    fontWeight: 400
    lineHeight: 1.5
  metric:
    fontFamily: '''Segoe UI'', ui-sans-serif, system-ui, sans-serif'
    fontSize: 25px
    fontWeight: 550
    lineHeight: 1.2
    letterSpacing: -.02em
rounded:
  badge: 5px
  filter: 6px
  control: 7px
  panel: 14px
spacing:
  compact: 8px
  related: 12px
  inset-small: 20px
  section: 24px
  inset: 26px
  work-gap: 28px
  page-inline: 44px
components:
  button-secondary:
    backgroundColor: '{colors.surface}'
    textColor: '{colors.accent}'
    rounded: '{rounded.control}'
    padding: 10px 16px
  input-search:
    textColor: '{colors.ink}'
    rounded: '{rounded.control}'
    height: 42px
  navigation-current:
    textColor: '{colors.accent}'
    padding: 0 5px
  badge-urgent:
    backgroundColor: '{colors.urgent-surface}'
    textColor: '{colors.urgent-label}'
    rounded: '{rounded.badge}'
    padding: 5px 9px
  panel:
    backgroundColor: '{colors.surface}'
    rounded: '{rounded.panel}'
  filter-selected:
    backgroundColor: '{colors.selected}'
    textColor: '{colors.accent}'
    rounded: '{rounded.filter}'
    padding: 9px 14px
  task-disclosure:
    backgroundColor: '{colors.surface}'
    textColor: '{colors.ink}'
    padding: 20px 26px
---

# Design System: Operational HRIS

## Overview

**Creative North Star: "Ruang Tim"**

Ruang Tim is a light, open workspace with plum actions, neutral dark text, white surfaces, and a barely lavender ground. Its modern character comes from measured spacing, readable type, thin borders, and a small number of clear destinations.

Information has room to breathe while repeated tasks remain easy to scan. Quiet local states and native disclosures reveal context where it is needed. The current expression includes a read-only dashboard, employee directory, profiles, organization view, schedules, attendance records, requests, leave balances, team availability, employment administration, lifecycle checklists, assets, payroll, reimbursement, attendance reports, period-close illustrations, target settings, audit examples, import previews, notifications, cross-module search, and a catalog of static role/form/state previews with synthetic fixtures; the visual system does not imply operational HR capabilities.

**Key Characteristics:**

- Open horizontal navigation for the available destinations.
- Plum action states on light neutral surfaces.
- Readable rows, native controls, and inline context.
- Minimal motion with reduced-motion support.

## Colors

Plum carries interaction while a near-white lavender ground and neutral ink keep the workspace open. Frontmatter values are normative and extracted from the current CSS; the superseded forest-and-sage palette is not part of this system.

### Primary

- **Team Plum** (`accent`, `accent-hover`): actions, active navigation, time labels, native-control accents and caret.
- **Focus Plum** (`focus`): keyboard and containing-field outlines.
- **Selected Lavender** (`selected`): pressed category filters and count backgrounds.

### Neutral

- **Neutral Ink** (`ink`) and **Muted Ink** (`muted`): principal and supporting text.
- **Light Lavender Ground** (`ground`), **White Surface** (`surface`), and **Fine Divider** (`line`): page, panels and boundaries.
- `field-line`, `field-icon`, and `field-placeholder` keep search legible; `selection` and `selection-ink` define selected text.
- `filter-hover`, `row-hover`, `context`, `table-heading`, and `row-line` distinguish local interaction and table hierarchy.

### Semantic status

- `urgent`, `urgent-surface`, `urgent-label`, and `urgent-dot` identify deadlines and attention badges.
- `present`, `scheduled`, `leave`, `incomplete`, and `absent` belong to the attendance bar and its labeled legend. They are not alternative action accents.
- Lavender, blue, sand and green avatar pairs distinguish synthetic initials through a quiet fill/text relationship; initials remain the identifier.

**The State Color Rule.** Plum identifies actions and selection. Attendance and urgency colors carry semantic meaning and always have accompanying text.

## Typography

**Heading and body font:** Segoe UI with the system fallbacks in the frontmatter. No external font is loaded. The larger page title and more spacious task rows establish hierarchy without a display typeface.

### Hierarchy

- **Headline:** the greeting/page title; mobile size (28px).
- **Title:** section headings; mobile size (17px), with the narrow task heading at (16px).
- **Body:** inherited interface text, with specific task and control roles below.
- **Task title:** primary row copy, wrapping as needed; mobile size (12px).
- **Navigation:** destination labels; current state uses weight (600), mobile size (12px).
- **Label:** search, filter and deadline context; some mobile labels reduce to (10–11px).
- **Metadata:** secondary row and agenda information. The smallest synthetic-data labels and footnotes use (10px).
- **Metric:** attendance counts with tabular figures; mobile size (24px).

Numbers use tabular figures. Headings use balanced wrapping and paragraphs use pretty wrapping where supported. Detail text has a maximum measure of (70ch).

**The Available Destination Rule.** Size navigation around destinations that exist, keeping the available header destinations directly visible on desktop and mobile.

## Layout

Header and main share a centered maximum width of (1264px), including horizontal padding of (44px). The desktop header is at least (88px) high, with brand, seven horizontal destination links, and persona. There is no reserved side rail or collapsed navigation control.

Main padding is (26px 44px 32px). The dashboard places attendance and agenda in a top grid with a (1.8:1) column ratio and (24px) gap; the task panel spans the full width below with a (28px) separation. This page-specific order is recorded in `docs/dashboard-surface.md`, not a mandatory layout for every future screen.

Implemented responsive rules, pending browser verification:

- At (1280px) and below, the header wraps with navigation on its own full-width, wrapping row below brand and persona. Navigation links are at least (48px) high, with no vertical gap and an (18px) horizontal gap.
- At (1100px) and below, horizontal page padding becomes (30px), the overview ratio becomes (1.5:1), and its gap becomes (20px). The attendance legend uses two columns.
- At (1000px) and below, persona text is hidden; the header retains its separate navigation row.
- At (760px) and below, brand and compact persona occupy the first line, and all seven navigation links stay visible on their wrapping row. Navigation uses no vertical gap and an (18px) horizontal gap, reducing to (8px) at (400px). Links are at least (48px) high. Main uses (22px) horizontal padding; the overview becomes one column, the attendance legend uses three columns, and search and branch selection stack.
- At (400px) and below, page padding becomes (16px), the attendance legend uses two columns, row avatars disappear, and task columns become a flexible text column plus (66px) deadline and (12px) indicator columns.

The people extension inherits this shell. Its directory preserves table columns inside a horizontal scroll region with a (900px) minimum table width. Profiles use a (1.65:1) field/history grid that stacks at (1000px); field pairs become one column at (450px), and team lists stack at (760px). Directory filters wrap with the same native controls. These are source rules, not browser-verified layouts. Surface-specific composition is recorded in `docs/people-surface.md`.

Static form previews use two field columns, becoming one at (760px) and below; textareas span the available width. Account previews use a separate compact shell capped at (650px), with single-column fields and no HR navigation. These inherit the existing type, controls, panels and focus vocabulary.

The 360px and 1440px acceptance sizes remain browser verification targets. Browser inventory was empty; this documentation records CSS/JS intent and source evidence, not approved rendered appearance.

## Elevation & Depth

The interface is flat at rest and defines no box-shadow tokens. White panels, pale local state fills, fine borders and deliberate spacing provide separation. Focus outlines identify interaction rather than simulated elevation.

**The Quiet Surface Rule.** Use white surfaces, fine borders, and spacing to establish hierarchy without decorative shadows.

## Shapes

Panels retain gently rounded corners (`panel`). Fields and the secondary button share the tighter control radius; category filters and badges use their smaller radii. Borders are generally (1px). Avatars and status dots are circles. Icons use inline outline SVGs with current text color and rounded strokes; the brand is CSS geometry. No raster assets ship.

## Components

### Navigation

Seven server routes share the global header: Ringkasan (`/prototype/dashboard`), Karyawan (`/prototype/employees`), Organisasi (`/prototype/organization`), Kehadiran (`/prototype/attendance`), Pengajuan (`/prototype/requests`), Administrasi (`/prototype/contracts`), and Keuangan (`/prototype/payroll`). The server assigns `aria-current="page"` and the current-link style, including employee profiles, schedules, and shift details under their parent destination. Plum text, a (2px) bottom border, and stronger weight identify the active page. Tugas and Agenda are local dashboard section anchors; profile sections also use local anchors. Attendance has four local route links: Rekap kehadiran, Jadwal mingguan, Laporan, and Penutupan periode. Pengajuan has local route links for Daftar pengajuan, Saldo cuti, and Kalender tim. Administrasi groups eight wrapping local routes—Kontrak, Surat, Perubahan karier, Siklus kerja, Aset, Pengaturan, Audit, and Impor—under the same server-selected global destination. Keuangan groups Batch payroll and Reimbursement under one server-selected global destination. Global navigation has seven destinations. Dashboard JavaScript focuses local destinations without changing global active-page state. Global navigation stays visible without JavaScript and at mobile widths. A wrapping utility row sits inside main above the prototype notice. Panduan semua layar statis aligns left through an automatic end margin, with Pencarian and Notifikasi on the right when space permits; these do not add global header destinations. The server marks active search and notification utilities with `aria-current="page"`. Shared layout properties can optionally supply a role persona, initials and navigation; the default seven destinations remain unchanged. The notification link name includes the fixed three-unread fixture count, while its visual badge is `aria-hidden` to avoid duplicate announcement. It is not a live count.

### Buttons and category filters

The bordered secondary button clears filters with a minimum height of (44px). Hover uses the selected fill. Category filters use rounded, filled selected states rather than underline tabs; minimum height is (40px) on desktop and (44px) on mobile. They retain `aria-pressed` group semantics. The directory adds a filled primary submit button using the existing accent and hover tokens, white text, the control radius, a (44px) minimum height, and (10px 17px) padding. Its label is Terapkan; it applies GET filters without mutating HR records. Disabled buttons use opacity (.55) and a not-allowed cursor.

### Search and native controls

Search combines an icon and (42px) high input. Its enclosing field gets a (2px) focus outline and (2px) offset. The native branch selector has a minimum height of (44px). The labeled urgency checkbox has a (40px) minimum-height label on desktop and (44px) on mobile. Search, category, branch and urgency combine locally; a polite live count communicates results. Reset restores all filters and focuses search.

### Panels, attendance and agenda

White bordered panels use the shared rounded shape. Desktop panel insets are approximately (24–26px), reducing at narrow breakpoints. The attendance bar is (12px) high with (4px) gaps and a textual legend. Agenda entries pair a clear time column with contextual copy and fine separators. Status labels remain distinct from available actions.

### Inline task context

Task rows use native `details` and `summary`. Desktop summary padding is (20px 26px), and the columns are flexible task text plus (130px) deadline and (64px) action. Hover and open fills remain subtle. Context appears directly below with ownership and next-step information. Filtering closes hidden disclosures; without JavaScript all fixture rows and native disclosures still work, while unavailable filters stay hidden with an explanatory notice.

### Directory filters and employee table

Status links retain the other query filters and use `aria-current` to identify the selected status. Native GET fields cover search, department, branch and sorting; submitted values remain visible after navigation. Apply and reset work without JavaScript. Empty results offer a clear reset destination, and invalid filters have an alert message. The table keeps column headers and row-header person links; its labeled, focusable horizontal region preserves column relationships on narrow screens. Person and detail links are at least (44px) high. Employment badges reuse existing green, blue, warm and neutral pairs with explicit labels; employment status stays separate from attendance or account access.

### Profile fields, history and team links

Use semantic definition lists for identity and assignment fields, and an ordered timeline with native `time` elements for employment history. Planned changes keep an outlined marker and explicit scheduled wording; they do not replace current assignment text. Profile, manager, report, and return-to-directory links preserve the originating directory query filters. Related-person links keep initials, readable name, role and branch together. This is read-only context, not an editable form or an audit trail.

### Organization disclosures

Native unit `details` preserve the same disclosure-chevron motion and focus treatment. Unit headings pair responsibility with a labeled working-member count. The branch GET filter narrows membership and counts to Active and Offboarding profiles while preserving the company hierarchy and responsible leads. Empty units explain the filtered result and retain a directory route for broader inspection. Organization structure does not imply authorization rules.

### Schedules, attendance and shift details

The attendance extension inherits native GET controls and existing state colors. Rekap filters use work date, employee, branch and status; schedules use an available week, employee and branch. Local route links, week navigation and detail/back links preserve their supported filter scope. The summary is explicitly calculated before the status filter. Empty combinations offer reset links; all controls remain useful without JavaScript.

The synthetic fixture covers 70 daily records for five people over two weeks (12–25 October 2026). Weekly roster columns identify the shift-start date; shift links state when the end falls on the next day. A night shift beginning on 19 October stays attached to that work date even when it ends on 20 October. The roster and recap keep native table headers inside labeled, focusable horizontal scroll regions rather than hiding columns. These are source provisions pending visual and keyboard verification.

Keep roster plans, raw clock events, and interpreted outcomes distinct. Late and early-departure flags are independent of Present status. Scheduled, In progress, Incomplete, Leave, Day off and Absent remain separately labeled. Detail fields show full start/end context, timezone, break and duration notes; semantic event timelines label the source SIMULATED. Native correction-guidance disclosures explain the example deadline and preserved raw events without offering submission, attachment or approval actions. The seven-day example is a PRD default, not an operational policy. Surface details remain in `docs/attendance-surface.md`.

### Requests, leave balances and team availability

The requests extension inherits native GET filters, labeled horizontal table regions, and the existing state colors. Search, type, status, branch and employee narrow the request list; local navigation carries only the filters supported by each destination. Detail/back links preserve the source list scope or the selected balance profile, while source-shift links identify the relevant attendance record. Empty combinations offer reset links without requiring JavaScript.

Ordered workflow steps distinguish completed, active, waiting, rejected and discontinued stages through text as well as markers. Show the example active-stage deadline separately from age since submission; age is not time spent waiting at each stage. Rejected or withdrawn requests have no active approver. These are read-only fixtures without submission, approval or notification actions.

Balance views keep credit, final debit, active reservation and reservation release distinct. Quantities use integer half-day units, formatted as days for readers. The displayed equation is credit minus debit minus active reservation; releasing a reservation does not create usage. The 12-day starting credit is synthetic, not a legal entitlement or operational leave policy. Ledger references link to request details and back to the same selected profile.

The availability calendar receives only roster/approved availability, not request payloads, private reasons or attachments. Labels are Terjadwal, Tidak tersedia and Libur; daily shift counts are not staffing requirements or proof of clock activity. Approved leave dates share one fixture source with attendance, while pending requests do not alter final availability. No approval, balance transaction or raw-event adjustment runs from these pages. Surface details remain in `docs/requests-surface.md`.

### Contracts, letters and career changes

Administrasi inherits native GET registers, labeled focusable table-scroll regions, status labels and read-only detail fields. Search, status, current-profile branch and employee filters persist through detail and back links. Moving between record kinds carries only branch and employee because search and status have different meanings. Branch filtering follows today's profile, not a document snapshot; label that distinction explicitly. Empty results retain reset links.

Letter previews present immutable version-specific HTML snapshots, with a prominent CONTOH · DATA SINTETIS stamp and a separate draft/unissued label where relevant. Retain the original name, assignment, branch, period, template version and number instead of substituting current-profile values. Original and reissued versions have reciprocal references and different example numbers; drafts have no final number. These are not signed, issued or downloadable documents.

Active-contract details illustrate reminder dates at 60, 30, 14 and 7 days before period end, labeled relative to the fixed snapshot. Archived, scheduled and review records retain their own states. No reminders are sent and no contract or account changes occur. Career comparisons use semantic before/after columns, clearly identifying proposed, scheduled and already-effective values. Future assignment values must not appear as today's profile. Related-version and current-profile links provide context without implying an effective-date engine or operational version transaction. Surface details remain in `docs/employment-surface.md`.

### Lifecycle checklists and asset custody

Onboarding and offboarding reuse native GET registers, labeled focusable table regions, read-only definition lists and existing status treatments. Detail/back links retain their register filters; cross-kind navigation carries only branch and employee. Lifecycle branch filters use the current profile, while asset branch filters use the inventory location. Case-to-asset links carry the related employee. Empty results retain a reset destination.

Checklist templates belong to a specific employment period. Keep owner, due date, required/optional label, dependency, status and synthetic evidence reference visible as text. Dependencies explain blocked tasks without presenting completion controls. Joining date, last working day, employment end and scenario access timing remain distinct. Open asset obligations do not imply active account access; a former employee can have revoked access and an outstanding return. Rehire checklists preserve the previous period's clearance archive separately. Evidence is text only, with no attachment acceptance, account activation, session revocation or reminder action.

Asset details distinguish a reserved recipient from an active holder, condition from custody, and returnable equipment from consumables. An ordered custody history shows handover, return, due date and evidence; the fixture contains at most one active holder per asset and preserves earlier holders. This does not establish operational conflict prevention. Consumables have a recorded recipient without entering a borrowing or return queue. Missing or damaged equipment can have a separate settlement-review case; its status neither assigns liability nor triggers salary deductions. Surface details remain in `docs/lifecycle-surface.md`.

### Payroll, payslip previews and reimbursement

Keuangan inherits native GET registers, labeled focusable table-scroll regions and read-only detail fields. Register filters persist through detail and back links; switching payroll and reimbursement drops filters with different meanings. Monetary values are synthetic integer IDR, formatted with the existing tabular-number typography. No rates, statutory calculations or payment actions are implied.

Present calculation status and payment status as separate labeled facts. A finalized batch can still have failed or partial payment. Preserve period-specific payee, assignment, component and source-version snapshots rather than replacing them with current master values; later adjustments remain separate. Show blockers, warnings and notes explicitly, including negative net values. Changed source versions invalidate earlier review/approval context. Before release, native disclosures expose working components clearly labeled as calculations rather than payslips.

Payslip previews are HTML only and require a finalized batch, a release date reached at the fixed snapshot, and membership in that batch. These fixture gates are not production owner authorization or private document storage. A slip explains calculation components and references, not proof of transfer. Settlement timelines separately identify successful and failed records with synthetic reference, date, actor and amount.

Each reimbursement claim has one settlement channel: payroll or a separate reimbursement batch. Keep original claim amount, successful amount and remaining portion distinct. Only successful settlements count as paid; failures do not reduce the original amount, and partial success remains partial. Rejected or withdrawn unpaid portions are not approved liabilities. Similar evidence/date/amount produces a review warning, not a fraud finding. No transfer, export, duplicate prevention or operational settlement runs from these pages. Surface details remain in `docs/finance-surface.md`.

### Attendance reports and period-close snapshots

Reports inherit native weekly GET filters for branch, current-profile department and employee, with reset links and labeled focusable horizontal table regions. Source-shift drilldowns preserve week, branch, department and employee through detail and back links. Hadir selesai means PRESENT with a complete accepted clock interval; late and early-departure counts are subsets of that group, not additional attendances. IN_PROGRESS remains separate, and total roster rows include days off rather than representing days worked. Rejected clock attempts do not establish presence.

Period registers label OPEN, REVIEW and CLOSED separately and expose source, reviewed, roster and policy versions before readiness checks. Version mismatches, blockers and warnings are written explicitly. The open 12–25 October fixture offers two distinctly labeled source-week links for 12–18 and 19–25 October. These examples neither perform closure nor establish a complete operational validator.

Closed views preserve historical identity, assignment and source-version snapshots, including employees whose current employment has ended. The September archive covers only 28–30 September, not the full month, and its text references do not open raw September events. The fixture SHA-256 describes example data, not a production lock, concurrency check or transaction guarantee. After final payroll, corrections require a separately linked adjustment instead of overwriting the old snapshot. Planned-versus-verified overtime examples explain possible inputs; durations, approvals and plans are not payment calculations. Surface details remain in `docs/reports-surface.md`.

### Target settings, audit examples and import previews

These administration surfaces inherit native GET filters, status text, definition lists, native disclosures and labeled focusable table regions. Same-register links and back navigation preserve their applicable query filters; switching kinds drops search/status values whose meanings differ. Global navigation and the wrapping header remain unchanged. Keep the current UI-preview scope explicit; project completion and domain acceptance are tracked separately in `docs/PROJECT_STATUS.md`.

Settings show nine planned roles with target mandates and boundaries, not active permissions or editable configuration. Server default-deny, no self-approval, separate payroll actors, MFA and delegation remain backend requirements. Do not expose environment values, credentials or secret settings. The synthetic persona is not a login.

The six audit examples identify actor, time, outcome, before/after context, reason and correlation through readable fields and native detail disclosures. They are fixture stories rather than a live logger, append-only archive or real user activity. Related-record links add context without exposing sensitive payloads, bank details or medical reasons.

Import previews use four fixed batches, with mapping fields and row numbers followed by the affected column, error reason and concrete remedy. A business error rejects the whole illustrative batch; even valid rows remain unapplied. Formula-like values are displayed as text. Matching payload/type/hash examples link a retry to its original batch without duplicate application in the scenario. Clearly separate illustrative applied-row counts from actual UI writes, which remain zero. No upload, CSV/XLSX parser, staging transaction or commit exists; fixture hashes do not establish backend atomicity or idempotency. Surface details remain in `docs/administration-surface.md`.

### Notifications and cross-module search

Workspace utilities inherit native GET filters, visible text states, existing panels and focus styles. Notification list/detail/back links preserve applicable query filters. Read/unread/archive state, email delivery state and source business status remain independent: failed email does not reject a request, and opening a GET detail does not mark a message read or complete its source task. Use HR-facing language for delivery, attempts and the next example schedule. Fixed quiet hours (21.00–09.00 WIB) and digest time (09.00 WIB) are read-only illustrations, with no save, retry, send or archive actions.

Search spans ten fixture categories through selected public-facing summaries. Exclude amounts, payslip components, private reasons, letter bodies, employee email, evidence and audit payloads from the result projection. An initial state explains the minimum two-character query; empty results offer an all-category search that preserves the query. Results are an ordered text list with separators rather than repeated cards.

Result links use fixed source routes and parameters. Search text is not forwarded as an unrelated local filter; browser Back returns to the search URL. Source links add context without implying production indexing, role-based result authorization or access logging. Notification counts and delivery histories are synthetic rather than live services. Surface details remain in `docs/workspace-surface.md`; project status remains a UI preview rather than completed backend behavior.

### Static catalog, role homes and form variants

The catalog maps core PRD screen families to existing list/detail pages, seven role-home variants, 48 form specifications with initial/validation-failed variants, and twelve common states. Employee, Manager, HR, Finance, IT/Assets, Auditor and Platform homes group the nine PRD roles: HR Officer/Admin share HR, and Payroll Officer/Finance Approver share Finance. Optional persona navigation is a preview composition, not authentication or authorization. Three cross-module review routes connect visual examples without forming an end-to-end transaction.

Each form declares its static boundary before a disabled fieldset. Labels, native input types, selects, textareas and error associations retain the shared visual system. Integer numeric fields use native `number` with `step="1"`. Invalid variants identify the first field and preserve other example values. No form names or submit endpoints accept data; password fields stay empty and file fields do not accept files. Disabled action labels reflect their purpose, and account previews omit draft-saving. Account screens use a compact shell without HR navigation and never accept credentials.

### Common static states

The twelve examples cover empty, nonanimated loading, error, forbidden, stale, queued, success confirmation, offline, device-permission denial, expired session/link, not found, and scenario-reset confirmation. Error/offline retained context derives from the linked form specification: correction context stays tied to its source shift, while leave context retains its own dates. Keep source/version distinctions visible instead of substituting unrelated example inputs.

Forbidden, expired and not-found examples withhold record payloads. Stale state identifies V1 versus V2 before further review. The loading skeleton is static and never implies an active background request. Scenario-reset confirmation identifies the allowed example scope and keeps its control disabled; success and queued examples do not prove persistence, export or processing. No login, uploads, device access, reset, payment or other mutation runs from these pages.

The core static implementation inventory is complete for this stage; browser QA remains incomplete. A direct in-app browser attempt returned “Browser is not available: iab”; there are no screenshots or visual/accessibility approval. See `docs/static-completion-surface.md`, `docs/STATIC_SCREENS.md` and `docs/PROJECT_STATUS.md` for coverage and the distinction from backend/domain completion.

### Focus and motion

General keyboard focus uses a (3px) plum outline with (4px) offset, with the search-field exception above. The disclosure indicator rotates over (160ms ease-out). Reduced-motion preference disables transitions, animation and smooth scrolling. These source provisions do not establish accessibility certification.

## Do's and Don'ts

### Do:

- Do use plum for action and selected states and retain readable text with semantic status colors.
- Do preserve the visible navigation destinations and align the header with the main container.
- Do keep contextual task details beside their originating row and preserve active filters.
- Do retain visible focus, native controls, and useful content without JavaScript.
- Do label synthetic data and unavailable operations clearly.

### Don't:

- Don't restore the superseded sage rail or add a hamburger for the current destinations.
- Don't add gradients, decorative photos, repeated statistic cards, or entrance animations to this direction.
- Don't treat source inspection or a passing build as visual approval or accessibility certification.
