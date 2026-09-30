# Podstrona „What it does”

Jeden moduł Kod w Divi: jak wtyczka działa i co potrafi, na jednej stronie.
Tabela na stronie jest prawdziwa i działa — moduł niesie w sobie arkusz stylów
i skrypt wtyczki, więc sortowanie, szukanie i składanie w karty dzieją się na
stronie sprzedażowej tak samo jak u klienta. Trzy zrzuty z kokpitu pokazują,
skąd się to bierze.

## Jak zbudować od nowa

```
php landing/mozliwosci/zbierz.php /tmp/lstab-env/wp71   # renderuje tabelę
node landing/mozliwosci/zrzuty.mjs                      # robi trzy zrzuty
python3 landing/mozliwosci/strona.py                    # składa moduł
cd landing/mozliwosci && node spr.mjs                   # sprawdza go
```

`zbierz.php` zakłada w prawdziwym WordPressie źródło z `trails.csv`, ustawia mu
szablon, reguły kolorów, wygląd kolumn i filtry dokładnie tak, jak robi się to
w kokpicie, i zostawia witrynę w tym stanie — bo `zrzuty.mjs` fotografuje potem
właśnie te karty. Na koniec przywraca język wtyczki: to ta sama witryna, na
której chodzi `tests/run-all.sh`, a zostawione tam `en_US` wywraca testy
tłumaczeń na wtyczce, której nic nie dolega.

## Co wkleić

Całą zawartość `MOZLIWOSCI-en.html` w jeden moduł Kod. Potem jedna podmiana:
trzy pliki z `zrzuty/` wgrywasz do Multimediów, kopiujesz adres folderu
i zamieniasz w pliku `ADRES` na ten adres. Nazwy plików muszą zostać.

`PODGLAD.html` to tylko podgląd do otwarcia w przeglądarce — podrabia tło
i dopełnienia Divi i wskazuje na lokalne zrzuty. Do Divi idzie wyłącznie
`MOZLIWOSCI-en.html`.

Podgląd maluje pod modułem **to samo tło co reszta witryny**: `#232a29`
i siatkę 88 × 44, dokładnie jak `landing/naglowek/HERO-podglad.html`. Przedtem
malował własną, ciemniejszą czerń — i cała ta podstrona była projektowana pod
tło, którego na stronie nie ma.

Plik waży ~200 kB, bo w środku siedzi arkusz stylów i skrypt wtyczki — bez nich
tabela byłaby obrazkiem.

Odsyłacze w tabeli (filtry, pobieranie, kamery) prowadzą do `#`: na stronie
sprzedażowej nie ma serwera, który by je obsłużył. U klienta prowadzą tam,
gdzie trzeba.

## Skąd się bierze wygląd

Nie stąd, tylko ze strony głównej. Ta podstrona ma być jej dalszym ciągiem,
a nie osobną witryną, więc język jest pożyczony i wszystkie wartości są te
same co w `landing/dwie-minuty` i `landing/naglowek`:

* **arkusz jako metafora.** „How it works” to pasek kolumn `A B C`, numer
  wiersza z boku, trzy komórki w środku i wiersz formuły pod spodem, w którym
  napisane jest, co się z arkuszem dzieje. Strona główna robi ten sam żart
  adresami komórek przy krokach; tu jest rozwinięty do całego wiersza.
* **para „płyta + okienko”**, raz z jednej, raz z drugiej strony, z włoskową
  kreską między wierszami. To jest jeden do jednego sekcja „dwie minuty”.
  Płyta ma nagłówek u góry i krótkie linijki mono u dołu, a luz ląduje między
  nimi — inaczej wysoki zrzut obok trzech zdań zostawia pod nimi pół ekranu
  pustki, co było pierwszą rzeczą, którą widać było na tej stronie.
* **okienko** z trzema oczkami i mono nazwą, ekran wyraźnie ciemniejszy od
  strony. Trzyma zrzuty z kokpitu, ekran telefonu i samą tabelę — dzięki temu
  tabela na żywo i zrzuty czytają się jak rzeczy z jednego miejsca.
* **znacznik przy każdej pozycji legendy** to komórka: z lewej litera kolumny
  w tabeli wyżej, z prawej `Free` albo `Pro`. Adres nie jest ozdobą — mówi,
  gdzie na tabeli tego szukać.
* mięta `95 227 207`, płyta `rgba( 13 18 17 / .62 )`, ekran `#0a1110`,
  mono IBM Plex.

**Tabela jest w kolorach strony.** Szablon Północ wybrany, a potem odmalowany
próbnikami wtyczki: tekst, tło, nagłówek, linie, najechanie i akcent. To jest
dokładnie to, co wtyczka obiecuje — „wybierz szablon, a potem się z nim nie
zgódź” — pokazane na stronie, która to obiecuje, zamiast opisane. Wcześniej
stał tu ciepły papier i tabela wyglądała jak wklejona z innej witryny.

## Ruch

Dwie rzeczy dzieją się same, raz:

* wejście komórek arkusza (380 ms, po 70 ms jedna po drugiej) — są w pierwszym
  ekranie, więc nie czekają na przewinięcie;
* strzałki w wierszu formuły, po kolei, i puls przy „Live on this page” —
  trzy razy i koniec.

Reszta jest na osi widoku (`animation-timeline: view()`): pary, kafelki
legendy, listy i sama tabela dojeżdżają na miejsce, kiedy strona je mija.
Robią to **wyłącznie przesunięciem** — żadna klatka nie rusza przezroczystości.
Element, który nie doszedł jeszcze do swojego zakresu, stoi w klatce
początkowej, więc `opacity: 0` w klatce startowej znaczyłoby, że przy zrzucie
całej strony, przy wydruku i w przeglądarce bez tej osi pół strony jest puste.
Sprawdza to `spr.mjs` na trzech wysokościach strony.

`prefers-reduced-motion` i `@media print` wyłączają wszystko, co się rusza,
i nie chowają przy tym niczego.

## Czego pilnuje `spr.mjs`

Układ i rozmiary pisma (tylko 14, 18 i 20), kontrast każdego napisu **wraz
z wartościami w tabeli**, brak suwaka poziomego przy 1500 i 390 px, wczytanie
zrzutów, obie tabele na Północy, jasność tabeli przeciw jasności okienek
i strony, cień, który ją trzyma nad stroną, ruch na osi widoku i to, że **na
żadnej wysokości strony nic nie jest niewidoczne**, `<br />` wstawiane przez
Divi, wrogi motyw pisany z `!important` (ten sam, którym mierzy się
`pokaz-na-zywo/`), stronę bez JavaScriptu, `prefers-reduced-motion`, pigułkę
w karcie, wyśrodkowanie ramy (luz z lewej równy luzowi z prawej), to, że arkusz
wtyczki dojechał cały — zbłąkana klamra w moim CSS potrafi zjeść regułę wtyczki
stojącą za nią — i to, że tabela naprawdę sortuje i szuka. 29 sprawdzeń.
