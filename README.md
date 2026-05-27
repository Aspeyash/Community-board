# ZYMARG Community Request Board

WordPress plugin powering the **Community Request Board** at [zymarg.com/community](https://zymarg.com/community/).
Logged-in shoppers post what they want to buy, vendors across Bangladesh respond. SEO-optimized,
bilingual (English + Bengali), Astra / Elementor Pro / WooCommerce / Dokan compatible.

## Highlights

- Custom post type `zcrb_request` with public archive `/community/` and readable single URLs `/community/{slug}`.
- **Crawlable + infinite scroll**: cards are server-side rendered. Above 50 published requests the UX switches to infinite scroll, but every page is also reachable via `?paged=N` with `rel="prev"` / `rel="next"`, so Googlebot indexes them all.
- Submission form for logged-in users only — Full Name, Phone, Email, Message (200 chars), optional Image (JPG/PNG/WEBP, 2 MB).
- Admin approval workflow: submissions land as `pending`, approve/reject from the WordPress dashboard. Phone & Email are visible only to admins.
- Public feed exposes only Name, Message, Date, and Image. Phone / Email never appear publicly.
- SEO: H1, meta description, Open Graph + Twitter Card, JSON-LD `FAQPage`, `ItemList`, `CollectionPage`, and `Question` schema.
- Bilingual UI via `?lang=bn` / `?lang=en`, persisted via cookie. Honours WP locale `bn_BD` automatically.
- White base + soft purple radial gradient orbs, ZYMARG primary purple (`#6b3fa0`) action buttons. Mobile-first responsive CSS, no theme overrides.

## Install

1. Copy the `zymarg-community-board/` folder into `wp-content/plugins/`.
2. Activate **ZYMARG Community Request Board** from **Plugins**.
3. Visit **Settings → Permalinks** and click **Save** to flush rewrite rules.
4. The board is now live at `https://zymarg.com/community/`.

## Embedding inside Elementor / Astra page templates

Drop a Shortcode widget with one of these:

```text
[zymarg_community_board]                       Full board: H1, form, feed, infinite scroll
[zymarg_community_board show_form="0"]         Feed only
[zymarg_community_board show_header="0"]       Hide H1 (use Elementor heading instead)
[zymarg_community_form]                        Standalone submission form
```

## Plugin layout

```
zymarg-community-board/
├── zymarg-community-board.php      # plugin bootstrap + asset enqueue
├── uninstall.php                   # full data cleanup on plugin delete
├── readme.txt                      # WP.org-style readme
├── includes/
│   ├── class-zcrb-i18n.php         # English + Bengali strings, language switcher
│   ├── class-zcrb-cpt.php          # CPT registration + private meta
│   ├── class-zcrb-form.php         # submission validation + image upload
│   ├── class-zcrb-ajax.php         # AJAX submit + load-more endpoints
│   ├── class-zcrb-admin.php        # meta box, list columns, approve/reject actions
│   ├── class-zcrb-shortcode.php    # [zymarg_community_board] / [zymarg_community_form]
│   ├── class-zcrb-seo.php          # meta description, OG, Twitter, JSON-LD
│   └── class-zcrb-template.php     # SSR card/board/single rendering
├── templates/
│   ├── archive-zcrb.php            # default archive (used if theme has no override)
│   └── single-zcrb.php             # default single
├── assets/
│   ├── css/zcrb.css                # white base + purple orbs styling
│   └── js/zcrb.js                  # form AJAX + infinite scroll
└── languages/
    └── zymarg-community-board-bn_BD.po
```

## Privacy

Phone numbers and email addresses are stored as private `post_meta` keys (`_zcrb_phone`, `_zcrb_email`). The `auth_callback` blocks REST exposure, the public render functions never echo them, and no public schema field references them.

## Hooks

- `do_action( 'zcrb_request_submitted', int $post_id, array $data )` — fires after a pending submission is saved.
- `apply_filters( 'zcrb_enqueue_assets', bool $load )` — force-load CSS/JS on custom templates.
