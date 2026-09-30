# Sekcja „How it works” — arkusz z krokami

Trzy kroki narysowane jako wiersz arkusza kalkulacyjnego: pasek kolumn `A B C`,
numer wiersza z boku, komórki w środku, a pod spodem wiersz formuły z tym, co
się z arkuszem dzieje. Ten sam żart, który strona główna robi adresami komórek
przy krokach, rozwinięty do całego wiersza.

## Jedno źródło, dwa wyjścia

```
python3 landing/arkusz/arkusz.py       # składa moduł
cd landing/arkusz && node spr.mjs      # sprawdza go
```

* `ARKUSZ-en.html` — osobny moduł Kod do wklejenia gdziekolwiek na stronie;
* ta sama sekcja wewnątrz podstrony „what it does” — `landing/mozliwosci/strona.py`
  importuje stąd `sekcja()` i `STYL`.

Dwie kopie tego samego CSS w dwóch plikach to dwie okazje, żeby zmienić jedną
i zapomnieć o drugiej. Tu jest jedna.

`PODGLAD.html` to podgląd do otwarcia w przeglądarce: podrabia tło witryny
(`#232a29` i siatkę 88 × 44) i dopełnienia Divi. Do Divi idzie wyłącznie
`ARKUSZ-en.html`, w całości, w jeden moduł Kod.

## Arkusz zachowuje się jak arkusz

Najechanie na komórkę robi pięć rzeczy. Cztery pierwsze robi każdy arkusz
kalkulacyjny, kiedy zaznaczy się w nim komórkę:

1. zapala się **litera jej kolumny**;
2. zapala się **numer jej wiersza**;
3. jej **adres pojawia się w polu nazwy** w lewym górnym rogu. Do tej pory ten
   róg był pustym kwadratem — czyli jedynym elementem tej sekcji, który udawał
   arkusz, nie robiąc tego, co arkusz;
4. komórka dostaje **obrys zaznaczenia z uchwytem** w prawym dolnym rogu.
   Uchwyt wystaje poza komórkę, w jednopikselowy odstęp między komórkami,
   dokładnie tak jak w arkuszu.

Piąta jest jedyną, która naprawdę czegoś uczy: zapala się **ten etap w wierszu
formuły, który z tego kroku wynika**. Udostępnij arkusz → twój arkusz. Wklej
odnośnik → kopia w twojej bazie. Wstaw na stronę → twoja strona. Działa w obie
strony, więc mapowanie da się odkryć z dowolnej połowy sekcji.

Do tego `fx` zapala się, kiedy kursor jest gdziekolwiek w wierszu formuły.

## Jak to jest zrobione

Wszystko na `:has()` i na `:hover`, **bez ani jednej linijki JavaScriptu**.
Przeglądarka, która `:has()` nie zna, dostaje sekcję bez podświetleń i nie
traci nic: żadna informacja nie jest podana wyłącznie najechaniem. Adres
komórki i tak stoi w niej wydrukowany, a droga arkusza i tak jest wypisana
słowami.

Całość pod `@media ( hover: hover )`. Na dotyku nie ma najechania, a stan,
który się zapala i nie gaśnie, jest gorszy niż brak stanu.

Blok z podświetleniami stoi **za** utwardzeniem na wrogie motywy i tła ma
pisane twardo. Utwardzenie przybija tło paska liter, żeby nie domalował się
tam cudzy motyw — i przybijało przy okazji podświetlenie, więc litera zapalała
się samym kolorem pisma, bez tła pod nim. Przy dwóch regułach z `!important`
wygrywa ta stojąca dalej w arkuszu. Pilnuje tego `spr.mjs`, i to samo
sprawdzenie chodzi drugi raz wewnątrz podstrony, bo tam utwardzeń jest więcej.

Wąsko arkusz przestaje być wierszem i staje się kolumną: trzy komórki jedna
pod drugą, a pasek liter, numer wiersza i `fx` znikają razem z całą zabawą
w podświetlanie — bo wiersz z jedną komórką nie jest już wierszem, a na dotyku
i tak nie ma najechania.

## Czego pilnuje `spr.mjs`

Podświetleń nie da się sprawdzić czytaniem arkusza stylów, bo `:has()` jest
dokładnie tym rodzajem selektora, który da się napisać tak, że pasuje do
wszystkiego naraz. Więc kursor naprawdę najeżdża na każdą z trzech komórek
i na każdy z trzech etapów, a mierzone jest to, co się po tym zapaliło: czy
zapaliła się właściwa litera **i żadna inna**, czy zapalił się numer wiersza,
co pokazuje pole nazwy, czy obrys i uchwyt ma tylko ta jedna komórka i czy
zapalił się właściwy etap. Do tego stan spoczynku (nic nie świeci, pole nazwy
pokazuje `A1`), zgaszenie po zjechaniu kursorem, rozmiary pisma (tylko 14, 18
i 20), brak myślników, brak suwaka poziomego i układ na telefonie.
30 sprawdzeń.
