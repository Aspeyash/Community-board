# ZYMARG Community Request Board -- Latest Release

**Latest version:** `v2.1.1`
**Released:** 2025-07-06
**Branch:** `main`

## Download

[**zymarg-community-board-v2.1.1.zip**](https://github.com/Aspeyash/Community-board/raw/refs/heads/main/zymarg-community-board-v2.1.1.zip)

> Install in WordPress: **Plugins > Add New > Upload Plugin** > choose the zip above > **Install Now** > **Activate**.

## What's new in v2.1.1

Settings page polish + a CRITICAL Discovery Spark visibility fix:

- **Discovery Spark visibility (brand-critical)** — the spark's purple paths (`#6833ea`) were invisible against the header's purple gradient. The spark now sits inside a small white rounded chip (12px radius, 8px padding, subtle shadow) so it's always visible. Permanent ZYMARG brand rule: **never** put a purple background behind the Discovery Spark.
- **Duplicate Settings-page heading removed** — the branded gradient header already shows "ZYMARG Community Board", so the extra `<h1>` beneath it was redundant. Description text refined.
- **Broken redirects fixed** — "Run cleanup now" and "Force re-check for updates" no longer send you to a 404. Both now redirect to `admin.php?page=zcrb-settings` (matches the new admin-menu location under the hub).
- **Save-Settings toast alignment** — toast now renders inline next to the Save Changes button instead of dropping to a new line.
- **Container width unified** — Settings page now shares the same 1200px centered container (`.zcrb-hub-wrap`) as the Dashboard hub.
- **Dead color-picker no-op removed** from the settings JS.

## Recent versions

| Version | Date       | Commit    | Highlights |
|---------|------------|-----------|------------|
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
