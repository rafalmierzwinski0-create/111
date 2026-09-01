# Live Sheets Table

A WordPress plugin that publishes a Google Sheet as a fast, responsive,
auto-refreshing table. This repository holds the plugin source, the local test
environment used to verify it, and the packaging scripts.

The distributable plugin is `live-sheets-table/`; everything else is
development tooling and is not shipped.

## Why it is built this way

The design answers specific, repeated complaints about the incumbent plugin in
this niche, rather than adding features for their own sake.

| Complaint about the incumbent | What this plugin does |
| --- | --- |
| Tables hang on "loading" or never appear | Data is fetched by WP-Cron in the background and stored locally. Pages render that copy in PHP and never wait on Google. |
| Tables sometimes render as raw code | Rendering is server-side and produces an escaped, well-formed `<table>`. A test asserts the output parses as valid HTML and that cell tags balance. |
| The free version caps rows at 30 | No row cap anywhere. A test renders a 5 000-row table and counts every row. Three sources, against the incumbent's one. |
| Conflicts break the whole site | No global JavaScript, no bundled libraries, everything prefixed `lstab_` / `LSTAB_`, assets enqueued only on pages that actually contain a table. |
| Basic configuration is confusing | One three-step screen, a parsed preview before anything is saved, and a tab picker for multi-tab spreadsheets. |

The behaviour that matters most: **a failed fetch never changes the front end.**
The stored snapshot is only ever replaced by a *successful* fetch, so a sheet
turning private, a timeout, a 403 or a Google sign-in page all leave the last
good copy on the page. The failure is reported in the dashboard, where someone
can act on it, and never to visitors.

## Wide tables

A table wider than its column keeps its shape and gains a **visible, draggable
slider** underneath. That is deliberate: macOS, iOS and Android render
horizontal scrollbars as overlays that only appear once you are already
scrolling, so a wide table looks simply cut off, which is what makes wide
tables feel broken. The slider is the plugin's own — drag, click, touch and
keyboard — and hides itself when the table fits.

Text is never shrunk to fit. The usual complaint about wide tables is
microscopic type, and that comes from squeezing a table rather than moving it.

Two details matter for this to read well:

- `width: 100%` alone makes a table that cannot fit collapse to *min-content*,
  wrapping every column as hard as possible and producing rows several lines
  tall for no benefit, since it still scrolls. `min-width: max-content` keeps
  columns at their natural width (capped per cell) and lets the slider work.
- Column count still decides the stacking threshold for sources that opt into
  the card layout, since five columns need far more room than two.

The card layout — one labelled card per row — remains available per source, and
suits tables of long prose. The choice lives on the source screen; the block
and shortcode can override it with `layout="table"`, `"cards"` or `"auto"`.

Columns whose values are overwhelmingly numeric are detected and right-aligned
with tabular figures, so decimal points line up. The heuristic tolerates
thousands separators, comma decimals, currency symbols and suffixes, and
percentages, and requires a clear majority so a stray number cannot flip a text
column.

## Repository layout

```
live-sheets-table/          The plugin — this is what ships
  live-sheets-table.php     Bootstrap and constants
  includes/                 Classes, one responsibility each
    class-lstab-storage.php   Custom table; snapshot only replaced on success
    class-lstab-url.php       Sheet URL parsing, host allow-list, endpoints
    class-lstab-csv-parser.php RFC 4180 parser (quotes, newlines, BOM, UTF-8)
    class-lstab-fetcher.php   HTTP layer and actionable error messages
    class-lstab-sync.php      Orchestration and the fallback guarantee
    class-lstab-cron.php      One recurring tick drives every source
    class-lstab-renderer.php  The single renderer behind block and shortcode
    class-lstab-block.php     Dynamic block registration
    class-lstab-shortcode.php Thin adapter over the renderer
    class-lstab-rest.php      Preview, source list, manual refresh
    class-lstab-admin.php     Dashboard screens and admin-post handlers
    class-lstab-limits.php    Every free/Pro boundary, behind filters
    class-lstab-customizer.php Editable appearance tokens and their sanitising
    class-lstab-columns.php   Display labels, visibility, and drift detection
    views/                    Admin templates
  blocks/sheet-table/       block.json + plain-ES5 editor script (no build step)
  assets/                   Front-end and admin CSS/JS
  languages/                POT, and a complete Polish PO/MO
  readme.txt                WordPress.org readme (English)
  readme-pl.txt             Polish translation of the readme

tests/                      Verification
  e2e-test.php              Free plugin suite against a real WordPress
  pro-test.php              Pro add-on suite, with Google's OAuth mocked
  harness/browser-test.mjs  38-assertion Playwright suite + screenshot capture
  harness/                  Site templates, install/activate/seed helpers
  mock-google-mu-plugin.php Answers docs.google.com from local fixtures
  fixtures/                 Deliberately awkward CSV and an htmlview page
  setup-env.sh              Builds three WordPress sites from scratch
  run-all.sh                Runs everything

live-sheets-table-pro/      The paid add-on — a separate plugin
  includes/
    class-lstabp-google-auth.php    OAuth client, token storage and refresh
    class-lstabp-private-sheets.php Authenticated fetch for private sheets
    class-lstabp-filters.php        Row filtering for one-sheet-many-pages
    class-lstabp-settings.php       Connection and per-sheet settings

tools/
  make-pot.php              String extractor and PO/MO compiler
  translations/pl_PL.php    The Polish translation table
  build-zip.sh              Packages build/live-sheets-table.zip

build/live-sheets-table.zip Installable archive
screenshots/                Captured by the browser suite
```

## Running the tests

Requirements: PHP 8 with `pdo_sqlite`, Node 18+, `git`, `zip`/`unzip`. No MySQL
and no internet access to Google are needed.

```bash
tests/setup-env.sh    # builds and starts three WordPress sites
tests/run-all.sh      # runs every suite
```

`setup-env.sh` creates three sites under `$LSTAB_SCRATCH` (default
`/tmp/lstab-env`), each on SQLite via the official
`sqlite-database-integration` drop-in and served by PHP's built-in server:

| Site | Port | Purpose |
| --- | --- | --- |
| `wp` | 8088 | WordPress 6.8, the oldest branch the plugin claims support for |
| `wp71` | 8089 | WordPress 7.1, the current release |
| `wpzip` | 8090 | Empty site; the built zip is installed into it |

`run-all.sh` then runs the PHP suite on 6.8 and 7.1, builds the zip, installs
and activates it on the clean site, runs the PHP suite again against that
*packaged* copy, and finally runs the browser suite and captures the
screenshots. It fails if the plugin raises a single PHP notice.

### Elementor

The widget's own suite (`tests/elementor-test.php`) runs against a checkout of
Elementor placed at `$LSTAB_SCRATCH/wp71/wp-content/plugins/elementor`, and is
skipped with a note when that is missing. A plain `git clone` is enough for the
PHP suite and for rendering the widget on the front end, but **not** for opening
Elementor's editor: the editor's JavaScript is not committed to their
repository. To photograph the editor panel, build it once —

```bash
git clone --depth 1 https://github.com/elementor/elementor.git "$LSTAB_SCRATCH/elementor"
cd "$LSTAB_SCRATCH/elementor" && npm install --ignore-scripts && npm run build
cp -r build "$LSTAB_SCRATCH/wp71/wp-content/plugins/elementor"
```

Copy it rather than symlinking: `plugins_url()` resolves a symlinked plugin
directory to its real path, and Elementor then asks the browser for its assets
under a URL that does not exist. PHP's realpath cache means the web server has
to be restarted after the swap, too.

### How Google is faked

`tests/mock-google-mu-plugin.php` hooks WordPress's own `pre_http_request`
filter, so every line of the plugin's fetch, parse, cron and fallback code runs
exactly as in production — only the network hop is replaced. A JSON state file
selects the scenario:

```json
{ "mode": "ok" | "http_403" | "timeout" | "html_login" | "empty", "tab": "main" | "second" }
```

The fixture CSV is deliberately awkward: a UTF-8 BOM, Polish diacritics, quoted
commas, doubled quotes, a newline inside a quoted field, an empty cell, and a
cell containing `<script>alert('xss')</script>` to prove escaping.

## Regenerating translations

```bash
php tools/make-pot.php /path/to/wordpress
```

This extracts every string from the PHP and JS sources, writes
`languages/live-sheets-table.pot`, and compiles a `.po`/`.mo` pair for each
table in `tools/translations/`. It prints any string that lacks a translation,
so a missing entry is visible rather than silently falling back to English.
There are no gettext binaries in this environment, so it uses WordPress's own
POMO classes.

## Building the zip

```bash
tools/build-zip.sh
```

Refuses to produce an archive if `readme.txt` is missing, if its stable tag
disagrees with the plugin header version, if the compiled catalogues are
absent, or if any PHP file fails a syntax check.

## The Pro add-on

`live-sheets-table-pro/` is a separate plugin, deliberately: WordPress.org
allows neither paid code inside a free plugin nor a free plugin that downloads
its paid half at runtime. It reaches the free plugin only through published
hooks, and the free plugin has no idea it exists — a claim the Pro suite
asserts by checking that no column and no credential ends up in the free
schema.

It adds:

- **Private sheets.** The free plugin reads a sheet's public CSV export, which
  needs the sheet shared as "anyone with the link" — fine for a price list,
  useless for buying prices, stock or client data. Pro connects a Google
  account and reads through the Sheets API instead, so the spreadsheet can stay
  entirely private. The OAuth client is the site owner's own, from their own
  Google Cloud project: routing every customer's sheets through one shared
  client would make this plugin a data processor for all of them. Only
  `spreadsheets.readonly` is requested.
- **Filtered views.** One saved sheet feeds as many pages as you like:
  `[sheet_table id="1" filter="Kategoria is Rowery"]`. Filtering happens at
  render time on the stored copy, so it costs no extra request to Google.
- Higher limits, one-minute syncing and the premium presets.

**Untested against live Google.** This environment cannot reach
`accounts.google.com`, so the token exchange, refresh and authenticated fetch
are exercised against a mock that reproduces Google's behaviour — including the
detail that a refresh response omits the refresh token. The consent screen
itself has never run for real and should be walked through once before release.

### Why the filter syntax uses words

`filter="Cena lt 100"` rather than `filter="Cena<100"` because WordPress blanks
any shortcode attribute containing an unclosed `<` as an XSS precaution, so the
symbol form silently arrives empty. Symbols still work where they survive
(`=`, `>`, and an entity-encoded `&lt;`); the words work everywhere, which is
why they are what the settings screen documents. The suite asserts both forms,
and asserts that WordPress really does blank the raw `<`.

## Appearance overrides

Every colour and metric the stylesheet uses is a CSS custom property on the
table wrapper, so the visual editor on the source screen is simply those
properties set inline: no generated stylesheet, no `!important`, and a control
left untouched falls straight back to the preset. Inline properties also beat
the dark-scheme block, so an explicit choice wins there too.

`LSTAB_Customizer` is the registry. Colours are validated with
`sanitize_hex_color()` and metrics are keyword lookups, so nothing
caller-supplied reaches the `style` attribute unchecked — the suite feeds it
`expression()`, `javascript:` and markup payloads and asserts none survive.

`lstab_customizer_enabled` gates the whole panel, so it can be moved behind the
Pro add-on later without touching this code.

## Extension points

Every free/Pro boundary is a filter, so the Pro add-on lifts limits without
forking the free plugin. The suite exercises these by simulating Pro.

| Hook | Purpose |
| --- | --- |
| `lstab_is_pro` | Flips the tier |
| `lstab_max_sources` | Number of saved sources (free: 6) |
| `lstab_min_sync_interval` | Fastest sync in seconds (free: 900) |
| `lstab_style_presets` | Register presets, or unlock the Pro-marked ones |
| `lstab_render_cell` | Replace a cell's HTML — conditional formatting |
| `lstab_column_alignments` | Override which columns are treated as numeric |
| `lstab_customizer_enabled` | Turn the visual appearance editor on or off |
| `lstab_customizer_colors` / `lstab_customizer_metrics` | Add or remove editable tokens |
| `lstab_shortcode_atts` | Register a shortcode attribute, which then reaches the renderer |
| `lstab_cell_attributes` | Add attributes to a cell |
| `lstab_render_rows` | Filter the rows rendered — pagination |
| `lstab_fetch_url` / `lstab_fetch_args` | Route fetches elsewhere — private sheets |
| `lstab_refresh_on_view` | Return false to never check a stale table while a visitor waits |
| `lstab_parsed_table` | Transform the parsed table before it is stored |
| `lstab_rendered_table`, `lstab_wrapper_classes` | Adjust the output |
| `lstab_before_sync`, `lstab_after_sync`, `lstab_sync_failed` | Sync lifecycle |
| `lstab_source_saved`, `lstab_source_deleted` | Source lifecycle |

## Licence

GPL-2.0-or-later.
