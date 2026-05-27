# ZYMARG Community Request Board

WordPress plugin powering the **Community Request Board** at [zymarg.com/community](https://zymarg.com/community/).
Logged-in shoppers post what they want to buy, vendors across Bangladesh respond. SEO-optimized,
bilingual (English + Bengali), Astra / Elementor Pro / WooCommerce / Dokan compatible.

> **Latest:** v1.4.0 — Material 3 inspired redesign + per-element font-size controls (desktop + mobile) for every text element.

## Highlights

- Custom post type `zcrb_request` with public archive `/community/` and readable single URLs `/community/{slug}`.
- **Crawlable + numbered pagination**: cards are server-side rendered. The feed shows **30 requests per page** (configurable) with classic numbered pagination. Every page has its own URL (`/community/page/2/`, `/community/page/3/`, …) with a unique `<title>`, `rel="canonical"`, `og:url`, plus `rel="prev"` / `rel="next"`, so Google indexes every request.
- Submission form for logged-in users only — Full Name, Phone, Email, Message (200 chars by default), optional Image (JPG/PNG/WEBP, 2 MB by default). Every limit and required-field toggle is configurable.
- Admin approval workflow: submissions land as `pending`, approve/reject from the WordPress dashboard. Phone & Email are visible only to admins.
- Public feed exposes only Name, Message, Date, and Image. Phone / Email never appear publicly.
- SEO: H1, meta description, Open Graph + Twitter Card, JSON-LD `FAQPage`, `ItemList`, `CollectionPage`, and `Question` schema.
- Bilingual UI via `?lang=bn` / `?lang=en`, persisted via cookie. Honours WP locale `bn_BD` automatically.
- White base + soft purple radial gradient orbs, ZYMARG primary purple (`#6b3fa0`) action buttons by default. **Every color is editable from Settings.**
- **GitHub Releases auto-updater** — push a tag, get a release, WordPress installs the update.

## Install

### Option A — Upload the ZIP

1. Download [`zymarg-community-board.zip`](https://github.com/Aspeyash/Community-page/raw/feat/community-board-v1/dist/zymarg-community-board.zip).
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the ZIP, click **Install Now**, then **Activate**.
3. Visit **Settings → Permalinks → Save** to flush rewrite rules.
4. Visit **Community Board → Settings** to configure.

### Option B — Copy the folder

1. Copy `zymarg-community-board/` into `wp-content/plugins/`.
2. Activate **ZYMARG Community Request Board** from **Plugins**.
3. **Settings → Permalinks → Save**, then **Community Board → Settings**.

The board is now live at `https://zymarg.com/community/`.

## Embedding inside Elementor / Astra page templates

Drop a Shortcode widget with one of these:

```text
[zymarg_community_board]                       Full board: H1, form, feed, numbered pagination
[zymarg_community_board show_form="0"]         Feed only
[zymarg_community_board show_header="0"]       Hide H1 (use Elementor heading instead)
[zymarg_community_form]                        Standalone submission form
```

## Settings

Everything you'd want to change lives at **Community Board → Settings**:

| Section | Configures |
|---|---|
| **General** | Requests per page (1–200), message char limit (50–2000), default language, submissions/user/hour |
| **Form & Image Upload** | Required toggles for Phone / Email / Image, image upload on/off, max size (MB), allowed MIME types |
| **Page Content (overrides)** | H1, subtitle, meta description per language (EN + BN). Blank = use built-in strings |
| **Branding & Colors** | 12 color pickers: primary, hover, light tints, privacy-notice background, 3 gradient orbs, text, muted, page bg, surface bg |
| **Typography** | Heading font family (Sora, Inter, Poppins, …), body font family, Google Fonts auto-load toggle, **per-element font sizes for 15 different elements × Desktop + Mobile breakpoint = 30 separate sliders** |
| **Notifications** | Moderation recipient email, subject template |
| **Privacy & Data Retention** | Auto-delete requests after 30 / 60 / 90 days (or never). The chosen period is also shown to visitors on the submission form. Manual "Run cleanup now" button. |
| **GitHub Auto-Updates** | Owner, repo, branch, optional PAT for private repos, "Check for updates" button |

The configured colors are emitted as CSS custom properties on the front-end, so changes apply instantly without touching the stylesheet.

## Updating from GitHub

This plugin behaves like a wordpress.org plugin even though it isn't hosted there: WordPress consults GitHub Releases for new versions and shows them on the Plugins screen.

To ship a new version:

```bash
# 1. bump the Version header in the main plugin file
# 2. commit + push to main
git tag v1.3.0
git push origin v1.3.0
# 3. on GitHub, create a Release for v1.3.0 (or let the tag-push workflow do it)
```

The bundled GitHub Actions workflow ([`.github/workflows/release.yml`](.github/workflows/release.yml)) builds `zymarg-community-board.zip` and attaches it to the release. Existing WordPress installs will see the update appear on the Plugins screen on the next 12-hour cron, or instantly via **Community Board → Settings → Check for updates**.

If you're updating a *private* fork of this repo, paste a GitHub personal access token into the **Personal access token** field on the settings page.

## Plugin layout

```
zymarg-community-board/
├── zymarg-community-board.php      # plugin bootstrap, dynamic CSS injection, GitHub Plugin URI headers
├── uninstall.php                   # full data cleanup on plugin delete
├── readme.txt                      # WP.org-style readme
├── includes/
│   ├── class-zcrb-settings.php     # admin Settings page + render_dynamic_css() + zcrb_get_setting()
│   ├── class-zcrb-updater.php      # GitHub Releases auto-updater
│   ├── class-zcrb-i18n.php         # English + Bengali strings, language switcher (settings overrides)
│   ├── class-zcrb-cpt.php          # CPT registration + private meta
│   ├── class-zcrb-form.php         # submission validation + image upload (settings-driven)
│   ├── class-zcrb-ajax.php         # AJAX submit endpoint
│   ├── class-zcrb-admin.php        # meta box, list columns, approve/reject actions
│   ├── class-zcrb-shortcode.php    # [zymarg_community_board] / [zymarg_community_form]
│   ├── class-zcrb-seo.php          # meta description, OG, Twitter, JSON-LD (per-page-aware)
│   └── class-zcrb-template.php     # SSR card/board/single rendering
├── templates/
│   ├── archive-zcrb.php            # default archive (used if theme has no override)
│   └── single-zcrb.php             # default single
├── assets/
│   ├── css/zcrb.css                # base styles using CSS custom properties
│   └── js/zcrb.js                  # form AJAX (pagination is plain HTML)
└── languages/
    └── zymarg-community-board-bn_BD.po
```

## Privacy

Phone numbers and email addresses are stored as private `post_meta` keys (`_zcrb_phone`, `_zcrb_email`). The `auth_callback` blocks REST exposure, the public render functions never echo them, and no public schema field references them.

## Hooks for developers

- `do_action( 'zcrb_request_submitted', int $post_id, array $data )` — fires after a pending submission is saved.
- `apply_filters( 'zcrb_enqueue_assets', bool $load )` — force-load CSS/JS on custom templates.
- `zcrb_get_setting( string $key, $default = null )` — read any plugin setting from theme code.
