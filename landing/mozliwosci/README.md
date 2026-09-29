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
szablon Szkło, reguły kolorów, wygląd kolumn i filtry dokładnie tak, jak robi
się to w kokpicie, i zostawia witrynę w tym stanie — bo `zrzuty.mjs`
fotografuje potem właśnie te karty.

## Co wkleić

Całą zawartość `MOZLIWOSCI-en.html` w jeden moduł Kod. Potem jedna podmiana:
trzy pliki z `zrzuty/` wgrywasz do Multimediów, kopiujesz adres folderu
i zamieniasz w pliku `ADRES` na ten adres. Nazwy plików muszą zostać.

`PODGLAD.html` to tylko podgląd do otwarcia w przeglądarce — podrabia tło
i dopełnienia Divi i wskazuje na lokalne zrzuty. Do Divi idzie wyłącznie
`MOZLIWOSCI-en.html`.

Plik waży ~180 kB, bo w środku siedzi arkusz stylów i skrypt wtyczki — bez nich
tabela byłaby obrazkiem.

Odsyłacze w tabeli (filtry, pobieranie, kamery) prowadzą do `#`: na stronie
sprzedażowej nie ma serwera, który by je obsłużył. U klienta prowadzą tam,
gdzie trzeba.

## Ruch

Trzy rzeczy i ani jedna z nich niczego nie chowa:

* wejście trzech kafelków na górze (260 ms, po 60 ms jedno po drugim) — są
  w pierwszym ekranie, więc nie czekają na przewinięcie;
* miętowa kropka jadąca po kresce między etapami — pokazuje kierunek;
* puls przy „Live on this page”.

Nic nie startuje od `opacity: 0` do odwołania: strona jest kompletna w
pierwszej klatce, także na zrzucie całej strony i na wydruku.
`prefers-reduced-motion` wyłącza wszystkie trzy.

## Czego pilnuje `spr.mjs`

Układ i rozmiary pisma (tylko 14, 18 i 20), kontrast każdego napisu, brak
suwaka poziomego przy 1500 i 390 px, wczytanie zrzutów, szklaną tabelę na
gradiencie, `<br />` wstawiane przez Divi, wrogi motyw pisany z `!important`
(ten sam, którym mierzy się `pokaz-na-zywo/`), stronę bez JavaScriptu,
`prefers-reduced-motion`, pigułkę w karcie i to, że tabela naprawdę sortuje
i szuka. 22 sprawdzenia.
