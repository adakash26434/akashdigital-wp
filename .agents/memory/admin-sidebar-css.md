---
name: Admin sidebar CSS approach
description: How the admin sidebar expand-on-hover works without JS or display:none
---

## Approach

Sidebar uses pure CSS `overflow: hidden` + `max-width` transition on text labels:

- `#admin-sidebar` → `width: var(--sb-w, 3.5rem)`, `overflow: hidden` always.
- `#admin-sidebar:hover` (desktop ≥768px) → `width: var(--sb-exp-w, 14rem)`.
- `.sb-label` → `max-width: 0; margin-left: 0; overflow: hidden; white-space: nowrap;` by default.
- `#admin-sidebar:hover .sb-label` → `max-width: 12rem; margin-left: 0.625rem;`.
- Mobile (`max-width: 767px`) → sidebar slides in via `translateX(-100%) → 0`, labels always visible.

**Why:** The previous approach used `display:none/inline-block` toggling which caused flash-of-invisible-text and required the broken negative-margin hack on the header (`margin-left: -2.25rem`). The max-width transition is smooth and CSS-only.

**How to apply:** All sidebar link text, group labels, chevrons, user info, and brand name use `.sb-label` or equivalent max-width pattern. Never use `display:none` for collapsing sidebar labels.

## Key CSS file
`assets/css/admin.css` — loaded in admin/portal context via `includes/head.php`.
