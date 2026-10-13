=== Live Sheets Table ===
Contributors: livesheetstable
Tags: google sheets, table, spreadsheet, csv, data table
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.43.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish a Google Sheet as a fast, responsive, auto-refreshing table. No row limit, no API key, and the page never breaks when Google is unreachable.

== Description ==

Live Sheets Table turns a Google Sheet into a real table on your WordPress site. Share the sheet as "Anyone with the link – Viewer", paste the link, check the preview, and drop it on a page with a block, an Elementor widget or a shortcode. Edit the spreadsheet and your site follows.

No API key. No Google Cloud project. No account with us.

= No row limit =

Your table has as many rows as your sheet does. Nothing caps them at 30, 50 or 100.

= The page renders on the server, from a local copy =

Most sheet plugins fetch from Google while your visitor waits, which is why tables so often hang on "loading" or flash raw markup. This plugin does the opposite:

* A scheduled job fetches the sheet in the background and stores it in your database.
* Pages render that local copy in PHP, as a real `<table>` element.
* A table older than its interval is checked before the page is drawn, with a hard four-second cap and the stored copy as the fallback — so a site nobody visits, or a host that blocks WordPress schedules, does not quietly publish last week's prices.
* It works behind a page cache too. LiteSpeed Cache, WP Rocket and the like serve stored pages without starting WordPress, so nothing would ever notice a table falling behind. Instead, the page itself checks a small file that says when each table is next due and, only if one is overdue, asks the site in the background to update it. The visitor waits for nothing, the stored copies of the pages showing that table are cleared, and the new table replaces the old one on screen. No cron job to set up.
* Nothing depends on JavaScript to draw the table, so it is readable to search engines and to browsers where a script has failed.

= It keeps working when the sheet does not =

If a fetch fails — someone flipped the sheet to private, Google rate-limited you, the network blipped — the last good copy stays on the page. The stored snapshot is only ever replaced by a successful fetch.

The failure is reported where it can be acted on: the dashboard shows what broke and how to fix it. Visitors never see an error, an empty table, or a stack trace.

= Genuinely responsive, not just "it scrolls" =

On a narrow screen the table reorganises into one card per row, each field labelled, instead of forcing a horizontal scroll through six-point text. Wide tables inside narrow sidebars reflow too, because the breakpoint follows the container, not the viewport.

= Check before you publish =

Paste your link and the dashboard shows you exactly what it read — headings, rows, merged-cell damage, the wrong tab — before anything is saved. A spreadsheet with several tabs offers a tab picker, already set to the tab your link points at.

= Getting the data in =

* As many sheets as you like.
* Unlimited rows in every one of them.
* A tab picker for spreadsheets with more than one tab.
* Background sync every 15 minutes, plus a "Refresh now" button.
* A guarantee that whoever opens the page sees data no older than the interval you set — if the schedule has not run, the check happens as the page is drawn, capped at four seconds and falling back to the copy you already have.
* A warning in the dashboard when a sheet parses ragged — a row with more cells than there are headings, usually a merged cell — naming the row to look at.

= Putting it on a page =

* A "Google Sheets Table" block, an Elementor widget, and a `[sheet_table id="123"]` shortcode — all three driven by the same renderer, so the three routes cannot drift apart.
* A caption above the table, in your words rather than the sheet's.
* Rename a column for your visitors without touching the spreadsheet.
* Hide columns and rows by clicking them in a picture of your own sheet, or move a column into a panel that opens under each row.
* A layout control per source: scroll the table sideways, or stack each row into a card.
* An "updated N minutes ago" label you can switch off.
* Web and e-mail addresses in cells become links, safely, and can be turned off per table.

= Reading a long or wide table =

* An optional search box, which highlights what it matched rather than leaving you to find it.
* Sortable columns, numeric-aware, so 1 215,50 sorts above 349,00.
* Numeric columns detected and right-aligned with tabular figures, so decimals line up.
* Optional paging, up to 500 rows a page — and searching and sorting still cover the whole sheet, not just the page you are looking at.
* Column headings stay in view while the page scrolls past them.
* The first column stays put while the rest scrolls sideways, so a price never stops belonging to a product — and can be switched off per table.
* A visible, draggable slider under any table too wide for its column, so nothing is ever hidden behind an invisible scrollbar. The End key jumps to the far edge.

= Making it look like your site =

* Four style presets: Clean, Striped and Bordered follow the reader's light or dark colour scheme, and Midnight is dark throughout.
* A visual appearance editor: colours, text size, row height and corners per table, with the preview updating as you go.
* Your own CSS per table, for administrators allowed to write it, checked before it is saved.

= In your dashboard =

* A card per sheet saying when it last synced, whether the last checks succeeded, how many rows and columns it holds, and which pages use it.
* Errors are shown to administrators only, with what to do about them.
* Every word of the plugin's own text is ready to be translated.
* Nothing is deleted when you remove the plugin unless you ask for that on the settings screen.

= Privacy =

The plugin talks to `docs.google.com` and nowhere else, only to download the sheets you configure. It sends no analytics and registers no external services. Sheet data is stored in your own database. A small file in your uploads folder, `live-sheets-table-due.json`, records when each table is next due; it holds no sheet content and is removed when the plugin is deleted. Visitors' browsers only ever talk to your own site, and nothing is stored on their device.

== External services ==

This plugin connects to Google Sheets (`docs.google.com`), a service run by Google, to read the spreadsheets you add to it. Without that connection there is nothing to show.

* **What is sent:** the ID of the spreadsheet and the tab you chose, in an ordinary request from your web server to Google's public export address. Nothing about your visitors, your site's users or your content is sent.
* **When:** when you preview or save a sheet in the dashboard, on the schedule you set for each table, and when a table is past its schedule as a page holding it is viewed.
* **Only your server talks to Google.** Visitors' browsers never contact Google on the plugin's behalf.

Google's terms of service: https://policies.google.com/terms
Google's privacy policy: https://policies.google.com/privacy

== Installation ==

1. Install and activate the plugin.
2. In Google Sheets open your spreadsheet and choose **Share → General access → Anyone with the link**, role **Viewer**. No API key or Google Cloud project is required.
3. Copy the address from your browser's address bar.
4. In WordPress go to **Sheets Tables → Add new**, paste the link and choose **Load preview**.
5. Check the preview, pick the tab if your spreadsheet has several, choose a style, and save.
6. Add the table to a page with the **Google Sheets Table** block, or paste the shortcode shown on the source list.

== Frequently Asked Questions ==

= Do I need a Google API key? =

No. The plugin reads the sheet's public CSV export, which works for any spreadsheet shared as "Anyone with the link – Viewer".

= Do I have to use "Publish to web"? =

No. Link sharing is enough. Publish-to-web URLs are also accepted if you already use one.

= Is there a row limit? =

No. The plugin renders every row your sheet contains. The one limit is size: a tab can hold up to 10 MB of data, around a hundred thousand rows of short values, which is far more than one page can show comfortably. A bigger tab is refused with a note in the dashboard, and the copy already held stays on show. Developers can change the size with the `lstab_max_sheet_bytes` filter.

= How quickly do changes appear? =

By default the plugin checks Google every 15 minutes, and you can trigger a refresh by hand at any time. Each table can be set to every 15 or 30 minutes, hourly, every 6 hours or daily.

Because pages render from the stored copy, a visitor never waits for Google — the trade-off is that an edit becomes visible on the next sync rather than instantly.

= What happens if the sheet becomes private or Google is down? =

Your page keeps showing the last successfully fetched version. The dashboard flags the failure and explains what to change; visitors see nothing unusual.

= Will it work with my page builder? =

Yes. The shortcode `[sheet_table id="123"]` works anywhere shortcodes are run — Elementor, Divi, Beaver Builder, classic editor, widgets. Elementor also gets a widget of its own. The block, the widget and the shortcode share one renderer, so they always produce the same table.

= My table is very wide. What happens on phones? =

It stays a table, at full text size, and a slider appears underneath so it can be moved left and right. Nothing shrinks and nothing is hidden — the usual complaint about wide tables is microscopic type, which comes from squeezing a table rather than scrolling it.

The slider is drawn by the plugin rather than left to the browser, because macOS, iOS and Android hide the horizontal scrollbar until you are already scrolling, which is exactly when it is too late to be useful.

By default the table keeps its shape and gains a slider you can drag, so the text stays full size and nothing is hidden. To stack rows into cards instead, change "On screens too narrow for the whole table" on the source screen, or pass `layout="cards"` to the shortcode. Giving the block a wide or full alignment is often better still, since it hands the table the room it needs.

= Can I hide a column, or give it a different name? =

Both, on the table's own screen. It is display only: the plugin never writes to your spreadsheet, so a column can be called `net_price_before_discount` in Google and simply "Price" on your page, and your formulas carry on working.

To hide a column or a row, open the Hide tab and click it in the picture of your sheet: a heading for a column, a line number for a row. Click again to bring it back. Hiding removes the column from the headings and from every row, so a working column is not just visually gone — its values never reach the page at all. The arrow on a heading moves a column into a panel that opens under each row instead.

Columns are matched by position, so inserting one in Google shifts the settings. The plugin remembers which heading each position held and tells you when they no longer line up, instead of silently mislabelling data.

= Can I change how the table looks? =

Pick one of four presets, then fine-tune it: the source screen has colour pickers for text, background, headings, lines, striped rows, hover and accent, plus text size, row height, lines and corner rounding. Anything you leave alone keeps following the preset, so changing one colour does not mean defining all of them — and a preset is a starting point, not a package: every setting you touch overrules it and every setting you do not keeps following it.

Every value is a CSS custom property on `.lstab`, so a theme stylesheet can override the same things.

= Does WP-Cron have to be working? =

The scheduled refresh uses WP-Cron. If you have disabled it, use a real system cron calling `wp-cron.php`, or press "Refresh now". A failed schedule never blanks your table — the stored copy keeps rendering.

You do not have to keep an eye on it: if the schedule stops running, the plugin says so on its own screens and explains what it means, rather than letting the table go quietly stale.

= Can I show several different sheets? =

Yes, as many as you like. Each sheet, or each tab of a sheet, is a source of its own with its own settings, and any number of pages can show it.

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

= 3.43.0 =
* First release in the WordPress.org plugin directory.
* Added: hide columns and rows by clicking them in a picture of your own sheet, under Hide on the table's own screen. A column can also go into a panel that opens under each row. What you hide stays hidden for as long as you want it to.
* Added: the block's style menu lists every style on the site, Midnight included.
* Changed: any number of sheets can be added.
* Changed: four table styles come with the plugin: Clean, Striped, Bordered and Midnight.
* Changed: a table can be checked for changes every 15 or 30 minutes, hourly, every 6 hours or daily.
* Fixed: deleting the plugin from the Plugins screen ended in an error. It now clears its background schedule every time, and its table and every setting when "Delete everything" is switched on.

== Upgrade Notice ==

= 3.43.0 =
Hiding columns and rows comes with the plugin, and any number of sheets can be added.
