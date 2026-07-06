# ZYMARG Community Request Board -- Latest Release

**Latest version:** `v2.0.3`
**Released:** 2025-07-06
**Branch:** `main`

## Download

[**zymarg-community-board-v2.0.3.zip**](https://github.com/Aspeyash/Community-board/raw/refs/heads/main/zymarg-community-board-v2.0.3.zip)

> Install in WordPress: **Plugins > Add New > Upload Plugin** > choose the zip above > **Install Now** > **Activate**.

## What's new in v2.0.3

- **Unified branded header** - the gradient header with Discovery Spark now appears on ALL admin pages (Dashboard, All Requests CPT list table, and Settings), not just the Dashboard page.
- **Integrated Discovery Spark** - the spark SVG is now embedded inside the gradient header (to the left of the title text) instead of being a separate panel below it. Layout: [Spark] ZYMARG Community Board [v2.0.3 badge].
- **Admin CSS on CPT screens** - `zcrb-admin.css` now loads on the CPT list table (`edit.php?post_type=zcrb_request`) and single post edit screens for consistent branding.

## Recent versions

| Version | Date       | Commit    | Highlights |
|---------|------------|-----------|------------|
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
