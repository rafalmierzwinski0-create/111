# Cztery kroki — którędy idą dane

Arkusz, pobranie w tle, kopia w bazie, strona odwiedzającego. Jedna kreska
przez cały blok i jedna strzałka, która nią płynie, chowając się po drodze za
każdym kafelkiem.

**Kod tego bloku nie powstał tutaj** — przyszedł gotowy i jest przepisany bez
zmian w układzie, stylach i komentarzach. Ten katalog dokłada do niego jedno:
obie wersje językowe składane z jednego źródła, żeby nie dało się poprawić
jednej i zapomnieć o drugiej.

## Co wkleić

Całą zawartość `KROKI-en.html` (albo `KROKI-pl.html`) w jeden moduł Kod
w Divi. Nic nie trzeba podmieniać: blok nie ma ani jednego obrazka, ikony są
narysowane w kodzie, a skryptu nie ma wcale.

`proba.html` to podgląd do otwarcia w przeglądarce. Stoi na **prawdziwym tle
witryny** — tym samym pliku, który idzie do Opcji motywu — bo blok jest
półprzezroczysty i ma przez niego przechodzić to, co witryna maluje pod
spodem. Na podrobionej siatce wyglądał inaczej niż na stronie, a różnicę
widać dopiero po wklejeniu.

## Jak zbudować od nowa

```
python3 landing/droga/modul.py     # składa obie wersje i podgląd
node landing/droga/spr.mjs         # sprawdza je w przeglądarce
```

Słowa siedzą w `EN` i `PL` na górze `modul.py`, układ i style są jedne.

## Czego pilnuje `spr.mjs`

Nie ocenia wyglądu — blok jest, jaki jest. Pilnuje rzeczy, które psują się po
cichu przy tłumaczeniu i przy wklejaniu do Divi: że obie wersje mają ten sam
układ, że ich arkusze stylów są identyczne co do znaku, że angielska nie ma
polskich ogonków, że strzałka wciąż jedzie i **chowa się za kafelkami** (czyli
że kafelek stoi piętro wyżej i ma nieprzezroczyste tło), że wąsko kroki stają
jeden pod drugim, a długi tor ustępuje krótkim odcinkom, i że nic nie rozpycha
strony w bok. 17 sprawdzeń.

Podpisy są dwuwierszowe z rozmysłu: dwa wiersze mniej więcej równej długości
stoją pod tytułem jak podpis pod zdjęciem. Przy tłumaczeniu trzeba pilnować
tej samej rzeczy, a nie liczby słów.
