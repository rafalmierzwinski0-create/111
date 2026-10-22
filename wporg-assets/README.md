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

The mark is a table in a rounded square, the same one the dashboard
shows beside the plugin’s name, so the listing and the plugin read as one thing.
Rebuild them from `tools/logo/assets.html` if the colours ever move.

The screenshots come from a throwaway test site, with only the free plugin
active, dressed as three made-up businesses: the theme in
`tools/wporg-shots/theme/`, the sheet in `tests/fixtures/demo/`, the pages in
`tools/wporg-shots/layout.mjs` (saved through the block editor), and the
browser windows and phones round them in `tools/wporg-shots/frames.mjs`:

```
node tools/wporg-shots/shoot.mjs /tmp/lstab-env/wpshots http://127.0.0.1:8091
```

The script empties that site of tables and pages first, so never point it at
a site anyone uses.
