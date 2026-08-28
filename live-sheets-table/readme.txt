=== Live Sheets Table – Google Sheets to WordPress ===
Contributors: livesheetstable
Tags: google sheets, table, spreadsheet, csv, data table
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish a Google Sheet as a fast, responsive, auto-refreshing table. No row limit, no API key, and the page never breaks when Google is unreachable.

== Description ==

Live Sheets Table turns a Google Sheet into a real table on your WordPress site. Share the sheet as "Anyone with the link – Viewer", paste the link, check the preview, and drop it on a page with a block or a shortcode. Edit the spreadsheet and your site follows.

= No row limit =

Your table has as many rows as your sheet does. The free version does not cap them at 30, 50 or 100.

= The page renders on the server, from a local copy =

Most sheet plugins fetch from Google while your visitor waits, which is why tables so often hang on "loading" or flash raw markup. This plugin does the opposite:

* A scheduled job fetches the sheet in the background and stores it in your database.
* Pages render that local copy in PHP, as a real `<table>` element.
* Nothing depends on JavaScript to draw the table, so it is readable to search engines and to browsers where a script has failed.

= It keeps working when the sheet does not =

If a fetch fails — someone flipped the sheet to private, Google rate-limited you, the network blipped — the last good copy stays on the page. The stored snapshot is only ever replaced by a successful fetch.

The failure is reported where it can be acted on: the dashboard shows what broke and how to fix it. Visitors never see an error, an empty table, or a stack trace.

= Genuinely responsive, not just "it scrolls" =

On a narrow screen the table reorganises into one card per row, each field labelled, instead of forcing a horizontal scroll through six-point text. Wide tables inside narrow sidebars reflow too, because the breakpoint follows the container, not the viewport.

= Check before you publish =

Paste your link and the parser shows you exactly what it read — headings, rows, merged-cell damage, the wrong tab — in the dashboard, before anything is saved. Multi-tab spreadsheets get a tab picker.

= What you get =

* Unlimited rows.
* Three saved sheet sources.
* A "Google Sheets Table" block plus a `[sheet_table id="123"]` shortcode, both driven by the same renderer.
* Background sync every 15 minutes, plus a "Refresh now" button.
* Optional search box and sortable columns (numeric-aware, so 1 215,50 sorts above 349,00).
* Numeric columns detected and right-aligned with tabular figures, so decimals line up.
* Three polished style presets, each following the reader's light or dark colour scheme.
* A layout control: let the table decide when to become cards, or pin it to one or the other.
* An "updated N minutes ago" label you can switch off.
* Full translation support.

= Pro =

Live Sheets Table Pro adds unlimited sheet sources, syncing as often as every minute, conditional cell formatting, pagination for large tables, premium presets and custom CSS, private-sheet support through an authenticated connection, a multi-site licence and priority support.

= Privacy =

The plugin talks to `docs.google.com` and nowhere else, only to download the sheets you configure. It sends no analytics and registers no external services. Sheet data is stored in your own database.

== Installation ==

1. Install and activate the plugin.
2. In Google Sheets open your spreadsheet and choose **Share → General access → Anyone with the link**, role **Viewer**. No API key or Google Cloud project is required.
3. Copy the address from your browser's address bar.
4. In WordPress go to **Sheets Tables → Add new**, paste the link and choose **Load preview**.
5. Check the preview, pick the tab if your spreadsheet has several, choose a style, and save.
6. Add the table to a page with the **Google Sheets Table** block, or paste the shortcode shown on the source list.

== Frequently Asked Questions ==

= Do I need a Google API key? =

No. The plugin reads the sheet's public CSV export, which works for any spreadsheet shared as "Anyone with the link – Viewer". Private sheets through an authenticated connection are a Pro feature.

= Do I have to use "Publish to web"? =

No. Link sharing is enough. Publish-to-web URLs are also accepted if you already use one.

= Is there a row limit? =

No. The free version renders every row your sheet contains.

= How quickly do changes appear? =

By default the plugin checks Google every 15 minutes, and you can trigger a refresh by hand at any time. Pro shortens the interval to one minute.

Because pages render from the stored copy, a visitor never waits for Google — the trade-off is that an edit becomes visible on the next sync rather than instantly.

= What happens if the sheet becomes private or Google is down? =

Your page keeps showing the last successfully fetched version. The dashboard flags the failure and explains what to change; visitors see nothing unusual.

= Will it work with my page builder? =

Yes. The shortcode `[sheet_table id="123"]` works anywhere shortcodes are run — Elementor, Divi, Beaver Builder, classic editor, widgets. The block and the shortcode share one renderer, so they always produce the same table.

= My table is very wide. What happens on phones? =

Each row becomes a labelled card, so every value stays readable at full size. There is no horizontal scrolling and no shrunken text.

The switch happens whenever the space per column drops too low, which depends on how many columns you have — so it also fires for a five-column table sitting in a narrow desktop theme column, not only on phones.

If you would rather always have a table, set the layout to "Always a table" in the block, or `layout="table"` in the shortcode; it will scroll sideways instead, with a shaded edge showing there is more to see. `layout="cards"` forces the opposite. Giving the block a wide or full alignment is usually the better fix, since it hands the table the room it needs.

= Can I change how the table looks? =

Pick one of three presets, or target the `.lstab-table` classes from your theme's CSS. Every colour is a CSS custom property on `.lstab`, so overriding one value restyles the whole table. Extra presets and a built-in custom CSS field are Pro features.

= Does WP-Cron have to be working? =

The scheduled refresh uses WP-Cron. If you have disabled it, use a real system cron calling `wp-cron.php`, or press "Refresh now". A failed schedule never blanks your table — the stored copy keeps rendering.

= Can I show several different sheets? =

The free version stores three sheet sources. Pro removes the limit.

= Is the sheet content safe to display? =

Yes. Everything from the spreadsheet is escaped on output, so a cell containing HTML or a `<script>` tag is shown as text and cannot inject anything into your page.

== Screenshots ==

1. Adding a source: paste the link and the parsed table appears immediately, before anything is saved.
2. The source list, showing sync status, size, schedule and the ready-to-copy shortcode.
3. Switching between the tabs of a multi-tab spreadsheet.
4. The published table on a desktop screen.
5. The same table on a phone: one labelled card per row, no horizontal scrolling.
6. Searching inside the table.
7. Sorting by a column, numerically where the data is numeric.
8. A failed sync reported in the dashboard, with instructions.
9. The public page during that failure — the last good copy is still there.
10. The block in the editor, previewing the real server-rendered table.

== Changelog ==

= 1.1.0 =
* Fixed: wide tables could hide their last columns inside a narrow theme column, with no scrollbar and no switch to the card layout. The point at which a table becomes cards now depends on how many columns it has.
* Fixed: choosing a style preset did not change the preview, and editing a saved source always previewed the default preset.
* Fixed: stylesheets and scripts were served under a fixed version, so an upgrade could keep using the previous release's cached CSS. Asset URLs now change whenever the file does.
* Added: three saved sheet sources in the free version, up from one.
* Added: a layout control (automatic, always a table, always cards) on the block and the shortcode.
* Added: a preview width switcher, so the table and card layouts can both be checked before publishing.
* Added: numeric columns are detected and right-aligned with tabular figures.
* Added: light and dark colour schemes for the free presets.
* Changed: refreshed table styling, and an edge fade marking a table that scrolls sideways.
* Changed: listing sources no longer loads every stored snapshot; single sources are read through the object cache.

= 1.0.0 =
* First release.
* Google Sheets source management with a live parsed preview before saving.
* Tab discovery for multi-tab spreadsheets.
* Background sync on WP-Cron with a configurable interval and a manual refresh.
* Last-good-copy fallback so a failed fetch never changes the front end.
* Server-side rendering shared by the Gutenberg block and the shortcode.
* Responsive card layout on narrow containers, driven by container queries.
* Optional search and numeric-aware column sorting.
* Three style presets, with light and dark colour schemes.
* Numeric column detection with right alignment and tabular figures.
* A layout control for pinning the table or card presentation.
* RFC 4180 CSV parser handling quoted commas, embedded quotes and newlines, and UTF-8 with or without a BOM.
* Full internationalisation, with a Polish translation included.

== Upgrade Notice ==

= 1.1.0 =
Fixes wide tables losing columns in narrow theme columns, and style presets not applying. Clear any page cache after updating.

= 1.0.0 =
First release.
