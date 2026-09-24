# Strona sprzedażowa

Sekcje strony `rizznet.pl`, każda jako jeden moduł Kod w Divi. Wersja angielska
i polska osobno. To nie jest część wtyczki — tu mieszka to, co opowiada o niej
na zewnątrz.

## Jak tego używać

Każdy plik `*-en.html` / `*-pl.html` wkleja się **w całości** do jednego modułu
Kod w Divi. Nic nie trzeba rozdzielać na CSS i integrację — styl i skrypt siedzą
w tym samym pliku.

Przy edycji: każdy znacznik musi zostać w jednej linijce. Divi wstawia w miejscu
złamanego wiersza `<br />`, a to rozbija znacznik. Wewnątrz `<style>` i
`<script>` Divi nic nie rusza.

Pliki `*.py` budują moduły. Po zmianie treści uruchamia się generator, nie
edytuje się wyniku ręcznie. Pliki `*.mjs` to testy w przeglądarce — sprawdzają
rozmiary pisma, zachowanie po wstawieniu `<br />` przez Divi, odporność na wrogi
motyw i układ na telefonie.

## Co gdzie leży

| Katalog | Sekcja |
|---|---|
| `naglowek/` | pasek na górze strony |
| `tlo/` | tło całej witryny — siatka, która ugina się pod kursorem |
| `dwie-minuty/` | „Z arkusza na stronę w dwie minuty" — trzy kroki z podglądami |
| `roznica/` | kafelki „zwykle jest tak, a tu jest tak" |
| `porownanie/` | tabela Darmowa / Pro |
| `cennik/` | trzy plany |
| `jak-dziala/` | podstrona z wyjaśnieniem i zrzutami z wtyczki |
| `pokaz-na-zywo/` | podstrona z działającą tabelą do klikania |
| `faq/` | pytania i odpowiedzi |
| `stopka/` | stopka |
| `KOTWICE-css.css` | do Divi → Opcje motywu → Własny CSS, żeby kotwice nie chowały się pod paskiem |

## Zrzuty na podstronie „jak działa"

`jak-dziala/zrzuty/` to dziesięć obrazków z prawdziwej wtyczki. Wgrywa się je do
Multimediów WordPressa, a potem w `JAK-DZIALA-*.html` podmienia `ADRES` na adres
folderu, do którego trafiły. Jeden raz — wszystkie dziesięć adresów zacznie
wskazywać, gdzie trzeba. Nazwy plików muszą zostać bez zmian.

Zrzuty robi się od nowa narzędziami z `narzedzia/`:

1. `demo-mock.php` kopiuje się do `wp-content/mu-plugins/` lokalnego
   WordPressa. Podstawia wtyczce czysty arkusz pokazowy zamiast fixture'a
   testowego, w którym siedzą próbki XSS.
2. `zasiej.php` zakłada źródło i stronę z tabelą.
3. `zdjecia.mjs` przechodzi przez panel i stronę i przycina zrzuty.

## Typografia

Tytuły sekcji — Inria Serif. Cała treść — IBM Plex Sans. Adresy komórek, nazwy
okien i wszystko, co udaje arkusz — IBM Plex Mono.

Rozmiary tylko **14, 18 i 20 pikseli**. Wyjątki: nazwa marki w stopce (25 px)
oraz tytuły sekcji i wielkie liczby, które skalują się z szerokością okna.
Testy tego pilnują.

## naglowek/HERO-en.html

Sekcja otwierająca stronę. Wklej **całą zawartość pliku** w jeden moduł Code
w Divi. Moduł niczego pod sobą nie maluje — tło jest przezroczyste, więc widać
przez niego tło sekcji i całej witryny.

Sekcja sama ustawia się na pełną wysokość ekranu: skrypt mierzy, ile miejsca
zabiera pasek nawigacji nad nią, i odejmuje to od wysokości okna. Następna
sekcja strony zaczyna się dokładnie pod dolną krawędzią ekranu, więc widać ją
dopiero po przewinięciu. W trakcie przewijania treść hero odjeżdża wolniej niż
strona i gaśnie (paralaksa).

`HERO-podglad.html` to tylko podgląd do otwarcia w przeglądarce — podrabia
pasek nawigacji i dopełnienia Divi. Do Divi idzie wyłącznie `HERO-en.html`.
