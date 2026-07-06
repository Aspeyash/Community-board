=== ZYMARG Community Request Board ===
Contributors: zymarg
Tags: community, requests, marketplace, dokan, woocommerce, bengali, bangla, seo
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 2.3.2
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

= 2.3.2 =
* **Rounded form fields on Settings page** - all text inputs, selects, and textareas on the Settings page now use `border-radius: 10px` to match the ZYMARG brand rounded style instead of plain square WordPress defaults. Checkboxes get 4px radius, color picker buttons get 10px radius. Smooth transition on focus border/shadow.

= 2.3.1 =
* **Horizontal top-nav layout** - removed the vertical left sidebar and replaced it with a horizontal header bar (Discovery Spark + "ZYMARG Community Board" title + version badge) and horizontal tab navigation (Dashboard / All Requests / Settings). Content area is now full-width with no sidebar consuming space.
* **CPT list table redirect** - navigating to `edit.php?post_type=zcrb_request` now automatically redirects to the branded SPA hub All Requests view. The raw WordPress list table is never shown.
* **Search icon overlap fix** - increased padding-left on the search input and adjusted icon positioning so the search placeholder text no longer overlaps the magnifying glass icon.
* **Status tabs scroll fix** - status filter tabs now use `flex-wrap: nowrap` with `overflow-x: auto` so they scroll horizontally instead of getting cut off at narrow widths.

= 2.3.0 =
* **Full-featured "All Requests" management surface — total parity with the WordPress CPT list table, built into the SPA hub.** The custom SPA list now covers every workflow an admin used to jump to `edit.php?post_type=zcrb_request` for: status filtering, bulk actions, sortable columns, permanent-delete flow with a proper Trash tab, restore-from-trash, per-page paging, live keyword search, and a one-click "Add New" shortcut. The "Advanced list view" escape hatch has been removed — users never have to visit the raw WP list table.
* **Status tab strip with live counts** — `All (N) | Approved (N) | Pending (N) | In Progress (N) | Fulfilled (N) | Trash (N)`. Counts come from `wp_count_posts()` in a single DB round-trip. Server-side filtering via `?zcrb_status={tab}` keeps pagination + counts accurate. On mobile the tabs collapse into a horizontally-scrollable strip so no viewport is too small to reach any status.
* **Sortable data table on desktop, card fallback on mobile** — `>= 768px` shows a proper `<table>` with columns: Checkbox / Ref / Title / Submitter / Status / Upvotes / Date / Actions. Ref, Title, Submitter, Upvotes and Date headers are clickable to sort ascending/descending (arrow indicator shows current direction). Row hover reveals the action buttons, matching WordPress core list-table UX. Below 768px the table hides and the same rows render as full-width cards with always-visible actions.
* **Bulk actions** — checkbox per row + "select all" in the table header. Bulk actions dropdown offers Approve / Reject / Move to Trash / Delete Permanently in normal tabs, and Restore / Delete Permanently in the Trash tab. Submits through `admin-post.php?action=zcrb_bulk_action` with a nonce (`zcrb_bulk` / `zcrb_bulk_nonce`), then redirects back to the SPA with a success notice (`X requests approved.` / `X requests moved to Trash.` etc). Delete Permanently double-confirms before firing.
* **Trash tab** — a full Trash workflow inside the SPA. Trashed requests are hidden from the main list (they get their own tab), and while the Trash tab is active the action buttons become Restore + Delete Permanently. `Restore` calls `wp_untrash_post()`; `Delete Permanently` uses the existing retention-safe delete pipeline.
* **"Add New" button** — a prominent action button next to the panel title opens the standard WP editor at `post-new.php?post_type=zcrb_request` so admins can seed requests manually.
* **Per-page selector** — a screen-options-style "Show [20|50|100|All] per page" dropdown at the top of the table reloads with `?per_page=N` when changed. All 4 tab links, the pagination bar, and the bulk-action form preserve the current per-page choice.
* **Empty state per tab** — instead of a single generic "no requests" line, each tab now renders its own friendly copy: "No approved requests.", "No pending requests.", "Trash is empty.", etc. If a keyword search filters everything out, a "No matching requests." line replaces the rows without wiping the toolbar.
* **Row-level quick actions redirect back to the SPA** — the individual Approve / Reject / Delete / Restore links now include `zcrb_return=hub` so the redirect after each action lands on the hub with the previous tab preserved, instead of dumping the user on the raw CPT list.
* **Fully responsive down to 320px width** — the panel padding, tab strip, bulk-action bar, per-page dropdown, and card actions all reshape at breakpoints `< 768px`, `<= 480px`, and `<= 360px`. Buttons wrap, tabs scroll, cards stack, action rows wrap — no horizontal overflow at any tested viewport width. The "Add New" button spans the full row width on the smallest phones.
* **New PHP hooks / handlers** — `ZCRB_Admin_Hub::handle_bulk_action()` (on `admin_post_zcrb_bulk_action`) processes bulk operations with capability + nonce + post-type guards and dedupes checkbox IDs (mobile-card + desktop-table checkboxes both submit under the same `zcrb_bulk_ids[]` name). `ZCRB_Admin::handle_quick_action()` now knows how to redirect back to the hub when `zcrb_return=hub` is passed, and gained a `restore` branch backed by `wp_untrash_post()`. The main plugin file adds a `bulk_done` admin notice that says e.g. "3 requests approved." with proper pluralization.

= 2.2.1 =
* **WP sidebar clicks now use SPA view switching** - clicking Dashboard, All Requests, or Settings in the WordPress admin sidebar no longer causes a full page reload. All sidebar submenu links are intercepted client-side and perform the same instant view switch as the in-app navigation buttons.
* **All Requests sidebar link fixed** - previously the "All Requests" sidebar item pointed to `edit.php?post_type=zcrb_request` (a completely different WordPress CPT list table page). It now points to the SPA hub with the Requests view active, matching the custom branded list inside the app.
* **CPT hidden from menu** - the custom post type no longer auto-registers its own submenu item under the hub. An explicit submenu slug (`zcrb-hub-requests`) is used instead, ensuring all sidebar items render the same SPA page.

= 2.2.0 =
* **Full SPA admin navigation — zero page reloads between sections.** Adopts the canonical ZYMARG admin design language (sidebar + main panel, matching ZYMARG Backups and Theme Builder). All three sections — Dashboard, All Requests, Settings — now render server-side into a single page, and switching between them is instant: JS just toggles which view is visible. No AJAX fetches, no re-renders, no re-inits. The URL updates via `pushState` so refresh / bookmark / share / browser back+forward all work exactly as expected.
* **New sidebar navigation** — replaces the old horizontal tab strip. Discovery Spark + brand mark at the top, three navigation buttons (Dashboard / All Requests / Settings) below. Active item gets the ZYMARG gradient chip; hover state uses the soft surface container. Users without `manage_options` never see the Settings tab.
* **Custom "All Requests" list view built into the hub.** Previously, clicking All Requests jumped out to the WordPress CPT list at `edit.php?post_type=zcrb_request` (full page reload, different UI). It now renders inline as a card list with Ref number, colored status badge, submitter name, upvote count, date, message excerpt, and inline action buttons (View, Edit, Approve, Reject, Delete). Live keyword + status filtering runs entirely client-side (no server round-trip). A link to the advanced WordPress list view is still available for power-user workflows.
* **Settings page hosted inside the SPA.** The Settings URL (`admin.php?page=zcrb-settings`) keeps working for existing bookmarks — it just renders the same SPA shell with the Settings view preselected. Existing AJAX save (nonce `zcrb_settings_save`, endpoint `zcrb_save_settings`) is unchanged: form submits without a page reload, toast confirms save.
* **Fixes the user-reported bug** — every click on a tab previously triggered a full page reload because the tabs were plain `<a>` hrefs to `admin.php?page=...`. Nav buttons are now proper `<button>` elements handled entirely client-side, so switching sections has zero latency.

= 2.1.2 =
* **Submenu order fixed — Dashboard is now the first item** — under Community Board the sidebar showed "All Requests" first, then "Dashboard", then "Settings". The root cause was WordPress' `_add_post_type_submenus()` running on `admin_menu` priority 9 (BEFORE our hub's `register_menu()` at the default priority 10), which put the CPT's auto-added "All Requests" entry at the top. Added a new `reorder_submenu()` pass hooked at `admin_menu` priority 999 — it runs after every module is done registering menu items and sorts `$submenu['zcrb-hub']` into the canonical order: **Dashboard → All Requests → Settings**.
* **Per-section header titles** — the branded gradient header used to show "ZYMARG Community Board" as the big title on every admin screen (Dashboard, All Requests, Settings), which gave three different pages the exact same heading. The header now follows the canonical ZYMARG Theme Builder pattern: the section name (Dashboard / All Requests / Edit Request / Settings) is the big bold title, and "ZYMARG Community Board" becomes a small uppercase kicker/eyebrow above it. `render_branded_header()` now accepts an optional `$section_title` argument; call sites pass "Dashboard" and "Settings" explicitly, and the CPT list/edit auto-injector auto-detects the title from the current admin screen.

= 2.1.1 =
* **Discovery Spark visibility fix (CRITICAL brand rule)** — the Discovery Spark's purple paths were invisible on the ZYMARG purple gradient header. The spark now sits inside a small white rounded "chip" (12px radius, 8px padding, subtle shadow) so the purple paths are always visible while the header keeps its gradient look. This enforces the permanent ZYMARG brand rule: **never** put a purple background behind the Discovery Spark.
* **Duplicate Settings-page heading removed** — the branded gradient header already shows "ZYMARG Community Board", so the second `<h1>` under it was redundant. Kept the branded header and updated the description paragraph to lead with "Settings — ...".
* **Broken redirects fixed** — after the admin menu was restructured (Settings now under `zcrb-hub`), the "Run cleanup now" and "Force re-check updates" buttons redirected to a `edit.php?post_type=…` URL that no longer exists. Both now correctly redirect to `admin.php?page=zcrb-settings`.
* **Save-Settings toast alignment fixed** — the AJAX save toast was dropping to a new line because `submit_button()` wrapped the button in its own `<p>`. Refactored to a single flex row (`.zcrb-submit-row`) with the button and toast as siblings; the toast now appears inline next to Save Changes.
* **Settings container width unified** — the Settings page now uses the same `.zcrb-hub-wrap` max-width (1200px, centered) as the Dashboard hub for visual consistency across the admin.
* **Dead color picker code removed** — deleted a no-op `.zcrb-color` loop in the settings JS (wpColorPicker syncs the underlying input value automatically).

= 2.1.0 =
* **Full AJAX save on the Settings page** - the Settings form now saves via `admin-ajax.php` (action `zcrb_save_settings`) instead of the classic `options.php` full page reload. On success a toast notification appears next to the Save Changes button, and the button is disabled + relabeled ("Saving…") for the duration of the request. Uses the same `sanitize()` pipeline as the classic path, so every knob (per-page, message limit, image rules, colors, typography, notifications, retention, GitHub updater) is validated exactly the same way. Nonce-protected (`zcrb_settings_save`) and gated on `manage_options`.

= 2.0.7 =
* **Duplicate "All Requests" submenu removed** - removed the manual `add_submenu_page` call for "All Requests" in the admin hub. WordPress already auto-creates this submenu entry from the CPT registration (`show_in_menu => 'zcrb-hub'`), so the manual call produced a duplicate sidebar item.

= 2.0.6 =
* **Duplicate admin menu removed** - removed stray `menu_icon` and `menu_position` args from the CPT registration. The CPT is nested under the hub menu (`show_in_menu => 'zcrb-hub'`), so these args are unnecessary and could cause a duplicate top-level "All Requests" item on some WP configurations.

= 2.0.5 =
* **Discovery Spark CSS verbatim from canonical** - replaced the Community Board's modified Discovery Spark CSS with the exact canonical copy from Theme Builder. Three separate per-group keyframes (zymarg-spark-lens--accent, zymarg-spark-lens--companion, zymarg-spark-lens--hero), fill via CSS custom properties, vertical-align: middle on the wrapper.
* **SVG inline fill attributes removed** - path elements no longer carry fill="#6833ea" or fill="#ffd166"; the CSS classes (.zymarg-spark-item--purple, .zymarg-spark-item--gold) handle fill via custom properties, matching the canonical implementation.
* **SVG accessibility** - added aria-hidden="true" focusable="false" to all Discovery Spark SVG elements.

= 2.0.4 =
* **Discovery Spark animation fix** - replaced broken ultra-fast flicker (0.20s/0.40s) with canonical sequential visible pulse. Shapes stay visible (opacity 0.85 baseline) and pulse brighter in sequence (accent, then companion, then hero) over a 2.4s cycle with purple glow. Gold paths remain static.
* **Discovery Spark background** - spark panel and empty-state containers now use solid white (#ffffff) for proper contrast with purple paths.

= 2.0.3 =
* **Unified branded header** - the gradient header with Discovery Spark now appears on ALL admin pages (Dashboard, All Requests CPT list, and Settings), not just the Dashboard.
* **Integrated Discovery Spark** - the spark SVG is now embedded inside the gradient header (to the left of the title text) instead of being a separate panel below it.
* **Admin CSS on CPT screens** - zcrb-admin.css now loads on the CPT list table and single edit screens for consistent branding.

= 2.0.2 =
* **Canonical Discovery Spark** - replaced wrong generic 8-point star SVG with the exact ZYMARG Discovery Spark at large (--xl 48px) size in both admin hub and public empty state.
* **Discovery Spark animation fixed** - fast sequential in-place pulse via keyframes with correct per-group delays (accent 0.20s, companion 0.40s, hero 0.40s), purple drop-shadow glow, gold paths static. Replaces the old slow 1.5s whole-group pulse.
* **Admin sidebar branding** - "Community Board" menu item now styled with ZYMARG brand purple (#9500A5), font-weight 600, turns white on hover/active/current.
* **pre_get_posts status conflict resolved** - main archive query now includes 'publish', 'zcrb_in_progress', and 'zcrb_fulfilled' statuses so the template's custom status filters work correctly.
* **Vendor response permissions expanded** - Dokan vendors (seller), MultiVendorX vendors (dc_vendor), and generic vendor roles can now respond to community requests. Previously only users with edit_others_posts capability could respond.

= 2.0.1 =
* **ZYMARG unified brand design alignment** - CSS updated to match the ZYMARG ecosystem design language used across Vendor Dashboard, Connection Engine, and WC Product Grid.
* Card radius updated from 12px to 18px (unified card radius).
* Card shadow switched to two-layer ZYMARG standard shadow.
* Card hover now includes translateY(-2px) lift, purple border, and purple-tinted elevated shadow.
* Section headings (feed title, form title) now use deep purple (#36003D) at font-weight 800.
* Primary button default is now #9500A5 (hover: #BD00D1) matching the ZYMARG button pattern.
* Status badge "In Progress" changed from orange to purple tint for brand consistency.
* Hero title line 2 now uses deep purple for contrast.
* Search bar elevated with card-like container shadow.
* Empty state SVG replaced with the canonical ZYMARG Discovery Spark mark.
* Shadow-md updated to purple-tinted elevated shadow.

= 2.0.0 =
* **Non-destructive uninstall** - uninstall.php now only deletes data when ZCRB_REMOVE_ALL_DATA constant is defined. Safe by default.
* **function_exists() guard** on zcrb_get_setting() to prevent fatal errors.
* **Fixed github_repo default** from 'Community-page' to 'Community-board'.
* **SEO optimization** - eliminated duplicate WP_Query in JSON-LD generation; reuses the main query.
* **Status lifecycle** - new statuses: In Progress and Fulfilled. Requests now follow Pending > Approved > In Progress > Fulfilled flow.
* **Vendor response system** - vendors can respond to community requests directly from the single request page.
* **Upvote/priority system** - logged-in users can upvote requests. Upvote count displayed on cards and single view.
* **Submitter notifications** - email notifications sent to the submitter when their request is approved or when a vendor responds.
* **Search and filter** on the public board - search by keyword, filter by status.
* **Duplicate detection** - live search-as-you-type in the submission form shows similar existing requests.
* **Bulk approve** - new bulk action in the admin list table to approve multiple pending requests at once.
* **Branded admin hub** - new top-level admin page with Discovery Spark animation, gradient header, version badge, and dashboard stats.
* **Purple admin sidebar** - Community Board menu text styled with ZYMARG brand purple (#9500A5).
* **Branded empty states** - decorative SVG illustrations and personality copy when no requests exist.
* **Image upload count setting** - admin can configure max images per submission (1-4).

= 1.4.4 =
* **Brand-alignment follow-up** — the Settings page in `class-zcrb-settings.php` was still
  shipping the OLD Material 3 default palette as its built-in defaults, which got injected
  as inline `<style>` and silently overrode the brand-aligned `:root` block in `zcrb.css`
  on fresh installs. All 11 off-brand default hex values are now updated to the official
  ZYMARG palette: Primary `#9500A5`, Container `#BD00D1`, Accent `#FEA9FF`, soft tint
  `#fceaff`, notice background `#f8e8fa`, three gradient orbs (`#9500A5` / `#BD00D1` /
  `#FEA9FF`), main text `#131B2E`, muted text `#534152`, secondary surface `#fcfaff`.
  The comment header was also corrected from "Material 3 inspired purple palette" to
  "ZYMARG brand palette". Fresh installs now look correct out of the box.
* **Version-constant single source of truth** — `ZCRB_VERSION` was hardcoded to `1.4.2`
  in the bootstrap file and had been stale for v1.4.3 too. Refactored to read the
  version straight from the `Version:` header at runtime via `get_file_data()`, so
  bumping the plugin header is now enough — every consumer (asset cache-buster, updater,
  etc.) stays in sync automatically.

= 1.4.3 =
* **Brand alignment** — switched the entire colour palette from the off-brand
  Material 3 default purples (`#4f00d0`, `#6833ea`, `#cdbdff`) to the official
  ZYMARG palette (`#9500A5` Primary, `#BD00D1` Container, `#FEA9FF` Accent).
  All `:root` tokens updated + all hardcoded inline rgba purples replaced.
  Plus the dark-purple text accent (`#36003D`), brand border (`#D8BFD3`),
  brand text colours (`#131B2E` / `#534152`), and a warm-white secondary
  surface (`#fcfaff`). Result: matches the ZYMARG OS theme + Vendor
  Dashboard + the rest of the ZYMARG product family.
* Inline admin "Approve" link colour also fixed (was `#6b3fa0`, now `#9500A5`).

= 1.4.2 =
* Use locally-bundled Cabinet Grotesk + Inter fonts; disable Google Fonts CDN
  load by default (privacy + performance + no third-party network cost).

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
