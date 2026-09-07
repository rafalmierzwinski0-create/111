# WordPress.org listing assets

These do **not** go in the plugin zip. They live in the `assets/` folder at the
top of the plugin's SVN checkout, beside `trunk/` and `tags/`:

```
live-sheets-table/
├── assets/          ← these files
├── tags/
└── trunk/           ← the plugin itself
```

| File | Where it shows |
|---|---|
| `icon-128x128.png` | Search results and the plugin card in wp-admin |
| `icon-256x256.png` | The same, on a high-resolution screen |
| `banner-772x250.png` | The top of the plugin's page in the directory |
| `banner-1544x500.png` | The same, on a high-resolution screen |
| `screenshot-1.png` … `screenshot-10.png` | The gallery on the plugin's page, in this order |

The screenshots are numbered to match the captions under `== Screenshots ==`
in `readme.txt`: caption 1 belongs to `screenshot-1.png` and so on. Change one
and the other has to move with it, or the gallery starts describing the wrong
picture.

Both sizes of each are wanted: the directory picks by screen, and a listing
with only the small one looks soft on every modern laptop.

The mark is the selected range with its handle — the same frame the dashboard
draws around every block, so the listing and the plugin read as one thing.
Rebuild them from `tools/logo/assets.html` if the colours ever move.
