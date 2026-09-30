# Sekcja pod hero — z arkusza na stronę

Po lewej słowo, po prawej obrazek.

Obrazek to dwa okna: arkusz Google z cennikiem i ta sama treść już na stronie
klienta — prawdziwa tabela z wtyczki, z pigułkami stanu magazynu, słupkiem przy
liczbie sztuk, przyciskiem w kolumnie z adresem, pomalowanym wierszem
wyprzedanego towaru i filtrem nad tabelą. Między nimi strzałka.

Słowo to tytuł, jedno zdanie, cztery ptaszki i przycisk. Nie opisuje obrazka
obok — obrazek jest dowodem, a nie tematem. Mówi, czego czytelnik przestaje
robić („Stop copying your spreadsheet into WordPress”), wymienia cztery rzeczy,
których nie będzie musiał u nikogo zamawiać, i daje mu przycisk.

Akapitu tu nie ma z rozmysłem: pod hero nikt nie czyta, tylko przebiega
wzrokiem, a ściana tekstu obok obrazka jest ścianą, którą się omija. Była tu
wcześniej i nie działała.

Idzie w miejsce zwykłej tabeli przykładowej pod hero. Tabela pokazuje, co
wtyczka potrafi; ta sekcja pokazuje **skąd to jest** — a to jest zdanie, które
sprzedaje.

Przycisk prowadzi pod `ADRES-POBIERANIA`, tak samo jak przyciski w hero
i w stopce. To pierwsze miejsce po hero, w którym jest co kliknąć.

## Jak zbudować od nowa

```
php landing/przeplyw/zbierz.php /tmp/lstab-env/wp71   # renderuje tabelę
python3 landing/przeplyw/zrob.py                      # składa sekcję
cd landing/przeplyw && node spr.mjs                   # sprawdza ją
```

`zbierz.php` zakłada w prawdziwym WordPressie źródło z `cennik.csv`, ustawia mu
szablon, reguły kolorów, wygląd kolumn i filtr dokładnie tak, jak robi się to
w kokpicie, renderuje tabelę **i przy okazji zapisuje surowe wiersze**. Arkusz
po lewej rysuje się z tych samych wierszy.

To jest cała sztuczka tej sekcji: po obu stronach strzałki naprawdę stoi ta
sama treść, a nie dwa osobno napisane przykłady. Nie da się ich rozjechać nie
przebudowując obu naraz, a `spr.mjs` porównuje je komórka po komórce — sekcja,
w której przykład po lewej mówi co innego niż przykład po prawej, kłamie
o produkcie, a wygląda dobrze.

Na koniec `zbierz.php` przywraca język wtyczki: to ta sama witryna, na której
chodzi `tests/run-all.sh`.

## Co wkleić

**W trzech kawałkach, a nie w jednym.** Cała sekcja to prawie dwieście
kilobajtów w jednym polu edytora wizualnego, a edytor trzyma to w pamięci
i przerysowuje przy każdym naciśnięciu klawisza — potrafi na tym stanąć razem
z całą stroną. Rozdzielone idzie tam, gdzie każdy kawałek waży tyle, ile ma
ważyć:

| plik | gdzie |
|---|---|
| `PRZEPLYW-kod.html` | moduł Kod w Divi (sam znacznik, ~21 kB) |
| `PRZEPLYW-css.css` | Divi → Opcje motywu → Własny CSS (~52 kB) |
| `PRZEPLYW-js.js` | Divi → Opcje motywu → Integracja → przed `</body>`, w `<script>` |

Skrypt jest **nieobowiązkowy**. Bez niego tabela jest kompletna i wygląda tak
samo; nie działa tylko wyszukiwarka, sortowanie i filtr nad nią. Na stronie
sprzedażowej to bywa nawet lepsze niż pole wyszukiwania, które odpowiada
„0 wyników”.

Arkusz stylów jest przed zapisaniem skracany — bez komentarzy i bez pustych
miejsc, ze 109 kB robi się 52. Cięte są tylko komentarze i białe znaki; spacje
wokół działań zostają, bo `calc( 100% - 2 * x )` bez spacji przestaje być
poprawnym wyrażeniem.

`PRZEPLYW-en.html` to ta sama sekcja **w jednym pliku**, ze stylami i skryptem
w środku. Zostaje dla porządku i do podglądu; do Divi lepiej brać trzy kawałki
wyżej.

`SLOWO-en.html` to **same napisy z lewej strony jako osobny moduł**: tytuł,
zdanie, cztery ptaszki i przycisk, bez okien i bez tabeli. Do wstawienia
w osobną kolumnę Divi, kiedy obrazek ma stać gdzie indziej albo wcale. Ma
własny, krótszy arkusz stylów i własny przedrostek klas (`lst-sl`), więc oba
moduły mogą stać na jednej stronie i nic się nie pomiesza. Słowa biorą się
z tych samych stałych co w całej sekcji (`TYTUL`, `PTASZKI`, `STOPKA`
w `zrob.py`), a `spr.mjs` porównuje jeden plik z drugim słowo w słowo: dwa
pliki z tym samym tekstem rozjeżdżają się pierwszego dnia, w którym ktoś
poprawi jeden z nich. Podgląd: `SLOWO-podglad.html`.

Tytuł w tym module jest większy niż w sekcji i mierzy się **szerokością
kolumny**, w której moduł stoi (`container-type: inline-size` i `cqi`), a nie
szerokością okna przeglądarki. Ten sam blok raz ląduje w kolumnie na jedną
trzecią strony, raz na całą, i w obu ma wyglądać tak, jak został zaprojektowany.

`PODGLAD.html` to podgląd do otwarcia w przeglądarce: podrabia tło witryny
(`#232a29` i siatkę 88 × 44, jak `landing/naglowek/HERO-podglad.html`)
i dopełnienia Divi.

`przeplyw-podglad.png` i `przeplyw-bez-tla.png` to zrzuty do pokazania, nie do
wklejenia. Robi je `node zdjecie.mjs`: pierwszy na tle strony, drugi
z przezroczystym tłem, do położenia na czymkolwiek.

`przeplyw-obrazek.png` i `przeplyw-obrazek-bez-tla.png` to **sama kompozycja,
bez kolumny z napisami** — dwa okna i łuk, do wstawienia jako zwykły obrazek
albo obok modułu `SLOWO-en.html`, kiedy napisy mają stać osobno. Kolumna
z tekstem jest przed zrzutem wyjmowana ze strony, a nie chowana: schowana
zostawiłaby po sobie kolumnę siatki i kompozycja stanęłaby w prawej połowie
kadru.

Kadr tych dwóch liczy się z krawędzi okien, a nie z pudełka sekcji: sekcja jest
szeroka na całe okno przeglądarki, więc po bokach zostawał przezroczysty pas
i obrazek przestawał wyglądać na wyśrodkowany, kiedy się go kładło na stronie.
Nic się przy tym nie traci — cienie tych okien mają rozmycie mniejsze niż
ujemny rozrzut i w bok nie sięgają ani o piksel.

**Nie otwieraj `PRZEPLYW-en.html` wprost w przeglądarce.** To fragment do
wklejenia w Divi i nie ma własnej deklaracji typu dokumentu, więc otwarty jako
plik wchodzi w tryb zgodności ze starociami — a w nim `<table>` nie dziedziczy
koloru tekstu po tym, w czym stoi. Cała tabela wychodzi wtedy szara i wygląda
to jak usterka wtyczki, którą nie jest. Do oglądania jest `PODGLAD.html`,
i stąd też robią się zrzuty.

## Dlaczego tak wygląda

* **Dwie kolumny dopiero od 1240 px, i to jest zmierzone.** Niżej tabela
  przestaje się mieścić w swoim oknie: najpierw wystaje jej ostatnia kolumna
  i okno zaczyna przewijać się w bok, a jeszcze niżej tabela składa się
  w karty. Karty w tym miejscu nie mówią nic o tym, co wtyczka potrafi, więc
  poniżej progu tekst staje nad obrazkiem i obrazek dostaje całą szerokość.
  Lepiej jedna kolumna z pełnym obrazkiem niż dwie z pustym.
* **Sekcja idzie przez całą szerokość ekranu.** Reszta strony stoi na szynie
  szerokiej na 1240 px i wyśrodkowanej; tu po bokach zostawały przez to dwa
  puste pasy. Kompozycja jest treścią, a nie ilustracją wstawioną w akapit,
  więc dostaje wszystko, co jest, minus jeden margines (`--pl-margines`), żeby
  napisy nie leżały na brzegu. Prawa strona wychodzi jeszcze o pół tego
  marginesu poza szynę, ale nigdy nie jest przycięta: ucięta byłaby akurat
  kolumna z przyciskami.
* **Dwa okna, jedno na drugim.** Strona klienta stoi z tyłu i zajmuje całą
  szerokość, bo to ona jest tym, co się sprzedaje. Arkusz leży na niej
  z przodu, przy lewej krawędzi, mniejszy: jest źródłem, a nie celem.
  Zachodzenie robi tu całą robotę — mówi „to jest to samo, tylko przepuszczone
  przez wtyczkę” bez ani jednego słowa.
* **Tabela rozpływa się dokładnie tam, gdzie zaczyna się arkusz.** Krawędź
  arkusza jest nieprzezroczysta i ucina w pół wszystko, co pod nią wejdzie: raz
  padła na drugi wiersz nazwy produktu i z „Insulated Bottle 750 ml” zostało
  „750”, co wygląda na usterkę tabeli, a nie na kompozycję. Zanik kończy się
  teraz tam, gdzie arkusz się zaczyna, więc nie ma czego ucinać. Dlatego też
  okno ma stałą wysokość w rem, a nie ułamek szerokości ekranu — to ona mówi,
  gdzie kończy się zanik.
* **Okno strony ma sufit i gaśnie u dołu.** Bez sufitu tabela ciągnie się aż do
  stopki z pobieraniem, a arkusz, który na nią nachodzi, zasłania właśnie tę
  stopkę — czyli jedyną rzecz w tym oknie, która jest przyciskiem. Z sufitem
  arkusz kładzie się na zanikniętym rogu i nic działającego nie ginie pod
  spodem. Zanik mówi przy okazji to, co trzeba: wierszy jest więcej.
* **Arkusz jest odrobinę jaśniejszy od strony klienta** i ma zielony znak
  arkusza zamiast miętowego. Dwa okna w tym samym kolorze czytają się jak jedno
  okno, a tu chodzi o to, że to są dwa różne miejsca. W belce stoi nazwa pliku
  (`prices.xls`), bo nazwa pliku mówi „to jest twój arkusz” krócej niż nazwa
  usługi.
* **Tabela jest w kolorach strony**: szablon Północ wybrany, a potem odmalowany
  próbnikami wtyczki — tekst, tło, nagłówek, linie, najechanie, akcent. To jest
  dokładnie to, co wtyczka obiecuje, pokazane zamiast opisanego.
* **Ale nie w kolorze okna, w którym stoi.** Brała przedtem dokładnie kolor
  ekranu i przez to znikała: okno, strona i tabela były jednym czarnym polem,
  w którym widać było tylko pigułki. Tabela na stronie jest przedmiotem
  leżącym NA stronie, a nie samą stroną, więc jest o kilka stopni jaśniejsza,
  ma wyraźniejsze linie i jaśniejszy tekst. Nadal ten sam ciemny motyw, tylko
  widać, gdzie się zaczyna.
* **Strzałka biegnie pasem pod oknem**, a nie przez tabelę. Cienka miętowa
  kreska na tle wierszy, pigułek i słupków — czyli na tle rzeczy, które same są
  miętowe — po prostu ginie. Pod oknem ma pod sobą samo tło strony, więc widać
  ją całą, a grot i tak dochodzi do dolnej krawędzi okna i pokazuje, dokąd te
  dane idą. `spr.mjs` liczy część wspólną prostokąta strzałki z widoczną
  częścią tabeli i wymaga zera.
* Bez metki przy strzałce. W kompozycji, w której okna na siebie nachodzą, taki
  napis nie ma gdzie stanąć, żeby nie leżeć na tabeli — a strzałka i tak mówi
  wszystko, co miał powiedzieć.

## Ruch

Dwie rzeczy, obie na osi widoku (`animation-timeline: view()`), obie raz:

* **łuk rysuje się** od arkusza do tabeli, w stronę, w którą idą dane;
* **wiersze tabeli przyjeżdżają po kolei**, bo tak właśnie arkusz ląduje na
  stronie. Sześć pierwszych, po 34 ms — dziesięć po kolei to już czekanie,
  a nie powitanie.

Nic nie startuje od `opacity: 0` do odwołania: sekcja jest kompletna
w pierwszej klatce, także na zrzucie całej strony i na wydruku. Łuk bez osi
widoku jest po prostu narysowany. `prefers-reduced-motion` i `@media print`
wyłączają jedno i drugie, i nie chowają przy tym niczego.

## Wąsko

Okna przestają na siebie nachodzić i stają jedno pod drugim, arkusz na górze,
bo to on jest pierwszy w tej historii. Strzałka wraca do zwykłego biegu tekstu
i obraca się o ćwierć obrotu, bo droga biegnie teraz z góry na dół. Tabela
składa się w karty sama, swoim własnym zapytaniem kontenerowym.

## Czego pilnuje `spr.mjs`

Dwa okna i prawdziwa tabela z wtyczki w prawym (osiem wierszy, pięć kolumn,
osiem pigułek, osiem słupków, osiem przycisków, filtr i pomalowany wiersz),
**zgodność treści po obu stronach strzałki komórka po komórce**, zgodność tego,
co widać, z tym, co zapisał render, kontrast najsłabszego napisu, rozmiary
pisma (tylko 14, 18 i 20), brak myślników, brak suwaka poziomego przy 1500
i 390 px, to, że nic nie jest niewidoczne w spoczynku, `prefers-reduced-motion`
i układ na telefonie razem z położeniem strzałki.

Do tego sam układ: że tekst stoi po lewej i **nie leży na żadnym z okien**, że
kompozycja wychodzi poza szynę, ale nie poza ekran, że tytuł jest szeryfowy
i zaczyna się tam, gdzie okno, oraz — na samym progu dwóch kolumn i tuż pod nim
— że tabela jest jeszcze tabelą, a nie kartami, i mieści się w swoim oknie
w całości. Na koniec osobny moduł `SLOWO-en.html`: że ma cztery ptaszki
i przycisk, że stoją w nim te same słowa co w całej sekcji, i że sam w wąskiej
kolumnie niczego nie rozpycha. 38 sprawdzeń.
