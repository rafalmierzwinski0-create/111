# Podstrona „What it does”

Jeden moduł Kod w Divi: jak wtyczka działa i co potrafi, na jednej stronie.
W odróżnieniu od `jak-dziala/` nie ma tu ani jednego zrzutu ekranu — tabela na
stronie jest prawdziwa i działa. Dlatego moduł niesie w sobie arkusz stylów
i skrypt wtyczki: sortowanie, szukanie i składanie w karty dzieją się na
stronie sprzedażowej tak samo jak u klienta.

## Jak zbudować od nowa

```
php landing/mozliwosci/zbierz.php /tmp/lstab-env/wp71   # renderuje tabelę
python3 landing/mozliwosci/strona.py                    # składa moduł
cd landing/mozliwosci && node spr.mjs                   # sprawdza go
```

`zbierz.php` zakłada w prawdziwym WordPressie źródło z `trails.csv`, ustawia mu
skórkę, reguły kolorów, wygląd kolumn i filtry dokładnie tak, jak robi się to
w kokpicie, i zapisuje gotowy kod tabeli do `markup.json` (plik nie idzie do
repozytorium — to render jednego uruchomienia). `strona.py` składa z tego
`MOZLIWOSCI-en.html`.

## Co wkleić

Całą zawartość `MOZLIWOSCI-en.html` w jeden moduł Kod. Nic nie trzeba dzielić
na CSS i treść. Plik waży ~170 kB, bo w środku siedzi arkusz stylów i skrypt
wtyczki — bez nich tabela byłaby obrazkiem.

Odsyłacze w tabeli (filtry, pobieranie, kamery) prowadzą do `#`: na stronie
sprzedażowej nie ma serwera, który by je obsłużył. U klienta prowadzą tam,
gdzie trzeba.

## Czego pilnuje `spr.mjs`

Układ i rozmiary pisma (tylko 14, 18 i 20), kontrast każdego napisu, brak
suwaka poziomego przy 1500 i 390 px, `<br />` wstawiane przez Divi, wrogi motyw
pisany z `!important` (ten sam, którym mierzy się `pokaz-na-zywo/`), stronę bez
JavaScriptu, `prefers-reduced-motion`, i to, że tabela naprawdę sortuje
i szuka.
