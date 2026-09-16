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

	// Menu and screens.
	'Live Sheets Table'                          => 'Live Sheets Table',
	'Sheets Tables'                              => 'Tabele z arkuszy',
	'All sheet sources'                          => 'Wszystkie źródła arkuszy',
	'All sources'                                => 'Wszystkie źródła',
	'Add new'                                    => 'Dodaj nowe',
	'Add new sheet source'                       => 'Dodaj nowe źródło arkusza',
	'Sheet sources'                              => 'Źródła arkuszy',
	'Add a sheet source'                         => 'Dodaj źródło arkusza',
	'Back to sources'                            => 'Wróć do źródeł',
	'Cancel'                                     => 'Anuluj',

	// List table columns.
	'Edit'                                       => 'Edytuj',
	'Delete'                                     => 'Usuń',
	'Delete this sheet source? Tables using it will stop rendering.'
		=> 'Usunąć to źródło arkusza? Tabele, które go używają, przestaną się wyświetlać.',

	// Sync status.
	'Last sync OK (%s ago)'                      => 'Ostatnia synchronizacja OK (%s temu)',
	'Sync error — visitors still see the last good copy'
		=> 'Błąd synchronizacji — odwiedzający wciąż widzą ostatnią poprawną kopię',
	'Sync error — nothing to show yet'           => 'Błąd synchronizacji — nie ma jeszcze czego pokazać',
	'Not synced yet'                             => 'Jeszcze nie zsynchronizowano',
	'Failing since the last good sync %s ago'    => 'Ostatnia udana synchronizacja %s temu, od tego czasu błędy',
	'Never synced successfully'                  => 'Nigdy nie zsynchronizowano poprawnie',

	// Editor form.
	'Google Sheets link'                         => 'Link do arkusza Google',
	'Load preview'                               => 'Wczytaj podgląd',
	'Sheet tab'                                  => 'Zakładka arkusza',
	'The first row contains column headings'     => 'Pierwszy wiersz zawiera nagłówki kolumn',
	'Title'                                      => 'Tytuł',
	'Price list'                                 => 'Cennik',
	'Only shown in the dashboard, to tell sources apart.'
		=> 'Widoczne tylko w kokpicie, żeby odróżnić źródła.',
	'Check Google for changes'                   => 'Sprawdzaj zmiany w Google',
	'Pro syncs as often as every minute.'        => 'Pro synchronizuje nawet co minutę.',
	'“%1$s” has not been refreshed for %2$s.'
		=> 'Arkusz „%1$s” odświeżał się ostatnio %2$s temu.',
	'In the table'                               => 'W tabeli',
	'Shown'                                      => 'Widoczna',
	'Hidden'                                     => 'Ukryta',
	'Columns and rows you hid will start showing again in %s.'
		=> 'Ukryte przez Ciebie kolumny i wiersze zaczną znów się pokazywać za %s.',
	// Settings screen.
	'Sheet sources'                              => 'Źródła arkuszy',
	'Settings'                                   => 'Ustawienia',
	'Live Sheets Table settings'                 => 'Ustawienia Live Sheets Table',
	'Settings saved.'                            => 'Ustawienia zapisane.',
	'Save settings'                              => 'Zapisz ustawienia',
	'Who can manage tables'                      => 'Kto może zarządzać tabelami',
	'Editors and above'                          => 'Redaktorzy i wyżej',
	'Administrators only'                        => 'Tylko administratorzy',
	'How often new tables check Google'          => 'Jak często nowe tabele sprawdzają Google',
	'The fastest allowed, currently every %s'
		=> 'Najszybciej, jak można — obecnie co %s',
	'Every %s'                                   => 'Co %s',
	'When this plugin is deleted'                => 'Gdy ta wtyczka zostanie usunięta',
	'Also delete every sheet source and setting' => 'Usuń też wszystkie źródła arkuszy i ustawienia',
	'You do not have permission to change these settings.'
		=> 'Nie masz uprawnień do zmiany tych ustawień.',
	'See what is hidden, and what will come back'
		=> 'Zobacz, co jest ukryte i co wróci',
	// When something hidden comes back.
	'Something you had taken out of a table is on the page again.'
		=> 'Coś, co usunąłeś z tabeli, jest znów na stronie.',
	'Something you had taken out of a table is no longer in the sheet.'
		=> 'Czegoś, co usunąłeś z tabeli, nie ma już w arkuszu.',
	'nothing'                                    => 'nic',
	'the empty row'                              => 'pusty wiersz',
	'Open this table'                            => 'Otwórz tę tabelę',
	'I have read this'                           => 'Przeczytałem',
	'You do not have permission to do that.'     => 'Nie masz do tego uprawnień.',
	'Save changes and sync'                      => 'Zapisz zmiany i zsynchronizuj',
	'Refresh'                                    => 'Odśwież',
	'next check in %s'                           => 'następne sprawdzenie za %s',
	'Save source and sync'                       => 'Zapisz źródło i zsynchronizuj',

	// Preview pane.
	'Preview'                                    => 'Podgląd',
	'Preview width'                              => 'Szerokość podglądu',
	'Width:'                                     => 'Szerokość:',
	'Full width'                                 => 'Pełna szerokość',
	'Narrow column'                              => 'Wąska kolumna',
	'Phone'                                      => 'Telefon',
	'Paste a link and choose “Load preview”.'    => 'Wklej link i wybierz „Wczytaj podgląd”.',
	'Loading preview…'                           => 'Wczytywanie podglądu…',
	'Preview failed'                             => 'Podgląd nie powiódł się',
	'Found %1$s rows across %2$s columns.'       => 'Znaleziono %1$s wierszy w %2$s kolumnach.',
	'Showing the first 25 rows.'                 => 'Pokazano pierwsze 25 wierszy.',
	'Pick the tab you want to publish:'          => 'Wybierz zakładkę, którą chcesz opublikować:',
	'Paste a Google Sheets link first.'          => 'Najpierw wklej link do arkusza Google.',

	// Columns and the pinned first column.
	'Columns'                                    => 'Kolumny',
	'In your sheet'                              => 'W Twoim arkuszu',
	'Shown as'                                   => 'Wyświetlane jako',
	'The columns in your sheet have moved.'      => 'Kolumny w Twoim arkuszu się przesunęły.',
	'Column %1$d was “%2$s”, now “%3$s”'         => 'Kolumna %1$d była „%2$s”, teraz jest „%3$s”',
	'(no longer there)'                          => '(już jej nie ma)',

	// Scheduler health.
	'The WordPress guide to system cron'
		=> 'Poradnik WordPressa o systemowym cronie',
	'Why it happens, and how to fix it for good'
		=> 'Dlaczego tak się dzieje i jak to naprawić na stałe',
	'Your pages still show the last copy that arrived, so nothing is broken for visitors.'
		=> 'Twoje strony nadal pokazują ostatnią kopię, która dotarła, więc dla odwiedzających nic nie jest zepsute.',

	// Layout and the horizontal slider.
	'Suggested'                                  => 'Sugerowane',
	'Table style'                                => 'Styl tabeli',
	'Table with a slider'                        => 'Tabela z suwakiem',
	'Stack into cards when narrow'               => 'Karty, gdy wąsko',
	'A wide table keeps its shape and gains a draggable slider. Card layouts stack each row instead, which suits tables of long text.'
		=> 'Szeroka tabela zachowuje swój kształt i zyskuje przeciągany suwak. Układy kartowe zamiast tego układają wiersze pionowo, co pasuje do tabel z długim tekstem.',
	'Table, scrollable sideways'                 => 'Tabela, przewijana w bok',
	'Scroll the table sideways'                  => 'Przewiń tabelę w bok',

	// Visual appearance editor.
	'Text'                                       => 'Tekst',
	'Background'                                 => 'Tło',
	'Header text'                                => 'Tekst nagłówka',
	'Header background'                          => 'Tło nagłówka',
	'Lines'                                      => 'Linie',
	'Striped rows'                               => 'Naprzemienne wiersze',
	'Row hover'                                  => 'Wiersz pod kursorem',
	'Accent'                                     => 'Akcent',
	'Text size'                                  => 'Wielkość tekstu',
	'Heading text size'                          => 'Wielkość tekstu nagłówków',
	'Row height'                                 => 'Wysokość wiersza',
	'Page buttons'                               => 'Przyciski stron',
	'The “updated … ago” line takes the other side'
		=> 'Napis „zaktualizowano … temu” staje po drugiej stronie',
	'Left'                                       => 'Do lewej',
	'Centred'                                    => 'Na środku',
	'Right'                                      => 'Do prawej',
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
	'Reset everything to the chosen style'
		=> 'Przywróć wszystko do wybranego stylu',

	// Style presets.
	'Clean'                                      => 'Czysty',
	'Light lines between rows and generous spacing. Uses your theme\'s fonts.'
		=> 'Delikatne linie między wierszami i sporo przestrzeni. Używa krojów pisma z Twojego motywu.',
	'Striped'                                    => 'Paski',
	'Alternating row shading, which makes a long list easier to follow.'
		=> 'Naprzemienne cieniowanie wierszy, które ułatwia śledzenie długiej listy.',
	'Bordered'                                   => 'Ramki',
	'A full grid with a shaded heading row. Suits dense columns of numbers.'
		=> 'Pełna siatka z przyciemnionym wierszem nagłówka. Pasuje do gęstych kolumn liczb.',
	'Midnight'
		=> 'Nocny',
	'A dark table with high contrast.'
		=> 'Ciemna tabela o wysokim kontraście.',
	'Editorial'                                  => 'Redakcyjny',
	'Serif headings and very thin lines, in the manner of a printed table.'
		=> 'Szeryfowe nagłówki i bardzo cienkie linie, jak w tabeli drukowanej.',

	// Usage panel.
	'Put it on a page'                           => 'Umieść na stronie',
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
	'Always cards'                               => 'Zawsze karty',
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
	'Live Sheets Table: every %s'                => 'Live Sheets Table: co %s',

	// Upsell.
	'Need more than one sheet?'                  => 'Potrzebujesz więcej niż jednego arkusza?',
	'Compare Free and Pro'                       => 'Porównaj wersję darmową i Pro',
	'See what Pro adds'                          => 'Zobacz, co daje Pro',
	'Pro'                                        => 'Pro',
	'Save this source first. It is read straight away, and your real columns appear here.'
		=> 'Najpierw zapisz to źródło. Arkusz zostanie od razu odczytany, a Twoje prawdziwe kolumny pojawią się tutaj.',
	'Some rows of this sheet could not be read.'
		=> 'Nie udało się odczytać części wierszy tego arkusza.',
	'Filter'                                     => 'Filtr',
	'Which rows'                                 => 'Które wiersze',
	'Show only matching rows, for example: Kategoria is Rowery. Join conditions with “and”. Operators: is, is not, has, gt, gte, lt, lte.'
		=> 'Pokaż tylko pasujące wiersze, na przykład: Kategoria is Rowery. Warunki łącz słowem „and”. Operatory: is, is not, has, gt, gte, lt, lte.',
	'Live Sheets Table: a sheet did not come back cleanly.' => 'Live Sheets Table: arkusz wrócił uszkodzony.',
	'Hide this until it happens again'           => 'Ukryj do następnego razu',
	'What Google actually sent'                  => 'Co dokładnie przysłał Google',
	'%1$s characters received.'                  => 'Odebrano %1$s znaków.',
	'Look at row %1$s: it came back with a different number of cells than the rest.'
		=> 'Zobacz wiersz %1$s: wrócił z inną liczbą komórek niż reszta.',
	'Pagination'                                 => 'Paginacja',
	'Rows on each page'                          => 'Wierszy na każdej stronie',
	'Search the whole sheet…'                    => 'Szukaj w całym arkuszu…',
	'Search'                                     => 'Szukaj',
	'Clear'                                      => 'Wyczyść',
	'Table pages'                                => 'Strony tabeli',
	'Previous'                                   => 'Poprzednia',
	'Next'                                       => 'Następna',
	'Page %1$s of %2$s'                          => 'Strona %1$s z %2$s',
	'Google Sheets'                              => 'Arkusze Google',
	'Sheet'                                      => 'Arkusz',
	'Display'                                    => 'Wygląd',
	'%1$s (%2$s rows)'                           => '%1$s (%2$s wierszy)',
	'Sheets are added and refreshed in %s.'      => 'Arkusze dodaje się i odświeża w %s.',

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

	// The redesigned dashboard: welcome screen, cards and the bundled example.
	' and %s more'
		=> ' i jeszcze %s',
	'%1$s rows × %2$s columns'
		=> '%1$s wierszy × %2$s kolumn',
	'%1$s sheet · %2$s rows'
		=> array( '%1$s arkusz · %2$s wierszy', '%1$s arkusze · %2$s wierszy', '%1$s arkuszy · %2$s wierszy' ),
	'%s and see the whole plugin working. One click removes it again.'
		=> '%s i obejrzyj całą wtyczkę w działaniu. Usuniesz go jednym kliknięciem.',
	'(no title)'
		=> '(bez tytułu)',
	'1 h'
		=> '1 godz.',
	'2 h'
		=> '2 godz.',
	'20 min'
		=> '20 min',
	'25 min'
		=> '25 min',
	'3 h'
		=> '3 godz.',
	'30 min'
		=> '30 min',
	'40 min'
		=> '40 min',
	'45 min'
		=> '45 min',
	'A change in the sheet reaches the page by itself'
		=> 'Zmiana w arkuszu trafia na stronę sama',
	'Add a sheet'
		=> 'Dodaj arkusz',
	'Add the example'
		=> 'Dodaj przykład',
	'Add the example price list'
		=> 'Dodaj przykładowy cennik',
	'Availability'
		=> 'Dostępność',
	'Basic service'
		=> 'Przegląd podstawowy',
	'Brake pads'
		=> 'Wymiana klocków',
	'Built into the plugin so you can try everything. Delete it whenever you like.'
		=> 'Wbudowany we wtyczkę, żebyś mógł wszystko wypróbować. Usuń go, kiedy zechcesz.',
	'Cables included, housing extra'
		=> 'Linki w cenie, pancerze osobno',
	'Chain replacement'
		=> 'Wymiana łańcucha',
	'Column %s'
		=> 'Kolumna %s',
	'Copied'
		=> 'Skopiowane',
	'Copy'
		=> 'Kopiuj',
	'Editing a sheet'
		=> 'Edycja arkusza',
	'Example'
		=> 'Przykład',
	'Example price list'
		=> 'Przykładowy cennik',
	'Example — not from Google'
		=> 'Przykład — nie z Google',
	'Full check, wash and lubrication'
		=> 'Przegląd, mycie i smarowanie',
	'Gear adjustment'
		=> 'Regulacja przerzutek',
	'Google Sheets link'
		=> 'Link do arkusza Google',
	'Google did not answer'
		=> 'Google nie odpowiedział',
	'In Google'
		=> 'W Google',
	'In stock'
		=> 'Od ręki',
	'Is the sheet private?'
		=> 'Arkusz jest prywatny?',
	'Loads instantly — the page reads a local copy'
		=> 'Ładuje się natychmiast — strona czyta lokalną kopię',
	'Meanwhile you can update any sheet by hand with “Refresh”.'
		=> 'W międzyczasie możesz odświeżyć każdy arkusz ręcznie przyciskiem „Odśwież”.',
	'No parts replaced'
		=> 'Bez wymiany części',
	'No sheets yet'
		=> 'Nie ma jeszcze arkuszy',
	'No spreadsheet yet?'
		=> 'Nie masz jeszcze arkusza?',
	'Not checked yet'
		=> 'Jeszcze nie sprawdzony',
	'Not on any page yet — safe to delete'
		=> 'Nie ma go na żadnej stronie — można bezpiecznie usunąć',
	'Notes'
		=> 'Uwagi',
	'Nothing to show yet'
		=> 'Nie ma jeszcze czego pokazać',
	'Organic or metallic compound'
		=> 'Okładziny organiczne lub metaliczne',
	'Part on back order'
		=> 'Część na zamówienie',
	'Paste a link and see the table before you save'
		=> 'Wklej link i zobacz tabelę, zanim zapiszesz',
	'Press Ctrl+C'
		=> 'Naciśnij Ctrl+C',
	'Price'
		=> 'Cena netto',
	'Rack fitting'
		=> 'Montaż bagażnika',
	'Season preparation'
		=> 'Przygotowanie do sezonu',
	'Seatpost or frame mount'
		=> 'Montaż na sztycę lub ramę',
	'Service'
		=> 'Usługa',
	'Settings for the whole site'
		=> 'Ustawienia dla całej witryny',
	'Show me the table'
		=> 'Pokaż tabelę',
	'Suspension overhaul'
		=> 'Serwis amortyzatora',
	'This sheet has never been read successfully.'
		=> 'Tego arkusza nie udało się jeszcze ani razu odczytać.',
	'Time'
		=> 'Czas',
	'To order'
		=> 'Na zamówienie',
	'Unavailable'
		=> 'Brak',
	'Up to date — %s ago'
		=> 'Aktualny — %s temu',
	'Up to five working days'
		=> 'Termin do 5 dni roboczych',
	'Used on'
		=> 'Użyty na',
	'Wheel truing'
		=> 'Centrowanie koła',
	'Your price list on the page in ten seconds'
		=> 'Twój cennik na stronie w dziesięć sekund',
	'“%s” has not synced yet. Open Live Sheets Table in the dashboard and choose “Refresh”.'
		=> '„%s” nie zostało jeszcze zsynchronizowane. Otwórz Tabele z arkuszy w kokpicie i wybierz „Odśwież”.',

	// The editor split into panes.
	'Columns and rows'
		=> 'Kolumny i wiersze',
	'Fine-tune the look'
		=> 'Dopracuj wygląd',
	'General'
		=> 'Ogólne',
	'Appearance'
		=> 'Wygląd',
	'Save changes'
		=> 'Zapisz zmiany',
	'Name it and set the schedule'
		=> 'Nazwij i ustaw harmonogram',
	'Look and behaviour'
		=> 'Wygląd i zachowanie',
	'Point at your sheet'
		=> 'Wskaż swój arkusz',
	'This is the built-in example'
		=> 'To jest wbudowany przykład',

	// Site-wide settings added alongside the redesign.
	'How long to wait for Google'
		=> 'Jak długo czekać na Google',
	'How new tables look'
		=> 'Jak wyglądają nowe tabele',
	'seconds'
		=> 'sekund',

	// Settings redrawn as panels.
	'Access'
		=> 'Dostęp',
	'Checking Google'
		=> 'Sprawdzanie Google',
	'Delete everything'
		=> 'Usuń wszystko',
	'How often, and how long a visitor may be made to wait'
		=> 'Jak często i jak długo odwiedzający może czekać',
	'The one setting here that cannot be undone'
		=> 'Jedyne ustawienie tutaj, którego nie da się cofnąć',
	'How a new table looks before you change anything'
		=> 'Jak wygląda nowa tabela, zanim cokolwiek zmienisz',
	'Who is trusted with the tables on this site'
		=> 'Komu powierzasz tabele na tej witrynie',
	'Your own CSS'
		=> 'Własny CSS',
	'Each rule is saved with %s in front, so it only ever affects this table.'
		=> 'Każda reguła zapisuje się z %s z przodu, więc działa tylko na tę tabelę.',
	'You are not allowed to write CSS on this site.'
		=> 'Nie masz uprawnień do pisania CSS na tej witrynie.',
	'Whole page cache cleared %s ago'
		=> 'Pamięć podręczna całej witryny wyczyszczona %s temu',
	'Page cache cleared on %1$s page, %2$s ago' => array(
		'Pamięć podręczna wyczyszczona na %1$s stronie, %2$s temu',
		'Pamięć podręczna wyczyszczona na %1$s stronach, %2$s temu',
		'Pamięć podręczna wyczyszczona na %1$s stronach, %2$s temu',
	),
	'In the details'
		=> 'W szczegółach',
	'Show details'
		=> 'Pokaż szczegóły',
	'This sheet has not been fetched yet, so there is nothing to redraw.'
		=> 'Ten arkusz nie został jeszcze pobrany, więc nie ma czego przerysować.',
	'Column %1$s'
		=> 'Kolumna %1$s',
	'In Google Sheets: Share → General access → “Anyone with the link”, role “Viewer”. Then copy the address from the browser. No API key needed.'
		=> 'W Arkuszach Google: Udostępnij → Ogólny dostęp → „Każdy, kto ma link”, rola „Przeglądający”. Potem skopiuj adres z przeglądarki. Klucz API nie jest potrzebny.',
	'How old the data on your page may be. Checks run in the background, and a table past its time is also checked when somebody opens the page.'
		=> 'Jak stare mogą być dane na Twojej stronie. Sprawdzanie działa w tle, a tabela po terminie jest sprawdzana także przy otwarciu strony.',
	'A built-in sheet that never contacts Google. Every other setting works as it does for a real one.'
		=> 'Wbudowany arkusz, który nigdy nie łączy się z Google. Wszystkie pozostałe ustawienia działają jak przy prawdziwym.',
	'Usually an unclosed quotation mark, or a comma inside a value that was not quoted. Open those rows in your sheet and compare them with the rest.'
		=> 'Zwykle niedomknięty cudzysłów albo przecinek w wartości bez cudzysłowów. Otwórz te wiersze w arkuszu i porównaj z pozostałymi.',
	'Your page still shows the copy that arrived. Fix the rows in Google, then choose “Save changes and sync”.'
		=> 'Twoja strona nadal pokazuje kopię, która dotarła. Popraw wiersze w arkuszu, a potem wybierz „Zapisz zmiany i synchronizuj”.',
	'Behaviour on narrow screens'
		=> 'Zachowanie na wąskich ekranach',
	'The preview switches to the width at which the difference is visible.'
		=> 'Podgląd przełącza się na szerokość, przy której widać różnicę.',
	'Table with a slider'
		=> 'Tabela z suwakiem',
	'Columns stay columns at every width. A slider under the table moves it sideways.'
		=> 'Kolumny pozostają kolumnami przy każdej szerokości. Suwak pod tabelą przesuwa ją w bok.',
	'Cards when it stops fitting'
		=> 'Karty, gdy przestaje się mieścić',
	'A table on a computer. On a phone each row becomes a block with its heading beside every value.'
		=> 'Na komputerze tabela. Na telefonie każdy wiersz staje się blokiem z nagłówkiem obok każdej wartości.',
	'Always cards'
		=> 'Zawsze karty',
	'Blocks at every width, including wide screens. Suits profiles and listings rather than figures to compare.'
		=> 'Bloki przy każdej szerokości, także na szerokich ekranach. Pasuje do profili i ogłoszeń, nie do liczb do porównywania.',
	'Keep the first column in view'
		=> 'Pierwsza kolumna zawsze widoczna',
	'Useful when the first column names the row. Turn it off if that column holds long text.'
		=> 'Przydatne, gdy pierwsza kolumna nazywa wiersz. Wyłącz, jeśli zawiera długi tekst.',
	'Keep the headings in view'
		=> 'Nagłówki zawsze widoczne',
	'The heading row stays visible while the page scrolls. Turn it off if your theme already pins something to the top of the screen.'
		=> 'Wiersz nagłówka pozostaje widoczny podczas przewijania strony. Wyłącz, jeśli twój motyw już przypina coś do góry ekranu.',
	'Turn addresses in cells into links'
		=> 'Zamieniaj adresy w komórkach na odnośniki',
	'Applies to http, https and e-mail addresses only.'
		=> 'Dotyczy wyłącznie adresów http, https i e-mail.',
	'Split the table into pages'
		=> 'Podziel tabelę na strony',
	'Page numbers appear under the table. Searching and sorting then cover the whole sheet, not only the page on screen.'
		=> 'Pod tabelą pojawiają się numery stron. Wyszukiwanie i sortowanie obejmują wtedy cały arkusz, a nie tylko stronę na ekranie.',
	'Optional. Anything left empty follows the style chosen above.'
		=> 'Opcjonalne. Wszystko, co zostawisz puste, bierze się ze stylu wybranego wyżej.',
	'Ordinary CSS rules, confined to this table automatically. Write & for the table element itself, as in &.lstab-paged.'
		=> 'Zwykłe reguły CSS, automatycznie ograniczone do tej tabeli. Wpisz &, gdy chodzi o sam element tabeli — jak w &.lstab-paged.',
	'Renames a column for visitors. Your spreadsheet keeps its own headings.'
		=> 'Zmienia nazwę kolumny dla odwiedzających. Twój arkusz zachowuje własne nagłówki.',
	'Settings are matched by position, so a column added or removed in Google shifts them. Check each row:'
		=> 'Ustawienia są dopasowywane po pozycji, więc kolumna dodana lub usunięta w Google je przesuwa. Sprawdź każdy wiersz:',
	'This sheet has not been read yet. Choose “Refresh” on the sources list.'
		=> 'Ten arkusz nie został jeszcze odczytany. Wybierz „Odśwież” na liście źródeł.',
	'Exactly what the plugin read from your sheet. Check the headings and a few rows before saving.'
		=> 'Dokładnie to, co wtyczka odczytała z arkusza. Sprawdź nagłówki i kilka wierszy przed zapisaniem.',
	'A wide table becomes one card per row when its column is too narrow.'
		=> 'Szeroka tabela zamienia się w jedną kartę na wiersz, gdy jej kolumna jest za wąska.',
	'The exported text as it arrived, before the plugin read it. If a value is wrong here too, fix it in the sheet.'
		=> 'Wyeksportowany tekst w postaci, w jakiej dotarł, zanim wtyczka go odczytała. Jeśli wartość jest błędna także tutaj, popraw ją w arkuszu.',
	'Use the “Google Sheets Table” block, or paste this shortcode:'
		=> 'Użyj bloku „Tabela z Arkuszy Google” albo wklej ten shortcode:',

	// Wording pass.
	'Applies only when a visitor opens a table that is overdue for a check. After this time the table is drawn from the copy already stored.'
		=> 'Dotyczy tylko sytuacji, gdy odwiedzający otwiera tabelę, której dane są już przeterminowane. Po tym czasie tabela zostaje narysowana z zapisanej kopii.',
	'WordPress runs scheduled work only when a page is requested, so a quiet site falls behind. On a busy site the usual causes are a page cache, a security plugin, or scheduling disabled by the host.'
		=> 'WordPress uruchamia zadania cykliczne tylko przy żądaniu strony, więc rzadko odwiedzana witryna zostaje w tyle. Na ruchliwej witrynie typowe przyczyny to pamięć podręczna stron, wtyczka zabezpieczająca lub wyłączony harmonogram po stronie hostingu.',
	'The value given to each new table. Existing tables are not affected, and every table can be set separately.'
		=> 'Wartość nadawana każdej nowej tabeli. Istniejące tabele pozostają bez zmian, a każdą tabelę można ustawić osobno.',
	'Applies only when the plugin is deleted from the Plugins screen, not when it is deactivated. Your spreadsheets in Google are never touched.'
		=> 'Dotyczy wyłącznie usunięcia wtyczki na ekranie Wtyczki, a nie jej wyłączenia. Arkusze w Google nigdy nie są zmieniane.',
	'This table shows only some of its rows, but the add-on that filters them is not active. No rows are shown rather than all of them. Activate the add-on, or remove the filter from the block or shortcode.'
		=> 'Ta tabela pokazuje tylko część wierszy, ale dodatek odpowiedzialny za filtrowanie nie jest aktywny. Zamiast wszystkich wierszy nie pokazano żadnego. Włącz dodatek albo usuń filtr z bloku lub shortcode’u.',
	'The column you hid was headed “%1$s”; that position now holds “%2$s”, so the column is back on the page. Select the column you want and save.'
		=> 'Ukryta kolumna nosiła nagłówek „%1$s”; w tym miejscu jest teraz „%2$s”, więc kolumna wróciła na stronę. Wskaż właściwą kolumnę i zapisz.',
	'The sheet no longer reaches line %1$d, where “%2$s” was hidden. The setting is kept: if the sheet grows back to that line, whatever is there will be hidden.'
		=> 'Arkusz nie sięga już wiersza %1$d, w którym ukryto „%2$s”. Ustawienie pozostaje: jeśli arkusz znów urośnie do tego wiersza, ukryta zostanie jego ówczesna zawartość.',
	'WordPress has no clock of its own: its schedule runs only when a page is requested. Most hosting panels have a “Cron jobs” screen. Paste this line into it:'
		=> 'WordPress nie ma własnego zegara: harmonogram działa tylko przy żądaniu strony. Większość paneli hostingowych ma ekran „Cron jobs”. Wklej do niego tę linię:',
	'Anyone who can manage tables can also read every sheet they point at, including columns left out of the published table.'
		=> 'Każdy, kto może zarządzać tabelami, może też odczytać każdy wskazany arkusz, łącznie z kolumnami pominiętymi w opublikowanej tabeli.',
	'The column you renamed to “%2$s” was headed “%1$s”; that position now holds “%3$s”, so the sheet\'s own heading is shown again.'
		=> 'Kolumna przemianowana na „%2$s” nosiła nagłówek „%1$s”; w tym miejscu jest teraz „%3$s”, więc pokazywany jest nagłówek z arkusza.',
	'The style given to each new table. Every table can still be changed afterwards.'
		=> 'Styl nadawany każdej nowej tabeli. Każdą tabelę można później zmienić.',
	'Line %1$d is no longer “%2$s”, so that row is back on the page. Rows have been inserted, removed or reordered in Google. Select the row you want and save.'
		=> 'Wiersz %1$d to już nie „%2$s”, więc wrócił on na stronę. W Google wstawiono, usunięto lub przestawiono wiersze. Wskaż właściwy wiersz i zapisz.',
	'Hiding columns and rows is a Pro feature, and Pro is not active on this site. Your choices are still applied for now.'
		=> 'Ukrywanie kolumn i wierszy to funkcja Pro, która nie jest aktywna w tej witrynie. Twoje ustawienia są na razie nadal stosowane.',
	'Open your sheet, copy the address from the browser bar and paste it here. The table appears straight away, before anything is saved.'
		=> 'Otwórz arkusz, skopiuj adres z paska przeglądarki i wklej go tutaj. Tabela pojawi się od razu, zanim cokolwiek zostanie zapisane.',
	'If your hosting has no cron screen, a free uptime monitor pointed at your home page does the same job: every visit it makes runs the schedule.'
		=> 'Jeśli hosting nie ma ekranu cron, tę samą rolę spełni bezpłatny monitor dostępności wskazujący na stronę główną: każde jego wejście uruchamia harmonogram.',
	'Sharing by link is all the free version needs. Connecting a Google account, for sheets that cannot be shared, is part of Pro.'
		=> 'Wersja bezpłatna potrzebuje tylko udostępnienia linkiem. Łączenie konta Google, dla arkuszy, których nie można udostępnić, to funkcja Pro.',
	'The number of rows is never limited. To publish several different sheets at once, upgrade to Pro.'
		=> 'Liczba wierszy nigdy nie jest ograniczona. Aby publikować kilka różnych arkuszy naraz, przejdź na Pro.',

	'Could not read the tab list; the tab from your link will be used.'
		=> 'Nie udało się odczytać listy kart; zostanie użyta karta z podanego adresu.',
	'To run checks without waiting for a visitor'
		=> 'Aby uruchamiać sprawdzanie bez czekania na odwiedzającego',
	'Visitors see the last copy that arrived, so nothing on your pages is broken. The next check runs shortly.'
		=> 'Odwiedzający widzą ostatnią pobraną kopię, więc nic na stronach nie jest zepsute. Kolejne sprawdzenie nastąpi wkrótce.',
	'Add the built-in example price list to try the settings out. It never contacts Google.'
		=> 'Dodaj wbudowany przykładowy cennik, aby wypróbować ustawienia. Nigdy nie łączy się z Google.',
	'Read-only access. Nothing is written'
		=> 'Dostęp tylko do odczytu. Nic nie jest zapisywane',
	'Pro adds unlimited sources, one-minute syncing, colour rules, filtered views, extra styles and private sheets.'
		=> 'Pro dodaje nieograniczoną liczbę źródeł, synchronizację co minutę, reguły kolorów, widoki filtrowane, dodatkowe style i arkusze prywatne.',
	'Hiding columns and rows is part of Pro; you choose them by clicking your own sheet.'
		=> 'Ukrywanie kolumn i wierszy to funkcja Pro; wybierasz je, klikając własny arkusz.',

	// Language of the plugin itself.
	'Language'
		=> 'Język',
	'Which language this plugin uses, here and on your pages'
		=> 'W jakim języku wtyczka mówi tutaj i na Twoich stronach',
	'Language of this plugin'
		=> 'Język tej wtyczki',
	'Covers these screens, the block in the editor, and the words visitors see in the table. The rest of the dashboard keeps the language set in WordPress.'
		=> 'Obejmuje te ekrany, blok w edytorze i napisy, które odwiedzający widzą w tabeli. Reszta kokpitu zachowuje język ustawiony w WordPressie.',
	'Same as the site'
		=> 'Taki jak witryna',

	// Lengths of time, so a duration is not left in the site's language in the
	// middle of a sentence this plugin has translated.
	// Biernik, bo każde zdanie, w które te długości trafiają, rządzi tym
	// przypadkiem: „co %s”, „za %s”, „%s temu”. Mianownik dawał „za 1 godzina”.
	'%s second'                                  => array( '%s sekundę', '%s sekundy', '%s sekund' ),
	'%s minute'                                  => array( '%s minutę', '%s minuty', '%s minut' ),
	'%s hour'                                    => array( '%s godzinę', '%s godziny', '%s godzin' ),
	'%s day'                                     => array( '%s dzień', '%s dni', '%s dni' ),
	'%s week'                                    => array( '%s tydzień', '%s tygodnie', '%s tygodni' ),
	'%s month'                                   => array( '%s miesiąc', '%s miesiące', '%s miesięcy' ),
	'%s year'                                    => array( '%s rok', '%s lata', '%s lat' ),

	// block.json — the name and description the block inserter shows.
	"block title\4Google Sheets Table"        => 'Tabela z Arkuszy Google',
	"block description\4Show a saved Google Sheet as a responsive, auto-refreshing table."
		=> 'Pokaż zapisany arkusz Google jako responsywną, samoodświeżającą się tabelę.',
	"block keyword\4google"                   => 'google',
	"block keyword\4sheets"                   => 'arkusze',
	"block keyword\4table"                    => 'tabela',
	"block keyword\4csv"                      => 'csv',
	"block keyword\4spreadsheet"              => 'arkusz kalkulacyjny',

	// Colour swatches that name nothing a reader can point at.
	'Between rows and around cells'              => 'Między wierszami i wokół komórek',
	'Only used by the Striped style'             => 'Używane tylko w stylu Paski',
	'When the mouse is over a row'               => 'Gdy myszka jest nad wierszem',
	'Links, sort arrows and page numbers'        => 'Odnośniki, strzałki sortowania i numery stron',

	// The shortcode's optional attributes, with what each one does.
	'You can add any of these inside the brackets:'
		=> 'W nawiasach możesz dopisać dowolne z poniższych:',
	'Write'                                      => 'Wpisz',
	'What it does'                               => 'Co robi',
	'Hide the search box'                        => 'Ukrywa pole wyszukiwania',
	'Turn off sorting by column'                 => 'Wyłącza sortowanie po kolumnach',
	'Hide the “updated … ago” line'              => 'Ukrywa wiersz „zaktualizowano … temu”',
	'Use a different table style'                => 'Ustawia inny styl tabeli',
	'Put a caption above the table'              => 'Dodaje podpis nad tabelą',
	'caption="My table"'                        => 'caption="Moja tabela"',
	'Choose from:'
		=> 'Do wyboru:',
	'Use a table style other than this table\'s own'
		=> 'Ustawia inny styl niż własny styl tej tabeli',

	// The row of marks on a source card: one per check, oldest first.
	'Last checks'                                => 'Ostatnie sprawdzenia',
	'%s failed'                                  => array( '%s nieudane', '%s nieudane', '%s nieudanych' ),
	'The last %1$s check, oldest first. %2$s of them failed.'
		=> array(
			'Ostatnie %1$s sprawdzenie, od najstarszego. Nieudanych: %2$s.',
			'Ostatnie %1$s sprawdzenia, od najstarszego. Nieudanych: %2$s.',
			'Ostatnie %1$s sprawdzeń, od najstarszego. Nieudanych: %2$s.',
		),
	'Tab: %s'                                    => 'Zakładka: %s',
	'%s more column'                             => array( '%s kolumna więcej', '%s kolumny więcej', '%s kolumn więcej' ),

	// Pages offered for a sheet too long to read in one go.
	'Saved. This sheet has %1$s rows, so it is being shown %2$s at a time — the whole thing on one page would be slow to load and hard to read. Your visitors get a search box and page buttons, and both look through every row, not just the page on screen. To show it all at once instead, turn pages off under “%3$s”.'
		=> 'Zapisano. Ten arkusz ma %1$s wierszy, więc pokazujemy go po %2$s naraz — całość na jednej stronie ładowałaby się wolno i źle się czytało. Odwiedzający dostają pole wyszukiwania i przyciski stron, a jedno i drugie przeszukuje wszystkie wiersze, nie tylko tę stronę, którą widać. Aby pokazać całość naraz, wyłącz strony w zakładce „%3$s”.',
	'This sheet has grown to %1$s rows. Shall it be shown %2$s at a time? Visitors get page buttons, the page loads quicker, and searching and sorting still cover every row.'
		=> 'Ten arkusz urósł do %1$s wierszy. Pokazywać go po %2$s naraz? Odwiedzający dostaną przyciski stron, strona wczyta się szybciej, a wyszukiwanie i sortowanie nadal obejmą wszystkie wiersze.',
	'Yes, %s at a time'                          => 'Tak, po %s naraz',
	'No, keep one long table'                    => 'Nie, zostaw jedną długą tabelę',
	'Kept as one long table. You will not be asked about this sheet again.'
		=> 'Zostaje jedna długa tabela. Nie zapytamy więcej o ten arkusz.',
	'“%1$s” is now shown %2$s rows at a time. Change the number, or go back to one long table, under “%3$s” on its own screen.'
		=> '„%1$s” jest teraz pokazywany po %2$s wierszy naraz. Liczbę zmienisz — albo wrócisz do jednej długiej tabeli — w zakładce „%3$s” na ekranie tego arkusza.',
	'That sheet is gone.'                        => 'Tego arkusza już nie ma.',
);
