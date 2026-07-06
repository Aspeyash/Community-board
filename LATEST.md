# ZYMARG Community Request Board -- Latest Release

**Latest version:** `v2.2.0`
**Released:** 2025-07-06
**Branch:** `main`

## Download

[**zymarg-community-board-v2.2.0.zip**](https://github.com/Aspeyash/Community-board/raw/refs/heads/main/zymarg-community-board-v2.2.0.zip)

> Install in WordPress: **Plugins > Add New > Upload Plugin** > choose the zip above > **Install Now** > **Activate**.

## What's new in v2.2.0

Full SPA admin — no page reloads between sections, matching the canonical ZYMARG admin design language.

- **Sidebar + main-panel layout** — the horizontal tab strip is replaced by a proper sidebar navigation (Discovery Spark brand mark + three nav buttons: Dashboard, All Requests, Settings) and a topbar that shows the current section name and plugin version badge. Design mirrors ZYMARG Backups and the Theme Builder admin so all three plugins now share one visual language.
- **Client-side view switching** — all three sections are rendered server-side in a single page load. Clicking a nav button just toggles which panel is visible. No AJAX fetch, no re-render, no re-init. The URL updates via `pushState` so refresh, bookmark, and browser back/forward all work.
- **Custom "All Requests" list inside the hub** — previously clicking All Requests navigated out to the WordPress CPT list table (full reload, different UI). Requests now render inline as branded cards with Ref number, colored status badge, submitter name, upvote count, date, message excerpt, and inline action buttons (View, Edit, Approve, Reject, Delete). Live keyword + status filtering happens entirely client-side. A link to the classic WP list view is still there for power-user workflows.
- **Settings hosted in the SPA** — `admin.php?page=zcrb-settings` still works for existing bookmarks and renders the same shell with the Settings view active. The AJAX save from v2.1.0 is preserved (toast confirmation, no page reload).
- **Fixes the "every click reloads" bug** — the old tabs were plain `<a>` hrefs to `admin.php?page=...`, so every click was a full page load. Nav is now `<button>` elements handled entirely client-side.

## Recent versions

| Version | Date       | Commit    | Highlights |
|---------|------------|-----------|------------|
| v2.2.0  | 2025-07-06 | `main`    | Full SPA admin: sidebar + main-panel layout, client-side view switching (no AJAX/reloads), custom All Requests list inside the hub |
| v2.1.2  | 2025-07-06 | `main`    | Submenu order fix (Dashboard first) + per-section header titles (Dashboard/All Requests/Settings) instead of the same "ZYMARG Community Board" on every page |
| v2.1.1  | 2025-07-06 | `main`    | Settings page fixes: white Spark backdrop, no duplicate heading, fixed redirects, inline toast, unified container width |
| v2.1.0  | 2025-07-06 | `main`    | Full AJAX save on the Settings page — no page reload, toast notification |
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
