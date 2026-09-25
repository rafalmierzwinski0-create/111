=== Live Sheets Table – Google Sheets to WordPress ===
Contributors: livesheetstable
Tags: google sheets, table, spreadsheet, csv, data table
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.31.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish a Google Sheet as a fast, responsive, auto-refreshing table. No row limit, no API key, and the page never breaks when Google is unreachable.

== Description ==

Live Sheets Table turns a Google Sheet into a real table on your WordPress site. Share the sheet as "Anyone with the link – Viewer", paste the link, check the preview, and drop it on a page with a block, an Elementor widget or a shortcode. Edit the spreadsheet and your site follows.

No API key. No Google Cloud project. No account with us.

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

Paste your link and the dashboard shows you exactly what it read — headings, rows, merged-cell damage, the wrong tab — before anything is saved. A spreadsheet with several tabs offers a tab picker, already set to the tab your link points at.

= Getting the data in =

* Six saved sheet sources.
* Unlimited rows in every one of them.
* A tab picker for spreadsheets with more than one tab.
* Background sync every 15 minutes, plus a "Refresh now" button.
* A guarantee that whoever opens the page sees data no older than the interval you set — if the schedule has not run, the check happens as the page is drawn, capped at four seconds and falling back to the copy you already have.
* A warning in the dashboard when a sheet parses ragged — a row with more cells than there are headings, usually a merged cell — naming the row to look at.

= Putting it on a page =

* A "Google Sheets Table" block, an Elementor widget, and a `[sheet_table id="123"]` shortcode — all three driven by the same renderer, so the three routes cannot drift apart.
* A caption above the table, in your words rather than the sheet's.
* Rename a column for your visitors without touching the spreadsheet.
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

* Three style presets, each following the reader's light or dark colour scheme.
* A visual appearance editor: colours, text size, row height and corners per table, with the preview updating as you go.
* Your own CSS per table, for administrators allowed to write it, checked before it is saved.

= In your dashboard =

* A card per sheet saying when it last synced, whether the last checks succeeded, how many rows and columns it holds, and which pages use it.
* Errors are shown to administrators only, with what to do about them.
* A Language setting: read the plugin in English or Polish whatever the site itself is set to. Only this plugin's own text changes.
* Full translation support, with Polish included.
* Nothing is deleted when you remove the plugin unless you ask for that on the settings screen.

= What Pro adds =

Live Sheets Table Pro adds unlimited sheet sources, syncing as often as every minute, hiding columns and rows by clicking them in a picture of your own sheet, moving columns into an expandable panel under each row, conditional cell colouring, fixed filtered views, filters your visitors can use themselves, Excel, CSV and print export for visitors, two premium presets, private sheets through an authenticated Google connection, a multi-site licence and priority support.

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

Yes. The shortcode `[sheet_table id="123"]` works anywhere shortcodes are run — Elementor, Divi, Beaver Builder, classic editor, widgets. Elementor also gets a widget of its own. The block, the widget and the shortcode share one renderer, so they always produce the same table.

= My table is very wide. What happens on phones? =

It stays a table, at full text size, and a slider appears underneath so it can be moved left and right. Nothing shrinks and nothing is hidden — the usual complaint about wide tables is microscopic type, which comes from squeezing a table rather than scrolling it.

The slider is drawn by the plugin rather than left to the browser, because macOS, iOS and Android hide the horizontal scrollbar until you are already scrolling, which is exactly when it is too late to be useful.

By default the table keeps its shape and gains a slider you can drag, so the text stays full size and nothing is hidden. To stack rows into cards instead, change "On screens too narrow for the whole table" on the source screen, or pass `layout="cards"` to the shortcode. Giving the block a wide or full alignment is often better still, since it hands the table the room it needs.

= Can I hide a column, or give it a different name? =

Renaming is free and lives on the source screen. It is display only: the plugin never writes to your spreadsheet, so a column can be called `cena_netto_bez_rabatu` in Google and simply "Price" on your page, and your formulas carry on working.

Hiding a column is part of Pro, where you choose it by clicking a picture of your own sheet. Hiding removes the column from the headings and from every row, so a working column is not just visually gone — its values never reach the page at all.

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

= 3.31.0 =
* Added (Pro): a column can be given a look of its own, on the Appearance tab. A column of numbers can carry a bar behind each value, as long as the value is large — a column of figures becomes a chart without stopping being a column of figures. A column of web addresses can become a column of buttons, in a background colour and a text colour you choose, saying what you tell it to say. Only a cell that really holds an address becomes one: a note or a blank in the same column is left alone, because a button that goes nowhere is worse than the note it replaced.
* Added (Pro): a colour rule can put a dot before the value instead of colouring anything — the quietest of the looks, for a status column where even a pill is more than the page wants.
* Fixed: a column of dates sorted by the day of the month. Sorting read every value as a number, and a number is all it kept: "15.01.2026" came out as 15.01, so dates in one month tied and the order inside them was whatever it had been. Dates written day first with a four-digit year — 15.01.2026, and 15.01.2026 20:20 — now sort as dates.
* Fixed: "9:30 am" and "5:45 pm" sorted as 930 and 545, so an afternoon came before a morning. Both clocks are now read properly, midnight and noon included: 12:15 am is a quarter past midnight and 12:15 pm a quarter past noon. Times on the 24-hour clock already sorted correctly and still do.
* Added: a column holding more than one kind of thing now comes out in a settled order — dates, then times, then everything else, each sorted among its own. The rule is the same in the browser and on the server, so a table sorted where it stands and a paged one sorted by the server can no longer disagree, and tests/sort-test.php holds them to one shared file of cases.
* Added (Pro): a colour rule can turn the value into a pill — an outline in the rule's colour with a wash of it behind, around the value itself. It is the fourth option in the rule's own sentence, beside "that cell", "the whole row" and "just the words", and it is what a status column usually wants: "Open", "Sold out", "Most popular". The badge is drawn around the text and changes nothing about it, so sorting, searching and a download still read the value exactly as the sheet spells it.
* Fixed (Pro): a rule that painted a row left the pinned first column unpainted. The pinned column paints its own backdrop, over the columns sliding past underneath, and that backdrop knew nothing about the rule — so a coloured row reached the reader with its colour starting at the second column.
* Fixed (Pro): on a phone, where each row becomes a card, the name introducing every value kept a muted colour picked for the table's own paper. On a row a rule had just painted, that pair fell to 4.03 to 1, under the readability bar. A rule now hands the label a quieter shade of its own ink: 6.56 to 1 on the same row.
* Fixed: searching matched every row whenever the term was the name of a column. Each cell carries the name of its column for the card layout to show, and the search read the row's text wholesale, names included — so "price" or "status" matched everything, nothing was marked, and the counter said the whole table still matched. The search now reads the values only.

= 3.30.0 =
* Added: turning a page no longer reloads the whole page, and neither does sorting a column or picking a filter. The new rows are fetched on their own and put in place of the old ones, so the reader keeps their position, the theme's header and images are not fetched a second time, and a slow host no longer means a blank screen between page two and page three. The address bar and the back button both keep working, and if anything at all is unavailable — an old browser, a login wall, an error page — the link is left alone and the browser follows it the ordinary way.
* Fixed: a pinned first column let the rest of the table show through it. The pinned cell took its colour from the row, which works for a striped table and fails for every other one: an unstriped table, or any custom CSS that clears cell backgrounds so a page background can show through, left the column see-through, and dragging the table sideways slid the second column's text under the first column's until the two read as one smear. The pinned cells now paint their own backdrop, which a custom stylesheet cannot take away, and still carry the stripe and the hover.
* Added: --lstab-sticky-bg, for a skin whose table background is deliberately see-through. The pinned column needs something solid to hide the columns passing underneath; this is where to say what.

= 3.29.3 =
* Added (Pro): a colour rule can paint just the words in a cell instead of filling it. A fill is right for "this one is a problem" and wrong for a column where half the rows are marked, which becomes a wall of colour with nothing standing out. The choice is the third option in the rule's own sentence — "paint that cell / the whole row / just the words in that cell" — and the swatch beside it shows which it is before anything is saved.

= 3.29.2 =
* Fixed: the preview beside the CSS field ignored every colour typed into it. The rules were confined to the frame the preview is drawn in rather than to the table inside it; colours are custom properties the table sets on itself, and a value set on an element beats one inherited from its parent, so sizes and spacings worked while colours did nothing. The published page was right the whole time — only the preview lied.
* Fixed: anything the CSS field had put on the preview was thrown away whenever the preview was drawn again, which happens for reasons that have nothing to do with the field — a colour picked, a column renamed. The rules came back only when somebody typed in the field again; they now re-apply themselves.

= 3.29.1 =
* Fixed: on a site whose theme adds "smooth scrolling", the page buttons, column sorting and the Pro filters did nothing at all. Every one of them is a link ending in a fragment so that clicking it lands on the table; a great many themes bind a handler to every link containing a fragment, compare the fragment alone, decide it points at this page, and cancel it. The plugin now takes those clicks in the capture phase, ahead of such handlers, and leaves the browser to follow the link — a middle click or one with a modifier held is still the browser's own.

= 3.29.0 =
* Added: the row of column names has a text size of its own, apart from the rows. A dense table often wants quiet headings and a short one wants loud ones, and until now both had to take whatever the table's own text size gave them.
* Added: the page buttons can sit under the left edge, in the middle, or under the right. Centred is still what a table does if nobody says otherwise.
* Changed: the "updated … ago" line no longer takes a line of its own. It shares the strip with the page buttons, and the setting above places both at once — always on opposite sides, so buttons on the left send it to the right, and buttons centred or right leave it on the left. On a screen too narrow for that the strip stacks, both lines centred.

= 3.28.0 =
* Fixed: the slider under a wide table went back to being a bare line lying across the rows. Two faults, one symptom. It worked out whether it was floating by measuring against the bottom of the window, which assumed the page itself was the thing scrolling — true on a published page, false in the editor's preview, where the table sits in a box that scrolls inside a window it never reaches the bottom of. And the decision waited for the first scroll, so a table already under the slider when the page opened showed a bare line until somebody moved the page. It now asks the rows directly — while any part of the table is below the top of the slider, the slider is on the data and dresses itself — and asks the moment it appears.
* Changed (Pro): "Downloads and printing" is on the General tab. It was on Appearance, on the reasoning that these are buttons a visitor sees; but nobody looks for "can people download this?" under how the table is coloured. General was also the one tab the add-on could not put anything on at all — it takes cards now, like the other two.

= 3.27.0 =
* Added: a sheet of two hundred rows or more gets pages the moment it is created, and is told so in the sentence that greets you — how long the sheet turned out to be, how many rows a page is holding, and where to change it. Nothing is decided quietly: if you touched the paging switch yourself, on either setting, that answer stands.
* Added: a sheet you already had that has grown past two hundred rows is asked rather than changed. Its card offers pages, says how many rows prompted the question, and takes yes or no in one click — a published page must not rearrange itself behind its author's back. "No" is remembered, because an offer that keeps coming back is not an offer.
* Fixed: the copy button beside a shortcode could sit there saying "Copy" for ever. The modern clipboard call does not always answer — a browser that decides the page is not in front of you leaves it hanging rather than refusing — so it is now given a second and a half, after which the shortcode is selected for you and the button says to press Ctrl+C. A button that answers neither way is worse than one that admits it could not.

= 3.26.0 =
* Fixed: a language chosen on a site that had never saved the plugin's settings did not take hold until something saved them a second time. The very first save creates the settings rather than changing them, and WordPress announces those two things differently; the plugin was listening for only one of them, so it answered the save in the language you had just stopped using. Found by rebuilding the test site from nothing — an environment that has been used before cannot show this, and now a test proves it without needing one.
* Fixed (Pro): deleting the add-on takes the key to your Google account with it. Connecting a private sheet leaves a credential in the database that opens those spreadsheets for as long as it exists, and nothing was removing it — deleting the plugin left it there for good. It is now deleted whenever the add-on is, on every site of a network, whatever else you have asked to keep. Everything that is your own work — the Google application's details, colour rules, filters, which sheets are private or exportable — still follows the "delete everything" setting, because deleting a plugin to reinstall it is a normal thing to do.
* Fixed: the sentence beside the sheet-tab picker explaining why the list is short is now read out with the field itself, not left lying beside it where a screen reader would never connect the two.
* Changed: the plugin asks for WordPress 6.7 or newer. It had been claiming 6.0, which its own block could not have honoured — the block speaks a version of the editor's language that arrived in 6.3 — and nothing below 6.8 was ever run against it. 6.7 is now both what it says and what it is tested on.

= 3.25.0 =
* Fixed: the picker for which tab of the sheet to publish is on the screen the moment the editor opens, already naming the tab this sheet is set to. It used to stay hidden until Google answered with the list of tabs a few seconds later, so anybody who looked at the form as it loaded saw no way to choose a tab and no reason to think one was coming.
* Fixed: when the list of tabs cannot be read, the picker stays where it is, holding the tab the link points at, and says beside it why the others are missing. It used to disappear without a word, which read as the plugin having taken the setting away.

= 3.24.0 =
* Added (Pro): an "Add a rule" button under the colour rules. Two blank lines were the whole answer to how many rules somebody wanted, so a fourth meant filling both, saving, and coming back for two more. One blank line waits now and the button adds the rest; it stops offering when the twenty a table can hold are all there, rather than letting the twenty-first be dropped without a word on the way in.

= 3.23.0 =
* Fixed: the little row of bars on a sheet's card was drawn at varying heights, which is the shape of a measurement — and nothing was being measured. The heights came out of an arithmetic pattern on the loop counter, so the one question the picture invited had no answer. They are one mark per check now, all the same height, labelled "Last checks", with the number that failed said in words rather than only in colour.
* Changed: the tab name on a card says it is one. A name on its own beside a stack of layers could have been anything.

= 3.22.0 =
* Fixed: "Add a sheet" opens at the beginning of the form, the same as opening a sheet you already have. The tab was remembered whenever one was clicked, so a new sheet opened wherever the last visit had ended up. It is remembered across a save now, and only across a save — which is what it was there for: a save that cannot go through comes back to the tab it was made from rather than throwing you to the front of the form.
* Changed: Settings and Pro are lines in the sidebar under the plugin, as well as tabs across the top of its screens. The tabs say these are views of one plugin, which they are, but somebody looking for a plugin's settings looks down the list on the left, and finding nothing there is a worse answer than a line that repeats itself.

= 3.21.0 =
* Fixed: the sideways slider looked like a stray line drawn across the rows while it floated over them. It becomes a control of its own there — its own paper, edge and shadow — and gives all of that back the moment the end of the table comes into view, so nothing is painted over the page.
* Added: an attribute that takes one of a fixed set of words now prints those words. style="striped" was no use to anybody who had no way of knowing what else could go in there.
* Changed: the add-on's filter attribute is listed with all the others beside the shortcode, instead of on a screen of its own. What is left under Pro settings is the reference for what may go inside it, and it says plainly that a filter chooses rows — which columns a table shows is settled once for the whole sheet.
* Changed: the nine colours a rule can paint with are a notch deeper. The first set was pale enough that a coloured cell read as a printing artefact rather than a decision; every one still clears the readability bar for the text on it, and rules saved against the old set move to the colour that replaced it.
* Changed: the two chips that are not a colour show what they do — a letter in the weight it would give the cell, and a letter with the line through it — instead of a "B" and an "S" set like everything else.
* Fixed: the wheel that says "a colour of your own" now opens the colour picker when it is clicked. It used to sit beside the picker, so clicking it chose "my own colour" without ever offering one; there is one circle now, and it wears the colour once one is picked.
* Added: the card for letting visitors narrow a table shows the bar as it appears on the page, drawn from the sheet's own headings, with the row count it would leave. It is the question people asked about most and a sentence was answering it slowly.

= 3.20.0 =
* Fixed: a Polish screen said “za 1 godzina” and “co 1 godzina”. Every sentence a duration lands in governs the accusative and the translation gave the nominative; all seven units now take the form the sentence around them needs.
* Added: the shortcode's optional attributes are a two-column reference — what to write, what it does — instead of five pieces of code with no meanings beside them.
* Added: the four colour swatches whose name points at nothing on the table now say what they paint. “Accent” turns out to mean links, sort arrows and page numbers, which no reader could have known.
* Changed: “Pick a look” is now “Look and behaviour”, because the card also holds pagination and what happens to addresses inside cells.
* Changed: the style descriptions dropped their jargon. “Hairline rules” is “very thin lines”, “high-contrast dark preset” is “a dark table with high contrast”, “inherits your theme fonts” is “uses your theme's fonts”.
* Changed: the screen for hiding columns and rows says “hide” throughout. It used to say “take out” and “removed” a line above a note promising nothing is written to Google.
* Changed: the picker says that the numbers down its left are the sheet's own line numbers, so the first row of data being number 2 is not a puzzle.
* Changed: the value box in a colour rule asks for “the value to match” rather than “what it says”, which named the cell instead of what to type.
* Changed (Polish): the Midnight style is “Nocny”. “Północ” means both midnight and north, and nothing about a table style says which.
* Fixed: the reference tables on both screens read left to right. Their meanings column was centred, inherited from a table whose last column is a checkbox.

= 3.19.0 =
* Fixed: half the dashboard could be in Polish and half in English. Three separate holes did it: the add-on shipped no Polish catalogue at all; the block's own panel in the editor was never handed its translations, because a .mo file is invisible to JavaScript; and the block's name and description come from block.json, which WordPress translates under a context of its own that was never extracted. All three are closed, so the screens agree.
* Fixed: a length of time inside a translated sentence stayed in the site's language, which read as "za 1 week" on a Polish screen. Only this plugin's own durations changed; nobody else's dates are touched.
* Added: a Language setting. The plugin and its add-on can be read in English or Polish whatever the site is set to, which suits an agency working in one language on sites published in another. Only this plugin's own text changes; the rest of the dashboard keeps WordPress's setting.
* Changed: another pass over the wording, this time for the words rather than the length. "Exactly what the parser sees" is now "Exactly what the plugin read from your sheet"; "Style preset" is now "Table style"; "follows the preset above" is now "follows the style chosen above". Jargon a site owner has no reason to know is gone from the screens, in both languages.

= 3.18.0 =
* Changed: every label, help line and notice in the dashboard has been rewritten. A label now names the setting; a help line says what it does in one sentence, without the reasoning behind it. The Polish translation was rewritten to match.
* Changed: the changelog below has been condensed to what each release changed.
* Fixed: on a short browser window the help text in the editor's preview panel was squeezed until its lines printed over the row beneath it.
* Fixed: the table's sideways slider now stays in view at every scroll position, instead of appearing only once the bottom of a long table is reached.

= 3.17.0 =
* Changed: the table is dark when the page is dark, rather than when the visitor's system is.
* The Midnight preset and any colour set by hand are untouched by all of this: a choice beats a guess.

= 3.16.1 =
* Fixed: on a light theme seen by somebody whose system is set to dark — about half of all visits — the table's title, its row count and its “updated” line vanished.
* Fixed: the label printed beside every value in the card layout was too faint to reach the readability bar — 3.38 to 1, where 4.5 is the line for text this size.

= 3.16.0 =
* Changed: the plugin has a mark of its own — a selected range with the handle you drag it by, which is the same frame the dashboard draws around every block.

= 3.15.0 =
* Added: searching marks the part of each cell that matched. A search answers “which rows” and used to leave “why these rows” — which on a wide table of long descriptions is a real hunt, and the answer is often in a column nobody was looking at.
* Fixed: a paged table too wide for its column was clipped with no slider (also in 3.14.0, restated here because the fix reached the built zip in this release).
* Added: `lstab_before_table`, an action inside the table's wrapper and above the table, for anything that narrows what the table shows.

= 3.14.0 =
* Added: the headings stay where you can read them. On a table that fits its column they follow the screen down; on one wide enough to need the sideways slider, a long table becomes a pane that holds its headings at the top.
* Fixed: “Keep the first column in view” did nothing in the editor's preview — the preview pinned the column whatever the setting said, because the settings were never handed to it.
* Fixed: a paged table too wide for its column was clipped with no slider and no way to reach the columns past the edge.
* Changed: the scheduling warning is one line with the rest folded away.

= 3.13.0 =
* Changed: the choice of what a table does on a phone is now three options side by side, each saying when it applies, instead of a dropdown whose two card options read as the same sentence twice.
* Changed: paging has a switch of its own that says the word “pagination”, and only asks how many rows a page holds once it is on.
* Fixed: the Columns tab's two blocks touched, with no gap between them — a side effect of levelling the blocks in 3.12.0.
* Fixed: “Put it on a page” was narrower than the cards above it, leaving the column with a ragged edge.

= 3.12.0 =
* Fixed: the blocks in the editor now end level, not just the columns holding them.
* Changed: the “Columns and rows” tab takes the whole width. The small preview was no use there — the picker under it shows the entire sheet with what you have taken out struck through — and keeping it meant squeezing the picker into half the screen for nothing.
* Added: an add-on can now show its own unsaved settings in the preview, which is what colour rules needed.

= 3.11.1 =
* Fixed: the editor's preview showed a column in the table that the published page put in the details drawer.
* Fixed: renaming a column wrote the new name onto the wrong heading when any column had been moved into the drawer.
* Fixed: a row picked out by a colour rule opened onto a drawer that was not coloured.
* Fixed: pressing Save on a table that is not on any page yet — which is every table, the first time — threw away the whole site's page cache.
* Fixed: a drawer's full-width cell is no longer treated as the pinned first column of a table that scrolls sideways.

= 3.11.0 =
* Fixed: the two columns of the editor now always end level. The form column runs from about 490 pixels on the hiding tab to 1790 on the appearance one, and opening “What Google actually sent” added another 360 to the other side — so which column was longer, and by how much, changed with every tab and every disclosure.
* Changed: the exported payload is no longer allowed to take a third of the page when opened; it scrolls inside its own box.
* Added: an add-on can now print its cards on whichever tab of the editor they belong to, instead of all of them landing under “Columns and rows”.

= 3.10.0 =
* Changed: every block in the dashboard now carries the same frame the sheets list has — the edge takes the accent and the little square you drag from appears in the corner, the way a spreadsheet marks a selected range.
* Added: a column can be renamed before the source has ever been saved. The list used to be three disabled placeholders and a note telling you to save first; it is now built from the preview that has already arrived, and typing a name changes the preview as you type.

= 3.9.0 =
* Added: a column can now live under its row instead of in the table. A wide sheet keeps the three or four columns worth scanning, and the rest open behind an arrow on the row itself.
* Fixed: opening the built-in example threw a JavaScript error that stopped the whole editor script, so nothing on the Appearance tab did anything on that table — colours, style, layout.
* Added: renaming a column shows in the preview as you type, without saving and without a request to Google.
* Changed: the numbering starts from one again once the last table is deleted.
* Changed: there is no longer a setting for clearing the page cache. "Should the page a visitor sees match the sheet?" is not a question worth putting to anybody, and the people it would bite are the least likely to find the switch.
* Changed: in the add-on's picker, the line numbers now sit on the same tint as the headings above them, the way a spreadsheet's own gutters do.

= 3.8.0 =
* Added: the page cache is cleared when a sheet actually changes, so a table cannot update in the dashboard while visitors keep seeing yesterday's price.
* WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, WP Fastest Cache, Cache Enabler, Hummingbird, SiteGround, WP Engine, Nginx Helper and Breeze are cleared by name; any other cache can listen for `lstab_purge_page_cache`.
* Fixed: the CSS field could be made to end its own style block. Removing the sequence that closes it once was not enough, because removing it from certain inputs joins what was on either side back into the same sequence.
* Fixed: a brace or a comma inside a quoted CSS value — `content: "}"` — was read as the end of a rule, which mangled everything after it.

= 3.7.0 =
* Added: a CSS field on the Appearance tab, for the last thing the settings do not cover.
* Fixed: a mismatch between the column list and the format list used when a source is first created, which stored a brand-new source's column settings as the number 0.

= 3.6.1 =
* Fixed: resetting a colour in the editor left the old colour sitting in the swatch beside it, so a reset that had worked still looked as though it had not.

= 3.6.0 =
* Fixed: the bundled example was counted when deciding whether the schedule had stalled, so a site whose schedule was working perfectly could be told it was not.
* Changed: the settings screen is one panel per subject with a row per decision, instead of a stack of identical white boxes.
* Added: two settings the competition charges for — the look every new table starts from, and how long a page may wait for Google before falling back to the stored copy.
* Changed: the welcome screen now stays until there is a sheet of your own.
* Changed: the sheets sit on graph paper, and pointing at one marks it the way a spreadsheet marks a selected range.
* Fixed: the wording of the scheduling notice, which used shell jargon in Polish and trailed its link off the end of a grey paragraph.

= 3.5.0 =
* Fixed: opening the built-in example showed an empty preview and could not be saved.
* Added: the editor is three tabs — General, Appearance, Columns and rows — instead of one long column.
* Added: a copy button beside the shortcode on the editor as well as the list.
* Fixed: the plugin's own buttons no longer come out in WordPress blue on screens that are otherwise the plugin's colour.

= 3.4.0 =
* Added: a welcome screen for a site with no sheets yet — one field for the link, and the table appears before anything is saved.
* Added: a built-in example price list. It lives inside the plugin, works with no internet and never contacts Google, so the plugin can be tried before you have a spreadsheet of your own.
* Added: a copy button beside every shortcode. It was the most repeated action in the plugin and the only one that needed a steady hand.
* Added: each sheet now says which pages use it — and says plainly when nothing does, so an unused source can be deleted without guessing.
* Added: the first few column names on each card, for telling similar sheets apart, and the last few sync results as a small bar chart.
* Changed: the source list is a card per sheet instead of a table row, ordered by what people actually look for: is it working, which sheet is it, how do I put it on a page.
* Changed: a sheet that is working normally is now stated in grey rather than green.
* Changed: the dashboard has its own heading, icons drawn in the page itself, and a colour scheme defined in one place rather than spread through the stylesheet.

= 3.3.0 =
* Changed: a hidden row is now remembered by the line it is on in Google, checked against everything that row said.
* Changed: a hidden or renamed column is remembered by its position, checked against the heading that was there when the choice was made — and a sync never quietly adopts a new heading in its place, which is what used to let a choice drift onto a different column.
* Changed: when a line or a heading has moved, nothing is taken out. The row or column is on the page and the dashboard says which one and why, rather than the wrong one disappearing where nobody would see it.
* Added: the Hide columns and rows screen says up front that moving a column or inserting a line above a hidden row in Google will bring it back, so it is not something to find out from a notice afterwards.
* Added: rows are labelled by the line number Google shows them on, in the picker and in every message about them.
* Fixed: a row that is blank in every cell can be taken out too.

= 3.2.0 =
* Fixed: the notice about a hidden thing no longer said what had happened.
* Changed: each message now says what was looked for, what was found instead, what it means for the page, and what to do about it — and names the likeliest cause, which for a column is almost always that its heading was renamed in Google rather than that the column vanished.

= 3.1.2 =
* Changed: a hidden row is now quoted by at most four of its cells, and named alongside the line it was last on.
* Changed: a column is referred to by its heading alone.

= 3.1.1 =
* Changed: a hidden row is now referred to by what it says — “Kask · M · 120” — rather than by its first cell alone.
* Fixed: a row whose first cell is empty can be hidden. It has no name to be known by, so it is recognised by everything else it says, which was already how an unedited row was found.

= 3.1.0 =
* Fixed: column settings follow their heading instead of their place in the row.
* Added: when a change to your sheet means something you had left out is being shown again, the dashboard says so, names it, and links to the table.
* Changed: a hidden row that has been edited is now recognised by how much of it still matches, rather than by its name alone.

= 3.0.0 =
* Added: a Settings screen — who may manage tables, what schedule a new table starts on, and whether deleting the plugin should also delete your tables.
* Changed: deleting the plugin no longer removes your tables unless you have said it should.
* Changed: the plugin's screens are now tabs across the top of one screen rather than separate entries in the sidebar.
* Changed: the countdown that runs after Pro stops now appears at the top of any dashboard screen, not only this plugin's, and can be put away — it comes back for the last two days, when it stops being information and starts being the last chance to act on it.
* Fixed: a hidden row now survives being edited even where its name is shared with others.

= 2.9.0 =
* Changed: leaving a column out of a table is part of Pro. The list of columns still shows which are in and which are out, and still renames them, but the choice itself is made in Pro by clicking your own sheet.
* Changed: choices only Pro can make keep working for ten days after Pro stops.
* Fixed: a hidden row is now recognised by everything it says, not only by its name.

= 2.8.0 =
* Changed: clicking your sheet to hide a column or a row moved to the Pro add-on.
* Rows that were hidden stay hidden whether or not the add-on is active.

= 2.7.0 =
* Added: point at what you want gone. The edit screen now shows your sheet as a table you can click: a heading drops that column, a row drops that row, and a second click brings it back.
* Added: hiding individual rows, which was not possible at all before — a row could only be excluded by a Pro filter, written as text.
* A hidden row is remembered by what it says, not by where it sits. Remembering the row number is the obvious approach and it is wrong: the sheet is a live document, and someone inserting a line at the top would then silently hide a different row.
* Hidden rows and columns stay hidden everywhere: they are not counted, cannot be found by searching, do not take up a place on a page, and are not in the file when a Pro table is downloaded.

= 2.6.0 =
* Changed: the dashboard no longer warns about DISABLE_WP_CRON. That line in wp-config.php is the normal setup on any host running a real system cron, so the warning fired on perfectly healthy sites — and a warning that appears when nothing is wrong teaches people to ignore warnings.
* Fixed: sorting a column that holds both numbers and words no longer treats every value as text.
* Fixed: control characters in a cell — a null byte pasted into a spreadsheet, most often — are dropped when the sheet is read.

= 2.5.0 =
* Changed: checking a stale table before the page is drawn is no longer a setting — it is simply how the plugin works.
* Fixed: a check that ran out of time no longer costs a whole interval of stale data.
* Added: when a check runs out of its four seconds, the same fetch is queued to run in a request of its own, where the full twenty-second timeout is nobody's wait.
* Fixed: the four-second cap now covers the whole check rather than each request inside it.
* Removed: the per-table “also check when someone opens the page” checkbox, and the database column behind it.

= 2.4.0 =
* Added: a per-table option to check Google before the page is drawn, when the local copy is older than that table's schedule.
* One request checks at a time, the wait is capped at four seconds, and a sheet that is slow or down leaves the stored copy on the page. The `lstab_refresh_on_view` filter turns the check off.
* Added: the dashboard now hands you the exact cron line for your own host, built from your address and your own interval, whenever the schedule is switched off or has fallen behind.
* Changed: one page load buys one check, however many tables the page holds.
* Changed: a check made while someone waits is never what reports a sheet as broken.

= 2.3.0 =
* Added: an Elementor widget, in a "Google Sheets" category of its own. Elementor keeps its own catalogue, so a plugin that is not in it is not there at all — its users had to paste a shortcode into a text widget and lose the live preview.

= 2.2.0 =
* Added: paging, set per table as a number of rows per page. A sheet with no row limit eventually makes a page nobody wants to download; this splits it without capping anything.
* Added: with paging on, searching and sorting move to the server and work across the whole sheet.
* Changed: a column left out of the table can no longer be reached by searching for its contents.

= 2.1.0 =
* Changed: the free version now keeps six sheet sources instead of three.

= 2.0.0 =
* Fixed: sheet data is now downloaded from Google's CSV export endpoint instead of the query endpoint.
* Added: the query endpoint is kept as a fallback for sheets whose sharing settings refuse the export, and is asked not to guess at headings when it is used.

= 1.9.1 =
* Fixed: a value ending in a quotation mark of its own — a product called Rower górski „Trek" — lost the rest of the sheet when the export failed to double that quote.

= 1.9.0 =
* Added: "What Google actually sent" on the source screen — the exported text exactly as it arrived, before the plugin reads it.

= 1.8.2 =
* Changed: the malformed-sheet warning ends with the likely cause again — a lone quotation mark or a comma inside a value — but offered as likely rather than asserted, so a reader who looks and finds neither does not conclude the warning is wrong.

= 1.8.1 =
* Changed: the malformed-sheet warning no longer names one cause. A row can come back short for several reasons, and pointing at only one sends people looking for the wrong thing; it now says what is wrong and what it means for the table, with the likely causes listed on the source screen where there is room for them.
* Fixed: a table that ends in a message for the site owner — not synced yet, source gone, filtering unavailable — did not load the stylesheet, so the message arrived as a bare paragraph that read like broken page content.

= 1.8.0 =
* Added: a table set to show only some of its rows now shows none of them when nothing is available to do the filtering, instead of falling back to every row.
* Added: the message after a save or a manual refresh now says when the sheet arrived malformed, rather than reporting a plain success.
* Added: that warning is now raised anywhere in the dashboard, not only on the plugin's own screens, with a link to the source and one to hide it.

= 1.7.0 =
* Added: web and e-mail addresses in cells can be made clickable, per table.
* Added: the dashboard now says when a sheet fetched correctly but arrived malformed.
* Added: linked addresses take the table's accent colour, so they read as links rather than as underlined body text.
* Added: an lstab_edit_page_settings hook, so an add-on can put its own fields on the source screen and have them saved with everything else.
* Fixed: row filtering ran after columns were hidden, so a filter naming a hidden column matched nothing and quietly returned every row.

= 1.6.0 =
* Fixed: one stray quotation mark anywhere in a sheet used to consume everything after it, collapsing a whole table into a single cell of run-together text.
* Fixed: the column settings sat outside the form, so renaming or hiding a column looked like it worked and then reverted on save.
* Fixed: an unticked "include" box was read as "no answer" rather than as "hide this column", so a column switched off came back visible.
* Changed: the column settings now appear from the start, greyed out and saying what they are waiting for, instead of materialising only after a sheet has been read once.
* Changed: the preview now shows renamed and hidden columns, so it matches the published page rather than the raw sheet.
* Changed: a table with room to spare now shares that room out equally between its columns instead of in proportion to what each already holds, which left one column sprawling beside several pinched ones.

= 1.5.0 =
* Added: rename a column for your visitors, or leave it out of the table.
* Added: because columns are matched by position, a column added or removed in Google is now reported in the dashboard rather than quietly shifting every label along.
* Added: pinning the first column can be switched off per table, for sheets whose first column is long text.

= 1.4.0 =
* Added: the first column stays pinned while a wide table scrolls sideways, so every row keeps its label.
* Added: the dashboard now says when scheduled syncing has stopped running.
* Fixed: the header background colour was reported as the table background, which made that control in the appearance editor look inert.

= 1.3.0 =
* Added: a visible, draggable slider beneath any table wider than its column.
* Changed: narrow screens now keep the table and scroll it, rather than stacking every row into a card.
* Changed: the layout choice is now a setting on the source screen, so it is made once rather than repeated on every shortcode; the block and shortcode can still override it.
* Fixed: a table too wide for its column collapsed its columns to the narrowest possible, making rows several lines tall for no benefit.

= 1.2.0 =
* Added: a visual appearance editor on the source screen. Colours, text size, row height and corner rounding can be set per table, and the preview updates live.
* Changed: refined the Midnight and Editorial presets.

= 1.1.0 =
* Fixed: wide tables could hide their last columns inside a narrow theme column, with no scrollbar and no switch to the card layout.
* Fixed: choosing a style preset did not change the preview, and editing a saved source always previewed the default preset.
* Fixed: stylesheets and scripts were served under a fixed version, so an upgrade could keep using the previous release's cached CSS.
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

= 3.17.0 =
Dark when the page is dark, not when the visitor's laptop is.

= 3.16.1 =
Text that vanished on a light site seen in dark mode.

= 3.16.0 =
A mark of its own, in place of the borrowed table icon.

= 3.15.0 =
Searching now shows you what it matched.

= 3.14.0 =
Headings that stay put, and the pinned-column setting finally showing in the preview.

= 3.13.0 =
Pagination is findable, and the phone layout shows you what it does.

= 3.12.0 =
The editor's blocks end level, and colour rules preview as you type.

= 3.11.1 =
Five fixes where the drawer, the colour rules and the cache met each other.

= 3.11.0 =
The editor's two columns always end level now.

= 3.10.0 =
Renaming columns no longer needs a save first, and every block wears the same frame.

= 3.9.0 =
Columns can now live under the row, behind an arrow. Fixes the built-in example's Appearance tab.

= 3.8.0 =
Clears the page cache when a sheet changes, and closes a hole in the CSS field.

= 3.7.0 =
Adds a CSS field for each table, confined to that table.

= 3.6.1 =
Fixes the colour swatches keeping their old value after a reset.

= 3.6.0 =
Fixes a false scheduling warning caused by the built-in example, and redraws the settings screen.

= 3.5.0 =
Fixes the built-in example, which would not open, and splits the editor into three tabs.

= 3.4.0 =
A redesigned dashboard: a welcome screen, sheets as cards, one-click shortcode copying, and each sheet says which pages use it.

= 3.3.0 =
Hidden rows and columns are now remembered by their line and their position, and anything that moves in Google comes back on the page with a message instead of the wrong thing disappearing.

= 3.2.0 =
The notice about a hidden row or column now says what actually happened, and stops calling a deleted row an exposure.

= 3.1.2 =
Shortens how a hidden row is quoted, and names a column by its heading.

= 3.1.1 =
Hidden rows are now referred to by what they say, and a row with an empty first cell can be hidden.

= 3.1.0 =
Column settings now follow their heading, and the dashboard tells you when something hidden has come back.

= 3.0.0 =
Adds a Settings screen, moves the plugin's screens into tabs, and stops deleting your tables when the plugin is deleted.

= 2.9.0 =
Hiding columns and rows is a Pro feature, with ten days of grace after Pro stops. A hidden row is now recognised by everything it says.

= 2.8.0 =
Clicking a sheet to hide parts of it is now a Pro feature; rows already hidden stay hidden regardless.

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
