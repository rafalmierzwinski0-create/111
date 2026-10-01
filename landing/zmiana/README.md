# „Edit the sheet, watch the page” — próba

Propozycja na miejsce sekcji **What to look for** w `landing/mozliwosci`.
Na razie **tylko makieta**: `PROBA.html` otwiera się w przeglądarce, na tle
witryny i w jej krojach. Nic jeszcze nie jest wpięte w podstronę.

## Co pokazuje

Po lewej trzy wiersze arkusza, po prawej te same wiersze jako karty na stronie.
W pętli, co 9 sekund: kursor wchodzi w komórkę `Status`, `Open` zmienia się na
`Closed`, iskra przebiega łączem, a karta po prawej przemalowuje się na
czerwono i pigułka przeskakuje na CLOSED.

Kolory po prawej są **zmierzone na prawdziwej tabeli**, nie dobrane na oko:
karta `#16211f`, pomalowany wiersz `#5a2733`, a słupek na pomalowanym wierszu
robi się biały przy przezroczystości 0.28 — dokładnie tak, jak robi to wtyczka.

Przy `prefers-reduced-motion` nic się nie rusza, a obie strony stoją w stanie
końcowym (arkusz mówi `Closed`, karta jest czerwona), więc nic nie znika
i nic nie miga.

## Czego jeszcze nie ma

* nie jest rozbite na `modul.py` + `spr.mjs` jak reszta sekcji,
* znaczniki nie są przygotowane pod Divi (każdy znacznik w jednej linii),
* znaczki **Free / Pro** z wymienianej sekcji trzeba gdzieś przenieść.

Wszystko to po decyzji, czy w ogóle tędy iść.
