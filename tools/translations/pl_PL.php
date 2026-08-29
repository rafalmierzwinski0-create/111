<?php
/**
 * Polish translation table, consumed by tools/make-pot.php.
 *
 * Keys are the msgid (prefixed with "context\4" where a context applies).
 * A plural entry's value is an array of the three Polish plural forms.
 *
 * @package LiveSheetsTable\Tools
 */

return array(
	// Front-end table.
	'%1$s of %2$s rows'                          => '%1$s z %2$s wierszy',
	'Search this table'                          => 'Przeszukaj tabelę',
	'Search…'                                    => 'Szukaj…',
	'No rows match your search.'                 => 'Żaden wiersz nie pasuje do wyszukiwania.',
	'Sort by %s'                                 => 'Sortuj według: %s',
	'Updated %s ago'                             => 'Zaktualizowano %s temu',
	'Column %d'                                  => 'Kolumna %d',
	'No sheet selected yet.'                     => 'Nie wybrano jeszcze arkusza.',
	'This sheet source no longer exists.'        => 'To źródło arkusza już nie istnieje.',
	'“%s” has not synced yet. Open Live Sheets Table in the dashboard and choose “Refresh now”.'
		=> '„%s” nie zostało jeszcze zsynchronizowane. Otwórz Tabele z arkuszy w kokpicie i wybierz „Odśwież teraz”.',

	// Menu and screens.
	'Live Sheets Table'                          => 'Live Sheets Table',
	'Sheets Tables'                              => 'Tabele z arkuszy',
	'All sheet sources'                          => 'Wszystkie źródła arkuszy',
	'All sources'                                => 'Wszystkie źródła',
	'Add new'                                    => 'Dodaj nowe',
	'Add new sheet source'                       => 'Dodaj nowe źródło arkusza',
	'Edit sheet source'                          => 'Edytuj źródło arkusza',
	'Sheet sources'                              => 'Źródła arkuszy',
	'Add a sheet source'                         => 'Dodaj źródło arkusza',
	'Publish your first Google Sheet'            => 'Opublikuj swój pierwszy arkusz Google',
	'Share a sheet as “Anyone with the link – Viewer”, paste the link, and check the preview before you save. No API key, no Google Cloud project.'
		=> 'Udostępnij arkusz jako „Każdy, kto ma link – Przeglądający”, wklej link i sprawdź podgląd przed zapisaniem. Bez klucza API i bez projektu Google Cloud.',
	'Back to sources'                            => 'Wróć do źródeł',
	'Cancel'                                     => 'Anuluj',

	// List table columns.
	'Source'                                     => 'Źródło',
	'Sync status'                                => 'Status synchronizacji',
	'Size'                                       => 'Rozmiar',
	'Schedule'                                   => 'Harmonogram',
	'Shortcode'                                  => 'Shortcode',
	'Actions'                                    => 'Akcje',
	'%1$s rows × %2$s cols'                      => '%1$s wierszy × %2$s kol.',
	'Tab: %s'                                    => 'Zakładka: %s',
	'Open in Google Sheets'                      => 'Otwórz w Arkuszach Google',
	'Edit'                                       => 'Edytuj',
	'Delete'                                     => 'Usuń',
	'Refresh now'                                => 'Odśwież teraz',
	'Delete this sheet source? Tables using it will stop rendering.'
		=> 'Usunąć to źródło arkusza? Tabele, które go używają, przestaną się wyświetlać.',

	// Sync status.
	'Last sync OK (%s ago)'                      => 'Ostatnia synchronizacja OK (%s temu)',
	'Sync error — visitors still see the last good copy'
		=> 'Błąd synchronizacji — odwiedzający wciąż widzą ostatnią poprawną kopię',
	'Sync error — nothing to show yet'           => 'Błąd synchronizacji — nie ma jeszcze czego pokazać',
	'Not synced yet'                             => 'Jeszcze nie zsynchronizowano',
	'Failing since the last good sync %s ago'    => 'Błąd od ostatniej udanej synchronizacji %s temu',
	'Never synced successfully'                  => 'Nigdy nie zsynchronizowano poprawnie',

	// Editor form.
	'1. Point at your sheet'                     => '1. Wskaż swój arkusz',
	'2. Name it and set the schedule'            => '2. Nazwij go i ustaw harmonogram',
	'3. Pick a look'                             => '3. Wybierz wygląd',
	'In Google Sheets choose Share → General access → “Anyone with the link”, role “Viewer”, then copy the link from your browser. No API key or Google Cloud project is needed.'
		=> 'W Arkuszach Google wybierz Udostępnij → Dostęp ogólny → „Każdy, kto ma link”, rola „Przeglądający”, a następnie skopiuj adres z przeglądarki. Klucz API ani projekt Google Cloud nie są potrzebne.',
	'Google Sheets link'                         => 'Link do arkusza Google',
	'Load preview'                               => 'Wczytaj podgląd',
	'Sheet tab'                                  => 'Zakładka arkusza',
	'The first row contains column headings'     => 'Pierwszy wiersz zawiera nagłówki kolumn',
	'Title'                                      => 'Tytuł',
	'Price list'                                 => 'Cennik',
	'Only shown in the dashboard, to tell sources apart.'
		=> 'Widoczne tylko w kokpicie, żeby odróżnić źródła.',
	'Check Google for changes'                   => 'Sprawdzaj zmiany w Google',
	'Pages always render from the local copy, so visitors never wait for Google. Pro syncs as often as every minute.'
		=> 'Strony zawsze renderują się z lokalnej kopii, więc odwiedzający nigdy nie czekają na Google. Pro synchronizuje nawet co minutę.',
	'Save changes and sync'                      => 'Zapisz zmiany i zsynchronizuj',
	'Save source and sync'                       => 'Zapisz źródło i zsynchronizuj',

	// Preview pane.
	'Preview'                                    => 'Podgląd',
	'Preview width'                              => 'Szerokość podglądu',
	'Width:'                                     => 'Szerokość:',
	'Full width'                                 => 'Pełna szerokość',
	'Narrow column'                              => 'Wąska kolumna',
	'Phone'                                      => 'Telefon',
	'A wide table becomes one card per row once its column gets too narrow. Use these to check both before you publish.'
		=> 'Szeroka tabela zamienia się w jedną kartę na wiersz, gdy jej kolumna staje się zbyt wąska. Sprawdź oba warianty, zanim opublikujesz.',
	'This is exactly what the parser sees. Check the headings and a few rows before you save — wrong tab, merged cells or a shifted header row show up here, not on your live page.'
		=> 'Dokładnie to widzi parser. Sprawdź nagłówki i kilka wierszy przed zapisaniem — zła zakładka, scalone komórki czy przesunięty wiersz nagłówka widać tutaj, a nie na działającej stronie.',
	'Paste a link and choose “Load preview”.'    => 'Wklej link i wybierz „Wczytaj podgląd”.',
	'Loading preview…'                           => 'Wczytywanie podglądu…',
	'Preview failed'                             => 'Podgląd nie powiódł się',
	'Found %1$s rows across %2$s columns.'       => 'Znaleziono %1$s wierszy w %2$s kolumnach.',
	'Showing the first 25 rows.'                 => 'Pokazano pierwsze 25 wierszy.',
	'Pick the tab you want to publish:'          => 'Wybierz zakładkę, którą chcesz opublikować:',
	'Could not read the tab list — the tab from your link will be used.'
		=> 'Nie udało się odczytać listy zakładek — zostanie użyta zakładka z Twojego linku.',
	'Paste a Google Sheets link first.'          => 'Najpierw wklej link do arkusza Google.',

	// Columns and the pinned first column.
	'Columns'                                    => 'Kolumny',
	'Rename a column for your visitors, or leave it out of the table entirely. Nothing here is written back to Google — your spreadsheet keeps its own headings, including working names nobody should see.'
		=> 'Zmień nazwę kolumny dla odwiedzających albo w ogóle pomiń ją w tabeli. Nic z tego nie jest zapisywane do Google — Twój arkusz zachowuje własne nagłówki, także robocze nazwy, których nikt nie powinien widzieć.',
	'In your sheet'                              => 'W Twoim arkuszu',
	'Shown as'                                   => 'Wyświetlane jako',
	'Include'                                    => 'Pokaż',
	'Show this column'                           => 'Pokaż tę kolumnę',
	'The columns in your sheet have moved.'      => 'Kolumny w Twoim arkuszu się przesunęły.',
	'Settings below are matched by position, so a column added or removed in Google shifts them. Check that each row still points at the right column:'
		=> 'Ustawienia poniżej dopasowują się po pozycji, więc kolumna dodana lub usunięta w Google je przesuwa. Sprawdź, czy każdy wiersz nadal wskazuje właściwą kolumnę:',
	'Column %1$d was “%2$s”, now “%3$s”'         => 'Kolumna %1$d była „%2$s”, teraz jest „%3$s”',
	'(no longer there)'                          => '(już jej nie ma)',
	'Keep the first column in view while the table scrolls sideways'
		=> 'Zachowaj pierwszą kolumnę w widoku, gdy tabela przewija się w bok',
	'Useful when the first column names the row — a product, a person, a date. Turn it off if your first column is long text, where pinning it would take up most of a phone screen.'
		=> 'Przydatne, gdy pierwsza kolumna nazywa wiersz — produkt, osobę, datę. Wyłącz, jeśli pierwsza kolumna to długi tekst, bo przyklejona zajęłaby większość ekranu telefonu.',

	// Scheduler health.
	'Scheduled syncing is switched off on this site.'
		=> 'Zaplanowana synchronizacja jest na tej stronie wyłączona.',
	'DISABLE_WP_CRON is set in wp-config.php, which is normal on hosts that run a real system cron. If yours does, your sheets are refreshing on that schedule and nothing is wrong. If it does not, your tables will keep showing the copy they already have until someone presses “Refresh now”.'
		=> 'W pliku wp-config.php ustawiono DISABLE_WP_CRON, co jest normalne na hostingach z prawdziwym systemowym cronem. Jeśli Twój go ma, arkusze odświeżają się według tamtego harmonogramu i wszystko jest w porządku. Jeśli nie ma, tabele będą pokazywać kopię, którą już mają, dopóki ktoś nie naciśnie „Odśwież teraz”.',
	'The sync schedule is missing.'              => 'Brakuje harmonogramu synchronizacji.',
	'Another plugin or a maintenance tool may have cleared it. Saving any sheet source restores it.'
		=> 'Mogła go wyczyścić inna wtyczka albo narzędzie serwisowe. Zapisanie dowolnego źródła arkusza przywraca go.',
	'Sheets have not been checked for %s.'       => 'Arkusze nie były sprawdzane od %s.',
	'WordPress runs scheduled work when someone visits the site, so a quiet site can fall behind. On a site that should be busy this usually means WP-Cron is blocked — by a security plugin, a page cache serving every request, or a host that disables it. Your tables are still showing their last good copy.'
		=> 'WordPress wykonuje zaplanowane zadania, gdy ktoś odwiedza stronę, więc mało odwiedzana witryna może się opóźniać. Na stronie, która powinna mieć ruch, zwykle oznacza to zablokowany WP-Cron — przez wtyczkę bezpieczeństwa, cache serwujący każde żądanie albo hosting, który go wyłącza. Twoje tabele nadal pokazują ostatnią poprawną kopię.',
	'Meanwhile you can update any sheet by hand with “Refresh now”. %s'
		=> 'W międzyczasie możesz zaktualizować dowolny arkusz ręcznie przyciskiem „Odśwież teraz”. %s',
	'How to run WordPress schedules from a system cron'
		=> 'Jak uruchamiać harmonogramy WordPressa z systemowego crona',

	// Layout and the horizontal slider.
	'On screens too narrow for the whole table'  => 'Na ekranach zbyt wąskich dla całej tabeli',
	'Keep the table and add a slider to scroll it sideways'
		=> 'Zachowaj tabelę i dodaj suwak do przewijania w bok',
	'Turn each row into a labelled card'         => 'Zamień każdy wiersz w kartę z etykietami',
	'Always use cards, at every width'           => 'Zawsze używaj kart, przy każdej szerokości',
	'The slider is always visible while there is more table to see, unlike the browser\'s own scrollbar. Use the width buttons beside the preview to check it.'
		=> 'Suwak jest widoczny zawsze, gdy tabela ma coś jeszcze do pokazania — inaczej niż pasek przewijania przeglądarki. Sprawdź go przyciskami szerokości obok podglądu.',
	'Table with a slider'                        => 'Tabela z suwakiem',
	'Stack into cards when narrow'               => 'Karty, gdy wąsko',
	'A wide table keeps its shape and gains a draggable slider. Card layouts stack each row instead, which suits tables of long text.'
		=> 'Szeroka tabela zachowuje swój kształt i zyskuje przeciągany suwak. Układy kartowe zamiast tego układają wiersze pionowo, co pasuje do tabel z długim tekstem.',
	'Table, scrollable sideways'                 => 'Tabela, przewijana w bok',
	'Scroll the table sideways'                  => 'Przewiń tabelę w bok',

	// Visual appearance editor.
	'4. Fine-tune the look'                      => '4. Dopracuj wygląd',
	'Optional. Anything you leave untouched follows the preset above, so you can change one colour without redefining the rest. The preview updates as you go.'
		=> 'Opcjonalne. Wszystko, czego nie ruszysz, podąża za presetem powyżej, więc możesz zmienić jeden kolor bez definiowania reszty. Podgląd aktualizuje się na bieżąco.',
	'Text'                                       => 'Tekst',
	'Background'                                 => 'Tło',
	'Header text'                                => 'Tekst nagłówka',
	'Header background'                          => 'Tło nagłówka',
	'Lines'                                      => 'Linie',
	'Striped rows'                               => 'Naprzemienne wiersze',
	'Row hover'                                  => 'Wiersz pod kursorem',
	'Accent'                                     => 'Akcent',
	'Text size'                                  => 'Wielkość tekstu',
	'Row height'                                 => 'Wysokość wiersza',
	'Corners'                                    => 'Narożniki',
	'Small'                                      => 'Mała',
	'Normal'                                     => 'Normalna',
	'Large'                                      => 'Duża',
	'Compact'                                    => 'Zwarta',
	'Roomy'                                      => 'Przestronna',
	'Square'                                     => 'Proste',
	'Rounded'                                    => 'Zaokrąglone',
	'Very rounded'                               => 'Mocno zaokrąglone',
	'Reset'                                      => 'Wyczyść',
	'Reset everything to the preset'             => 'Przywróć wszystko do presetu',

	// Style presets.
	'Clean'                                      => 'Czysty',
	'Light rules between rows, generous spacing. Inherits your theme fonts.'
		=> 'Delikatne linie między wierszami, sporo przestrzeni. Dziedziczy kroje pisma motywu.',
	'Striped'                                    => 'Paski',
	'Alternating row tint for scanning long lists.'
		=> 'Naprzemienne tło wierszy, ułatwia przeglądanie długich list.',
	'Bordered'                                   => 'Ramki',
	'Full grid with a shaded header. Good for dense numeric data.'
		=> 'Pełna siatka z cieniowanym nagłówkiem. Dobre dla gęstych danych liczbowych.',
	'Midnight'                                   => 'Północ',
	'High-contrast dark preset.'                 => 'Ciemny preset o wysokim kontraście.',
	'Editorial'                                  => 'Redakcyjny',
	'Serif headings and hairline rules, styled after print tables.'
		=> 'Szeryfowe nagłówki i włosowe linie, stylizowane na tabele drukowane.',

	// Usage panel.
	'Put it on a page'                           => 'Umieść na stronie',
	'Use the “Google Sheets Table” block, or paste this shortcode into any editor, widget or page builder:'
		=> 'Użyj bloku „Tabela z Arkuszy Google” albo wklej ten shortcode w dowolnym edytorze, widżecie lub kreatorze stron:',
	'Optional attributes: search="no", sort="no", meta="no", style="striped", caption="My table".'
		=> 'Opcjonalne atrybuty: search="no", sort="no", meta="no", style="striped", caption="Moja tabela".',

	// Block editor.
	'Google Sheets Table'                        => 'Tabela z Arkuszy Google',
	'Sheet source'                               => 'Źródło arkusza',
	'Saved source'                               => 'Zapisane źródło',
	'Select a sheet…'                            => 'Wybierz arkusz…',
	'Choose which saved sheet to show.'          => 'Wybierz, który zapisany arkusz pokazać.',
	'No sheet sources yet. Add one in the dashboard, then pick it here.'
		=> 'Nie ma jeszcze żadnych źródeł arkuszy. Dodaj jedno w kokpicie, a potem wybierz je tutaj.',
	'Manage sheet sources'                       => 'Zarządzaj źródłami arkuszy',
	'Table options'                              => 'Opcje tabeli',
	'Search box'                                 => 'Pole wyszukiwania',
	'Sortable columns'                           => 'Sortowalne kolumny',
	'Show “updated … ago”'                       => 'Pokaż „zaktualizowano … temu”',
	'Layout'                                     => 'Układ',
	'Automatic — stack into cards when the column is narrow'
		=> 'Automatyczny — zamień w karty, gdy kolumna jest wąska',
	'Always a table — scroll sideways instead'   => 'Zawsze tabela — przewijaj w poziomie',
	'Always cards'                               => 'Zawsze karty',
	'Narrow theme columns cannot fit a wide table. "Automatic" turns each row into a labelled card rather than hiding columns.'
		=> 'Wąskie kolumny motywu nie pomieszczą szerokiej tabeli. „Automatyczny” zamienia każdy wiersz w kartę z etykietami, zamiast chować kolumny.',
	'Style preset'                               => 'Preset stylu',
	'Use the source default'                     => 'Użyj domyślnego dla źródła',
	'Caption'                                    => 'Podpis',
	'rows'                                       => 'wierszy',

	// Notices.
	'Sheet source saved and synced.'             => 'Źródło arkusza zapisane i zsynchronizowane.',
	'Sheet source deleted.'                      => 'Źródło arkusza usunięte.',
	'Sheet refreshed from Google.'               => 'Arkusz odświeżony z Google.',
	'Saved, but the first sync failed: %s'       => 'Zapisano, ale pierwsza synchronizacja nie powiodła się: %s',
	'Refresh failed: %s'                         => 'Odświeżanie nie powiodło się: %s',
	'Untitled sheet'                             => 'Arkusz bez tytułu',

	// Errors.
	'Paste the link to your Google Sheet first.' => 'Najpierw wklej link do swojego arkusza Google.',
	'That does not look like a valid link. Copy the address straight from your browser.'
		=> 'To nie wygląda na poprawny link. Skopiuj adres bezpośrednio z przeglądarki.',
	'Only Google Sheets links are supported. The address must start with https://docs.google.com/spreadsheets/.'
		=> 'Obsługiwane są wyłącznie linki do Arkuszy Google. Adres musi zaczynać się od https://docs.google.com/spreadsheets/.',
	'No spreadsheet ID found in that link. Use the address of the sheet itself, for example https://docs.google.com/spreadsheets/d/ABC123/edit.'
		=> 'Nie znaleziono identyfikatora arkusza w tym linku. Użyj adresu samego arkusza, na przykład https://docs.google.com/spreadsheets/d/ABC123/edit.',
	'The sheet returned no data. Check that the tab you picked actually contains rows.'
		=> 'Arkusz nie zwrócił żadnych danych. Sprawdź, czy wybrana zakładka faktycznie zawiera wiersze.',
	'Could not reach Google: %s'                 => 'Nie udało się połączyć z Google: %s',
	'Google refused access to this sheet (HTTP 403). Open the sheet, choose Share, and set access to "Anyone with the link – Viewer".'
		=> 'Google odmówił dostępu do tego arkusza (HTTP 403). Otwórz arkusz, wybierz Udostępnij i ustaw dostęp na „Każdy, kto ma link – Przeglądający”.',
	'Google could not find this spreadsheet (HTTP 404). Check that the link is correct and the file has not been deleted.'
		=> 'Google nie znalazł tego arkusza (HTTP 404). Sprawdź, czy link jest poprawny i czy plik nie został usunięty.',
	'Google is rate limiting requests (HTTP 429). The next scheduled sync will try again.'
		=> 'Google ogranicza liczbę żądań (HTTP 429). Kolejna zaplanowana synchronizacja spróbuje ponownie.',
	'Google responded with HTTP %d.'             => 'Google odpowiedział kodem HTTP %d.',
	'Google returned a sign-in page instead of data. Open the sheet, choose Share, and set access to "Anyone with the link – Viewer".'
		=> 'Google zwrócił stronę logowania zamiast danych. Otwórz arkusz, wybierz Udostępnij i ustaw dostęp na „Każdy, kto ma link – Przeglądający”.',
	'Google returned an empty response for this tab.'
		=> 'Google zwrócił pustą odpowiedź dla tej zakładki.',
	'Could not read the tab list for this spreadsheet.'
		=> 'Nie udało się odczytać listy zakładek tego arkusza.',
	'Could not save the sheet source.'           => 'Nie udało się zapisać źródła arkusza.',
	'That sheet source no longer exists.'        => 'To źródło arkusza już nie istnieje.',
	'You are not allowed to manage sheet sources.'
		=> 'Nie masz uprawnień do zarządzania źródłami arkuszy.',
	'You are not allowed to list sheet sources.' => 'Nie masz uprawnień do przeglądania źródeł arkuszy.',

	// Schedules.
	'Every minute'                               => 'Co minutę',
	'Every 5 minutes'                            => 'Co 5 minut',
	'Every 15 minutes'                           => 'Co 15 minut',
	'Every 30 minutes'                           => 'Co 30 minut',
	'Hourly'                                     => 'Co godzinę',
	'Every 6 hours'                              => 'Co 6 godzin',
	'Daily'                                      => 'Codziennie',
	'Every %s'                                   => 'Co %s',
	'Live Sheets Table: every %s'                => 'Live Sheets Table: co %s',

	// Upsell.
	'Need more than one sheet?'                  => 'Potrzebujesz więcej niż jednego arkusza?',
	'Pro adds unlimited sources, one-minute syncing, conditional cell formatting, pagination, premium presets and private-sheet support.'
		=> 'Pro dodaje nielimitowaną liczbę źródeł, synchronizację co minutę, formatowanie warunkowe komórek, paginację, presety premium i obsługę arkuszy prywatnych.',
	'Compare Free and Pro'                       => 'Porównaj wersję darmową i Pro',
	'See what Pro adds'                          => 'Zobacz, co daje Pro',
	'Pro'                                        => 'Pro',
	'Rows are never limited — a source can hold as many rows as your sheet does. To publish several different sheets at once, upgrade to Pro.'
		=> 'Liczba wierszy nigdy nie jest ograniczona — źródło pomieści tyle wierszy, ile ma Twój arkusz. Aby publikować kilka różnych arkuszy naraz, przejdź na Pro.',
	'Waiting for a first look at the sheet. Choose “Refresh now” on the sources list, and your real columns will appear here.'
		=> 'Czekamy na pierwsze odczytanie arkusza. Kliknij „Odśwież teraz” na liście źródeł, a pojawią się tutaj Twoje prawdziwe kolumny.',
	'Save this source first. It is read straight away, and your real columns appear here.'
		=> 'Najpierw zapisz to źródło. Arkusz zostanie od razu odczytany, a Twoje prawdziwe kolumny pojawią się tutaj.',
	'Make web and e-mail addresses in cells clickable' => 'Zamieniaj adresy WWW i e-mail w komórkach na klikalne linki',
	'A link in a cell is otherwise plain text a visitor has to select and copy, which on a phone is close to impossible. Only http, https and e-mail addresses are linked.'
		=> 'Bez tego link w komórce jest zwykłym tekstem, który odwiedzający musi zaznaczyć i skopiować — na telefonie to praktycznie niewykonalne. Linkowane są wyłącznie adresy http, https i e-mail.',
	'This sheet did not come back cleanly.'      => 'Ten arkusz wrócił uszkodzony.',
	'The table below still renders from the copy that arrived, so nothing on your site is broken. Fix the rows in Google and choose “Save changes and sync”.'
		=> 'Tabela poniżej nadal renderuje się z kopii, która dotarła, więc nic na Twojej stronie nie jest zepsute. Popraw te wiersze w Google i kliknij „Zapisz zmiany i zsynchronizuj”.',
	'The usual cause is a value that runs two cells together — a lone quotation mark, or a comma inside a value that was not quoted. It can also be a cell holding a line break, or a copy of the sheet edited by hand rather than exported by Google. Open those rows in your sheet and compare them against the ones around them.'
		=> 'Najczęstsza przyczyna to wartość, która skleiła dwie komórki w jedną — pojedynczy cudzysłów albo przecinek w wartości, która nie została ujęta w cudzysłów. Bywa też, że komórka zawiera znak nowej linii, albo że arkusz był edytowany ręcznie zamiast wyeksportowany przez Google. Otwórz te wiersze w arkuszu i porównaj je z sąsiednimi.',
	'Filter'                                     => 'Filtr',
	'Which rows'                                 => 'Które wiersze',
	'Show only matching rows, for example: Kategoria is Rowery. Join conditions with “and”. Operators: is, is not, has, gt, gte, lt, lte.'
		=> 'Pokaż tylko pasujące wiersze, na przykład: Kategoria is Rowery. Warunki łącz słowem „and”. Operatory: is, is not, has, gt, gte, lt, lte.',
	'Live Sheets Table: a sheet did not come back cleanly.' => 'Live Sheets Table: arkusz wrócił uszkodzony.',
	'Hide this until it happens again'           => 'Ukryj do następnego razu',
	'This table is set to show only some of its rows, but the add-on that does the filtering is not active. Nothing is shown rather than every row, which is not what this page asked for. Activate the add-on, or remove the filter from the block or shortcode.'
		=> 'Ta tabela ma pokazywać tylko część wierszy, ale dodatek odpowiedzialny za filtrowanie nie jest aktywny. Zamiast wszystkich wierszy nie pokazujemy żadnego — bo to nie jest to, o co prosiła ta strona. Włącz dodatek albo usuń filtr z bloku lub shortcode’u.',

	// Plurals: Polish has three forms.
	'The free version keeps %d sheet source'     => array(
		'Wersja darmowa przechowuje %d źródło arkusza',
		'Wersja darmowa przechowuje %d źródła arkuszy',
		'Wersja darmowa przechowuje %d źródeł arkuszy',
	),
	'Row %3$s came back with a different number of cells than the other rows (%2$d), so a value in it may be missing or sitting in the wrong column. Most often a lone quotation mark or a comma inside a value has run two cells into one.' => array(
		'Wiersz %3$s wrócił z inną liczbą komórek niż pozostałe (%2$d), więc jakaś wartość może być pominięta albo trafić do złej kolumny. Najczęściej odpowiada za to pojedynczy cudzysłów albo przecinek w wartości, który skleił dwie komórki w jedną.',
		'%1$d wiersze wróciły z inną liczbą komórek niż reszta (%2$d), więc jakieś wartości mogą być pominięte albo trafić do złych kolumn. Najczęściej odpowiada za to pojedynczy cudzysłów albo przecinek w wartości, który skleił dwie komórki w jedną. Wiersze: %3$s.',
		'%1$d wierszy wróciło z inną liczbą komórek niż reszta (%2$d), więc jakieś wartości mogą być pominięte albo trafić do złych kolumn. Najczęściej odpowiada za to pojedynczy cudzysłów albo przecinek w wartości, który skleił dwie komórki w jedną. Wiersze: %3$s.',
	),
	'The free version stores %d sheet source. Remove the existing one, or upgrade to add more.' => array(
		'Wersja darmowa przechowuje %d źródło arkusza. Usuń istniejące albo przejdź na Pro, aby dodać więcej.',
		'Wersja darmowa przechowuje %d źródła arkuszy. Usuń jedno albo przejdź na Pro, aby dodać więcej.',
		'Wersja darmowa przechowuje %d źródeł arkuszy. Usuń jedno albo przejdź na Pro, aby dodać więcej.',
	),
);
