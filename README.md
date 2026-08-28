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

## How the table decides to be a table

A table stays a table while it fits and reflows into one labelled card per row
when it does not. The threshold scales with the column count — five columns
need far more room than two — because a fixed breakpoint leaves a wide table
clipped inside a typical 650px theme column, hiding the last columns behind an
invisible scrollbar.

Authors who want to override the decision can set the block's Layout control,
or `layout="table"` / `layout="cards"` on the shortcode. A table pinned to
`table` scrolls sideways instead, with a shaded edge so a clipped column always
announces itself. Giving the block a wide or full alignment is usually the
better answer, since it hands the table the room it needs.

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
    views/                    Admin templates
  blocks/sheet-table/       block.json + plain-ES5 editor script (no build step)
  assets/                   Front-end and admin CSS/JS
  languages/                POT, and a complete Polish PO/MO
  readme.txt                WordPress.org readme (English)
  readme-pl.txt             Polish translation of the readme

tests/                      Verification
  e2e-test.php              155-assertion suite against a real WordPress
  harness/browser-test.mjs  38-assertion Playwright suite + screenshot capture
  harness/                  Site templates, install/activate/seed helpers
  mock-google-mu-plugin.php Answers docs.google.com from local fixtures
  fixtures/                 Deliberately awkward CSV and an htmlview page
  setup-env.sh              Builds three WordPress sites from scratch
  run-all.sh                Runs everything

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

## Extension points

Every free/Pro boundary is a filter, so the Pro add-on lifts limits without
forking the free plugin. The suite exercises these by simulating Pro.

| Hook | Purpose |
| --- | --- |
| `lstab_is_pro` | Flips the tier |
| `lstab_max_sources` | Number of saved sources (free: 3) |
| `lstab_min_sync_interval` | Fastest sync in seconds (free: 900) |
| `lstab_style_presets` | Register presets, or unlock the Pro-marked ones |
| `lstab_render_cell` | Replace a cell's HTML — conditional formatting |
| `lstab_column_alignments` | Override which columns are treated as numeric |
| `lstab_cell_attributes` | Add attributes to a cell |
| `lstab_render_rows` | Filter the rows rendered — pagination |
| `lstab_fetch_url` / `lstab_fetch_args` | Route fetches elsewhere — private sheets |
| `lstab_parsed_table` | Transform the parsed table before it is stored |
| `lstab_rendered_table`, `lstab_wrapper_classes` | Adjust the output |
| `lstab_before_sync`, `lstab_after_sync`, `lstab_sync_failed` | Sync lifecycle |
| `lstab_source_saved`, `lstab_source_deleted` | Source lifecycle |

## Licence

GPL-2.0-or-later.
