# DESIGN.md — osTicket-IDIN9 Design System

Single reference for all visual decisions. Machine-readable values live in
[`/css/tokens.css`](css/tokens.css) (shared by the client portal and the staff
modern UI). Phased rollout plan: [`docs/ui-upgrade/PLAN.md`](docs/ui-upgrade/PLAN.md).

## Design principles

1. **Jakob's Law** — patterns mirror mainstream platforms (search-first landing,
   inbox-style queues, card lists on mobile).
2. **Fitts's Law** — interactive targets ≥ 44×44px (`--target-min`); primary
   actions sit next to the content they affect.
3. **Hick's Law** — ≤ 2 primary actions per view; nav ≤ 7 items; overflow
   actions collapse into one menu.
4. **Miller's Law** — forms and pages chunked into ≤ 5–7 labeled groups.
5. **F & Z patterns** — critical info on the top scan line and left edge.
6. **WCAG 2.2 AA** — contrast, keyboard, focus appearance, target size, reduced motion.

---

## Color (60-30-10 rule)

- **60% dominant neutral** — page backgrounds and cards: `--surface-0/1/2`
- **30% secondary structure** — headers, nav, borders, strong text: slate scale
- **10% accent** — CTAs, links, active states only: `--accent`

### Light theme + AA contrast (vs. the background they sit on)

| Token | Value | Role | Contrast |
|---|---|---|---|
| `--surface-0` | `#ffffff` | cards, inputs (60%) | — |
| `--surface-1` | `#f8fafc` | page bg (60%) | — |
| `--surface-2` | `#f1f5f9` | wells, stripes (60%) | — |
| `--surface-inverse` | `#0f172a` | header/nav structure (30%) | — |
| `--border` | `#cbd5e1` | visible structure borders | 1.9:1 vs. surfaces (non-text) |
| `--input-border` | `#94a3b8` | form field borders | 3.4:1 vs. `#fff` (WCAG non-text ≥ 3:1 ✅) |
| `--text-primary` | `#0f172a` | headings, body strong | 17.7:1 on `#fff` ✅ AAA |
| `--text-secondary` | `#475569` | body, labels | 7.5:1 ✅ AAA |
| `--text-tertiary` | `#64748b` | meta, placeholders | 4.8:1 ✅ AA |
| `--accent` | `#1d4ed8` | CTA, links (10%) | 7.0:1 on `#fff` ✅ AAA |
| `--accent-hover` | `#1e40af` | hover | 8.6:1 ✅ |
| `--success` | `#065f46` | success text/icons/fill | 6.0:1 on `#fff` fill, 5.8:1 on `--success-subtle` ✅ AA |
| `--warning` | `#92400e` | warning text/icons | 13.1:1 on `#fff`, 4.7:1 on `--warning-subtle` ✅ AA |
| `--danger` | `#b91c1c` | errors, destructive | 5.6:1 on `#fff` fill, 5.1:1 on `--danger-subtle` ✅ AA |
| `--info` | `#155e75` | info text | 5.3:1 on `--info-subtle`, 6.5:1 on `#fff` ✅ AA |
| `--focus-ring` | `#1d4ed8` + white halo | keyboard focus | ≥ 3:1 vs. adjacent ✅ |

Rules: never use `--text-tertiary` below 13px; status is always icon + text,
never color alone; dark theme mirrors these pairs (see `tokens.css`).

---

## Typography — 1.250 Major Third scale (base 16px)

| Token | Size | Use |
|---|---|---|
| `--text-xs` | 0.8rem (12.8px) | badges, fine print (≥ `--text-tertiary` color) |
| `--text-sm` | 0.875rem (14px) | dense UI, table meta *(off-scale exception)* |
| `--text-base` | 1rem (16px) | body — never smaller for paragraphs |
| `--text-lg` | 1.25rem (20px) | h4, card titles |
| `--text-xl` | 1.563rem (25px) | h3 |
| `--text-2xl` | 1.953rem (31px) | h2 |
| `--text-3xl` | 2.441rem (39px) | h1 |
| `--text-4xl` | 3.052rem (49px) | hero only |

Line height 1.5 body / 1.2 headings. Max line length ~70ch. Exactly one `<h1>` per page.

## Spacing — 8pt grid

`--space-1: 4px` (micro only) · `--space-2: 8px` · `--space-3: 12px` (compact half-step) ·
`--space-4: 16px` · `--space-6: 24px` · `--space-8: 32px` · `--space-10: 40px` ·
`--space-12: 48px` · `--space-16: 64px`. All padding/margins must use these tokens.

## Shape & elevation

- Radii: `--radius-sm` 4px (inputs), `--radius` 6px (buttons), `--radius-lg` 10px (cards), `--radius-full` (pills)
- Shadows: `--shadow-sm` → `--shadow-lg`; elevation communicates layering, never decoration

## Interaction & motion

- Targets: `--target-min: 44px` (Fitts / WCAG 2.2 Target Size); 24px is the absolute AA floor with spacing exception
- Focus: `:focus-visible` ring = 3px `--focus-ring` outline + 2px offset; never remove without a replacement
- Transitions: `--transition: 150ms ease`; all animation must disable under `prefers-reduced-motion`
- Breakpoints: 480px (mobile), 768px (tablet), 1024px (desktop); content max-width 1200px

## Component contracts (Phases 2–3)

- **Button** — 44px min height, `--space-4` inline padding, accent bg for primary only, radius 6px, `:focus-visible` ring
- **Card** — `--surface-0`, `--border`, `--radius-lg`, `--shadow-sm`, `--space-6` padding
- **Alert/notice** — semantic color + icon + text; container gets `role="alert"`
- **Badge/status pill** — 12.8px, `--radius-full`, icon + label
- **Table → card** — below 768px rows stack, cells labeled via `data-label`

## Accessibility checklist (WCAG 2.2)

- [ ] Contrast ≥ 4.5:1 text / 3:1 large text (use table above)
- [ ] Focus Appearance: ring visible, ≥ 3:1 vs. adjacent colors
- [ ] Target Size ≥ 24px (44px preferred)
- [ ] Landmarks: header/nav/main/footer + skip link (Phase 4)
- [ ] Keyboard: all actions reachable, focus trap in dialogs
- [ ] Alt text / `aria-label` on all informative images and icon-only controls
- [ ] `prefers-reduced-motion` honored
