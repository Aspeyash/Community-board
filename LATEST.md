# ZYMARG Community Request Board — Latest Release

**Latest version:** `v1.4.4`
**Released:** 2026-06-27
**Branch:** `main`

## Download

[**zymarg-community-board-v1.4.4.zip**](https://github.com/Aspeyash/Community-board/raw/refs/heads/main/zymarg-community-board-v1.4.4.zip)

> Install in WordPress: **Plugins → Add New → Upload Plugin** → choose the zip above → **Install Now** → **Activate**.

## What's new in v1.4.4

- **Brand-alignment follow-up.** The Settings page was still shipping the old off-brand Material 3 palette as its built-in defaults, which silently overrode the brand-aligned CSS on fresh installs. All 11 off-brand default colours are now ZYMARG brand (`#9500A5`, `#BD00D1`, `#FEA9FF`, `#36003D`, `#131B2E`, `#534152`, etc.). Fresh installs look correct out of the box.
- **Version single source of truth.** `ZCRB_VERSION` was hardcoded to `1.4.2` and had been stale for two versions. Now reads from the plugin header via `get_file_data()` — bumping the header is enough.

## Recent versions

| Version | Date       | Commit    | Highlights |
|---------|------------|-----------|------------|
| v1.4.4  | 2026-06-27 | `main`    | Settings defaults to brand palette + version constant from header |
| v1.4.3  | 2026-06-27 | `95c4e9b` | First brand-alignment pass (CSS + admin link) |
| v1.4.2  | 2026-06-26 | `d2043af` | Local Cabinet Grotesk + Inter, Google Fonts CDN disabled |
| v1.4.1  | 2026-06-26 | `950efe0` | Removed dead "All Requests" badge |
| v1.4.0  | 2026-06-26 | `56b2789` | Material 3 redesign + customizable typography |
| v1.3.0  | —          | `2816b33` | Configurable data retention + visible privacy notice |
| v1.2.0  | —          | `dd350ee` | Customizable Settings page + GitHub Releases auto-updater |

## How to find the latest version (4 independent pointers)

1. **This file** — [`LATEST.md`](./LATEST.md) at the repo root.
2. **`VERSION`** — one-line plain-text file at the repo root for scripts/CI: [`VERSION`](./VERSION).
3. **Plugin header** — `Version:` line in [`zymarg-community-board/zymarg-community-board.php`](./zymarg-community-board/zymarg-community-board.php) (also the runtime source of truth for `ZCRB_VERSION` via `get_file_data()`).
4. **GitHub Releases** — once a tagged release is published, [`/releases/latest`](https://github.com/Aspeyash/Community-board/releases/latest) is a permanent URL that always points at the newest.

All four are updated in the same commit on every release.

## License

GPL-2.0-or-later. © ZYMARG.
