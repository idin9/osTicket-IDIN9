# Modern Theme Usage Guide

osTicket-IDIN9 ships with a modern responsive theme for both the **Staff Control
Panel** (SCP) and the **Client Portal**. The theme builds on a unified design
token system defined in [`/css/tokens.css`](../../css/tokens.css) and documented
in [`/DESIGN.md`](../../DESIGN.md).

---

## Staff Control Panel (SCP)

### Toggling the Modern UI

The modern staff UI is **opt-in** and can be toggled at four levels (priority
order):

1. **URL parameter** — append `?ui=modern` or `?ui=classic` to any SCP page.
   Sets a cookie that persists for 1 year.
2. **Cookie** — `ost_modern_ui=1` / `ost_modern_ui=0`.
3. **Per-agent preference** — each agent can set their preference under
   their Profile page. Options: *System Default*, *Modern*, *Classic*.
4. **System configuration** — Admin Panel → Settings → System → *Enable
   modern responsive interface with dark mode support*.

### What's included (Phase 3)

| Feature | Details |
|---|---|
| **Responsive layout** | Fluid 95% container, max 1200px; breaks down gracefully at 1024px/768px/480px |
| **Dark mode** | Automatic via `prefers-color-scheme`; override with `[data-theme="light"]` on `<html>` |
| **Ticket queues** | Inbox-style list with `.queue-subject` emphasis, `.status-badge` pills with icons, `.overflow-menu` for row actions, modern pagination |
| **Ticket view** | F-pattern `.ticket-summary` header strip (number/status/assignee/SLA), `.ticket-properties` card grid replaces infoTable |
| **Navigation** | Horizontal top nav on desktop; hamburger toggle + full-width dropdown on mobile; `aria-current="page"` for active section |
| **Dialogs** | Smooth entrance animation (`dialog-enter`), themed overlay overlay (`.dialog`, `.ui-dialog`, `.ui-widget-overlay`) |
| **Dashboard** | Stat cards on 8pt grid with semantic chart colors (`.dashboard-chart .chart-color-*`) |
| **Forms** | Token-driven inputs (44px min-height), focus rings, error states, inline validation |
| **Skeleton loading** | `.skeleton-*` classes with shimmer animation for async content |
| **Empty states** | `.empty-state-*` pattern with one clear next action |

### Customization

The modern theme is driven entirely by CSS custom properties in
[`tokens.css`](../../css/tokens.css). To customize colors, typography, or
spacing, override the tokens in your own stylesheet loaded after `tokens.css`:

```css
:root {
  --accent: #7c3aed;       /* Change accent to purple */
  --text-base: 0.9375rem;  /* Slightly smaller base text (15px) */
  --radius: 8px;           /* Rounder corners */
}
```

If you need deeper overrides, edit `scp/css/modern/scp.css`. The classic
`scp/css/scp.css` is unaffected and remains the safe fallback.

---

## Client Portal

The client portal theme is **always on** — it replaces the legacy fixed-width
layout with a fluid, responsive, token-driven design.

### What's included (Phase 2)

| Feature | Details |
|---|---|
| **Responsive layout** | Fluid 95% container, max 1200px; 3 breakpoints (480/768/1024px); no horizontal scroll at 360px |
| **Z-pattern landing** | Logo top-left → account info top-right → search center → two CTAs in `.front-page-button` card (Hick's: ≤ 2 actions) |
| **Touch targets** | All interactive elements ≥ 44×44px (Fitts's Law); `.button`, inputs, nav links |
| **Typography** | 1.250 Major Third scale (12.8px–49px) via `var(--text-*)`; body 16px/1.5; max line length ~70ch |
| **Forms** | Chunked into ≤ 5 bordered sections (Miller's Law); all inputs 44px min-height with focus rings; responsive CAPTCHA |
| **Chunked navigation** | ≤ 5 nav items; wraps on mobile |
| **Tables → cards** | `#ticketTable` collapses to stacked cards at ≤ 768px using `data-label` |
| **Components** | Token-driven buttons, badges, alerts, pagination; links underlined by default (Jakob's Law) |

### Source files

The client theme is built from LESS sources in `assets/default/less/` and
compiled to `assets/default/css/theme.css`.

```bash
lessc assets/default/less/theme.less > assets/default/css/theme.css
lessc assets/default/less/print.less > assets/default/css/print.css
```

Edit the `.less` files, then recompile. The token file `css/tokens.css` is
shared with the staff modern UI — changes to it affect both interfaces.

---

## Accessibility Notes (WCAG 2.2)

Both themes aim for WCAG 2.2 AA compliance:

- **Contrast**: all text/semantic tokens in `tokens.css` are ≥ 4.5:1 against
  their surface (`--text-tertiary` was fixed from `#94a3b8` 2.9:1 → `#64748b`
  4.8:1). Full table in `DESIGN.md`.
- **Focus**: `:focus-visible` rings are applied globally via `tokens.css`;
  3px width, 2px offset, accent color.
- **Landmarks**: `<header>`, `<nav>`, `<main>`, `<footer>` with skip-to-content
  links on every page.
- **Screen readers**: `role="alert"` on errors, `role="status"` on notices,
  `aria-current` on nav, `aria-label` on icon-only controls.
- **Motion**: all animations respect `prefers-reduced-motion`.
- **Forms**: every input has a programmatic `<label>`; errors use
  `color: var(--danger)` + descriptive text (not color alone).

---

## Rollback

- **Staff SCP**: toggle back to classic at any time via `?ui=classic`, agent
  profile, or admin setting.
- **Client portal**: remove or replace the `css/tokens.css` `<link>` in
  `include/client/header.inc.php` and restore the original compiled `theme.css`.
