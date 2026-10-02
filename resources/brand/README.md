# Brand Asset System

Vector masters and raster exports for the eight candidate identities defined in
[§105 of the master spec](../../docs/AI_Omnichannel_Lead_Generation_CRM_SaaS_Master_System_Prompt_UPDATED.md).

Everything here is **generated**. Do not hand-edit the output — edit the mark
geometry in [`scripts/brand/brands.mjs`](../../scripts/brand/generate.mjs) and
re-run the generator:

```bash
node scripts/brand/generate.mjs
```

## What gets produced

Per §105.3, filtered to the three artifacts the supplied brand board actually
shows (App Icon, Light Favicon, Dark Favicon) plus the lockups and the symbol
master they derive from.

### `resources/brand/<brand>/` — SVG masters

| File                        | Purpose                                                                  |
| --------------------------- | ------------------------------------------------------------------------ |
| `<brand>-symbol.svg`        | Symbol alone, brand gradient, transparent background. Source of truth.   |
| `<brand>-app-icon.svg`      | Gradient squircle, white knockout mark.                                  |
| `<brand>-favicon-light.svg` | Mark on `#F1F5F9` plate.                                                 |
| `<brand>-favicon-dark.svg`  | Mark on `#0B1220` plate, gradient shifted to lighter stops for contrast. |
| `<brand>-logo-light.svg`    | Horizontal lockup: symbol + wordmark + tagline, for light surfaces.      |
| `<brand>-logo-dark.svg`     | Same, for dark surfaces.                                                 |

### `public/brand/<brand>/` — raster exports

| File                              | Sizes                                                        |
| --------------------------------- | ------------------------------------------------------------ |
| `app-icon-<n>.png` / `.webp`      | 1024, 512, 192, 180                                          |
| `favicon-light-<n>.png` / `.webp` | 16, 32, 48, 64                                               |
| `favicon-dark-<n>.png` / `.webp`  | 16, 32, 48, 64                                               |
| `favicon.ico`                     | Multi-resolution bundle (16/32/48), light                    |
| `favicon-dark.ico`                | Multi-resolution bundle (16/32/48), dark                     |
| `favicon.svg`                     | Scalable symbol for `<link rel="icon" type="image/svg+xml">` |

WebP runs ~55% smaller than PNG at these sizes. Serve WebP as the primary and
keep PNG as the fallback; `.ico` exists only for legacy browsers and for
Windows pinned-site tiles.

## The eight identities

| Brand     | Primary → Secondary   | Accent    | Mark                                      |
| --------- | --------------------- | --------- | ----------------------------------------- |
| Leadora   | `#4F46E5` → `#2563EB` | `#06B6D4` | Abstract L with forward foot + AI spark   |
| LeadPulse | `#0F766E` → `#14B8A6` | `#2563EB` | Heartbeat waveform                        |
| LeadMinds | `#4F46E5` → `#7C3AED` | `#A855F7` | Head silhouette, knocked-out node network |
| LeadFlow  | `#2563EB` → `#0EA5E9` | `#10B981` | Three staggered directional streaks       |
| Revora    | `#059669` → `#10B981` | `#2563EB` | Rising revenue bars + trend arrow         |
| LeadForge | `#EA580C` → `#F97316` | `#EF4444` | Slanted geometric F                       |
| LeadOrbit | `#6D28D9` → `#7C3AED` | `#4F46E5` | Orbital ring around an opportunity node   |
| LeadNest  | `#059669` → `#0D9488` | `#14B8A6` | Leaf-cradle holding three ascending leads |

## Design constraints these marks are built against

- **Legible at 16px.** Each mark is a handful of heavy shapes. Anything that
  survived only at 64px was redesigned — LeadMinds moved from an outlined head
  to a filled silhouette with high-contrast knockouts, and LeadNest's cradle was
  given a pointed base because a round-bottomed vessel under three nodes reads
  unmistakably as a smiley face.
- **Favicons are symbol-only** (§105.3). No mark depends on readable text.
- **Rendered once at 1024px, then downsampled** with Lanczos. Rasterising each
  size directly from SVG produces visibly worse antialiasing at 16px.
- **Knockout-aware.** `mark(P, A, K)` takes a knockout paint so silhouette marks
  cut detail against whatever reads as background in that variant.

## Known limitation: live text in the lockups

`*-logo-*.svg` uses live `<text>` with `font-family: Inter`. That renders
correctly wherever Inter is installed or loaded, but **not** in isolated
contexts — third-party viewers, PDF pipelines, print vendors.

Before any production or print use, open the lockups in a vector editor and
convert the text to outlines. The symbol, app icon and favicons are pure
geometry and are unaffected.

## Selecting the production brand

Nothing is wired into the app yet — all eight sit here as an archive.

1. Pick the brand.
2. Set its palette as the brand token layer (see
   [`docs/plan/04-UI-SYSTEM.md`](../../docs/plan/04-UI-SYSTEM.md)); the accent
   layer is deliberately separate from the product's Deep Navy + Indigo
   foundation so the two can change independently.
3. Copy `public/brand/<brand>/favicon*` to `public/`, and point the
   `<link rel="icon">` / `apple-touch-icon` tags at them.
4. Reference the lockups from the app shell and the marketing site.

> **Before commercial launch**, run independent trademark, company-name,
> social-handle and domain availability checks (§105.1). None of these names
> have been cleared — they are generated candidates.
