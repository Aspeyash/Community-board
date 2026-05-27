=== ZYMARG Community Request Board ===
Contributors: zymarg
Tags: community, requests, marketplace, dokan, woocommerce, bengali, bangla, seo
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO-optimized Community Request Board for ZYMARG (zymarg.com). Logged-in shoppers post what they want to buy; vendors respond. 30 requests per page with numbered pagination. Bengali + English. Compatible with Astra, Elementor Pro, WooCommerce, and Dokan.

== Features ==

* Public archive at `/community/` with single posts at `/community/{slug}` (readable URL slugs).
* Server-side rendered cards — fully crawlable by Google. **30 requests per page** with classic numbered pagination. Every page (`/community/page/2/`, `/community/page/3/`, …) has its own unique title, `rel="canonical"`, `og:url`, plus `rel="prev"` / `rel="next"`.
* Submission form for logged-in users only. Required fields: Full Name, Phone Number, Email Address, Request Message (200-character cap), and an optional Image upload (JPG/PNG/WEBP, 2 MB max).
* Admin approval workflow. Submissions land as `pending`. Approve/Reject from the WP dashboard.
* **Privacy:** Phone and Email are stored as private post meta (`auth_callback` blocks REST). Public feed shows only Name, Message, Date, and Image.
* SEO ready: H1, meta description, Open Graph, Twitter Card, JSON-LD `FAQPage`, `ItemList`, `CollectionPage` (archive) and `Question` (single).
* Bilingual interface: English + Bengali. Switch via `?lang=bn` / `?lang=en` (persisted via cookie). Honours WP locale `bn_BD` automatically.
* White base + soft purple radial gradient orbs, ZYMARG primary purple (`#6b3fa0`) action buttons.
* Mobile responsive, accessible, no theme overrides — works inside Astra and Elementor Pro out of the box. Coexists with WooCommerce and Dokan.

== Installation ==

1. Upload the `zymarg-community-board` folder to `/wp-content/plugins/`.
2. Activate **ZYMARG Community Request Board** from the Plugins screen.
3. Go to **Settings → Permalinks** and click **Save** once to flush rewrite rules. The board will be available at `https://zymarg.com/community/`.
4. Optional: place the board inside an Elementor Pro / Astra page using the shortcode `[zymarg_community_board]`. Use `[zymarg_community_board show_form="0"]` for a feed-only block, or `[zymarg_community_form]` for just the submission form.

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

= 1.1.0 =
* Switched the public feed from infinite scroll to **30 requests per page with classic numbered pagination**.
* Each paginated archive URL now ships its own unique `<title>`, `rel="canonical"`, `og:url`, plus `rel="prev"` / `rel="next"`, so Google indexes every page distinctly.
* Removed the load-more AJAX endpoint — pagination is fully server-rendered.

= 1.0.0 =
* Initial release.
