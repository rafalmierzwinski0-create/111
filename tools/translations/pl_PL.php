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

	// Plurals: Polish has three forms.
	'The free version keeps %d sheet source'     => array(
		'Wersja darmowa przechowuje %d źródło arkusza',
		'Wersja darmowa przechowuje %d źródła arkuszy',
		'Wersja darmowa przechowuje %d źródeł arkuszy',
	),
	'The free version stores %d sheet source. Remove the existing one, or upgrade to add more.' => array(
		'Wersja darmowa przechowuje %d źródło arkusza. Usuń istniejące albo przejdź na Pro, aby dodać więcej.',
		'Wersja darmowa przechowuje %d źródła arkuszy. Usuń jedno albo przejdź na Pro, aby dodać więcej.',
		'Wersja darmowa przechowuje %d źródeł arkuszy. Usuń jedno albo przejdź na Pro, aby dodać więcej.',
	),
);
