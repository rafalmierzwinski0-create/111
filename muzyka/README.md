# Pipeline muzyki klubowej / letniej

Opisujesz słowami, co chcesz usłyszeć. Claude komponuje utwór jako plik JSON:
akordy, melodie, linię basu, groove, aranżację i automatykę. Renderuje go i
odsyła MP3, a potem iterujecie na podstawie Twojego odsłuchu.

```
opis / referencje ──► Claude komponuje utwory/<nazwa>.json ──► stworz.py ──► MP3 + WAV + MIDI (+ stemy)
        ▲                                                                         │
        └────────────── „w 1:20 za pusto, hook wyżej, bas bardziej skaczący” ◄────┘
```

## Jak pracujemy razem

1. **Opisz klimat i podaj referencje**, np. „jak RÜFÜS DU SOL, melancholijnie,
   na zachód słońca” albo „jak Calvin Harris, lato, radio”.
2. **Claude komponuje**: dobiera progresję (akordy rozszerzone, voicingi z
   płynnym prowadzeniem głosów), pisze hook i melodie nuta po nucie, układa
   bas, groove perkusji, aranżację z buildami i dropami oraz automatykę
   filtrów. Potem renderuje utwór i sprawdza miks pomiarami.
3. **Słuchasz i mówisz, co zmienić**, najlepiej z czasem: „w 1:04 za pusto”,
   „hook za wysoko”, „mniej pogłosu”, „bas bardziej funkowy”, „szybciej”.
4. **Wersja końcowa**: `--stemy` zapisuje każdy instrument osobno, a `.mid`
   zawiera wszystkie nuty. Oba można wczytać do FL Studio, Abletona albo
   Logica, podmienić brzmienia na swoje wtyczki i dograć wokal.

## Uruchomienie

```bash
pip install -r muzyka/requirements.txt              # numpy, scipy, mido, dawdreamer, pyloudnorm
python3 muzyka/stworz.py muzyka/utwory/glebia.json  # cały utwór
python3 muzyka/stworz.py muzyka/utwory/glebia.json --od 32 --takty 16   # szybki podgląd fragmentu
python3 muzyka/stworz.py muzyka/utwory/glebia.json --stemy              # + ślady do DAW
```

Wymagane są też `ffmpeg` i `npm`. Przy pierwszym uruchomieniu skrypt pobiera
bibliotekę sampli (ok. 80 MB) przez `npm pack` do `muzyka/sample/`.

Wynik trafia do `muzyka/wyjscie/<nazwa>/`:

| Plik | Co to jest |
| --- | --- |
| `<nazwa>.mp3` / `.wav` | gotowy miks, zmasterowany do docelowej głośności (LUFS) |
| `<nazwa>.mid` | wszystkie nuty: każdy instrument osobno, bębny na kanale 10 |
| `opis.json` | sekcje z czasami startu, progresje, instrumenty, LUFS i analiza miksu |
| `stemy/*.flac` | (z `--stemy`) każdy instrument osobno, 24 bit, plus pogłos i echo |

## Utwory w `utwory/`

| Plik | Inspiracja | Tempo, tonacja | Harmonia |
| --- | --- | --- | --- |
| `glebia.json` | RÜFÜS DU SOL: pad Juno, bas Moog w szesnastkach, hook w rytmie tresillo, chór „ooh”, długi pogłos | 120, F#-moll | F#m9 – Dmaj9 – Bm11 – C#7sus4 – C#m7 |
| `slonce.json` | Calvin Harris: stabsy fortepianu z lat 90., funkowy bas oktawowy, hook supersaw | 124, A-moll | Am9 – Dm9 – Gsus4 G – Cmaj9 – Fmaj9 – Dm9 – Esus4 E – E7 |
| `wiadomosc.json` | Fred again..: gospelowy fortepian, garage 2-step → euforyczne 4×4, pocięty wokal, breakbeat amen, winyl | 132, Eb-dur | Abmaj9 – Bb6 – Gm11 – Cm9 – Abmaj9 – Bb/D – Ebmaj9/G – Cm9 Bb |

## Format utworu

```json
{
  "tytul": "Głębia",
  "bpm": 120,
  "swing": 0.03,
  "zestaw": "deep",
  "poglos": {"czas": 4.2, "predelay": 35, "ton": 5500},
  "master": {"lufs": -9.5},
  "instrumenty": {
    "pad": {"barwa": "juno_pad"},
    "bas": {"barwa": "moog_bas", "rejestr": [30, 45]},
    "hook": {"barwa": "pluck", "glosnosc": -8, "delay": 0.3}
  },
  "progresje": {"A": "F#m9 | F#m9 | Dmaj9 | Dmaj9 | Bm11 | Bm11 | C#7sus4 | C#m7"},
  "wzory": {
    "pad_A": {"typ": "akordy", "instrument": "pad", "progresja": "A"},
    "bas_A": {"typ": "bas", "instrument": "bas", "progresja": "A", "rytm": ".xxo.xxx.xxo.xox"},
    "hook_A": {"typ": "melodia", "instrument": "hook", "nuty": ["C#6:3 B5:3 A5:2 G#5:3 A5:3 E5:2", "..."]},
    "beat": {"typ": "bebny", "kick": "x...x...x...x...", "clap": "....x.......x...", "ohat": "..x...x...x...x."}
  },
  "aranzacja": [
    {"sekcja": "intro", "takty": 8, "graja": ["pad_A"], "filtr": {"*": [300, 1800]}},
    {"sekcja": "build", "takty": 8, "graja": ["pad_A", "bas_A"], "fx": ["riser", "odwrocony", "cisza"]},
    {"sekcja": "drop", "takty": 16, "graja": ["pad_A", "bas_A", "hook_A", "beat"], "fx": ["crash", "uderzenie"]}
  ]
}
```

### Zapis muzyczny

- **Akordy**: `Fmaj9`, `F#m11`, `C#7sus4`, `Bb6/9`, `Ebmaj9/G` (z basem),
  `Esus4`, `Cadd9`, `G13`, `Am7b5` itd. Takty oddziela się znakiem `|`. Kilka
  akordów w takcie dzieli go po równo, a `Gsus4:8 G:8` podaje długość w
  szesnastkach.
- **Rytm**: 16 znaków na takt. `X`/`x`/`o`/`g` to uderzenie (akcent / zwykłe
  / ciche / ghost), `-` przedłuża nutę, `.` to pauza. W basie zamiast `x`
  można użyć `o` (oktawa wyżej), `5` (kwinta), `3` (tercja), `7` (septyma).
- **Melodia**: `"r:4 C#5:4 E5:4 F#5:4"`, czyli nuta i długość w szesnastkach,
  `r` to pauza, `A4,C5:8` to dwudźwięk, `!` akcent, `?` ciszej. Lista
  napisów oznacza kolejne takty, a każdy jest sprawdzany, czy ma dokładnie
  16 szesnastek.

### Wzory (`typ`)

| Typ | Co robi |
| --- | --- |
| `akordy` | voicing bez prymy (gra ją bas), max 4 głosy, płynne prowadzenie głosów; `rytm` (bez niego akord trwa całą długość), `lewa_reka`: `true` albo `"oktawa"`, `bez_prymy`, `oktawa` |
| `arp` | arpeggio z dźwięków akordu: `rytm`, `kolejnosc` (`gora` / `dol` / `gora_dol`), `oktawy` |
| `bas` | linia basu z progresji według `rytm` albo wprost z `nuty` |
| `melodia` | nuty wprost (`nuty`), opcjonalnie `transpozycja` i `legato` |
| `bebny` | `instrument: rytm` dla dowolnych bębnów z zestawu |
| `petla` | pętla dociągnięta do tempa, np. break amen (`"barwa": "petla:sp/loop_amen"`) |
| `tekstura` | zapętlone tło, np. winyl (`"barwa": "tekstura:sp/vinyl_hiss"`) |
| `roll` | werbel przyspieszający przez całą sekcję (ćwierćnuty → trzydziestodwójki) |

### Sekcje aranżacji

- `graja`: lista wzorów. Krótszy wzór zapętla się do długości sekcji.
- `filtr` / `hp`: `{instrument | "*" | "bebny": [od_Hz, do_Hz]}`, czyli
  przemiatanie dolno- lub górnoprzepustowe przez sekcję. `*` obejmuje
  wszystkie instrumenty poza bębnami.
- `glosnosc`: `{instrument | "*": [od_dB, do_dB]}` (wejścia i wyciszenia).
- `fx`: `riser` (narastający szum), `odwrocony` (odwrócony talerz do końca
  sekcji), `cisza` (ostatnia ćwierćnuta przed dropem), `crash` i `uderzenie`
  (na początku sekcji).

### Brzmienia (`barwa`)

| Grupa | Brzmienia |
| --- | --- |
| klawisze | `fortepian` (sample Salamander Grand, 4 warstwy dynamiki), `rhodes` (FM + tremolo), `organy` (house M1) |
| pady i smyczki | `juno_pad` (piły + PWM, filtr Moog, chorus), `smyczki` |
| leady | `supersaw`, `pluck`, `dzwonek` (FM) |
| bas | `moog_bas`, `sub`, `reese` |
| wokale | `vox_a`, `vox_o`, `vox_u` (sopran: „aa” / „oo” / „uu”), `vox_alt` (model formantów) |
| sample | `sample:<id>`, `petla:<id>`, `tekstura:<id>`, gdzie `<id>` to np. `909/hhcd2`, `808/bd2550`, `sp/perc_snap`, `sp/loop_amen` |

Zestawy perkusyjne (`zestaw`): `909`, `deep`, `garage`. Zawierają `kick`,
`clap`, `snare`, `rim`, `hat`, `ohat`, `ride`, `crash`, `shaker`, `tamb`,
`snap`.

Ustawienia miksu instrumentu: `glosnosc` (dB), `pan` (−1..1), `szerokosc`
(0 = mono, >1 = szerzej), `hp` / `lp` (Hz), `poglos` i `delay` (wysyłka 0..1),
`sidechain` (0..1, pompowanie od stopy), `chorus`, `przester`, `humanizacja`
(ms), `rejestr` ([najniższa, najwyższa] nuta MIDI).

## Co dzieje się w środku (`silnik/`)

| Moduł | Rola |
| --- | --- |
| `teoria.py` | nuty, ponad 30 rodzajów akordów, voicingi (przewroty, drop-2), parser rytmów i melodii |
| `aranzacja.py` | z pliku utworu robi zdarzenia: voicingi, arpeggia, bas, melodie, groove'y, swing, humanizację |
| `brzmienia.py` | syntezatory w Fauście (filtry Moog, model formantów głosu) uruchamiane przez DawDreamer, sampler fortepianu, one-shoty, pętle |
| `miks.py` | wyrównanie poziomów, EQ, chorus, przester, automatyka sekcji, sidechain, pogłos zita-rev1, echo ping-pong, kompresor szyny, limiter, docelowe LUFS |
| `sample.py` | pobieranie i indeks biblioteki sampli |
| `eksport.py` | WAV, MP3, MIDI, FLAC, opis |

## Ograniczenia

- **Wokale** pochodzą z modelu formantów („aa/oo”, wokalne chopy). To nie
  jest śpiewany tekst. Najwięcej realizmu da prawdziwy głos: nagranie z
  telefonu można pociąć i wpleść tak, jak robi to Fred again.. (to następny
  krok rozbudowy).
- **Brzmienia** są dobre jak na syntezę w kodzie, ale nie zastąpią Serum czy
  Diva. Do wydania warto wczytać MIDI lub stemy do DAW.
- **Claude nie słyszy wyniku.** Miks jest sprawdzany pomiarami (`analiza` w
  `opis.json`), a ostatnie słowo należy do Twojego odsłuchu.

## Licencje sampli

Sonic Pi: CC0. Salamander Grand Piano: CC BY 3.0 (Alexander Holm). TR-808 i
TR-909: nagrania Michaela Fischera i z machines.hyperreal.org, bez ograniczeń
licencyjnych. Sample nie są trzymane w repozytorium, tylko pobierane z npm.
