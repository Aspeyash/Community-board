# ZYMARG Community Request Board -- Latest Release

**Latest version:** `v2.1.2`
**Released:** 2025-07-06
**Branch:** `main`

## Download

[**zymarg-community-board-v2.1.2.zip**](https://github.com/Aspeyash/Community-board/raw/refs/heads/main/zymarg-community-board-v2.1.2.zip)

> Install in WordPress: **Plugins > Add New > Upload Plugin** > choose the zip above > **Install Now** > **Activate**.

## What's new in v2.1.2

Sidebar submenu order fix + branded header now shows the current section name:

- **Submenu order corrected** — under Community Board the sidebar was rendering **All Requests → Dashboard → Settings**. WordPress' `_add_post_type_submenus()` runs on `admin_menu` priority 9 (before the hub's own `register_menu()` at priority 10), so the CPT's auto-added "All Requests" entry ended up first. A new `reorder_submenu()` pass hooked at priority 999 now runs last and sorts the submenu into the canonical **Dashboard → All Requests → Settings** order.
- **Per-section header titles** — every admin screen used to show the exact same "ZYMARG Community Board" title in the branded gradient header (Dashboard, All Requests, Settings — all identical). The header now follows the canonical ZYMARG Theme Builder pattern: the big bold title is the SECTION NAME (Dashboard / All Requests / Edit Request / Settings), and "ZYMARG Community Board" becomes a small uppercase kicker/eyebrow above it. `render_branded_header()` takes an optional `$section_title` argument; the CPT-screen auto-injector auto-detects the title from the current screen id.

## Recent versions

| Version | Date       | Commit    | Highlights |
|---------|------------|-----------|------------|
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
