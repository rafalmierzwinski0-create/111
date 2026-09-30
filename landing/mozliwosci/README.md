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
szablon Księga, reguły kolorów, wygląd kolumn i filtry dokładnie tak, jak robi
się to w kokpicie, i zostawia witrynę w tym stanie — bo `zrzuty.mjs`
fotografuje potem właśnie te karty. Na koniec przywraca język wtyczki: to ta
sama witryna, na której chodzi `tests/run-all.sh`, a zostawione tam `en_US`
wywraca testy tłumaczeń na wtyczce, której nic nie dolega.

## Co wkleić

Całą zawartość `MOZLIWOSCI-en.html` w jeden moduł Kod. Potem jedna podmiana:
trzy pliki z `zrzuty/` wgrywasz do Multimediów, kopiujesz adres folderu
i zamieniasz w pliku `ADRES` na ten adres. Nazwy plików muszą zostać.

`PODGLAD.html` to tylko podgląd do otwarcia w przeglądarce — podrabia tło
i dopełnienia Divi i wskazuje na lokalne zrzuty. Do Divi idzie wyłącznie
`MOZLIWOSCI-en.html`.

Plik waży ~190 kB, bo w środku siedzi arkusz stylów i skrypt wtyczki — bez nich
tabela byłaby obrazkiem.

Odsyłacze w tabeli (filtry, pobieranie, kamery) prowadzą do `#`: na stronie
sprzedażowej nie ma serwera, który by je obsłużył. U klienta prowadzą tam,
gdzie trzeba.

## Dlaczego tak wygląda

Strona jest ciemna, a tabela jest jedynym ciepłym, jasnym przedmiotem na niej —
bo tabela jest tym, co się sprzedaje, a cała reszta jest ramą dookoła. Stąd
Księga: ciepły papier, podwójna kreska pod nagłówkami, pas co drugą kolumnę.
Arkusz nie jest wpuszczony w stronę, tylko na niej **leży**: ciemna mata pod
nim, jasny kant u góry i prawdziwy cień pod spodem.

Etykiety sekcji (`HOW IT WORKS`, `LIVE ON THIS PAGE`) ciągną za sobą włosową
kreskę przez całą szerokość — ta sama kreska jest podziałem i licznikiem
postępu strony.

## Ruch

Trzy rzeczy dzieją się same, raz:

* wejście kafelków i etapów na górze (380 ms, po 70 ms jedno po drugim) — są
  w pierwszym ekranie, więc nie czekają na przewinięcie;
* kreski między etapami rysujące się **raz**, w stronę, w którą idą dane;
* puls przy „Live on this page” — trzy razy i koniec.

Reszta jest na osi widoku (`animation-timeline: view()`): kafelki, wiersze
legendy, okna kokpitu i arkusz dojeżdżają na miejsce, kiedy strona je mija.
Robią to **wyłącznie przesunięciem** — żadna klatka nie rusza przezroczystości.
Element, który nie doszedł jeszcze do swojego zakresu, stoi w klatce
początkowej, więc `opacity: 0` w klatce startowej znaczyłoby, że przy zrzucie
całej strony, przy wydruku i w przeglądarce bez tej osi pół strony jest puste.
Sprawdza to `spr.mjs` na trzech wysokościach strony.

Wcześniej po kresce jeździła kropka w kółko. Została usunięta: ruch bez końca
na skraju oka nie pokazuje niczego, czego nie pokazuje sama kreska, a widać go
przez cały czas, kiedy się czyta.

`prefers-reduced-motion` i `@media print` wyłączają wszystko, co się rusza,
i nie chowają przy tym niczego.

## Czego pilnuje `spr.mjs`

Układ i rozmiary pisma (tylko 14, 18 i 20), kontrast każdego napisu, brak
suwaka poziomego przy 1500 i 390 px, wczytanie zrzutów, obie tabele na Księdze,
jasność arkusza przeciw jasności strony pod nim, cień, który go trzyma nad nią,
ruch na osi widoku i to, że **na żadnej wysokości strony nic nie jest
niewidoczne**, `<br />` wstawiane przez Divi, wrogi motyw pisany z `!important`
(ten sam, którym mierzy się `pokaz-na-zywo/`), stronę bez JavaScriptu,
`prefers-reduced-motion`, pigułkę w karcie, wyśrodkowanie ramy (luz z lewej
równy luzowi z prawej), to, że arkusz wtyczki dojechał cały — zbłąkana klamra
w moim CSS potrafi zjeść regułę wtyczki stojącą za nią — i to, że tabela
naprawdę sortuje i szuka. 29 sprawdzeń.
