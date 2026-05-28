=== ZYMARG Community Request Board ===
Contributors: zymarg
Tags: community, requests, marketplace, dokan, woocommerce, bengali, bangla, seo
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO-optimized Community Request Board for ZYMARG (zymarg.com). Logged-in shoppers post what they want to buy; vendors respond. 30 requests per page with crawlable numbered pagination. Bilingual (English/Bengali). Fully customizable via the Settings page. Auto-updates from GitHub Releases. Compatible with Astra, Elementor Pro, WooCommerce, and Dokan.

== Features ==

* Public archive at `/community/` with single posts at `/community/{slug}` (readable URL slugs).
* Server-side rendered cards — fully crawlable by Google. Numbered pagination with one URL per page (`/community/page/2/`, `/community/page/3/`, …) and unique title, `rel="canonical"`, `og:url`, `rel="prev"`, `rel="next"` per page.
* Submission form for logged-in users only. Default required fields: Full Name, Phone Number, Email Address, Request Message (200-character cap). Optional Image upload (JPG/PNG/WEBP, 2 MB max by default).
* Admin approval workflow. Submissions arrive as `pending`. Approve/Reject from the WP dashboard.
* **Privacy:** Phone and Email are stored as private post meta (`auth_callback` blocks REST). Public feed shows only Name, Message, Date, and Image.
* SEO ready: H1, meta description, Open Graph, Twitter Card, JSON-LD `FAQPage`, `ItemList`, `CollectionPage` (archive) and `Question` (single).
* Bilingual interface: English + Bengali. Switch via `?lang=bn` / `?lang=en` (cookie persisted). Honours WP locale `bn_BD` automatically.
* White base + soft purple radial gradient orbs; ZYMARG primary purple action buttons. Mobile responsive, accessible, no theme overrides — works inside Astra and Elementor Pro out of the box. Coexists with WooCommerce and Dokan.
* **Fully customizable Settings page** — colors, per-page, message limit, image rules, required-field toggles, content overrides, notifications.
* **GitHub Releases auto-updater** — publish a release on GitHub and WordPress shows an "Update Now" link.

== Installation ==

1. Upload the `zymarg-community-board` folder to `/wp-content/plugins/` *or* upload `zymarg-community-board.zip` via Plugins → Add New → Upload Plugin.
2. Activate **ZYMARG Community Request Board** from the Plugins screen.
3. Go to **Settings → Permalinks** and click **Save** once to flush rewrite rules. The board will be available at `https://zymarg.com/community/`.
4. Go to **Community Board → Settings** to customize colors, per-page count, form requirements, and the GitHub repository for auto-updates.

== Settings ==

The settings live under **Community Board → Settings** in the WordPress admin and cover:

* **General** — requests per page, message character limit, default language, submissions per user per hour.
* **Form & Image Upload** — required-field toggles for Phone, Email, Image; max image size; allowed MIME types; whether to allow uploads at all.
* **Page Content (overrides)** — H1, subtitle, and meta description per language. Leave blank to fall back to the built-in strings.
* **Branding & Colors** — primary purple, hover purple, light purple, gradient orb colors, body/muted/background colors. WordPress color picker.
* **Notifications** — moderation email recipient and subject template.
* **GitHub Auto-Updates** — owner / repo / branch and an optional personal access token for private repositories. A "Check for updates" button forces an immediate re-query.

== Updates from GitHub ==

The bundled `.github/workflows/release.yml` builds a `zymarg-community-board.zip` for every published release and attaches it as a release asset. WordPress then sees a new release as a regular plugin update.

To ship a new version:

1. Bump the `Version:` header in `zymarg-community-board/zymarg-community-board.php` and `Stable tag:` in `readme.txt`.
2. Commit and push.
3. Create a GitHub Release with tag `v1.x.y`. The workflow attaches the ZIP automatically.
4. Existing installs will see the update on the Plugins screen within hours (or instantly via **Community Board → Settings → Check for updates**).

== Shortcodes ==

* `[zymarg_community_board]` — Full board (H1, form, feed, numbered pagination).
* `[zymarg_community_board show_form="0"]` — Feed only.
* `[zymarg_community_board show_header="0"]` — Hide the H1 (useful when Elementor provides its own).
* `[zymarg_community_form]` — Standalone submission form.

== Hooks ==

* `do_action( 'zcrb_request_submitted', int $post_id, array $data )` — fires after a pending submission is saved.
* `apply_filters( 'zcrb_enqueue_assets', bool $load )` — force-load CSS/JS on custom templates.

== Privacy ==

Phone numbers and email addresses are stored as private post meta. They are visible only to users with `edit_posts` capability and are never echoed in the public feed or REST responses.

== Changelog ==

= 1.4.1 =
* Removed the decorative "All Requests" / "সব রিকোয়েস্ট" badge from the feed header — it was non-functional dead text. Cleaned up the related CSS class and bilingual strings.

= 1.4.0 =
* New **Material 3 inspired design** matching the ZYMARG mockup: deep purple primary (`#4f00d0`), glass cards with backdrop-blur, decorative gradient orbs, sticky sidebar form, and a 12-column responsive layout.
* Brand-new **Typography settings** — every text element on the public page now has its own Desktop AND Mobile font-size knob (15 elements × 2 breakpoints = 30 sliders). Also configurable: heading font family, body font family, and a "Load Google Fonts" toggle that auto-fetches Sora + Inter (or any Google Font you choose) from the CDN.
* Cards redesigned: 16:9 image header with hover zoom, author name in primary purple, uppercase date badge, 3-line clamped message, ref-number footer (`Ref: #RQ0123`), and "View Details →" link with SVG arrow.
* Pagination redesigned to match the mockup: rounded-rectangle page numbers, primary-filled active page, "Showing X-Y of Z requests" meta line below.
* Privacy notice moved into its own purple-tinted card below the submission form (matching the mockup) and re-worded to match the new copy. Bilingual.
* New i18n strings: Recent Requests, All Requests, View Details, Ref:, Showing X-Y of Z, plus form placeholders and an upload hint with file size — all bilingual (English + Bengali).
* `data-default-color` for every color picker so admins can reset to factory defaults with one click.

= 1.3.0 =
* New **Privacy & Data Retention** setting — admin picks 30 / 60 / 90 days (or "Disabled"). A daily WP-Cron sweep deletes any request older than that, including the post meta (Name, Phone, Email) and the uploaded image.
* The submission form now displays a clear privacy notice telling visitors how long their data is kept (e.g. "For your privacy, every submission is automatically deleted after 30 days."). When retention is disabled, only the "Phone and email are private" reassurance is shown.
* New **Run cleanup now** button under Settings to trigger a manual sweep without waiting for cron.

= 1.2.0 =
* New **Settings page** under Community Board → Settings with color pickers, per-page count, message limit, image rules, required-field toggles, content overrides (per language), notification config, and GitHub repo settings.
* New **GitHub Releases auto-updater** — WP shows new versions on the Plugins screen exactly like a wordpress.org plugin. Smart folder renaming handles both release-asset ZIPs and zipballs.
* New **GitHub Actions workflow** that builds and attaches `zymarg-community-board.zip` to every published release.
* Brand colors are now driven entirely by CSS variables generated from settings — change colors without touching CSS.
* `zcrb_get_setting()` helper for theme/site-builder code that wants to read plugin config.

= 1.1.0 =
* Switched from infinite scroll to **30 requests per page with classic numbered pagination**.
* Each paginated archive URL ships its own unique `<title>`, `rel="canonical"`, `og:url`, `rel="prev"` / `rel="next"`.
* Removed the load-more AJAX endpoint; pagination is fully server-rendered.

= 1.0.0 =
* Initial release.
