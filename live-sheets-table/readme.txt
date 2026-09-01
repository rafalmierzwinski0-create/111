=== Live Sheets Table – Google Sheets to WordPress ===
Contributors: livesheetstable
Tags: google sheets, table, spreadsheet, csv, data table
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.7.0
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
* A table older than its interval is checked before the page is drawn, with a hard four-second cap and the stored copy as the fallback — so a site nobody visits, or a host that blocks WordPress schedules, does not quietly publish last week's prices.
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
* Six saved sheet sources.
* Optional paging for long sheets, with searching and sorting that still cover every row.
* A "Google Sheets Table" block, an Elementor widget, and a `[sheet_table id="123"]` shortcode for everything else — all three driven by the same renderer.
* Background sync every 15 minutes, plus a "Refresh now" button.
* A guarantee that whoever opens the page sees data no older than the interval you set — if the schedule has not run, the check happens as the page is drawn, capped at four seconds and falling back to the copy you already have.
* Optional search box and sortable columns (numeric-aware, so 1 215,50 sorts above 349,00).
* Numeric columns detected and right-aligned with tabular figures, so decimals line up.
* Three polished style presets, each following the reader's light or dark colour scheme.
* A visual appearance editor: set colours, text size, row height and corners per table, with the preview updating as you go.
* A visible, draggable slider under any table too wide for its column, so nothing is ever hidden behind an invisible scrollbar.
* The first column stays pinned while the rest scrolls, so a price never stops belonging to a product — and can be switched off per table.
* Click a heading or a row in your own sheet to leave it out of the table. Rename columns for your visitors too. Nothing is written back to the spreadsheet.
* A layout control per source: scroll the table sideways, or stack each row into a card.
* An "updated N minutes ago" label you can switch off.
* Full translation support.

= Pro =

Live Sheets Table Pro adds unlimited sheet sources, syncing as often as every minute, conditional cell formatting, filtered views, CSV and print export for visitors, premium presets, private-sheet support through an authenticated connection, a multi-site licence and priority support.

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

Every value is a CSS custom property on `.lstab`, so a theme stylesheet can override the same things. Extra presets are a Pro feature.

= Does WP-Cron have to be working? =

The scheduled refresh uses WP-Cron. If you have disabled it, use a real system cron calling `wp-cron.php`, or press "Refresh now". A failed schedule never blanks your table — the stored copy keeps rendering.

You do not have to keep an eye on it: if the schedule stops running, the plugin says so on its own screens and explains what it means, rather than letting the table go quietly stale.

= Can I show several different sheets? =

The free version stores six sheet sources. Pro removes the limit.

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

= 2.7.0 =
* Added: point at what you want gone. The edit screen now shows your sheet as a table you can click: a heading drops that column, a row drops that row, and a second click brings it back. The checkbox list is still there and the two stay in step, so whichever one you reach for, the other agrees.
* Added: hiding individual rows, which was not possible at all before — a row could only be excluded by a Pro filter, written as text.
* A hidden row is remembered by what it says, not by where it sits. Remembering the row number is the obvious approach and it is wrong: the sheet is a live document, and someone inserting a line at the top would then silently hide a different row. Rows can be added, removed and reordered in Google without disturbing anything.
* Hidden rows and columns stay hidden everywhere: they are not counted, cannot be found by searching, do not take up a place on a page, and are not in the file when a Pro table is downloaded.

= 2.6.0 =
* Changed: the dashboard no longer warns about DISABLE_WP_CRON. That line in wp-config.php is the normal setup on any host running a real system cron, so the warning fired on perfectly healthy sites — and a warning that appears when nothing is wrong teaches people to ignore warnings. What matters is whether the tables are current, and that can simply be measured: the dashboard now says nothing at all unless a sheet has actually fallen well behind its own interval, and then it names which one and for how long.
* Fixed: sorting a column that holds both numbers and words no longer treats every value as text. A single “brak” or “n/a” in a price list used to do that, and text sorting puts 1 000 000 below 1 215 because “1” sorts below “2”. Numbers now compare as numbers wherever both values are numbers, blanks sink to the bottom whichever way the column is sorted, and a paged table sorts exactly like an unpaged one — a visitor cannot see which is which, so the two must not disagree.
* Fixed: control characters in a cell — a null byte pasted into a spreadsheet, most often — are dropped when the sheet is read. They are invisible in Google and invisible in a browser, but they make a feed or an export invalid, which is the kind of fault that surfaces months later as an unexplained blank.

= 2.5.0 =
* Changed: checking a stale table before the page is drawn is no longer a setting — it is simply how the plugin works. Asking a site owner whether their prices should be current has one answer, and a checkbox that is only ever ticked is a question not worth putting on the screen. The schedule is now the only thing to configure: whoever opens the page sees data no older than the interval you chose, whether or not the schedule managed to run. On a site where it is running this costs nothing, because there is never anything to do.
* Fixed: a check that ran out of time no longer costs a whole interval of stale data. Staleness is now measured from the last successful refresh rather than the last attempt, so a four-second timeout does not buy fifteen more minutes of the old copy — the visitor after the one who waited is better off for that waiting. A failed check instead holds off the next one for half a minute, doubling while it keeps failing, up to the interval.
* Added: when a check runs out of its four seconds, the same fetch is queued to run in a request of its own, where the full twenty-second timeout is nobody's wait. A sheet too large to arrive in four seconds is no longer beyond the reach of a visit — whoever arrives next sees the result.
* Fixed: the four-second cap now covers the whole check rather than each request inside it. A sheet whose sharing settings refuse the export endpoint is asked twice, and four seconds each would have been eight seconds of waiting — in exactly the case where Google is already being slow.
* Removed: the per-table “also check when someone opens the page” checkbox, and the database column behind it. A site that would rather serve a day-old table than ever make one visitor wait has the lstab_refresh_on_view filter.

= 2.4.0 =
* Added: a per-table option to check Google before the page is drawn, when the local copy is older than that table's schedule. WordPress has no clock of its own — the schedule runs on a visit, in a request of its own, after the page has already been sent — so the visitor who triggers a check is the one who sees the old copy. Prices and stock levels can now be right for the visitor who waited. One request checks at a time, the wait is capped at four seconds, and a sheet that is slow, down or newly failing leaves the copy you already have on the page.
* Added: the dashboard now hands you the exact cron line for your own host, built from your address and your own interval, whenever the schedule is switched off or has fallen behind. WordPress has no clock — its schedule only runs when a page is requested, so a site nobody visits checks nothing, and no plugin can fix that from inside PHP. Telling someone to “set up a system cron” and leaving them to work out what to type is most of that problem.
* Changed: one page load buys one check, however many tables the page holds. Four tables that all wanted checking would otherwise be four four-second waits in a row, which is the fault this feature exists to avoid.
* Changed: a check made while someone waits is never what reports a sheet as broken. It is given four seconds where the scheduler is given twenty, so a sheet that syncs perfectly well can miss the shorter deadline — and a red dashboard over a deadline of the plugin's own invention would be a fault it made up. Refusals, sign-in pages and empty replies mean the same thing at four seconds as at twenty, and are still reported.

= 2.3.0 =
* Added: an Elementor widget, in a "Google Sheets" category of its own. Elementor keeps its own catalogue, so a plugin that is not in it is not there at all — its users had to paste a shortcode into a text widget and lose the live preview. The widget hands its settings to the same renderer the block and the shortcode use, so all three agree.

= 2.2.0 =
* Added: paging, set per table as a number of rows per page. A sheet with no row limit eventually makes a page nobody wants to download; this splits it without capping anything.
* Added: with paging on, searching and sorting move to the server and work across the whole sheet. Searching the rows that happen to be on screen and calling the result the table would be worse than not offering it — a search from page one still finds a row on page nine. Every control is an ordinary link or form, so each page has its own address and needs no JavaScript.
* Changed: a column left out of the table can no longer be reached by searching for its contents.

= 2.1.0 =
* Changed: the free version now keeps six sheet sources instead of three. Three turned people away at the third page they wanted to publish, which is not what the paid tier is for — rows are, and those have never been capped.

= 2.0.0 =
* Fixed: sheet data is now downloaded from Google's CSV export endpoint instead of the query endpoint. The query endpoint decides a single type for each column and blanks every cell that disagrees with it, and it guesses how many leading rows are headings and runs them together into one label. A price list holding "1 215,50" as text among plain numbers lost that price and gained a two-row heading — and the payload arrived that way, before the plugin read a byte of it. Re-sync any table that looked wrong.
* Added: the query endpoint is kept as a fallback for sheets whose sharing settings refuse the export, and is asked not to guess at headings when it is used.

= 1.9.1 =
* Fixed: a value ending in a quotation mark of its own — a product called Rower górski „Trek" — lost the rest of the sheet when the export failed to double that quote. The two characters sit exactly where the field ends, and reading them as an escaped quote swallowed every following row into one cell. A delimiter straight after the pair now settles it. Both spellings of such a sheet are covered by tests taken from a real one.

= 1.9.0 =
* Added: "What Google actually sent" on the source screen — the exported text exactly as it arrived, before the plugin reads it. When a table comes out wrong the first question is whether the sheet or the plugin is at fault, and nothing else answers it. A row that came back with the wrong number of cells is pointed at by number, so nobody has to count lines.

= 1.8.2 =
* Changed: the malformed-sheet warning ends with the likely cause again — a lone quotation mark or a comma inside a value — but offered as likely rather than asserted, so a reader who looks and finds neither does not conclude the warning is wrong. Saying only what was wrong turned out to leave people with nowhere to start.

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

= 2.7.0 =
Click a heading or a row in your sheet to hide it. Rows can now be hidden at all, and are remembered by what they say rather than where they sit.

= 2.6.0 =
Stops warning about a healthy configuration, and fixes sorting a column that mixes numbers and words.

= 2.5.0 =
One setting instead of two: tables are kept current for whoever opens the page, with nothing to switch on.

= 2.4.0 =
Tables can now be checked for changes before the page is drawn, so the visitor who waited sees the new data.

= 2.3.0 =
Adds an Elementor widget.

= 2.2.0 =
Adds paging, with searching and sorting that still cover the whole sheet.

= 2.1.0 =
The free version now keeps six sheet sources instead of three.

= 2.0.0 =
Fixes values being blanked and heading rows being merged by Google's query endpoint. Re-sync any table that looked wrong.

= 1.9.1 =
Fixes a sheet losing its rows when a value ends in a quotation mark.

= 1.9.0 =
Adds a view of the raw text Google sent, for working out whether a wrong-looking table is the sheet's fault or the plugin's.

= 1.8.2 =
The malformed-sheet warning again suggests where to start looking.

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
