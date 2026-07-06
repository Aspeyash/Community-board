# ZYMARG Community Request Board -- Latest Release

**Latest version:** `v2.3.0`
**Released:** 2025-07-06
**Branch:** `main`

## Download

[**zymarg-community-board-v2.3.0.zip**](https://github.com/Aspeyash/Community-board/raw/refs/heads/main/zymarg-community-board-v2.3.0.zip)

> Install in WordPress: **Plugins > Add New > Upload Plugin** > choose the zip above > **Install Now** > **Activate**.

## What's new in v2.3.0

Full-featured **All Requests** management surface — total parity with the WordPress CPT list table, built into the SPA hub. Admins never need to leave the branded view.

- **Status tab strip with counts** — `All (N) | Approved (N) | Pending (N) | In Progress (N) | Fulfilled (N) | Trash (N)`. Server-side filtering keeps pagination + counts accurate. On mobile the tabs collapse into a horizontally-scrollable strip.
- **Sortable table on desktop, card fallback on mobile** — `>= 768px` shows a proper `<table>` with columns Checkbox / Ref / Title / Submitter / Status / Upvotes / Date / Actions. Sortable columns toggle asc/desc. Row hover reveals action buttons (WP-core-like). Below 768px the same rows render as full-width cards with always-visible actions.
- **Bulk actions** — checkbox per row + "select all" in the table header. Dropdown offers Approve / Reject / Move to Trash / Delete Permanently, plus Restore / Delete Permanently in the Trash tab. Nonce-protected POST to `admin-post.php`, redirect back with a "X requests approved." notice. Delete Permanently double-confirms.
- **Trash tab** — full trash workflow inside the SPA. Restore uses `wp_untrash_post()`; Delete Permanently uses the retention-safe delete pipeline.
- **"Add New" button** — prominent action next to the panel title opens the standard WP editor at `post-new.php?post_type=zcrb_request`.
- **Per-page selector** — screen-options-style "Show [20|50|100|All] per page" dropdown; changing it reloads with `?per_page=N`. Tabs, pagination and the bulk-form all preserve the current per-page choice.
- **Empty state per tab** — friendly copy per tab ("No approved requests.", "Trash is empty.", etc). A separate "No matching requests." line replaces rows when live keyword search filters everything out.
- **Row-level quick actions bounce back to the SPA** — Approve / Reject / Delete / Restore links now pass `zcrb_return=hub` so the redirect lands on the hub with the previous tab preserved.
- **Fully responsive down to 320px** — panel padding, tab strip, bulk-action bar, per-page dropdown, and card actions all reshape at `< 768px`, `<= 480px`, and `<= 360px`. Buttons wrap, tabs scroll, cards stack, action rows wrap — no horizontal overflow at any tested viewport width.
- **"Advanced list view" escape hatch removed** — the link to `edit.php?post_type=zcrb_request` is gone; the SPA now covers every workflow.

## Recent versions

| Version | Date       | Commit    | Highlights |
|---------|------------|-----------|------------|
| v2.3.0  | 2025-07-06 | `main`    | Full-featured All Requests view — status tabs with counts, bulk actions, sortable table, Trash tab, per-page selector, mobile-responsive down to 320px |
| v2.2.1  | 2025-07-06 | `main`    | WP sidebar clicks use SPA view switching, All Requests link fixed |
| v2.2.0  | 2025-07-06 | `main`    | Full SPA admin: sidebar + main-panel layout, client-side view switching (no AJAX/reloads), custom All Requests list inside the hub |
| v2.1.2  | 2025-07-06 | `main`    | Submenu order fix (Dashboard first) + per-section header titles (Dashboard/All Requests/Settings) instead of the same "ZYMARG Community Board" on every page |
| v2.1.1  | 2025-07-06 | `main`    | Settings page fixes: white Spark backdrop, no duplicate heading, fixed redirects, inline toast, unified container width |
| v2.1.0  | 2025-07-06 | `main`    | Full AJAX save on the Settings page - no page reload, toast notification |
| v2.0.7  | 2025-07-06 | `main`    | Remove duplicate "All Requests" submenu entry |
| v2.0.6  | 2025-07-06 | `main`    | Remove duplicate admin menu - CPT nested under hub |
| v2.0.5  | 2025-07-06 | `main`    | Discovery Spark CSS copied verbatim from canonical Theme Builder source, SVG fill attributes removed |
| v2.0.4  | 2025-07-06 | `main`    | Discovery Spark animation - sequential visible pulse matching canonical, white background |
| v2.0.3  | 2025-07-06 | `main`    | Unified branded header across all admin sections, integrated Discovery Spark in header |
| v2.0.2  | 2025-07-06 | `main`    | Canonical Discovery Spark, admin branding, pre_get_posts fix, vendor response permissions |
| v2.0.1  | 2025-07-06 | `main`    | ZYMARG unified brand design alignment: 18px radius, two-layer shadow, purple hover states, Discovery Spark empty state, deep purple headings |
| v2.0.0  | 2025-07-06 | `main`    | Major release: status lifecycle, vendor responses, upvotes, notifications, search/filter, duplicate detection, bulk approve, branded admin hub, empty states, image count setting |
| v1.4.4  | 2026-06-27 | `main`    | Settings defaults to brand palette + version constant from header |
| v1.4.3  | 2026-06-27 | `95c4e9b` | First brand-alignment pass (CSS + admin link) |
| v1.4.2  | 2026-06-26 | `d2043af` | Local Cabinet Grotesk + Inter, Google Fonts CDN disabled |
| v1.4.1  | 2026-06-26 | `950efe0` | Removed dead "All Requests" badge |
| v1.4.0  | 2026-06-26 | `56b2789` | Material 3 redesign + customizable typography |
| v1.3.0  | -          | `2816b33` | Configurable data retention + visible privacy notice |
| v1.2.0  | -          | `dd350ee` | Customizable Settings page + GitHub Releases auto-updater |

## How to find the latest version (4 independent pointers)

1. **This file** - [`LATEST.md`](./LATEST.md) at the repo root.
2. **`VERSION`** - one-line plain-text file at the repo root for scripts/CI: [`VERSION`](./VERSION).
3. **Plugin header** - `Version:` line in [`zymarg-community-board/zymarg-community-board.php`](./zymarg-community-board/zymarg-community-board.php) (also the runtime source of truth for `ZCRB_VERSION` via `get_file_data()`).
4. **GitHub Releases** - once a tagged release is published, [`/releases/latest`](https://github.com/Aspeyash/Community-board/releases/latest) is a permanent URL that always points at the newest.

All four are updated in the same commit on every release.

## License

GPL-2.0-or-later. (c) ZYMARG.
