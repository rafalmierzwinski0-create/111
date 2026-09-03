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
	'Pro syncs as often as every minute.'        => 'Pro synchronizuje nawet co minutę.',
	'Whoever opens the page sees data no older than this. Checking normally happens in the background; if it has not run — a quiet site, or a host that blocks WordPress schedules — the check happens as the page is drawn instead, waits at most %d seconds, and falls back to the copy you already have.'
		=> 'Kto otworzy stronę, zobaczy dane nie starsze niż tyle. Sprawdzanie zwykle odbywa się w tle; jeśli się nie wykonało — witryna bez ruchu albo hosting blokujący harmonogramy WordPressa — sprawdzenie następuje w trakcie rysowania strony, czeka najwyżej %d sekund i wraca do kopii, którą już masz.',
	'“%1$s” has not been refreshed for %2$s.'
		=> 'Arkusz „%1$s” nie był odświeżany od %2$s.',
	'Your pages are still showing the last copy that arrived, so nothing is broken for visitors. But WordPress runs scheduled work only when someone visits the site, so a quiet site falls behind — and on a site that should be busy this usually means something is stopping it: a security plugin, a page cache answering every request without running WordPress, or a host that switches scheduling off without replacing it.'
		=> 'Twoje strony nadal pokazują ostatnią kopię, która dotarła, więc dla odwiedzających nic nie jest zepsute. Ale WordPress wykonuje zaplanowane zadania tylko wtedy, gdy ktoś odwiedzi witrynę, więc strona bez ruchu zostaje w tyle — a na stronie, która powinna mieć ruch, zwykle oznacza to, że coś to blokuje: wtyczka bezpieczeństwa, cache stron odpowiadający na każde żądanie bez uruchamiania WordPressa albo hosting, który wyłącza harmonogram i nie daje nic w zamian.',
	'Meanwhile you can update any sheet by hand with “Refresh now”.'
		=> 'W międzyczasie każdy arkusz zaktualizujesz ręcznie przyciskiem „Odśwież teraz”.',
	'To check sheets even when nobody visits'
		=> 'Aby sprawdzać arkusze nawet wtedy, gdy nikt nie wchodzi na stronę',
	'WordPress has no clock of its own — its schedule only runs when a page is requested, so a quiet site checks nothing. Give your host a real clock instead. Most hosting panels have a “Cron jobs” screen; paste this line into it:'
		=> 'WordPress nie ma własnego zegara — jego harmonogram rusza dopiero wtedy, gdy ktoś otworzy stronę, więc witryna bez ruchu nie sprawdza niczego. Zegar może dać mu hosting. Większość paneli hostingowych ma ekran „Cron jobs” albo „Zadania cron”; wklej tam tę linię:',
	'No cron screen on your hosting? A free uptime monitor pointed at your home page does the same job, because every visit it makes runs the schedule.'
		=> 'Twój hosting nie ma ekranu z cronem? Darmowy monitor dostępności skierowany na Twoją stronę główną zrobi to samo — każde jego wejście uruchamia harmonogram.',
	'In the table'                               => 'W tabeli',
	'Shown'                                      => 'Widoczna',
	'Hidden'                                     => 'Ukryta',
	'Leaving a column or a row out of the table is part of Pro, where you choose it by clicking your own sheet.'
		=> 'Pomijanie kolumny lub wiersza w tabeli jest częścią Pro — wybierasz je, klikając we własny arkusz.',
	'Columns and rows you hid will start showing again in %s.'
		=> 'Ukryte przez Ciebie kolumny i wiersze zaczną znów się pokazywać za %s.',
	'Choosing what to leave out of a table is part of Pro, and Pro is not active on this site. Your choices are still being honoured for now, so nothing on your pages has changed yet.'
		=> 'Wybieranie, co pominąć w tabeli, jest częścią Pro, a Pro nie jest aktywne na tej witrynie. Twoje wybory są na razie respektowane, więc na stronach nic się jeszcze nie zmieniło.',
	// Settings screen.
	'Sheet sources'                              => 'Źródła arkuszy',
	'Settings'                                   => 'Ustawienia',
	'Live Sheets Table settings'                 => 'Ustawienia Live Sheets Table',
	'Settings saved.'                            => 'Ustawienia zapisane.',
	'Save settings'                              => 'Zapisz ustawienia',
	'Who can manage tables'                      => 'Kto może zarządzać tabelami',
	'Whoever can manage tables can also read every sheet they point at, including any column left out of the published table. Editors are the default because they are the people publishing the pages these tables go on.'
		=> 'Kto może zarządzać tabelami, może też odczytać każdy wskazany arkusz — łącznie z kolumnami pominiętymi w opublikowanej tabeli. Domyślnie są to redaktorzy, bo to oni publikują strony, na których te tabele stoją.',
	'Editors and above'                          => 'Redaktorzy i wyżej',
	'Administrators only'                        => 'Tylko administratorzy',
	'How often new tables check Google'          => 'Jak często nowe tabele sprawdzają Google',
	'Every table has its own “Check Google for changes” setting. This is only the value a table is given the moment you add it, so that you are not choosing the same thing over and over. It changes nothing about the tables you already have, and any table can be set differently afterwards.'
		=> 'Każda tabela ma własne ustawienie „Sprawdzaj zmiany w Google”. To jest tylko wartość, którą tabela dostaje w chwili dodania — żebyś nie wybierał w kółko tego samego. Nie zmienia niczego w tabelach, które już masz, i każdą tabelę można potem ustawić inaczej.',
	'As often as allowed — every %s at present'  => 'Tak często, jak wolno — obecnie co %s',
	'Every %s'                                   => 'Co %s',
	'When this plugin is deleted'                => 'Gdy ta wtyczka zostanie usunięta',
	'Also delete every sheet source and setting' => 'Usuń też wszystkie źródła arkuszy i ustawienia',
	'Off by default, because deleting a plugin to reinstall it is a normal thing to do and losing every table for it would not be. This only applies when the plugin is deleted from the Plugins screen, not when it is deactivated. Your spreadsheets in Google are never touched either way.'
		=> 'Domyślnie wyłączone, bo usunięcie wtyczki po to, żeby zainstalować ją ponownie, jest czymś normalnym, a utrata przy tym wszystkich tabel nie byłaby. Dotyczy tylko usunięcia z ekranu Wtyczki, nie dezaktywacji. Twoje arkusze w Google i tak nigdy nie są ruszane.',
	'You do not have permission to change these settings.'
		=> 'Nie masz uprawnień do zmiany tych ustawień.',
	'See what is hidden, and what will come back'
		=> 'Zobacz, co jest ukryte i co wróci',
	// When something hidden comes back.
	'Something you had taken out of a table is on the page again.'
		=> 'Coś, co usunąłeś z tabeli, jest znów na stronie.',
	'Something you had taken out of a table is no longer in the sheet.'
		=> 'Czegoś, co usunąłeś z tabeli, nie ma już w arkuszu.',
	'The column you took out was headed “%1$s”. That position now holds “%2$s” instead, so nothing is being taken out and the column is on the page. Somebody has moved, renamed or removed a column in Google. Click the one you want and save.'
		=> 'Kolumna, którą usunąłeś, miała nagłówek „%1$s”. W tym miejscu jest teraz „%2$s”, więc nic nie jest usuwane i ta kolumna jest na stronie. Ktoś przestawił, przemianował albo usunął kolumnę w Google. Kliknij tę, o którą Ci chodzi, i zapisz.',
	'The column you renamed to “%2$s” was headed “%1$s”. That position now holds “%3$s” instead, so it is showing the sheet\'s own heading again. Somebody has moved, renamed or removed a column in Google.'
		=> 'Kolumna, którą przemianowałeś na „%2$s”, miała nagłówek „%1$s”. W tym miejscu jest teraz „%3$s”, więc pokazuje znów własny nagłówek z arkusza. Ktoś przestawił, przemianował albo usunął kolumnę w Google.',
	'nothing'                                    => 'nic',
	'the empty row'                              => 'pusty wiersz',
	'Line %1$d is not “%2$s” any more, so nothing is being taken out there and that row is on the page. Somebody has inserted, removed or reordered rows in Google. Click the row you want and save.'
		=> 'W linii %1$d nie ma już „%2$s”, więc nic nie jest tam usuwane i ten wiersz jest na stronie. Ktoś dodał, usunął albo przestawił wiersze w Google. Kliknij wiersz, o który Ci chodzi, i zapisz.',
	'The sheet no longer reaches line %1$d, where “%2$s” was taken out. Nothing is on the page that should not be. The setting is left alone: if the sheet grows back to that line, whatever is there will be taken out, so check it then.'
		=> 'Arkusz nie sięga już linii %1$d, w której usunięto „%2$s”. Na stronie nie ma nic, czego nie powinno być. Ustawienie zostaje nietknięte: jeśli arkusz znów urośnie do tej linii, to, co się tam znajdzie, zostanie usunięte — sprawdź to wtedy.',
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
	'Rename a column for your visitors. Nothing here is written back to Google — your spreadsheet keeps its own headings, including working names nobody should see.'
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
	'Meanwhile you can update any sheet by hand with “Refresh now”. %s'
		=> 'W międzyczasie możesz zaktualizować dowolny arkusz ręcznie przyciskiem „Odśwież teraz”. %s',
	'The WordPress guide to system cron'
		=> 'Poradnik WordPressa o systemowym cronie',

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
	'Pro adds unlimited sources, one-minute syncing, conditional cell formatting, filtered views, premium presets and private-sheet support.'
		=> 'Pro dodaje nielimitowaną liczbę źródeł, synchronizację co minutę, formatowanie warunkowe komórek, widoki filtrowane, presety premium i obsługę arkuszy prywatnych.',
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
	'What Google actually sent'                  => 'Co dokładnie przysłał Google',
	'The exported text, exactly as it arrived, before the plugin read it. If a value looks wrong in the table above, find it here: if it is already wrong here, the sheet is where to fix it. Copy this if you need to send it to support.'
		=> 'Wyeksportowany tekst dokładnie w takiej postaci, w jakiej dotarł — zanim wtyczka go odczytała. Jeśli jakaś wartość wygląda źle w tabeli powyżej, znajdź ją tutaj: jeśli już tu jest zła, to arkusz jest miejscem do poprawki. Skopiuj to, jeśli musisz wysłać do wsparcia.',
	'%1$s characters received.'                  => 'Odebrano %1$s znaków.',
	'Look at row %1$s: it came back with a different number of cells than the rest.'
		=> 'Zobacz wiersz %1$s: wrócił z inną liczbą komórek niż reszta.',
	'Rows per page'                              => 'Wierszy na stronę',
	'Leave at 0 to put the whole sheet on the page. Any other number splits it, and searching and sorting then happen on the server across the whole sheet rather than on the page in front of you — so a search still finds a row on page nine.'
		=> 'Zostaw 0, aby cały arkusz był na jednej stronie. Każda inna liczba dzieli go na strony, a wyszukiwanie i sortowanie przenoszą się wtedy na serwer i obejmują cały arkusz, a nie tylko stronę, którą masz przed sobą — więc wyszukiwarka nadal znajdzie wiersz ze strony dziewiątej.',
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
	'Open your sheet, copy the address from the browser bar, paste it here. You will see the table straight away — before anything is saved and before we ask you anything else.'
		=> 'Otwórz arkusz, skopiuj adres z paska przeglądarki i wklej go tutaj. Tabelę zobaczysz od razu — zanim cokolwiek zapiszesz i zanim zapytamy Cię o cokolwiek innego.',
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
	'Sharing by link is all the free version needs. Connecting a Google account, for sheets that cannot be shared at all, is part of Pro.'
		=> 'Wersji darmowej wystarczy udostępnienie linkiem. Połączenie konta Google — dla arkuszy, których nie da się udostępnić w ogóle — jest częścią Pro.',
	'Show me the table'
		=> 'Pokaż tabelę',
	'Suspension overhaul'
		=> 'Serwis amortyzatora',
	'The last few checks, oldest first'
		=> 'Kilka ostatnich sprawdzeń, od najstarszego',
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
	'Visitors are seeing the last good copy, so nothing on your pages is broken. We will try again shortly.'
		=> 'Odwiedzający widzą ostatnią dobrą kopię, więc nic na Twoich stronach nie jest zepsute. Spróbujemy ponownie za chwilę.',
	'Waiting for a first look at the sheet. Choose “Refresh” on the sources list, and your real columns will appear here.'
		=> 'Czekamy na pierwsze spojrzenie na arkusz. Wybierz „Odśwież” na liście źródeł, a pojawią się tu Twoje prawdziwe kolumny.',
	'Want somewhere safe to try the settings? Add the built-in example price list — it never touches Google.'
		=> 'Chcesz mieć gdzie bezpiecznie sprawdzić ustawienia? Dodaj wbudowany przykładowy cennik — nigdy nie sięga do Google.',
	'We only ever read. Never write'
		=> 'Tylko czytamy. Nigdy nie zapisujemy',
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
	'It lives inside the plugin, so there is no link to point at and nothing to fetch. Everything else works exactly as it does for a real sheet — change the look, rename a column, hide a row, put it on a page. Delete it whenever you like.'
		=> 'Mieszka wewnątrz wtyczki, więc nie ma tu żadnego linku ani niczego do pobrania. Cała reszta działa dokładnie tak jak przy prawdziwym arkuszu — zmień wygląd, przemianuj kolumnę, ukryj wiersz, wstaw na stronę. Usuń go, kiedy zechcesz.',
	'Name it and set the schedule'
		=> 'Nazwij i ustaw harmonogram',
	'Pick a look'
		=> 'Wybierz wygląd',
	'Point at your sheet'
		=> 'Wskaż swój arkusz',
	'This is the built-in example'
		=> 'To jest wbudowany przykład',

	// Site-wide settings added alongside the redesign.
	'How long to wait for Google'
		=> 'Jak długo czekać na Google',
	'How new tables look'
		=> 'Jak wyglądają nowe tabele',
	'Only ever applies when the schedule has not run and a visitor arrives to a table that is due a check. The page waits this long, then gives up and shows the copy it already has — so the visitor always gets a table either way. Raise it only if you have a very large sheet on slow hosting; every extra second is a second somebody waits.'
		=> 'Dotyczy wyłącznie sytuacji, gdy harmonogram nie zadziałał, a odwiedzający trafia na tabelę, której należy się sprawdzenie. Strona czeka tyle, po czym odpuszcza i pokazuje kopię, którą już ma — więc odwiedzający i tak zawsze dostaje tabelę. Zwiększaj tylko przy bardzo dużym arkuszu na wolnym hostingu; każda dodatkowa sekunda to sekunda czyjegoś czekania.',
	'The style a table is given the moment you add it. Every table can still be changed afterwards; this is only so that somebody who has settled on one look is not choosing it again for every sheet.'
		=> 'Styl, który tabela dostaje w chwili dodania. Każdą tabelę da się potem zmienić; chodzi tylko o to, żeby ktoś, kto wybrał już swój wygląd, nie wskazywał go od nowa przy każdym arkuszu.',
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
	'What a table looks like before you have touched it'
		=> 'Jak wygląda tabela, zanim jej dotkniesz',
	'Who is trusted with the tables on this site'
		=> 'Komu powierzasz tabele na tej witrynie',
	'Your own CSS'
		=> 'Własny CSS',
	'For the last thing the settings above do not cover. Write ordinary rules — the plugin puts this table’s own selector in front of each one, so nothing written here can reach the rest of the page. Where you mean the table itself rather than something inside it, write & — as in &.lstab-paged.'
		=> 'Na to jedno, czego nie obejmują ustawienia powyżej. Pisz zwykłe reguły — wtyczka sama dopisuje przed każdą z nich selektor tej tabeli, więc nic stąd nie sięgnie reszty strony. Gdy chodzi Ci o samą tabelę, a nie o coś w środku, napisz & — na przykład &.lstab-paged.',
	'Every rule is saved as %s followed by what you wrote.'
		=> 'Każda reguła zapisuje się jako %s, a po nim to, co napisałeś.',
	'You are not allowed to write CSS on this site.'
		=> 'Nie masz uprawnień do pisania CSS na tej witrynie.',
);
