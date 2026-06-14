---
name: Stat card standard component
description: Single canonical stat card component and accent variant system
---

**Standard component:** `.stat-card` (defined in `assets/theme.css` section 26).

**Child elements:**
- `.stat-card__label` — small uppercase label above the value
- `.stat-card__value` — large number/metric
- `.stat-card__change` — small text below (positive/negative)

**Accent variants** (border + subtle background tint):
- `.stat-card.is-danger`
- `.stat-card.is-warning`
- `.stat-card.is-success`
- `.stat-card.is-primary`

All four variants are defined globally in `theme.css` — do NOT redefine them per-page.

**Why:** Project previously had 5 incompatible stat card implementations (.crm-stat-card, .admin-stat-card, local .stat-card overrides, inline styles). Consolidated to one. Old `.stat-value`/`.stat-label` aliases still work inside `.stat-card` for backward compatibility.
