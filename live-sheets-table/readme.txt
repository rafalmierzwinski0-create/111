=== Live Sheets Table – Google Sheets to WordPress ===
Contributors: livesheetstable
Tags: google sheets, table, spreadsheet, csv, data table
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.8.1
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
* A visual appearance editor: set colours, text size, row height and corners per table, with the preview updating as you go.
* A visible, draggable slider under any table too wide for its column, so nothing is ever hidden behind an invisible scrollbar.
* The first column stays pinned while the rest scrolls, so a price never stops belonging to a product — and can be switched off per table.
* Rename columns for your visitors, or leave a column out of the table entirely, without touching the spreadsheet.
* A layout control per source: scroll the table sideways, or stack each row into a card.
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

It stays a table, at full text size, and a slider appears underneath so it can be moved left and right. Nothing shrinks and nothing is hidden — the usual complaint about wide tables is microscopic type, which comes from squeezing a table rather than scrolling it.

The slider is drawn by the plugin rather than left to the browser, because macOS, iOS and Android hide the horizontal scrollbar until you are already scrolling, which is exactly when it is too late to be useful.

By default the table keeps its shape and gains a slider you can drag, so the text stays full size and nothing is hidden. To stack rows into cards instead, change "On screens too narrow for the whole table" on the source screen, or pass `layout="cards"` to the shortcode. Giving the block a wide or full alignment is often better still, since it hands the table the room it needs.

= Can I hide a column, or give it a different name? =

Yes, on the source screen. Renaming is display only: the plugin never writes to your spreadsheet, so a column can be called `cena_netto_bez_rabatu` in Google and simply "Price" on your page, and your formulas carry on working.

Hiding removes the column from the headings and from every row, so a working column is not just visually gone — its values never reach the page at all.

Columns are matched by position, so inserting one in Google shifts the settings. The plugin remembers which heading each position held and tells you when they no longer line up, instead of silently mislabelling data.

= Can I change how the table looks? =

Pick one of three presets, then fine-tune it: the source screen has colour pickers for text, background, headings, lines, striped rows, hover and accent, plus text size, row height and corner rounding. Anything you leave alone keeps following the preset, so changing one colour does not mean defining all of them.

Every value is a CSS custom property on `.lstab`, so a theme stylesheet can override the same things. Extra presets and a free-form custom CSS field are Pro features.

= Does WP-Cron have to be working? =

The scheduled refresh uses WP-Cron. If you have disabled it, use a real system cron calling `wp-cron.php`, or press "Refresh now". A failed schedule never blanks your table — the stored copy keeps rendering.

You do not have to keep an eye on it: if the schedule stops running, the plugin says so on its own screens and explains what it means, rather than letting the table go quietly stale.

= Can I show several different sheets? =

The free version stores three sheet sources. Pro removes the limit.

= Is the sheet content safe to display? =

Yes. Everything from the spreadsheet is escaped on output, so a cell containing HTML or a `<script>` tag is shown as text and cannot inject anything into your page.

== Screenshots ==

1. Adding a source: paste the link and the parsed table appears immediately, before anything is saved.
2. The source list, showing sync status, size, schedule and the ready-to-copy shortcode.
3. Switching between the tabs of a multi-tab spreadsheet.
4. The published table on a desktop screen.
5. The same table on a phone: full-size text, with a slider to move it sideways.
6. Searching inside the table.
7. Sorting by a column, numerically where the data is numeric.
8. A failed sync reported in the dashboard, with instructions.
9. The public page during that failure — the last good copy is still there.
10. The block in the editor, previewing the real server-rendered table.

== Changelog ==

= 1.8.1 =
* Changed: the malformed-sheet warning no longer names one cause. A row can come back short for several reasons, and pointing at only one sends people looking for the wrong thing; it now says what is wrong and what it means for the table, with the likely causes listed on the source screen where there is room for them.
* Fixed: a table that ends in a message for the site owner — not synced yet, source gone, filtering unavailable — did not load the stylesheet, so the message arrived as a bare paragraph that read like broken page content.

= 1.8.0 =
* Added: a table set to show only some of its rows now shows none of them when nothing is available to do the filtering, instead of falling back to every row. An add-on can be deactivated by an expired licence, a conflict or a tidy-up, and a page built to show one category would otherwise publish the whole sheet — working rows included — with nobody the wiser. An empty table is a gap someone fixes; a full one is a disclosure nobody notices.
* Added: the message after a save or a manual refresh now says when the sheet arrived malformed, rather than reporting a plain success. The fetch working and the sheet arriving intact are two different things.
* Added: that warning is now raised anywhere in the dashboard, not only on the plugin's own screens, with a link to the source and one to hide it. Hiding covers the fault that was found; a different one is raised again.

= 1.7.0 =
* Added: web and e-mail addresses in cells can be made clickable, per table. A link in a cell was otherwise plain text a visitor had to select and copy, which on a phone is close to impossible. Only http, https and e-mail become links, and they carry rel="nofollow ugc" because the sheet is not necessarily yours.
* Added: the dashboard now says when a sheet fetched correctly but arrived malformed. Google gives every row the same number of cells, so a row that disagrees is a fault worth naming — with the row number to look at. Visitors see the table as usual; only someone who can fix it is told.
* Added: linked addresses take the table's accent colour, so they read as links rather than as underlined body text.
* Added: an lstab_edit_page_settings hook, so an add-on can put its own fields on the source screen and have them saved with everything else.
* Fixed: row filtering ran after columns were hidden, so a filter naming a hidden column matched nothing and quietly returned every row. Filtering now runs first, which is also what makes "show one category, hide the category column" work.

= 1.6.0 =
* Fixed: one stray quotation mark anywhere in a sheet used to consume everything after it, collapsing a whole table into a single cell of run-together text. A quote now opens a field only at the start of one and closes it only where a real closing quote can appear, so a stray mark costs one character instead of every row.
* Fixed: the column settings sat outside the form, so renaming or hiding a column looked like it worked and then reverted on save. Nothing typed there was ever submitted.
* Fixed: an unticked "include" box was read as "no answer" rather than as "hide this column", so a column switched off came back visible.
* Changed: the column settings now appear from the start, greyed out and saying what they are waiting for, instead of materialising only after a sheet has been read once.
* Changed: the preview now shows renamed and hidden columns, so it matches the published page rather than the raw sheet.
* Changed: a table with room to spare now shares that room out equally between its columns instead of in proportion to what each already holds, which left one column sprawling beside several pinched ones. A column whose content genuinely needs more still takes it, and a table that has to scroll keeps its natural widths.

= 1.5.0 =
* Added: rename a column for your visitors, or leave it out of the table. Nothing is written back to Google, so a spreadsheet keeps its own headings — including working columns nobody should see.
* Added: because columns are matched by position, a column added or removed in Google is now reported in the dashboard rather than quietly shifting every label along.
* Added: pinning the first column can be switched off per table, for sheets whose first column is long text.

= 1.4.0 =
* Added: the first column stays pinned while a wide table scrolls sideways, so every row keeps its label. It is capped so it can never take up the whole screen, and shows a divider only once something is hidden behind it.
* Added: the dashboard now says when scheduled syncing has stopped running. A blocked WP-Cron does not break anything — pages keep serving the stored copy — it just quietly stops updating, which is the kind of fault nobody notices until a customer does.
* Fixed: the header background colour was reported as the table background, which made that control in the appearance editor look inert.

= 1.3.0 =
* Added: a visible, draggable slider beneath any table wider than its column. Browsers hide the horizontal scrollbar until you scroll on macOS, iOS and Android, which made a wide table look cut off; this one stays on screen while there is more to see, and works by drag, click, touch and keyboard.
* Changed: narrow screens now keep the table and scroll it, rather than stacking every row into a card. Text stays full size — the table moves, it does not shrink. The card layout is still available per source.
* Changed: the layout choice is now a setting on the source screen, so it is made once rather than repeated on every shortcode; the block and shortcode can still override it.
* Fixed: a table too wide for its column collapsed its columns to the narrowest possible, making rows several lines tall for no benefit. Columns now keep their natural width and the slider does the work.

= 1.2.0 =
* Added: a visual appearance editor on the source screen. Colours, text size, row height and corner rounding can be set per table, and the preview updates live. Anything left untouched follows the chosen preset, so one colour can be changed without redefining the rest.
* Changed: refined the Midnight and Editorial presets.

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

= 1.8.1 =
Clearer wording for the malformed-sheet warning, and admin-only messages now carry their own styling.

= 1.8.0 =
A filtered table now shows nothing rather than every row when filtering is unavailable, and a malformed sheet is reported across the dashboard.

= 1.7.0 =
Adds clickable links in cells and a warning when a sheet arrives malformed. Fixes filtering on a hidden column.

= 1.6.0 =
Fixes column renaming and hiding, which never saved, and evens out column widths on tables with room to spare.

= 1.5.0 =
Adds column renaming and hiding, and makes the pinned first column optional.

= 1.4.0 =
Pins the first column of a scrolling table, and warns when scheduled syncing has stopped.

= 1.3.0 =
Wide tables now scroll with a visible slider instead of stacking into cards. Existing tables switch automatically; choose "Turn each row into a labelled card" on the source screen to keep the old behaviour.

= 1.2.0 =
Adds a visual appearance editor for per-table colours and spacing.

= 1.1.0 =
Fixes wide tables losing columns in narrow theme columns, and style presets not applying. Clear any page cache after updating.

= 1.0.0 =
First release.
