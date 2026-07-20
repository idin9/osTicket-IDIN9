# Modern UX/UI Upgrade Plan — osTicket-IDIN9

This plan modernizes the client portal and staff control panel (SCP) against four
pillars: **Core Cognitive Laws**, **Visual Hierarchy & Layout**, and
**Accessibility (WCAG 2.2)**.

Single source of truth for all visual values: [`/DESIGN.md`](../../DESIGN.md)
(tokens live in [`/css/tokens.css`](../../css/tokens.css)).

---

## Guiding principles → where they are applied

| Principle | Applied in |
|---|---|
| **Jakob's Law** — behave like platforms users already know | Phase 2 (client portal patterns: card lists, top-nav, search-first landing), Phase 3 (SCP queues like modern inbox/issue trackers) |
| **Fitts's Law** — big, near targets | All phases: `--target-min: 44px` touch targets (token, Phase 1); primary actions placed adjacent to their context (Phases 2–3) |
| **Hick's Law** — fewer choices | Phase 2: landing page reduced to 2 primary actions; nav ≤ 5 items. Phase 3: queue actions collapsed into one overflow menu |
| **Miller's Law** — chunking (7 ± 2) | Phase 2: open-ticket form grouped into ≤ 5 labeled sections; Phase 3: ticket view split into summary / thread / sidebar chunks |
| **F & Z patterns** — critical info top-left | Phase 2: landing Z-layout (logo → sign-in / search → CTA); Phase 3: queue title + primary action on the top scan line |
| **60-30-10 color** | Phase 1 tokens: 60% neutral surfaces, 30% slate structure, 10% blue accent reserved for CTA/interactive |
| **8pt grid** | Phase 1: `--space-*` scale; Phases 2–3: all padding/margins migrate to tokens |
| **1.250 Major Third type scale** | Phase 1: `--text-*` scale; Phases 2–3: headings/body migrate to scale |
| **WCAG 2.2** | Phase 1: AA-verified color tokens + focus ring tokens; Phase 4: full hardening pass |

---

## Phase 1 — Design Token Foundation ✅

**Deliverables**
- `/css/tokens.css` — unified custom properties:
  - Type scale 1.250 Major Third (`--text-xs` … `--text-4xl`), rem-based
  - 8pt spacing scale (`--space-1` … `--space-16`)
  - 60-30-10 palette: neutral surfaces (60), slate structure (30), one accent (10)
  - Semantic colors with **verified AA contrast**
  - Focus-ring tokens (WCAG 2.2 Focus Appearance), `--target-min: 44px` (Fitts)
  - Dark-mode values via `prefers-color-scheme` + `[data-theme]` override
  - `prefers-reduced-motion` support
- Back-compat: `/scp/css/modern/tokens.css` → `@import` shim
- Wiring: client header loads tokens before `theme.css`; staff modern UI loads shared file

---

## Phase 2 — Client Portal Modernization ✅

**Goal:** responsive, predictable, scannable public portal.

**Scope delivered**
1. **Layout & grid** — fluid `#container` (95%, max 1200px), 8pt spacing tokens, 3 breakpoints
   (480/768/1024px); tables → stacked cards at ≤768px via `data-label` pattern.
2. **Landing page (F/Z + Hick's)** — Z-pattern flex layout, `.front-page-button` card with
   full-width 44px accent CTAs; search form centered.
3. **Forms (Miller + Fitts)** — `#ticketForm` sections chunked as bordered `tbody` groups;
   all inputs 44px min-height; error states; responsive button bar.
4. **Components (Jakob)** — legacy gradient buttons/pills replaced with token-driven components;
   links underlined by default.
5. **Typography** — headings on 1.250 Major Third scale; body 16px/1.5; max line ~70ch.
6. All LESS files rewritten and recompiled via `lessc`.

**Files changed:** `assets/default/less/*` (7 files), `assets/default/css/theme.css` (recompiled)

---

## Phase 3 — Staff Panel Deep Modernization ✅

**Scope delivered**
1. **Ticket queue** — `.queue-subject` emphasis, `.overflow-menu` for action collapse (Hick's),
   `.status-badge` with icon support, `.pagination` modern styles.
2. **Ticket view (F-pattern)** — `.ticket-summary` header strip (number, status, assignee, SLA on
   top scan line); `.ticket-properties` grid with `.ticket-property-card` for properties.
3. **Navigation** — `.nav-toggle` hamburger for mobile; `<nav aria-label="Main navigation">`
   wraps `#nav`; mobile dropdown with `position: absolute`; 5–7 top-level items (Miller).
4. **Dialogs & forms** — `.dialog` / `.ui-dialog` keyframe entrance animation; `#overlay`
   fade-in; focus-visible ring on dialog focus.
5. **Dashboard** — `.dashboard-chart .chart-color-*` semantic color tokens; stat card hover
   lift effect.
6. `.skip-link` on both interfaces; `aria-current="page"` on active staff nav items.

**Files changed:** `scp/css/modern/scp.css` (+~400 lines), `include/staff/templates/navigation.tmpl.php`

---

## Phase 4 — Accessibility Hardening (WCAG 2.2) ✅

**Scope delivered**
1. **Landmarks & structure** — `<header>` / `<nav>` / `<main>` / `<footer>` on both client
   and staff templates; one `<h1>` per page (existing); skip-to-content link before `#container`.
2. **Keyboard** — `:focus-visible` ring from tokens.css applied globally; `tabindex > 0`
   audit deferred to JS layer (none added); focus-trap CSS pattern for dialogs.
3. **Screen readers** — `role="alert"` on `#msg_error` / `#msg_warning`; `role="status"` on
   `#msg_notice`; `aria-current="page"` on active staff nav links; `aria-label` on nav
   elements and nav-toggle button; `aria-expanded` on toggle.
4. **Color & motion** — status badges use icon + text, never color alone; icon-only buttons
   get `aria-label`; `prefers-reduced-motion` honored (tokens.css + scp.css).
5. **Forms** — `.sr-only` / `.visually-hidden` classes available; `aria-describedby`
   pattern ready for template-level error binding (deferred to per-PHP-file audit).

**Files changed:** `include/{staff,client}/header.inc.php`, `include/{staff,client}/footer.inc.php`,
`include/staff/templates/navigation.tmpl.php`, `scp/css/modern/scp.css`

---

## Phase 5 — Motion, States & Verification ✅

**Scope delivered**
- **Skeleton loading** — `.skeleton`, `.skeleton-text`, `.skeleton-heading`,
  `.skeleton-avatar`, `.skeleton-button`, `.skeleton-row` tokens and keyframe shimmer
  (Phase 1 tokens + Phase 3 CSS).
- **Empty states** — `.empty-state`, `.empty-state-icon`, `.empty-state-title`,
  `.empty-state-description` with one clear next action (Hick's).
- **Micro-interactions** — `--transition: 150ms ease`; button press `scale(0.98)`;
  card hover `translateY(-1px)` + shadow lift; row hover shadow.
- **Reduced motion** — `prefers-reduced-motion: reduce` disables all animations,
  transitions, and transforms in both token layer and scp.css.
- **Content fade-in** — `@keyframes content-fade-in` on `#content` entrance.
- **Dialog entrance** — `@keyframes dialog-enter` (slide + fade).

**Files changed:** Phase 3 scp.css additions cover these; `css/tokens.css` already has
motion tokens.

---

## Guardrails (all phases)

- **Rollback:** staff modern UI stays opt-in (URL > cookie > staff pref > system
  config). Client portal changes are CSS/LESS-only; classic UI still functional.
- **No JS framework introduction** — vendored jQuery stack stays; enhancements are progressive.
- **Minimal template edits** — HTML changes only for landmarks and a11y (Phase 4).
- **i18n/RTL preserved** — all layout changes use flex/grid with logical properties;
  `.rtl` overrides kept from originals.
- Contrast reference values live in `DESIGN.md`; new colors added with their ratios.
