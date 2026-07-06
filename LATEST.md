# ZYMARG Community Request Board — Latest Release

**Latest version:** `v2.0.0`
**Released:** 2025-07-06
**Branch:** `main`

## Download

[**zymarg-community-board-v2.0.0.zip**](https://github.com/Aspeyash/Community-board/raw/refs/heads/main/zymarg-community-board-v2.0.0.zip)

> Install in WordPress: **Plugins → Add New → Upload Plugin** → choose the zip above → **Install Now** → **Activate**.

## What's new in v2.0.0

- **Non-destructive uninstall.** uninstall.php now only deletes data when `ZCRB_REMOVE_ALL_DATA` constant is defined. Safe by default.
- **function_exists() guard** on `zcrb_get_setting()` to prevent fatal errors.
- **Fixed github_repo default** to the correct repository name `Community-board`.
- **SEO optimization.** Eliminated duplicate WP_Query in JSON-LD generation; reuses the main query.
- **Status lifecycle.** New statuses: In Progress and Fulfilled. Requests follow Pending > Approved > In Progress > Fulfilled.
- **Vendor response system.** Vendors can respond to community requests directly from the single request page.
- **Upvote/priority system.** Logged-in users can upvote requests. Upvote count displayed on cards and single view.
- **Submitter notifications.** Email notifications sent to the submitter when their request is approved or when a vendor responds.
- **Search and filter** on the public board - search by keyword, filter by status.
- **Duplicate detection.** Live search-as-you-type in the submission form shows similar existing requests.
- **Bulk approve.** New bulk action in the admin list table to approve multiple pending requests at once.
- **Branded admin hub.** New top-level admin page with Discovery Spark animation, gradient header, version badge, and dashboard stats.
- **Purple admin sidebar.** Community Board menu text styled with ZYMARG brand purple (#9500A5).
- **Branded empty states.** Decorative SVG illustrations and personality copy when no requests exist.
- **Image upload count setting.** Admin can configure max images per submission (1-4).

## Recent versions

| Version | Date       | Commit    | Highlights |
|---------|------------|-----------|------------|
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
