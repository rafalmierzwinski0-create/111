=== Live Sheets Table ===
Contributors: livesheetstable
Tags: google sheets, table, spreadsheet, csv, data table
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.43.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show a Google Sheet as a fast, responsive table that updates itself. No row limit, no API key, and pages keep working when Google is down.

== Description ==

Live Sheets Table shows a Google Sheet as a table on your site. Share the sheet as "Anyone with the link" (Viewer), paste the link, check the preview and add the table to a page with the block, the Elementor widget or a shortcode. When you edit the sheet, the table on your site follows.

You do not need an API key, a Google Cloud project or an account with us.

= How it works =

* The plugin downloads the sheet in the background and keeps a copy in your database.
* Pages are built from that copy on the server, as a normal HTML table. Visitors never wait for Google, and the table is readable without JavaScript.
* If a table is older than its refresh interval when someone opens the page, the plugin checks Google first, for at most four seconds, and uses the stored copy if Google is slow.
* It works with page caches such as LiteSpeed Cache and WP Rocket. When a table changes, the plugin clears the cached pages that show it.
* If Google cannot be reached or the sheet stops being shared, the last good copy stays on the page. The dashboard tells you what went wrong and how to fix it. Visitors see no errors.

= Features =

* Any number of sheets, and no row limit.
* A preview of exactly what was read from the sheet before you save, and a tab picker for spreadsheets with several tabs.
* Refresh every 15 or 30 minutes, hourly, every 6 hours or daily, plus a "Refresh" button.
* A warning when a row has more cells than there are headings, usually caused by a merged cell.
* Rename columns for your visitors without changing the sheet.
* Hide columns and rows by clicking them in a picture of your sheet, or move a column into a panel that opens under each row.
* Search, sorting that understands numbers and dates, and optional pages of up to 500 rows. Search and sorting always cover the whole sheet.
* Headings stay visible while the page scrolls, and the first column can stay in place while the table scrolls sideways.
* A visible slider under tables that are too wide for the page, or, if you prefer, one card per row on narrow screens.
* Web and e-mail addresses in cells become links. This can be turned off per table.
* Four styles (Clean, Striped, Bordered and Midnight), an appearance editor for colours, text size, row height and corners, and your own CSS per table.
* A card per sheet in the dashboard showing when it last updated, its size and the pages that use it.

= Privacy =

The plugin only contacts Google, to download the sheets you add (see External services below). It sends no analytics. Sheet data is stored in your own database. A small file in your uploads folder, `live-sheets-table-due.json`, records when each table is next due for a check; it holds no sheet content and is removed when the plugin is deleted. Visitors' browsers only talk to your own site, and nothing is stored on their devices.

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

No. The plugin reads the sheet's public CSV export, which works for any sheet shared as "Anyone with the link" (Viewer).

= Do I have to use "Publish to web"? =

No. Sharing by link is enough. Publish-to-web links also work.

= Is there a row limit? =

No. The only limit is size: one tab can hold up to 10 MB of data, which is roughly a hundred thousand rows of short values. A bigger tab is refused with a note in the dashboard, and the copy you already have stays on the page. Developers can change the limit with the `lstab_max_sheet_bytes` filter.

= How quickly do changes appear? =

By default the plugin checks Google every 15 minutes, and you can refresh a table by hand at any time. Each table can be set to every 15 or 30 minutes, hourly, every 6 hours or daily. Because pages are built from the stored copy, a change in the sheet appears after the next check, not instantly.

= What happens if the sheet becomes private or Google is down? =

Your page keeps showing the last copy that was downloaded successfully. The dashboard shows the problem and what to change.

= Will it work with my page builder? =

Yes. The shortcode `[sheet_table id="123"]` works anywhere shortcodes work, including Elementor, Divi, Beaver Builder, the classic editor and widgets. Elementor also has its own widget. The block, the widget and the shortcode produce the same table.

= My table is very wide. What happens on phones? =

By default it stays a table at full text size, with a slider underneath to move it left and right. To show one card per row instead, change "Behaviour on narrow screens" on the table's screen, or add `layout="cards"` to the shortcode. A wide or full-width block also gives the table more room.

= Can I rename or hide a column? =

Yes, on the table's own screen. Changes only affect your site; the plugin never writes to your spreadsheet.

To hide a column or a row, open the "Columns and rows" tab and click it in the picture of your sheet: a heading for a column, a line number for a row. Click again to show it. A hidden column is removed from the page completely, not just made invisible. The arrow on a heading moves a column into a panel under each row instead.

If you move or rename columns in Google, the plugin notices that the settings no longer match and tells you, rather than hiding the wrong column.

= Can I change how the table looks? =

Pick one of the four styles, then adjust colours, text size, row height, lines and corners on the table's screen. Anything you do not change follows the style. Every value is a CSS custom property on `.lstab`, so your theme's stylesheet can change them too.

= Does WP-Cron have to be working? =

The background refresh uses WP-Cron. If it is disabled on your site, use a real system cron that calls `wp-cron.php`, or refresh tables by hand. If the schedule stops running, the plugin says so in the dashboard, and the stored copy keeps showing in the meantime.

= Is the sheet content safe to display? =

Yes. Everything from the sheet is escaped, so a cell containing HTML or a `<script>` tag is shown as plain text.

== Screenshots ==

1. Adding a source: paste the link and the parsed table appears immediately, before anything is saved.
2. The source list, showing sync status, size, schedule and the ready-to-copy shortcode.
3. Switching between the tabs of a multi-tab spreadsheet.
4. The published table on a desktop screen.
5. The same table on a phone: full-size text, with a slider to move it sideways.
6. Searching inside the table.
7. Sorting by a column, numerically where the data is numeric.
8. A failed sync reported in the dashboard, with instructions.
9. The public page during that failure, still showing the last good copy.
10. The block in the editor, previewing the real server-rendered table.

== Changelog ==

= 3.43.0 =
* First public release.

== Upgrade Notice ==

= 3.43.0 =
First public release.
