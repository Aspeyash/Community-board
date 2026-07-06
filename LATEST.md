# ZYMARG Community Request Board -- Latest Release

**Latest version:** `v2.5.0`
**Released:** 2025-07-06
**Branch:** `v2.5.0`

## Download

[**zymarg-community-board-v2.5.0.zip**](https://github.com/Aspeyash/Community-board/raw/refs/heads/v2.5.0/zymarg-community-board-v2.5.0.zip)

> Install in WordPress: **Plugins > Add New > Upload Plugin** > choose the zip above > **Install Now** > **Activate**.

## What's new in v2.5.0

**Freeform image cropper + auto-compression on upload** - a new client-side crop tool and server-side image optimization for the community board image upload.

### Client-side freeform cropper

- After selecting an image file, a crop UI modal appears showing the full image preview
- Users can drag a freeform box (any width/height, no forced aspect ratio) over the image
- Draggable and resizable crop rectangle with corner and edge handles
- Rule-of-thirds grid overlay for composition guidance
- "Crop & Use" button applies the crop and creates a new blob that replaces the file input
- "Skip Crop" button keeps the original image unchanged
- ZYMARG brand styling: purple border, 18px rounded corners, white background
- Touch-friendly for mobile devices
- Lightweight, dependency-free implementation using HTML5 Canvas API

### Server-side auto-compression

- After upload, images are automatically compressed using WordPress WP_Image_Editor (GD/Imagick)
- JPEG and WebP images are saved at 80% quality
- Images wider than 1920px are resized to 1920px width (maintaining aspect ratio)
- Original file is replaced with the compressed version (saves storage)
- Attachment metadata and thumbnails are regenerated from the optimized source

## Recent versions

| Version | Date       | Commit    | Highlights |
|---------|------------|-----------|------------|
| v2.5.0  | 2025-07-06 | `v2.5.0`  | Freeform image cropper + auto-compression on upload |
| v2.4.2  | 2025-07-06 | `main`    | Mobile px inline fix |
| v2.4.1  | 2025-07-06 | `main`    | Remove settings form-table border on mobile |
| v2.4.0  | 2025-07-06 | `main`    | Mobile-only full-width inputs + stacked labels |
| v2.3.6  | 2025-07-06 | `main`    | Settings defaults fix |
| v2.3.2  | 2025-07-06 | `main`    | Rounded form fields on Settings page |
| v2.3.1  | 2025-07-06 | `main`    | Horizontal top-nav layout |
| v2.3.0  | 2025-07-06 | `main`    | Full-featured All Requests view |
| v2.2.1  | 2025-07-06 | `main`    | WP sidebar clicks use SPA view switching |
| v2.2.0  | 2025-07-06 | `main`    | Full SPA admin hub |
| v2.0.0  | 2025-07-06 | `main`    | Major release: status lifecycle, vendor responses, upvotes, notifications |

## How to find the latest version (4 independent pointers)

1. **This file** - [`LATEST.md`](./LATEST.md) at the repo root.
2. **`VERSION`** - one-line plain-text file at the repo root for scripts/CI: [`VERSION`](./VERSION).
3. **Plugin header** - `Version:` line in [`zymarg-community-board/zymarg-community-board.php`](./zymarg-community-board/zymarg-community-board.php) (also the runtime source of truth for `ZCRB_VERSION` via `get_file_data()`).
4. **GitHub Releases** - once a tagged release is published, [`/releases/latest`](https://github.com/Aspeyash/Community-board/releases/latest) is a permanent URL that always points at the newest.

All four are updated in the same commit on every release.

## License

GPL-2.0-or-later. (c) ZYMARG.
