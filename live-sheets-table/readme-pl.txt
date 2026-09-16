=== Live Sheets Table – Arkusze Google w WordPressie ===
Contributors: livesheetstable
Tags: arkusze google, tabela, arkusz kalkulacyjny, csv, tabela danych
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.28.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publikuj arkusz Google jako szybką, responsywną, automatycznie odświeżaną tabelę. Bez limitu wierszy, bez klucza API, a strona nie psuje się, gdy Google jest niedostępne.

== Description ==

Live Sheets Table zamienia arkusz Google w prawdziwą tabelę na Twojej stronie WordPress. Udostępnij arkusz jako „Każdy, kto ma link – Przeglądający”, wklej link, sprawdź podgląd i umieść go na stronie blokiem, widżetem Elementora albo shortcodem. Edytujesz arkusz — strona nadąża.

Bez klucza API. Bez projektu w Google Cloud. Bez konta u nas.

= Bez limitu wierszy =

Tabela ma tyle wierszy, ile ma Twój arkusz. Wersja darmowa nie ucina ich na 30, 50 czy 100.

= Strona renderuje się na serwerze, z lokalnej kopii =

Większość wtyczek do arkuszy pobiera dane z Google, gdy Twój odwiedzający czeka — dlatego tabele tak często zawieszają się na „ładowaniu” albo pokazują surowy kod. Ta wtyczka robi odwrotnie:

* Zaplanowane zadanie pobiera arkusz w tle i zapisuje go w Twojej bazie danych.
* Strony renderują tę lokalną kopię w PHP, jako prawdziwy element `<table>`.
* Tabela starsza niż jej odstęp jest sprawdzana, zanim strona zostanie narysowana — z twardym limitem czterech sekund i zapisaną kopią jako zabezpieczeniem. Dzięki temu witryna, której nikt nie odwiedza, albo serwer blokujący harmonogram WordPressa, nie opublikuje po cichu zeszłotygodniowych cen.
* Nic nie zależy od JavaScriptu przy rysowaniu tabeli, więc jest czytelna dla wyszukiwarek i dla przeglądarek, w których jakiś skrypt zawiódł.

= Działa dalej, nawet gdy arkusz przestaje =

Jeśli pobranie się nie powiedzie — ktoś przełączył arkusz na prywatny, Google ograniczyło liczbę żądań, sieć zamrugała — na stronie zostaje ostatnia poprawna kopia. Zapisana migawka jest zastępowana wyłącznie przez udane pobranie.

Błąd trafia tam, gdzie da się na niego zareagować: kokpit pokazuje, co się zepsuło i jak to naprawić. Odwiedzający nigdy nie widzą błędu, pustej tabeli ani zrzutu stosu.

= Naprawdę responsywna, nie tylko „przewija się w bok” =

Na wąskim ekranie tabela przeorganizowuje się w jedną kartę na wiersz, z etykietą przy każdym polu, zamiast zmuszać do poziomego przewijania mikroskopijnego tekstu. Szerokie tabele w wąskich kolumnach też się przekładają, bo punkt przełamania podąża za kontenerem, a nie za oknem przeglądarki.

= Sprawdź, zanim opublikujesz =

Wklejasz link, a kokpit pokazuje dokładnie to, co odczytał — nagłówki, wiersze, skutki scalonych komórek, złą zakładkę — zanim cokolwiek zostanie zapisane. Arkusz z kilkoma zakładkami dostaje listę wyboru, od razu ustawioną na zakładkę, na którą wskazuje Twój link.

= Wciąganie danych =

* Sześć zapisanych źródeł arkuszy.
* Nielimitowana liczba wierszy w każdym z nich.
* Wybór zakładki dla arkuszy, które mają ich więcej niż jedną.
* Synchronizacja w tle co 15 minut i przycisk „Odśwież teraz”.
* Gwarancja, że kto otworzy stronę, zobaczy dane nie starsze niż ustawiony przez Ciebie odstęp — jeśli harmonogram nie zadziałał, sprawdzenie odbywa się w trakcie rysowania strony, z limitem czterech sekund i powrotem do kopii, którą już masz.
* Ostrzeżenie w kokpicie, gdy arkusz wczytuje się poszarpany — wiersz ma więcej komórek niż jest nagłówków, zwykle przez scaloną komórkę — ze wskazaniem numeru wiersza do sprawdzenia.

= Umieszczanie na stronie =

* Blok „Tabela z Arkuszy Google”, widżet Elementora i shortcode `[sheet_table id="123"]` — wszystkie trzy oparte na tym samym rendererze, więc nie mogą się rozjechać.
* Podpis nad tabelą, Twoimi słowami, a nie słowami arkusza.
* Zmiana nazwy kolumny dla odwiedzających, bez ruszania arkusza.
* Sterowanie układem per źródło: przewijaj tabelę w bok albo układaj każdy wiersz jako kartę.
* Etykieta „zaktualizowano N minut temu”, którą można wyłączyć.
* Adresy stron i adresy e-mail w komórkach stają się linkami, bezpiecznie, i da się to wyłączyć per tabela.

= Czytanie długiej albo szerokiej tabeli =

* Opcjonalne pole wyszukiwania, które podświetla to, co znalazło, zamiast zostawiać Cię z szukaniem tego wzrokiem.
* Sortowanie kolumn świadome liczb, więc 1 215,50 sortuje się nad 349,00.
* Wykrywanie kolumn liczbowych i wyrównanie ich do prawej cyframi tabelarycznymi, dzięki czemu przecinki dziesiętne są w jednej linii.
* Opcjonalne stronicowanie, do 500 wierszy na stronę — a wyszukiwanie i sortowanie i tak obejmują cały arkusz, nie tylko oglądaną stronę.
* Nagłówki kolumn zostają widoczne, gdy strona przewija się obok nich.
* Pierwsza kolumna zostaje na miejscu, gdy reszta przewija się w bok, więc cena nigdy nie przestaje należeć do produktu — i można to wyłączyć per tabela.
* Widoczny, przeciągany suwak pod każdą tabelą szerszą od swojej kolumny, więc nic nigdy nie chowa się za niewidocznym paskiem przewijania. Klawisz End przeskakuje na sam koniec.

= Dopasowanie do wyglądu witryny =

* Trzy style tabeli, każdy podąża za jasnym albo ciemnym schematem kolorów czytelnika.
* Wizualny edytor wyglądu: kolory, wielkość tekstu, wysokość wiersza i zaokrąglenie narożników per tabela, z podglądem aktualizowanym na bieżąco.
* Własny CSS per tabela, dla administratorów, którzy mają prawo go pisać, sprawdzany przed zapisem.

= W kokpicie =

* Karta na każdy arkusz: kiedy ostatnio się zsynchronizował, czy ostatnie sprawdzenia się udały, ile ma wierszy i kolumn oraz które strony go używają.
* Błędy widzą wyłącznie administratorzy, razem z tym, co z nimi zrobić.
* Ustawienie języka: wtyczkę można czytać po polsku albo po angielsku niezależnie od języka samej witryny. Zmienia się tylko tekst tej wtyczki.
* Pełne wsparcie tłumaczeń, z polskim w komplecie.
* Przy usunięciu wtyczki nic nie jest kasowane, chyba że sam o to poprosisz na ekranie ustawień.

= Co dodaje Pro =

Live Sheets Table Pro dodaje nielimitowaną liczbę źródeł, synchronizację nawet co minutę, ukrywanie kolumn i wierszy przez klikanie ich na obrazku własnego arkusza, przenoszenie kolumn do rozwijanego panelu pod wierszem, warunkowe kolorowanie komórek, stałe widoki filtrowane, filtry, z których odwiedzający korzystają sami, eksport do Excela, CSV i do druku dla odwiedzających, dwa style premium, arkusze prywatne przez uwierzytelnione połączenie z Google, licencję na kilka witryn oraz priorytetowe wsparcie.

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

Tak. Shortcode `[sheet_table id="123"]` działa wszędzie tam, gdzie wykonywane są shortcode'y — Elementor, Divi, Beaver Builder, klasyczny edytor, widżety. Elementor ma też własny widżet. Blok, widżet i shortcode korzystają z jednego renderera, więc zawsze dają tę samą tabelę.

= Moja tabela jest bardzo szeroka. Co się dzieje na telefonach? =

Zostaje tabelą, w pełnym rozmiarze tekstu, a pod nią pojawia się suwak, którym można ją przesuwać w lewo i w prawo. Nic się nie kurczy i nic nie znika — typowa skarga na szerokie tabele dotyczy mikroskopijnego tekstu, a ten bierze się ze ściskania tabeli zamiast jej przewijania.

Suwak rysuje wtyczka, a nie przeglądarka, bo macOS, iOS i Android chowają poziomy pasek przewijania, dopóki już nie przewijasz — czyli dokładnie wtedy, gdy jest za późno, by się przydał.

Domyślnie tabela zachowuje swój kształt i zyskuje suwak, który można przeciągać, więc tekst zostaje w pełnym rozmiarze i nic się nie chowa. Aby zamiast tego układać wiersze jako karty, zmień „Na ekranach zbyt wąskich dla całej tabeli” na ekranie źródła albo przekaż `layout="cards"` do shortcode. Często jeszcze lepiej jest nadać blokowi szerokie lub pełne wyrównanie, bo daje tabeli miejsce, którego potrzebuje.

= Czy mogę ukryć kolumnę albo nadać jej inną nazwę? =

Zmiana nazwy jest darmowa i jest na ekranie źródła. Dotyczy wyłącznie wyświetlania: wtyczka nigdy nie zapisuje do Twojego arkusza, więc kolumna może nazywać się `cena_netto_bez_rabatu` w Google i po prostu „Cena” na Twojej stronie, a formuły działają dalej.

Ukrywanie kolumn to część Pro — wybiera się je, klikając na obrazku własnego arkusza. Ukrycie usuwa kolumnę z nagłówków i z każdego wiersza, więc kolumna robocza nie znika tylko wizualnie — jej wartości w ogóle nie trafiają na stronę.

Kolumny dopasowują się po pozycji, więc wstawienie jednej w Google przesuwa ustawienia. Wtyczka pamięta, jaki nagłówek miała każda pozycja, i informuje, gdy przestają się zgadzać, zamiast po cichu podpisać dane nie tą etykietą.

= Czy mogę zmienić wygląd tabeli? =

Wybierz jeden z trzech presetów, a potem dopracuj go: ekran źródła ma próbniki kolorów dla tekstu, tła, nagłówków, linii, naprzemiennych wierszy, podświetlenia i akcentu, a także wielkość tekstu, wysokość wiersza i zaokrąglenie narożników. To, czego nie ruszysz, nadal podąża za presetem, więc zmiana jednego koloru nie oznacza definiowania wszystkich.

Każda wartość jest własnością niestandardową CSS na `.lstab`, więc te same rzeczy można nadpisać z arkusza motywu. Jest też pole na Twój własny CSS per tabela. Dodatkowe style tabeli to funkcja Pro.

= Czy WP-Cron musi działać? =

Zaplanowane odświeżanie korzysta z WP-Cron. Jeśli go wyłączyłeś, użyj systemowego cron-a wywołującego `wp-cron.php` albo naciśnij „Odśwież teraz”. Niedziałający harmonogram nigdy nie wyczyści Twojej tabeli — zapisana kopia renderuje się dalej.

= Czy mogę pokazać kilka różnych arkuszy? =

Wersja darmowa przechowuje sześć źródeł arkuszy. Pro znosi ten limit.

= Czy zawartość arkusza jest bezpieczna do wyświetlenia? =

Tak. Wszystko z arkusza jest escapowane przy wyjściu, więc komórka zawierająca HTML albo znacznik `<script>` pokazuje się jako tekst i nie może niczego wstrzyknąć na Twoją stronę.

== Changelog ==

Pełna historia wydań znajduje się w pliku readme.txt.

= 3.28.0 =
* Poprawka: suwak pod szeroką tabelą znów był gołą kreską leżącą na wierszach. Dwie usterki, jeden objaw. To, czy suwak unosi się nad danymi, było liczone względem dolnej krawędzi okna — a to zakłada, że przewija się sama strona. Na opublikowanej stronie prawda; w podglądzie w kokpicie nie, bo tabela siedzi tam w pudełku, które przewija się wewnątrz okna i nigdy nie dojeżdża do jego dołu. Do tego decyzja czekała na pierwsze przewinięcie, więc tabela, która już przy wczytaniu leżała pod suwakiem, dostawała gołą kreskę, dopóki ktoś nie ruszył strony. Teraz suwak pyta wprost wierszy — dopóki choć kawałek tabeli jest poniżej jego górnej krawędzi, leży na danych i ubiera się w tło — i pyta o to w chwili, w której się pojawia.
* Zmiana (Pro): „Pobieranie i drukowanie” jest w zakładce Ogólne. Było w Wyglądzie, bo to przyciski, które widzi odwiedzający — ale nikt nie szuka odpowiedzi na „czy ludzie mogą to pobrać?” pod tym, jak tabela jest pokolorowana. Ogólne były też jedyną zakładką, na której dodatek nie mógł umieścić niczego; teraz przyjmuje karty jak dwie pozostałe.

= 3.27.0 =
* Nowość: arkusz mający dwieście wierszy lub więcej dostaje strony już przy zakładaniu i od razu jest o tym mowa w zdaniu, które Cię wita — ile wierszy arkusz ostatecznie ma, po ile jest pokazywany i gdzie to zmienić. Nic nie dzieje się po cichu: jeśli sam dotknąłeś przełącznika stron, w jedną czy w drugą stronę, Twoja decyzja zostaje.
* Nowość: arkusz, który już miałeś i który urósł powyżej dwustu wierszy, jest pytany, a nie zmieniany. Jego karta proponuje strony, mówi, ile wierszy skłoniło ją do tego pytania, i przyjmuje „tak” albo „nie” jednym kliknięciem — opublikowana strona nie ma prawa przestawiać się za plecami autora. „Nie” jest zapamiętywane, bo propozycja, która wraca w kółko, przestaje być propozycją.
* Poprawka: przycisk kopiowania obok shortcode'u potrafił w nieskończoność stać z napisem „Kopiuj”. Nowoczesne wywołanie schowka nie zawsze odpowiada — przeglądarka, która uzna, że strona nie jest przed Tobą, zawiesza je zamiast odmówić — więc dostaje teraz półtorej sekundy, po czym shortcode zostaje zaznaczony, a przycisk mówi, żeby nacisnąć Ctrl+C. Przycisk, który nie odpowiada wcale, jest gorszy od takiego, który przyznaje się, że nie dał rady.

= 3.26.0 =
* Poprawka: język wybrany na witrynie, która nigdy wcześniej nie zapisała ustawień wtyczki, nie zaczynał działać, dopóki ktoś nie zapisał ich po raz drugi. Pierwszy zapis tworzy ustawienia, a nie je zmienia, i WordPress ogłasza te dwie rzeczy inaczej; wtyczka nasłuchiwała tylko jednej z nich, więc odpowiadała na zapis w języku, z którego właśnie zrezygnowałeś. Wykryte przez odbudowanie witryny testowej od zera — używane wcześniej środowisko tego nie pokaże — a teraz pilnuje tego test, który świeżej instalacji nie potrzebuje.
* Poprawka (Pro): usunięcie dodatku zabiera ze sobą klucz do Twojego konta Google. Podłączenie arkusza prywatnego zostawia w bazie poświadczenie, które otwiera te arkusze tak długo, jak długo istnieje, a nic go nie kasowało — usunięcie wtyczki zostawiało je tam na zawsze. Teraz znika razem z dodatkiem, na każdej witrynie sieci, niezależnie od tego, co kazałeś zachować. Wszystko, co jest Twoją pracą — dane aplikacji Google, reguły kolorów, filtry, informacja, które arkusze są prywatne albo do pobrania — nadal podlega ustawieniu „usuń wszystko”, bo usunięcie wtyczki po to, by zainstalować ją ponownie, jest rzeczą normalną.
* Poprawka: zdanie obok pola wyboru zakładki, tłumaczące, dlaczego lista jest krótka, jest teraz odczytywane razem z samym polem, a nie leży obok, gdzie czytnik ekranu nigdy by go z nim nie połączył.
* Zmiana: wtyczka wymaga WordPressa 6.7 lub nowszego. Deklarowała 6.0, czego jej własny blok nie mógł dotrzymać — blok mówi wersją języka edytora, która pojawiła się w 6.3 — a nic poniżej 6.8 nigdy nie było na niej uruchamiane. Teraz 6.7 to zarazem to, co deklaruje, i to, na czym jest testowana.

= 3.25.0 =
* Poprawka: pole wyboru zakładki arkusza jest na ekranie od razu po otwarciu edytora i od razu pokazuje zakładkę, na którą arkusz jest ustawiony. Wcześniej było schowane, dopóki Google nie odpowiedziało listą zakładek — kilka sekund później — więc kto patrzył na formularz w trakcie wczytywania, nie widział sposobu na zmianę zakładki ani powodu, by sądzić, że taki się pojawi.
* Poprawka: gdy listy zakładek nie da się odczytać, pole zostaje na miejscu z zakładką z Twojego linku i obok pisze, dlaczego pozostałych nie ma. Wcześniej znikało bez słowa, co wyglądało, jakby wtyczka odebrała to ustawienie.

= 3.24.0 =
* Nowość (Pro): przycisk „Dodaj regułę” pod regułami kolorów. Dwie puste linijki były całą odpowiedzią na pytanie, ile reguł ktoś chce, więc czwarta oznaczała wypełnienie obu, zapis i powrót po kolejne dwie. Teraz czeka jedna pusta linijka, a resztę dodaje przycisk — i przestaje je oferować, gdy tabela ma już wszystkie dwadzieścia, zamiast pozwolić po cichu zgubić dwudziestą pierwszą.

= 3.23.0 =
* Poprawka: rządek słupków na karcie arkusza miał różne wysokości, czyli kształt pomiaru — a nic nie było mierzone. Wysokości brały się ze wzoru na liczniku pętli, więc jedyne pytanie, jakie ten obrazek nasuwał, nie miało odpowiedzi. Teraz to jeden znacznik na sprawdzenie, wszystkie tej samej wysokości, z podpisem „Ostatnie sprawdzenia” i liczbą nieudanych napisaną słowami, a nie tylko kolorem.
* Zmiana: nazwa zakładki na karcie mówi, że jest zakładką. Sama nazwa obok ikony warstw mogła znaczyć cokolwiek.

= 3.22.0 =
* Poprawka: „Dodaj arkusz” otwiera formularz od początku, tak samo jak otwarcie istniejącego arkusza. Zakładka była zapamiętywana przy każdym kliknięciu, więc nowy arkusz otwierał się tam, gdzie skończyła się poprzednia wizyta. Teraz jest zapamiętywana tylko na czas zapisu — i po to była: nieudany zapis wraca do zakładki, z której go zrobiono.
* Zmiana: Ustawienia i Pro są pozycjami w bocznym menu pod wtyczką, a nie tylko zakładkami nad jej ekranami. Kto szuka ustawień wtyczki, patrzy najpierw na listę po lewej.

= 3.21.0 =
* Poprawka: poziomy suwak wyglądał jak przypadkowa kreska na wierszach, gdy nad nimi wisiał. Teraz staje się tam osobnym elementem — z własnym tłem, obramowaniem i cieniem — i oddaje to wszystko, gdy tylko widać koniec tabeli.
* Nowość: atrybut, który przyjmuje jedno z ustalonych słów, wypisuje teraz te słowa. style="striped" nic nie mówiło komuś, kto nie mógł wiedzieć, co jeszcze da się tam wpisać.
* Zmiana: atrybut filter z dodatku jest wymieniony razem z pozostałymi przy shortcode’zie, a nie na osobnym ekranie. W ustawieniach Pro został wykaz tego, co może się w nim znaleźć, i wprost napisane, że filtr wybiera wiersze — o kolumnach decyduje się raz dla całego arkusza.
* Zmiana: dziewięć kolorów reguł jest o ton głębszych. Poprzednie były tak blade, że pokolorowana komórka wyglądała jak wada druku, a nie jak decyzja. Każdy nadal spełnia próg czytelności tekstu, a reguły zapisane na starej palecie przechodzą na kolor, który je zastąpił.
* Zmiana: dwa znaczniki, które nie są kolorem, pokazują, co robią — litera w grubości, jaką nada komórce, i litera z przekreśleniem — zamiast „B” i „S” złożonych jak wszystko inne.
* Poprawka: kółko „własny kolor” otwiera teraz paletę po kliknięciu. Wcześniej stało obok próbnika, więc kliknięcie w nie wybierało „mój kolor”, nie proponując żadnego. Teraz jest jedno kółko i przyjmuje wybrany kolor.
* Nowość: karta zawężania tabeli pokazuje pasek tak, jak wygląda na stronie — z nagłówkami z Twojego arkusza i liczbą wierszy, jaka zostanie. To pytanie padało najczęściej, a odpowiadało na nie jedno zdanie.

= 3.20.0 =
* Poprawka: polski ekran pisał „za 1 godzina” i „co 1 godzina”. Każde zdanie, w które trafia długość czasu, rządzi biernikiem, a tłumaczenie dawało mianownik; wszystkie siedem jednostek ma teraz formę, której wymaga zdanie.
* Nowość: opcjonalne atrybuty shortcode’u to dwukolumnowy wykaz — co wpisać i co to robi — zamiast pięciu fragmentów kodu bez wyjaśnień.
* Nowość: cztery próbki kolorów, których nazwa nie wskazywała niczego w tabeli, mówią teraz, co kolorują. „Akcent” to odnośniki, strzałki sortowania i numery stron.
* Zmiana: „Wybierz wygląd” to teraz „Wygląd i zachowanie”, bo karta zawiera też paginację i zamianę adresów w komórkach na odnośniki.
* Zmiana: opisy stylów straciły żargon — zniknęły „włosowe linie”, „preset” i „dziedziczy kroje pisma”.
* Zmiana: ekran ukrywania kolumn i wierszy mówi wszędzie „ukryj”. Wcześniej pisał „usuń” tuż nad zdaniem obiecującym, że nic nie jest zapisywane w Google.
* Zmiana: picker wyjaśnia, że numery po lewej to numery wierszy w arkuszu, więc pierwszy wiersz danych z numerem 2 nie jest zagadką.
* Zmiana: styl Midnight nazywa się po polsku „Nocny”. „Północ” znaczy i midnight, i north, a nic w stylu tabeli nie mówi które.
* Poprawka: wykazy na obu ekranach czytają się od lewej do prawej. Kolumna ze znaczeniami była wyśrodkowana, odziedziczona po tabeli, w której ostatnia kolumna to pole wyboru.

= 3.19.0 =
* Poprawka: połowa kokpitu mogła być po polsku, a połowa po angielsku. Złożyły się na to trzy luki: dodatek nie miał w ogóle polskiego tłumaczenia; panel bloku w edytorze nigdy nie dostawał swoich tłumaczeń, bo plik .mo jest niewidoczny dla JavaScriptu; a nazwa i opis bloku pochodzą z block.json, którego WordPress tłumaczy w osobnym kontekście, nigdy dotąd nieeksportowanym. Wszystkie trzy są zamknięte.
* Poprawka: długość czasu wewnątrz przetłumaczonego zdania zostawała w języku witryny, co dawało „za 1 week” na polskim ekranie.
* Nowość: ustawienie języka. Wtyczkę i dodatek można czytać po polsku albo po angielsku niezależnie od języka witryny. Zmienia się tylko tekst tej wtyczki; reszta kokpitu zachowuje ustawienie WordPressa.
* Zmiana: kolejne przejście po opisach, tym razem pod kątem słów, a nie długości. Zniknął żargon, którego właściciel witryny nie ma powodu znać.

= 3.18.0 =
* Zmiana: przepisano wszystkie etykiety, podpowiedzi i komunikaty w kokpicie. Etykieta nazywa ustawienie, a podpowiedź w jednym zdaniu mówi, co ono robi.
* Poprawka: przy niskim oknie przeglądarki tekst pomocy w panelu podglądu edytora nachodził na wiersz poniżej.
* Poprawka: poziomy suwak tabeli jest teraz widoczny przy każdej pozycji przewijania, a nie dopiero po dojechaniu na dół długiej tabeli.

= 3.17.0 =
* Zmiana: tabela jest ciemna wtedy, gdy ciemna jest strona, a nie gdy ciemny jest system odwiedzającego. Wybrany ręcznie kolor i preset Midnight pozostają bez zmian.

= 3.16.1 =
* Poprawka: na jasnym motywie oglądanym przy ciemnym ustawieniu systemu znikał tytuł tabeli, liczba wierszy i wiersz „zaktualizowano”.
* Poprawka: etykieta obok każdej wartości w układzie kart była zbyt jasna, by spełnić próg czytelności.

= 3.16.0 =
* Zmiana: wtyczka ma własny znak graficzny w kokpicie, menu i wstawiaczu bloków.

= 1.5.0 =
* Nowość: zmiana nazwy kolumny dla odwiedzających albo pominięcie jej w tabeli. Nic nie jest zapisywane do Google, więc arkusz zachowuje własne nagłówki — także kolumny robocze, których nikt nie powinien widzieć.
* Nowość: ponieważ kolumny dopasowują się po pozycji, kolumna dodana lub usunięta w Google jest teraz zgłaszana w kokpicie, zamiast po cichu przesuwać wszystkie etykiety.
* Nowość: przyklejanie pierwszej kolumny można wyłączyć per tabela, dla arkuszy, w których pierwsza kolumna to długi tekst.

= 1.4.0 =
* Nowość: pierwsza kolumna zostaje przyklejona, gdy szeroka tabela przewija się w bok, więc każdy wiersz zachowuje swoją etykietę. Jest ograniczona, żeby nigdy nie zajęła całego ekranu, i pokazuje linię oddzielającą dopiero wtedy, gdy coś się za nią chowa.
* Nowość: kokpit informuje, gdy zaplanowana synchronizacja przestała działać. Zablokowany WP-Cron niczego nie psuje — strony nadal serwują zapisaną kopię — po prostu cicho przestaje aktualizować, a to rodzaj usterki, której nikt nie zauważa, dopóki nie zauważy jej klient.
* Poprawka: kolor tła nagłówka był raportowany jako tło tabeli, przez co ta kontrolka w edytorze wyglądu sprawiała wrażenie martwej.

= 1.3.0 =
* Nowość: widoczny, przeciągany suwak pod każdą tabelą szerszą od swojej kolumny. Przeglądarki na macOS, iOS i Androidzie chowają poziomy pasek przewijania, dopóki nie zaczniesz przewijać — przez co szeroka tabela wyglądała na uciętą. Ten suwak zostaje na ekranie, dopóki jest co pokazywać, i działa przeciąganiem, kliknięciem, dotykiem i klawiaturą.
* Zmiana: wąskie ekrany zachowują teraz tabelę i przewijają ją, zamiast układać każdy wiersz jako kartę. Tekst zostaje w pełnym rozmiarze — tabela się przesuwa, a nie kurczy. Układ kartowy nadal jest dostępny per źródło.
* Zmiana: wybór układu jest teraz ustawieniem na ekranie źródła, więc podejmuje się go raz zamiast powtarzać przy każdym shortcode; blok i shortcode nadal mogą go nadpisać.
* Poprawka: tabela za szeroka dla swojej kolumny zwijała kolumny do minimum, przez co wiersze miały po kilka linii bez żadnego zysku. Kolumny zachowują teraz naturalną szerokość, a robotę wykonuje suwak.

= 1.2.0 =
* Nowość: wizualny edytor wyglądu na ekranie źródła. Kolory, wielkość tekstu, wysokość wiersza i zaokrąglenie narożników można ustawić per tabela, a podgląd aktualizuje się na żywo. Wszystko, czego nie ruszysz, podąża za wybranym presetem, więc zmiana jednego koloru nie wymaga definiowania reszty.
* Zmiana: dopracowane presety Midnight i Editorial.

= 1.1.0 =
* Poprawka: szerokie tabele mogły chować ostatnie kolumny w wąskiej kolumnie motywu, bez paska przewijania i bez przełączenia na karty. Moment przejścia w karty zależy teraz od liczby kolumn.
* Poprawka: wybór presetu stylu nie zmieniał podglądu, a edycja zapisanego źródła zawsze pokazywała preset domyślny.
* Poprawka: arkusze stylów i skrypty były serwowane pod stałą wersją, więc po aktualizacji mógł zostać stary CSS z cache. Adresy plików zmieniają się teraz razem z plikami.
* Nowość: trzy zapisane źródła arkuszy w wersji darmowej zamiast jednego.
* Nowość: sterowanie układem (automatyczny, zawsze tabela, zawsze karty) w bloku i shortcode.
* Nowość: przełącznik szerokości podglądu, żeby sprawdzić oba układy przed publikacją.
* Nowość: wykrywanie kolumn liczbowych i wyrównanie ich do prawej cyframi tabelarycznymi.
* Nowość: jasny i ciemny schemat kolorów dla darmowych presetów.
* Zmiana: odświeżona stylistyka tabel i cieniowana krawędź oznaczająca przewijanie w poziomie.
* Zmiana: lista źródeł nie ładuje już wszystkich zapisanych migawek; pojedyncze źródła czytane są przez object cache.

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
