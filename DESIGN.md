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

Information has room to breathe while repeated tasks remain easy to scan. Quiet local states and native disclosures reveal context where it is needed. The current expression is a read-only dashboard with synthetic fixtures; the visual system does not imply operational HR capabilities.

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

**The Available Destination Rule.** Size navigation around destinations that exist, keeping this three-link header directly visible on desktop and mobile.

## Layout

Header and main share a centered maximum width of (1264px), including horizontal padding of (44px). The desktop header is at least (88px) high, with brand, three horizontal destination links, and persona. There is no reserved side rail or collapsed navigation control.

Main padding is (26px 44px 32px). The dashboard places attendance and agenda in a top grid with a (1.8:1) column ratio and (24px) gap; the task panel spans the full width below with a (28px) separation. This page-specific order is recorded in `docs/dashboard-surface.md`, not a mandatory layout for every future screen.

Implemented responsive rules, pending browser verification:

- At (1100px) and below, horizontal page padding becomes (30px), the overview ratio becomes (1.5:1), and its gap becomes (20px). The attendance legend uses two columns.
- At (760px) and below, the header wraps: brand and compact persona occupy the first line, and all three navigation links remain visible on a second line. Links are at least (48px) high. Main uses (22px) horizontal padding; the overview becomes one column, the attendance legend uses three columns, and search and branch selection stack.
- At (400px) and below, page padding becomes (16px), the attendance legend uses two columns, row avatars disappear, and task columns become a flexible text column plus (66px) deadline and (12px) indicator columns.

The 360px and 1440px acceptance sizes remain browser verification targets. Browser inventory was empty; this documentation records CSS/JS intent and source evidence, not approved rendered appearance.

## Elevation & Depth

The interface is flat at rest and defines no box-shadow tokens. White panels, pale local state fills, fine borders and deliberate spacing provide separation. Focus outlines identify interaction rather than simulated elevation.

**The Quiet Surface Rule.** Use white surfaces, fine borders, and spacing to establish hierarchy without decorative shadows.

## Shapes

Panels retain gently rounded corners (`panel`). Fields and the secondary button share the tighter control radius; category filters and badges use their smaller radii. Borders are generally (1px). Avatars and status dots are circles. Icons use inline outline SVGs with current text color and rounded strokes; the brand is CSS geometry. No raster assets ship.

## Components

### Navigation

The three real section links share the header. The current link has plum text, a (2px) bottom border, and stronger weight; hover changes text color. Counts use selected-lavender fills. JavaScript updates `aria-current` and moves focus to the destination. Navigation remains fully visible without JavaScript and at mobile widths.

### Buttons and category filters

The bordered secondary button clears filters with a minimum height of (44px). Hover uses the selected fill. Category filters use rounded, filled selected states rather than underline tabs; minimum height is (40px) on desktop and (44px) on mobile. They retain `aria-pressed` group semantics. No filled primary action is present in the prototype. Disabled buttons use opacity (.55) and a not-allowed cursor.

### Search and native controls

Search combines an icon and (42px) high input. Its enclosing field gets a (2px) focus outline and (2px) offset. The native branch selector has a minimum height of (44px). The labeled urgency checkbox has a (40px) minimum-height label on desktop and (44px) on mobile. Search, category, branch and urgency combine locally; a polite live count communicates results. Reset restores all filters and focuses search.

### Panels, attendance and agenda

White bordered panels use the shared rounded shape. Desktop panel insets are approximately (24–26px), reducing at narrow breakpoints. The attendance bar is (12px) high with (4px) gaps and a textual legend. Agenda entries pair a clear time column with contextual copy and fine separators. Status labels remain distinct from available actions.

### Inline task context

Task rows use native `details` and `summary`. Desktop summary padding is (20px 26px), and the columns are flexible task text plus (130px) deadline and (64px) action. Hover and open fills remain subtle. Context appears directly below with ownership and next-step information. Filtering closes hidden disclosures; without JavaScript all fixture rows and native disclosures still work, while unavailable filters stay hidden with an explanatory notice.

### Focus and motion

General keyboard focus uses a (3px) plum outline with (4px) offset, with the search-field exception above. The disclosure indicator rotates over (160ms ease-out). Reduced-motion preference disables transitions, animation and smooth scrolling. These source provisions do not establish accessibility certification.

## Do's and Don'ts

### Do:

- Do use plum for action and selected states and retain readable text with semantic status colors.
- Do preserve the three visible navigation destinations and align the header with the main container.
- Do keep contextual task details beside their originating row and preserve active filters.
- Do retain visible focus, native controls, and useful content without JavaScript.
- Do label synthetic data and unavailable operations clearly.

### Don't:

- Don't restore the superseded sage rail or add a hamburger for the three current destinations.
- Don't add gradients, decorative photos, repeated statistic cards, or entrance animations to this direction.
- Don't treat source inspection or a passing build as visual approval or accessibility certification.
