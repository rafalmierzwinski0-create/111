<?php
/**
 * Polish translation table for the add-on, consumed by tools/make-pot.php.
 *
 * Keys are the msgid (prefixed with "context\4" where a context applies).
 * A plural entry's value is an array of the three Polish plural forms.
 *
 * The vocabulary follows the free plugin's, because the two sit on the same
 * screens: źródło arkusza, wiersz, kolumna, nagłówek, podgląd, odwiedzający.
 *
 * @package LiveSheetsTablePro\Tools
 */

return array(
	// The add-on itself.
	'Pro'                                        => 'Pro',
	'Pro settings'                               => 'Ustawienia Pro',
	'Private sheets, filters and everything Pro adds'
		=> 'Arkusze prywatne, filtry i wszystko, co dodaje Pro',
	'Pro is active on this site.'                => 'Pro jest aktywne w tej witrynie.',
	'Pro is not running here.'                   => 'Pro nie działa w tej witrynie.',
	'Pro is not running here. Columns and rows you hid will start showing again in %s.'
		=> 'Pro nie działa w tej witrynie. Ukryte kolumny i wiersze zaczną znów się pokazywać za %s.',
	'Live Sheets Table Pro needs the free Live Sheets Table plugin to be installed and active. Pro adds to it rather than replacing it.'
		=> 'Live Sheets Table Pro wymaga zainstalowanej i aktywnej bezpłatnej wtyczki Live Sheets Table. Pro ją rozszerza, a nie zastępuje.',

	// Subscription.
	'Your subscription'                          => 'Twoja subskrypcja',
	'Billing and cancellation'                   => 'Płatności i rezygnacja',
	'What Pro is doing on this site, and how to stop paying for it'
		=> 'Co Pro robi w tej witrynie i jak przestać za nie płacić',
	'Billing is handled in your account, not on this site. Cancelling takes effect at the end of the period you have paid for.'
		=> 'Płatnościami zarządzasz na swoim koncie, nie w witrynie. Rezygnacja działa od końca opłaconego okresu.',
	'Manage or cancel subscription'              => 'Zarządzaj subskrypcją lub zrezygnuj',

	// Google account and private sheets.
	'Connect a Google account'                   => 'Połącz konto Google',
	'Your own credentials, so your spreadsheets are never read through anyone else\'s account'
		=> 'Własne dane logowania, więc Twoje arkusze nigdy nie są odczytywane przez cudze konto',
	'To read sheets that are not shared publicly, this site signs in to Google as you. That requires an OAuth client from your own Google Cloud project.'
		=> 'Aby odczytywać arkusze nieudostępnione publicznie, witryna loguje się do Google jako Ty. Wymaga to klienta OAuth z Twojego własnego projektu Google Cloud.',
	'Open Google Cloud Console and create a project.'
		=> 'Otwórz Google Cloud Console i utwórz projekt.',
	'Enable the Google Sheets API for it.'       => 'Włącz w nim Google Sheets API.',
	'Create an OAuth client of type “Web application”.'
		=> 'Utwórz klienta OAuth typu „Aplikacja internetowa”.',
	'Add this exact address as an authorised redirect URI:'
		=> 'Dodaj dokładnie ten adres jako autoryzowany URI przekierowania:',
	'Your Google client'                         => 'Twój klient Google',
	'Client ID'                                  => 'Identyfikator klienta',
	'Client secret'                              => 'Klucz tajny klienta',
	'Save client'                                => 'Zapisz klienta',
	'Google client saved.'                       => 'Klient Google został zapisany.',
	'Sign in to Google'                          => 'Zaloguj się do Google',
	'Only read access to spreadsheets is requested; this connection cannot change or delete anything in your Google account. Disconnecting takes effect immediately.'
		=> 'Wymagany jest wyłącznie dostęp do odczytu arkuszy; to połączenie nie może niczego zmienić ani usunąć na Twoim koncie Google. Rozłączenie działa natychmiast.',
	'Read access only — this can never change anything in Google'
		=> 'Tylko odczyt — to nigdy niczego nie zmieni w Google',
	'Save a client above first.'                 => 'Najpierw zapisz klienta powyżej.',
	'Add your Google client ID and secret first.'
		=> 'Najpierw podaj identyfikator klienta Google i klucz tajny.',
	'No client saved yet'
		=> 'Nie zapisano jeszcze klienta',
	'Not connected yet'                          => 'Jeszcze niepołączone',
	'Connected. Private sheets can be read.'     => 'Połączono. Arkusze prywatne można odczytywać.',
	'The connected account'                      => 'Połączone konto',
	'Disconnect'                                 => 'Rozłącz',
	'Google account connected. Private sheets can now be used as sources.'
		=> 'Konto Google połączone. Arkuszy prywatnych można teraz używać jako źródeł.',
	'Google account disconnected. Private sheets will stop updating.'
		=> 'Konto Google rozłączone. Arkusze prywatne przestaną się aktualizować.',
	'No Google account is connected, so private sheets cannot be read.'
		=> 'Nie połączono żadnego konta Google, więc arkuszy prywatnych nie da się odczytać.',
	'Google did not return an authorisation code.'
		=> 'Google nie zwróciło kodu autoryzacji.',
	'Google refused the connection: %s'          => 'Google odrzuciło połączenie: %s',
	'Google rejected the sign-in: %s'            => 'Google odrzuciło logowanie: %s',
	'Could not reach Google to complete sign-in: %s'
		=> 'Nie udało się połączyć z Google, aby dokończyć logowanie: %s',
	'That sign-in did not match the request that started it, so it was discarded. Please try connecting again.'
		=> 'To logowanie nie pasowało do żądania, które je rozpoczęło, więc zostało odrzucone. Spróbuj połączyć się jeszcze raz.',
	'You are not allowed to connect an account.'
		=> 'Nie masz uprawnień, aby połączyć konto.',
	'HTTP %d'                                    => 'HTTP %d',

	// Which sheets are private.
	'Which sheets are private'                   => 'Które arkusze są prywatne',
	'Tick one and you can remove link sharing in Google entirely'
		=> 'Zaznacz jeden, a udostępnianie linkiem w Google możesz całkiem wyłączyć',
	'Tick a sheet to read it through the connected account instead of its public link. You can then turn off link sharing in Google.'
		=> 'Zaznacz arkusz, aby odczytywać go przez połączone konto zamiast publicznego linku. Możesz wtedy wyłączyć udostępnianie linkiem w Google.',
	'Sheet source'                               => 'Źródło arkusza',
	'Read through the connected account'         => 'Odczyt przez połączone konto',
	'Private'                                    => 'Prywatny',
	'No sheet sources yet.'                      => 'Nie ma jeszcze żadnych źródeł arkuszy.',
	'Sheet settings saved.'                      => 'Ustawienia arkuszy zapisane.',
	'Save'                                       => 'Zapisz',

	// Filtered views in the shortcode.
	'Filtered views'                             => 'Widoki filtrowane',
	'One sheet, as many pages as you like'       => 'Jeden arkusz, dowolnie wiele stron',
	'One saved sheet can feed as many pages as you like. Add a filter to the shortcode and each page shows only the rows it needs.'
		=> 'Jeden zapisany arkusz może zasilać dowolnie wiele stron. Dodaj filtr do shortcode’u, a każda strona pokaże tylko potrzebne wiersze.',
	'Conditions are separated by commas and all must match. Column names match either the heading in your sheet or the name you gave it.'
		=> 'Warunki oddziela się przecinkami i wszystkie muszą pasować. Nazwa kolumny to nagłówek z arkusza albo nazwa, którą jej nadałeś.',
	'Symbols such as = and > also work, but WordPress strips a “less than” sign from shortcode attributes, so the words above are the safer form.'
		=> 'Symbole takie jak = i > również działają, ale WordPress usuwa znak „mniejszości” z atrybutów shortcode’u, dlatego powyższe słowa są formą bezpieczniejszą.',
	'When'
		=> 'Gdy',
	'Meaning'                                    => 'Znaczenie',
	'is'                                         => 'jest',
	'is not'                                     => 'nie jest',
	'contains'                                   => 'zawiera',
	'is exactly'                                 => 'jest dokładnie',
	'is anything but'                            => 'jest czymkolwiek poza',
	'is greater than'                            => 'jest większe niż',
	'is greater than or equal to'                => 'jest większe lub równe',
	'is less than'                               => 'jest mniejsze niż',
	'is less than or equal to'                   => 'jest mniejsze lub równe',
	'is at least'                                => 'jest co najmniej',
	'is at most'                                 => 'jest najwyżej',

	// Colour rules.
	'Colour rules'                               => 'Reguły kolorów',
	'Colour a cell, or its whole row, according to the cell\'s value. Colours are worked out on the server, so they are already in the page a visitor receives.'
		=> 'Pokoloruj komórkę lub cały wiersz na podstawie jej wartości. Kolory są wyliczane na serwerze, więc trafiają do strony przed jej wysłaniem.',
	'Rules are read from the top down. If two of them colour the same place, the lower one wins, so put the general rule first.'
		=> 'Reguły są czytane od góry. Jeśli dwie kolorują to samo miejsce, wygrywa niższa, więc regułę ogólną umieść wyżej.',
	'A colour on a cell sits on top of a colour on its row, so “grey row, one red cell” is two rules.'
		=> 'Kolor komórki nakłada się na kolor wiersza, więc „szary wiersz, jedna czerwona komórka” to dwie reguły.',
	'“is” and “is not” compare text, ignoring case and spacing. The number comparisons read a price as a number, so 1 215,50 and 1215.5 are the same figure.'
		=> '„jest” i „nie jest” porównują tekst, pomijając wielkość liter i odstępy. Porównania liczbowe czytają cenę jako liczbę, więc 1 215,50 i 1215.5 to ta sama wartość.',
	'Nothing is coloured by %s until the rule points at a heading that exists. The rule is kept until you change it.'
		=> 'Nic nie jest kolorowane przez %s, dopóki reguła nie wskaże istniejącego nagłówka. Reguła pozostaje zapisana.',
	'One rule names a column your sheet no longer has.'
		=> array(
			'Jedna reguła wskazuje kolumnę, której nie ma już w arkuszu.',
			'%s reguły wskazują kolumny, których nie ma już w arkuszu.',
			'%s reguł wskazuje kolumny, których nie ma już w arkuszu.',
		),
	'Load the preview first. Once the columns are known you can set rules on them.'
		=> 'Najpierw wczytaj podgląd. Gdy kolumny będą znane, będzie można ustawić na nich reguły.',
	'%s — not in the sheet any more'             => '%s — już nie ma tego w arkuszu',
	'— pick a column —'                          => '— wybierz kolumnę —',
	'— remove this rule —'                       => '— usuń tę regułę —',
	'the value to match'
		=> 'szukana wartość',
	'paint'
		=> 'pokoloruj',
	'that cell'                                  => 'tę komórkę',
	'the whole row'                              => 'cały wiersz',
	'Abc'                                        => 'Abc',
	'Bold text'                                  => 'Pogrubienie',
	'Struck through'                             => 'Przekreślenie',
	'B'                                          => 'B',
	'S'                                          => 'S',
	'A colour of your own'                       => 'Własny kolor',
	'Pick a colour of your own'                  => 'Wybierz własny kolor',
	'Red'                                        => 'Czerwony',
	'Orange'                                     => 'Pomarańczowy',
	'Yellow'                                     => 'Żółty',
	'Green'                                      => 'Zielony',
	'Teal'                                       => 'Morski',
	'Blue'                                       => 'Niebieski',
	'Purple'                                     => 'Fioletowy',
	'Pink'                                       => 'Różowy',
	'Grey'                                       => 'Szary',

	// Facets: letting a visitor narrow the table.
	'Let visitors narrow the table'              => 'Pozwól odwiedzającym zawężać tabelę',
	'A filter shows visitors what a column contains, instead of asking them to guess the words. The counts below come from the copy stored now.'
		=> 'Filtr pokazuje odwiedzającym, co zawiera kolumna, zamiast kazać im zgadywać słowa. Liczby poniżej pochodzą z aktualnie zapisanej kopii.',
	'Filters appear above the table as menus. Each choice has an address of its own that can be shared, and works together with search and pages across the whole sheet.'
		=> 'Filtry pojawiają się nad tabelą jako rozwijane menu. Każdy wybór ma własny adres, który można udostępnić, i działa razem z wyszukiwaniem oraz stronami w obrębie całego arkusza.',
	'Load the preview first. Once the columns are known you can offer filters on them.'
		=> 'Najpierw wczytaj podgląd. Gdy kolumny będą znane, będzie można zaproponować na nich filtry.',
	'The sheet has not been read yet, so there are no columns to choose from.'
		=> 'Arkusz nie został jeszcze odczytany, więc nie ma jeszcze kolumn do wyboru.',
	'Suits a filter'                             => 'Nadaje się na filtr',
	'This column is empty.'                      => 'Ta kolumna jest pusta.',
	'Every row holds the same value, so a filter here would narrow nothing.'
		=> 'Każdy wiersz ma tę samą wartość, więc filtr niczego by tu nie zawęził.',
	'Every row is different, so each choice would leave one row. A search box does this better.'
		=> 'Każdy wiersz jest inny, więc każdy wybór zostawiłby jeden wiersz. Lepiej sprawdzi się wyszukiwarka.',
	'%1$s different values across %2$s rows, close to one per row. A search box suits this column better.'
		=> '%1$s różnych wartości w %2$s wierszach, blisko jednej na wiersz. Do tej kolumny lepiej nadaje się wyszukiwarka.',
	'%1$s different values, about %2$s rows each. A useful filter, but a long list; visitors can type to narrow it. Commonest: %3$s'
		=> '%1$s różnych wartości, około %2$s wierszy na każdą. Filtr przydatny, ale lista długa; odwiedzający mogą ją zawężać, pisząc. Najczęstsze: %3$s',
	'%1$s different values: %2$s'                => '%1$s różnych wartości: %2$s',
	'The %1$s commonest of %2$s values.'         => '%1$s najczęstszych spośród %2$s wartości.',
	'Show only:'                                 => 'Pokaż tylko:',
	'Find a value'                               => 'Znajdź wartość',
	'Type to narrow this list…'                  => 'Pisz, aby zawęzić listę…',
	'Nothing here matches that.'                 => 'Nic tu nie pasuje.',
	'Clear filters — show all %s'                => 'Wyczyść filtry — pokaż wszystkie: %s',
	'any'                                        => 'dowolne',

	// Hiding columns and rows.
	'Hide columns and rows'                      => 'Ukryj kolumny i wiersze',
	'Click a heading to hide that column, or a line number to hide that row. Click again to bring it back. Nothing is written to Google.'
		=> 'Kliknij nagłówek, aby ukryć kolumnę, albo numer wiersza, aby ukryć wiersz. Kliknij ponownie, aby przywrócić. Nic nie jest zapisywane w Google.',
	'Moving a column or row in Google will show it again'
		=> 'Przeniesienie kolumny lub wiersza w Google przywróci je na stronę',
	'A hidden column is matched by its heading, a hidden row by its line number. Reordering columns, renaming a heading or inserting a row above breaks that match.'
		=> 'Ukryta kolumna jest dopasowywana po nagłówku, a ukryty wiersz po numerze wiersza. Przestawienie kolumn, zmiana nagłówka lub wstawienie wiersza powyżej przerywa to dopasowanie.',
	'When a match breaks, the column or row is shown again rather than the wrong one being hidden. A notice in the dashboard names the table, and you can hide it again here.'
		=> 'Gdy dopasowanie przestaje działać, kolumna lub wiersz wraca na stronę, zamiast ukryć niewłaściwy element. Powiadomienie w kokpicie wskaże tabelę, a ukryć ją można ponownie na tym ekranie.',
	'Click a heading or a line number to hide it'
		=> 'Kliknij nagłówek albo numer wiersza, aby go ukryć',
	'Click it again to put it back'
		=> 'Kliknij ponownie, aby przywrócić',
	'Click the arrow to move a column under the row instead'
		=> 'Kliknij strzałkę, aby przenieść kolumnę pod wiersz',
	'Move “%s” under the row, behind an arrow'   => 'Przenieś „%s” pod wiersz, za strzałkę',
	'Show this row again'                        => 'Pokaż ten wiersz ponownie',
	'Showing the first %1$d of %2$d rows. Beyond that, use the filter above rather than picking rows one at a time.'
		=> 'Pokazano pierwsze %1$d z %2$d wierszy. Powyżej tej liczby użyj filtra zamiast wybierać wiersze pojedynczo.',
	'Rows — page %1$s of %2$s (%3$s in all)'     => 'Wiersze — strona %1$s z %2$s (razem %3$s)',
	'Columns — page %1$s of %2$s (%3$s in all)'  => 'Kolumny — strona %1$s z %2$s (razem %3$s)',
	'No rows hidden yet.'
		=> 'Nie ukryto jeszcze żadnego wiersza.',
	'not on that line now'                       => 'nie ma tego teraz w tym wierszu',
	'Line'                                       => 'Wiersz',
	'Column %s'                                  => 'Kolumna %s',
	'Table'                                      => 'Tabela',
	'Hidden'                                     => 'Ukryta',
	'Shown'                                      => 'Widoczna',
	'In the details'                             => 'W szczegółach',
	'Write'                                      => 'Wpisz',

	// Download and print.
	'Downloads and printing'
		=> 'Pobieranie i drukowanie',
	'Let visitors download or print this table'  => 'Pozwól odwiedzającym pobrać lub wydrukować tę tabelę',
	'Excel, CSV and Print buttons under the table. A download contains exactly what the visitor sees: the rows left after filtering, and the columns you kept.'
		=> 'Przyciski Excel, CSV i Drukuj pod tabelą. Pobrany plik zawiera dokładnie to, co widzi odwiedzający: wiersze po filtrowaniu i pozostawione kolumny.',
	'Download for Excel'                         => 'Pobierz dla Excela',
	'Download CSV'                               => 'Pobierz CSV',
	'Print'                                      => 'Drukuj',
	'Copy'                                       => 'Kopiuj',
	'This table cannot be downloaded.'           => 'Tej tabeli nie można pobrać.',
	'This download link is not valid.'           => 'Ten link do pobrania jest nieprawidłowy.',
	'This server cannot build Excel files.'      => 'Ten serwer nie potrafi tworzyć plików Excela.',
	'Could not build the Excel file.'            => 'Nie udało się utworzyć pliku Excela.',
	'Could not create a temporary file for the download.'
		=> 'Nie udało się utworzyć pliku tymczasowego do pobrania.',

	// Permissions.
	'You are not allowed to do that.'            => 'Nie masz uprawnień, aby to zrobić.',
	'You are not allowed to view this page.'     => 'Nie masz uprawnień, aby zobaczyć tę stronę.',
	'The numbers down the left are the line numbers in your sheet, so line 1 is the headings.'
		=> 'Numery po lewej to numery wierszy w Twoim arkuszu, więc wiersz 1 to nagłówki.',
	'Words a filter understands'
		=> 'Słowa, które rozumie filtr',
	'The reference for the filter attribute, one sheet feeding several pages'
		=> 'Wykaz do atrybutu filter — jeden arkusz zasilający kilka stron',
	'The filter attribute is listed with every other one beside the shortcode, on the sheet\'s own screen. This is what may go inside it.'
		=> 'Atrybut filter jest wymieniony razem z pozostałymi przy shortcode’zie, na ekranie danego arkusza. Tutaj jest to, co może się w nim znaleźć.',
	'A filter chooses rows, not columns. Which columns a table shows is decided once for the whole sheet, on its own screen, and every page using that sheet shows the same ones.'
		=> 'Filtr wybiera wiersze, nie kolumny. To, które kolumny pokazuje tabela, ustala się raz dla całego arkusza, na jego własnym ekranie, i każda strona używająca tego arkusza pokazuje te same.',
	'filter="Column is value"'
		=> 'filter="Kolumna is wartość"',
	'Show only the rows that match, so one sheet can feed several pages'
		=> 'Pokazuje tylko pasujące wiersze, więc jeden arkusz może zasilać kilka stron',
	'Join conditions with a comma; the words to compare with are listed under Pro settings.'
		=> 'Warunki łączy się przecinkiem; słowa porównań są wypisane w ustawieniach Pro.',
	'Tick a column and a menu of its values appears above the table. A visitor opens the menu, picks a value, and the table keeps only the rows that match.'
		=> 'Zaznacz kolumnę, a nad tabelą pojawi się menu z jej wartościami. Odwiedzający otwiera menu, wybiera wartość, a tabela zostawia tylko pasujące wiersze.',
	'This is what a visitor sees above the table:'
		=> 'Tak to widzi odwiedzający nad tabelą:',
	'The table then shows %1$s of its %2$s rows.'
		=> 'Tabela pokazuje wtedy %1$s z %2$s wierszy.',
	'Every choice has an address of its own, so a filtered table can be sent to somebody as a link. Filters work together with the search box and with pages, over the whole sheet rather than the page on screen.'
		=> 'Każdy wybór ma własny adres, więc przefiltrowaną tabelę można komuś wysłać linkiem. Filtry działają razem z wyszukiwarką i stronami, w obrębie całego arkusza, a nie tylko strony na ekranie.',
	'The counts beside each column below come from the copy the plugin holds now.'
		=> 'Liczby przy kolumnach poniżej pochodzą z kopii, którą wtyczka ma teraz.',
	'Availability'
		=> 'Dostępność',
	'In stock'
		=> 'W magazynie',
	'Add a rule'                                 => 'Dodaj regułę',
	'To remove a rule, set its column back to “remove this rule”.'
		=> 'Aby usunąć regułę, ustaw jej kolumnę z powrotem na „usuń tę regułę”.',
);
