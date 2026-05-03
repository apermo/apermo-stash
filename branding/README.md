# LinkStash branding source files

This directory holds the source-of-truth artwork for the LinkStash brand
mark. It is **excluded from the plugin zip** via `.gitattributes`
(`/branding/ export-ignore`) and from the WordPress.org `/trunk/` deploy
via the same mechanism — see `10up/action-wordpress-plugin-deploy`'s
documentation on `.gitattributes` fallback.

| File | Purpose |
|---|---|
| `logo.svg` | Vector master. Use this when generating new derived sizes. |
| `logo.png` | Raster export at the master's native resolution. |

## Derived files (NOT in this directory)

The WordPress.org listing uses fixed-dimension PNGs that live in
`.wordpress-org/`:

- `icon-128x128.png` — square 128 × 128.
- `icon-256x256.png` — square 256 × 256.
- `banner-772x250.png` — header banner.
- `banner-1544x500.png` — retina header banner.

See `.wordpress-org/README.md` for the full list and dimensions.

## Generating new sizes

If you have ImageMagick installed:

```bash
magick branding/logo.svg -resize 128x128 -background none .wordpress-org/icon-128x128.png
magick branding/logo.svg -resize 256x256 -background none .wordpress-org/icon-256x256.png
```

For banners (which combine the logo with text/composition), prefer a
proper design tool (Figma, Affinity, etc.) rather than a one-shot
ImageMagick resize.
