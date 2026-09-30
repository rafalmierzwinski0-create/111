# Sekcja pod hero — z arkusza na stronę

Po lewej arkusz Google z cennikiem. Po prawej ta sama treść już na stronie
klienta: prawdziwa tabela z wtyczki, z pigułkami stanu magazynu, słupkiem przy
liczbie sztuk, przyciskiem w kolumnie z adresem, pomalowanym wierszem
wyprzedanego towaru i filtrem nad tabelą. Między nimi strzałka.

Idzie w miejsce zwykłej tabeli przykładowej pod hero. Tabela pokazuje, co
wtyczka potrafi; ta sekcja pokazuje **skąd to jest** — a to jest zdanie, które
sprzedaje.

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

Całą zawartość `PRZEPLYW-en.html` w jeden moduł Kod w Divi. Nic nie trzeba
podmieniać — sekcja nie ma żadnego obrazka, wszystko jest kodem.

`PODGLAD.html` to podgląd do otwarcia w przeglądarce: podrabia tło witryny
(`#232a29` i siatkę 88 × 44, jak `landing/naglowek/HERO-podglad.html`)
i dopełnienia Divi.

`przeplyw-podglad.png` i `przeplyw-bez-tla.png` to zrzuty do pokazania, nie do
wklejenia. Robi je `node zdjecie.mjs`: pierwszy na tle strony, drugi
z przezroczystym tłem, do położenia na czymkolwiek.

**Nie otwieraj `PRZEPLYW-en.html` wprost w przeglądarce.** To fragment do
wklejenia w Divi i nie ma własnej deklaracji typu dokumentu, więc otwarty jako
plik wchodzi w tryb zgodności ze starociami — a w nim `<table>` nie dziedziczy
koloru tekstu po tym, w czym stoi. Cała tabela wychodzi wtedy szara i wygląda
to jak usterka wtyczki, którą nie jest. Do oglądania jest `PODGLAD.html`,
i stąd też robią się zrzuty.

## Dlaczego tak wygląda

* **Dwa okna, jedno na drugim.** Strona klienta stoi z tyłu i zajmuje całą
  szerokość, bo to ona jest tym, co się sprzedaje. Arkusz leży na niej
  z przodu, przy lewej krawędzi, mniejszy: jest źródłem, a nie celem.
  Zachodzenie robi tu całą robotę — mówi „to jest to samo, tylko przepuszczone
  przez wtyczkę” bez ani jednego słowa.
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
i układ na telefonie razem z położeniem strzałki. 18 sprawdzeń.
