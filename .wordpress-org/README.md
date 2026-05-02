# WordPress.org listing assets

The `apermo/reusable-workflows` `wporg-deploy` job (`10up/action-wordpress-plugin-deploy`)
copies every file in this directory to the WordPress.org plugin's `/assets/`
SVN path on release. That SVN path is what powers the listing page on
wordpress.org/plugins — it does **not** ship inside the plugin zip.

## Required files

| File | Dimensions | Notes |
|---|---|---|
| `icon-128x128.png` | 128 × 128 | Square. Used as the plugin's avatar in search results and the directory listing. |
| `icon-256x256.png` | 256 × 256 | Square. Retina version of the above. |
| `banner-772x250.png` | 772 × 250 | Header banner on the plugin's listing page. |
| `banner-1544x500.png` | 1544 × 500 | Retina banner. WordPress.org serves whichever fits the viewport. |
| `screenshot-1.png` | any | Caption from `readme.txt → == Screenshots ==`, line 1. |
| `screenshot-2.png` | any | Caption from `readme.txt`, line 2. |
| `screenshot-3.png` | any | Caption from `readme.txt`, line 3. |
| `screenshot-4.png` | any | Caption from `readme.txt`, line 4. |

`.jpg`/`.jpeg` are also accepted in place of `.png` for any of the above.

Screenshot dimensions can be anything reasonable; WordPress.org scales
the listing thumbnails automatically. Aim for ~1280 px wide so the
detail page renders crisply on retina screens, and keep file size
under ~500 KB per image to keep the listing snappy.

## Caption convention

WordPress.org pairs each `screenshot-N.png` with the matching numbered
line under `readme.txt` `== Screenshots ==`. Add new screenshots in
order, keep the captions short (one sentence) and present-tense.

## Adding a new screenshot

1. Drop `screenshot-N.png` into this directory.
2. Add a matching line to `readme.txt` `== Screenshots ==`.
3. Bump the patch version (`Stable tag` in `readme.txt`, `Version` in
   `plugin.php`, `VERSION` in `src/Main.php`) and add a CHANGELOG
   entry under that version.

## Skipping deploy of a stale image

There's no per-file ignore list. Either replace the image or remove
the file outright; the SVN sync is one-way and reflects whatever is in
this directory at release time.
