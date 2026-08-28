=== Live Sheets Table – Arkusze Google w WordPressie ===
Contributors: livesheetstable
Tags: arkusze google, tabela, arkusz kalkulacyjny, csv, tabela danych
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publikuj arkusz Google jako szybką, responsywną, automatycznie odświeżaną tabelę. Bez limitu wierszy, bez klucza API, a strona nie psuje się, gdy Google jest niedostępne.

== Description ==

Live Sheets Table zamienia arkusz Google w prawdziwą tabelę na Twojej stronie WordPress. Udostępnij arkusz jako „Każdy, kto ma link – Przeglądający”, wklej link, sprawdź podgląd i umieść go na stronie blokiem albo shortcodem. Edytujesz arkusz — strona nadąża.

= Bez limitu wierszy =

Tabela ma tyle wierszy, ile ma Twój arkusz. Wersja darmowa nie ucina ich na 30, 50 czy 100.

= Strona renderuje się na serwerze, z lokalnej kopii =

Większość wtyczek do arkuszy pobiera dane z Google, gdy Twój odwiedzający czeka — dlatego tabele tak często zawieszają się na „ładowaniu” albo pokazują surowy kod. Ta wtyczka robi odwrotnie:

* Zaplanowane zadanie pobiera arkusz w tle i zapisuje go w Twojej bazie danych.
* Strony renderują tę lokalną kopię w PHP, jako prawdziwy element `<table>`.
* Nic nie zależy od JavaScriptu przy rysowaniu tabeli, więc jest czytelna dla wyszukiwarek i dla przeglądarek, w których jakiś skrypt zawiódł.

= Działa dalej, nawet gdy arkusz przestaje =

Jeśli pobranie się nie powiedzie — ktoś przełączył arkusz na prywatny, Google ograniczyło liczbę żądań, sieć zamrugała — na stronie zostaje ostatnia poprawna kopia. Zapisana migawka jest zastępowana wyłącznie przez udane pobranie.

Błąd trafia tam, gdzie da się na niego zareagować: kokpit pokazuje, co się zepsuło i jak to naprawić. Odwiedzający nigdy nie widzą błędu, pustej tabeli ani zrzutu stosu.

= Naprawdę responsywna, nie tylko „przewija się w bok” =

Na wąskim ekranie tabela przeorganizowuje się w jedną kartę na wiersz, z etykietą przy każdym polu, zamiast zmuszać do poziomego przewijania mikroskopijnego tekstu. Szerokie tabele w wąskich kolumnach też się przekładają, bo punkt przełamania podąża za kontenerem, a nie za oknem przeglądarki.

= Sprawdź, zanim opublikujesz =

Wklejasz link, a parser pokazuje dokładnie to, co odczytał — nagłówki, wiersze, skutki scalonych komórek, złą zakładkę — w kokpicie, zanim cokolwiek zostanie zapisane. Arkusze z wieloma zakładkami dostają listę wyboru zakładki.

= Co dostajesz =

* Nielimitowaną liczbę wierszy.
* Trzy zapisane źródła arkuszy.
* Blok „Tabela z Arkuszy Google” oraz shortcode `[sheet_table id="123"]`, oba oparte na tym samym rendererze.
* Synchronizację w tle co 15 minut i przycisk „Odśwież teraz”.
* Opcjonalne pole wyszukiwania i sortowanie kolumn (świadome liczb, więc 1 215,50 sortuje się nad 349,00).
* Wykrywanie kolumn liczbowych i wyrównanie ich do prawej cyframi tabelarycznymi, dzięki czemu przecinki dziesiętne są w jednej linii.
* Trzy dopracowane presety stylu, każdy podąża za jasnym lub ciemnym schematem kolorów czytelnika.
* Sterowanie układem: pozwól tabeli samej zdecydować, kiedy zamienić się w karty, albo przypnij ją na stałe do jednego z trybów.
* Etykietę „zaktualizowano N minut temu”, którą można wyłączyć.
* Pełne wsparcie tłumaczeń, z polskim w komplecie.

= Pro =

Live Sheets Table Pro dodaje nielimitowaną liczbę źródeł, synchronizację nawet co minutę, formatowanie warunkowe komórek, paginację dużych tabel, presety premium i własny CSS, obsługę arkuszy prywatnych przez połączenie uwierzytelnione, licencję na kilka witryn oraz priorytetowe wsparcie.

= Prywatność =

Wtyczka łączy się z `docs.google.com` i z niczym więcej, wyłącznie po to, by pobrać skonfigurowane przez Ciebie arkusze. Nie wysyła żadnej analityki i nie rejestruje usług zewnętrznych. Dane arkusza są przechowywane w Twojej własnej bazie danych.

== Installation ==

1. Zainstaluj i włącz wtyczkę.
2. W Arkuszach Google otwórz swój arkusz i wybierz **Udostępnij → Dostęp ogólny → Każdy, kto ma link**, rola **Przeglądający**. Klucz API ani projekt Google Cloud nie są potrzebne.
3. Skopiuj adres z paska przeglądarki.
4. W WordPressie przejdź do **Tabele z arkuszy → Dodaj nowe**, wklej link i wybierz **Wczytaj podgląd**.
5. Sprawdź podgląd, wybierz zakładkę, jeśli arkusz ma ich kilka, wybierz styl i zapisz.
6. Dodaj tabelę na stronę blokiem **Tabela z Arkuszy Google** albo wklej shortcode pokazany na liście źródeł.

== Frequently Asked Questions ==

= Czy potrzebuję klucza API Google? =

Nie. Wtyczka czyta publiczny eksport CSV arkusza, który działa dla każdego arkusza udostępnionego jako „Każdy, kto ma link – Przeglądający”. Arkusze prywatne przez połączenie uwierzytelnione to funkcja Pro.

= Czy muszę użyć „Opublikuj w internecie”? =

Nie. Wystarczy udostępnienie linkiem. Adresy z „Opublikuj w internecie” też są akceptowane, jeśli już takiego używasz.

= Czy jest limit wierszy? =

Nie. Wersja darmowa renderuje każdy wiersz, który zawiera Twój arkusz.

= Jak szybko pojawiają się zmiany? =

Domyślnie wtyczka sprawdza Google co 15 minut, a odświeżenie możesz wywołać ręcznie w każdej chwili. Pro skraca interwał do jednej minuty.

Ponieważ strony renderują się z zapisanej kopii, odwiedzający nigdy nie czeka na Google — kosztem tego jest to, że zmiana staje się widoczna przy następnej synchronizacji, a nie natychmiast.

= Co się stanie, jeśli arkusz stanie się prywatny albo Google przestanie działać? =

Twoja strona nadal pokazuje ostatnią poprawnie pobraną wersję. Kokpit sygnalizuje błąd i wyjaśnia, co zmienić; odwiedzający nie widzą niczego niezwykłego.

= Czy zadziała z moim kreatorem stron? =

Tak. Shortcode `[sheet_table id="123"]` działa wszędzie tam, gdzie wykonywane są shortcode'y — Elementor, Divi, Beaver Builder, klasyczny edytor, widżety. Blok i shortcode korzystają z jednego renderera, więc zawsze dają tę samą tabelę.

= Moja tabela jest bardzo szeroka. Co się dzieje na telefonach? =

Każdy wiersz staje się kartą z etykietami, więc każda wartość zostaje czytelna w pełnym rozmiarze. Nie ma poziomego przewijania ani pomniejszonego tekstu.

Przełączenie następuje wtedy, gdy miejsca na kolumnę robi się za mało — a to zależy od liczby kolumn. Zadziała więc także dla pięciokolumnowej tabeli w wąskiej kolumnie motywu na komputerze, nie tylko na telefonie.

Jeśli wolisz zawsze mieć tabelę, ustaw układ „Zawsze tabela" w bloku albo `layout="table"` w shortcode — będzie przewijana w poziomie, z cieniowaną krawędzią pokazującą, że jest tam coś jeszcze. `layout="cards"` wymusza odwrotność. Zwykle lepszym rozwiązaniem jest nadanie blokowi szerokiego lub pełnego wyrównania, bo daje tabeli miejsce, którego potrzebuje.

= Czy mogę zmienić wygląd tabeli? =

Wybierz jeden z trzech presetów albo napisz własny CSS pod klasy `.lstab-table` w swoim motywie. Każdy kolor jest własnością niestandardową CSS na `.lstab`, więc nadpisanie jednej wartości przestyluje całą tabelę. Dodatkowe presety i wbudowane pole na własny CSS to funkcje Pro.

= Czy WP-Cron musi działać? =

Zaplanowane odświeżanie korzysta z WP-Cron. Jeśli go wyłączyłeś, użyj systemowego cron-a wywołującego `wp-cron.php` albo naciśnij „Odśwież teraz”. Niedziałający harmonogram nigdy nie wyczyści Twojej tabeli — zapisana kopia renderuje się dalej.

= Czy mogę pokazać kilka różnych arkuszy? =

Wersja darmowa przechowuje trzy źródła arkuszy. Pro znosi ten limit.

= Czy zawartość arkusza jest bezpieczna do wyświetlenia? =

Tak. Wszystko z arkusza jest escapowane przy wyjściu, więc komórka zawierająca HTML albo znacznik `<script>` pokazuje się jako tekst i nie może niczego wstrzyknąć na Twoją stronę.

== Changelog ==

= 1.0.0 =
* Pierwsze wydanie.
* Zarządzanie źródłami arkuszy Google z podglądem sparsowanej tabeli przed zapisem.
* Wykrywanie zakładek w arkuszach wielozakładkowych.
* Synchronizacja w tle na WP-Cron z konfigurowalnym interwałem i ręcznym odświeżaniem.
* Fallback na ostatnią poprawną kopię, dzięki czemu nieudane pobranie nie zmienia frontu.
* Renderowanie po stronie serwera, wspólne dla bloku Gutenberga i shortcode'a.
* Responsywny układ kart na wąskich kontenerach, oparty na container queries.
* Opcjonalne wyszukiwanie i sortowanie kolumn świadome liczb.
* Trzy presety stylu, z jasnym i ciemnym schematem kolorów.
* Wykrywanie kolumn liczbowych z wyrównaniem do prawej i cyframi tabelarycznymi.
* Sterowanie układem — przypięcie prezentacji tabelarycznej lub kartowej.
* Parser CSV zgodny z RFC 4180: cudzysłowy, przecinki i znaki nowej linii wewnątrz pól, UTF-8 z BOM i bez.
* Pełna internacjonalizacja, z dołączonym tłumaczeniem polskim.
