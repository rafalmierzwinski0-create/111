# Którędy idą dane

Cztery przystanki w jednym rzędzie: arkusz, pobranie w tle, kopia w bazie,
strona odwiedzającego. Między nimi kreska z grotem, a po kreskach biegnie
rozjaśnienie, jedno za drugim, od arkusza do strony.

Sekcja mówi jedną rzecz, której nie widać na żadnym zrzucie: **między Google
a czytelnikiem stoi kopia**. Stąd bierze się wszystko inne — że strona nie
czeka na Google, że działa, kiedy Google nie działa, i że wyszukiwanie po
tabeli jest natychmiastowe.

## Co wkleić

Całą zawartość `DROGA-en.html` (albo `DROGA-pl.html`) w jeden moduł Kod
w Divi. Nic nie trzeba podmieniać: sekcja nie ma ani jednego obrazka, ikony
są narysowane w kodzie.

`proba.html` to podgląd do otwarcia w przeglądarce — podrabia tło witryny
i dopełnienia Divi.

## Jak zbudować od nowa

```
python3 landing/droga/modul.py     # składa obie wersje
node landing/droga/spr.mjs         # sprawdza je
```

Słowa siedzą w `EN` i `PL` na górze `modul.py`, układ jest jeden. Dwa pliki
z tym samym układem rozjeżdżają się pierwszego dnia, w którym ktoś poprawi
jeden z nich, więc buduje je ten sam kod, a `spr.mjs` porównuje, czy dalej
mają tyle samo przystanków.

## Dlaczego tak wygląda

* **Rozjaśnienie biegnące po kresce jest treścią, a nie ozdobą.** Cała sekcja
  mówi o tym, że dane JADĄ, i to jedyne miejsce, w którym widać kierunek.
  Zrobione tłem, a nie kropką: kropka musiałaby znać długość kreski, a ta
  zmienia się z szerokością okna. Tło szerokie na 40 procent, przesuwane od
  minus 40 do 140, przejeżdża każdą kreskę w całości, jakakolwiek by była.
* **Każda kreska startuje później od poprzedniej**, więc rozjaśnienia idą
  jedno za drugim, od arkusza do strony, a nie wszystkie naraz.
* **Grot na końcu każdej kreski.** Kreska mówi „połączone", grot mówi „w tę
  stronę", a tu chodzi wyłącznie o tę drugą rzecz.
* **Podpisy pisane monospace**, jak wszystko na tej witrynie, co udaje arkusz.
* **Wąsko przystanki stają jeden pod drugim**, a kreska kładzie się w pionie
  i dalej biegnie w dół: droga jest ta sama, tylko obrócona.

`prefers-reduced-motion` i `@media print` zatrzymują rozjaśnienie, nie chowając
przy tym ani kreski, ani niczego innego.
