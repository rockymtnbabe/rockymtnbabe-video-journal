# RockyMtnBabe Video Journal — v0.1.0

A lightweight, private-source WordPress plugin for publishing R2-hosted MP4 videos with Photo Journal-inspired gallery styling.

## Installation (staging preferred)
1. Back up your WordPress website/database.
2. Upload the packaged ZIP via **Plugins → Add New → Upload Plugin** and activate.
3. In **Video Journal → Video Categories**, add `Fashion` (slug `fashion`).
4. Choose **Video Journal → Add New**. Enter a title, Cloudflare R2 MP4 URL, journal date, category and **Featured Image** (portrait thumbnail from WordPress Media Library). Publish.
5. On an *unpublished test page*, add Elementor **Shortcode** widget with `[rmb_video_gallery limit="30"]`.
6. Preview and verify thumbnail, date, hover, lightbox, scroll locking and mobile layout.
7. **Only after verification**, replace the existing manual HTML gallery with the shortcode.

## Supported shortcodes
- `[rmb_video_gallery limit="30"]` — most recent 30 by journal date.
- `[rmb_video_gallery category="fashion" limit="30"]` — filter by video category slug.
- `[rmb_video_gallery month="2026-10" limit="100"]` — preliminary month filter for future archive pages.

## Architecture / boundaries
- WordPress database holds catalog entries; WordPress Media Library holds thumbnails.
- R2 stores MP4s; this plugin does not upload videos to R2.
- R2 URL validation is intentionally restricted to HTTPS `videos.rockymtnbabe.com` with an `.mp4` pathname.
- Drafts and private entries are not displayed. A missing featured image or invalid URL excludes the entry.
- Existing hand-coded Elementor gallery remains unaffected until you replace it.
- Not a membership/paywall system; R2 video URLs are publicly reachable.
- Monthly archive navigation, pagination, and editorial archive content are reserved for v0.2.
- Publishing entries does not automatically update your R2 storage; manage files in Cloudflare separately.

## GitHub (private repository)
Create a new **private** repo called `rockymtnbabe-video-journal`, then in this project folder:

```
git init
git add .
git commit -m "Initial Video Journal v0.1"
git branch -M main
git remote add origin https://github.com/YOUR-ACCOUNT/rockymtnbabe-video-journal.git
git push -u origin main
```

Do not commit secrets, R2 API tokens, or a WordPress export. The built-in Gallery URL is public and not a secret.
