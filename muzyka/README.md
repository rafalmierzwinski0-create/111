# Pipeline muzyki klubowej / letniej

Opisujesz słowami, jaki utwór chcesz. Claude zamienia ten opis na plik JSON,
renderuje go i odsyła MP3. Potem iterujecie na podstawie Twojego odsłuchu.
Wszystko, łącznie z dźwiękami, powstaje w kodzie (`numpy`/`scipy`), bez
sampli i bez zewnętrznych serwisów.

```
opis słowami ──► utwory/<nazwa>.json ──► stworz.py ──► MP3 + WAV + MIDI (+ stemy)
      ▲                                                        │
      └──────────── „w 0:46 drop za słaby, melodia wyżej” ◄────┘
```

## Jak pracujemy razem

1. **Opisz klimat**, np. „wakacyjne, radosne, jak Kygo, ok. 110 BPM, flet w
   leadzie”. Możesz też podać tonację, akordy albo długość.
2. **Claude tworzy** `muzyka/utwory/<nazwa>.json`, renderuje utwór i wysyła
   Ci MP3 razem z rozpiską sekcji i czasów.
3. **Słuchasz i mówisz, co zmienić**, najlepiej z czasem: „intro krótsze”,
   „w 1:04 za pusto”, „bas bardziej skaczący”, „szybciej”, „smutniej”.
4. **Wybierasz melodię.** `--warianty 4` renderuje 4 wersje, które różnią się
   melodią i perkusją (różne `seed`). Wybierasz tę z najlepszym hookiem.
5. **Wynik końcowy.** `--stemy` zapisuje osobne ślady, a plik `.mid` ma
   wszystkie nuty. Oba można wczytać do FL Studio, Abletona albo Logica i
   podmienić brzmienia na lepsze wtyczki (np. darmowy Vital), dograć wokal
   i zmiksować.

## Uruchomienie

Wymagania: Python 3, `pip install -r muzyka/requirements.txt` oraz `ffmpeg`.

```bash
python3 muzyka/stworz.py muzyka/utwory/letni-zachod.json              # jeden render
python3 muzyka/stworz.py muzyka/utwory/letni-zachod.json --warianty 4 # 4 melodie do wyboru
python3 muzyka/stworz.py muzyka/utwory/letni-zachod.json --seed 9 --stemy
```

Wynik trafia do `muzyka/wyjscie/<nazwa>/` (katalog jest w `.gitignore`, bo
każdy render da się odtworzyć z pliku JSON i seeda):

| Plik | Co to jest |
| --- | --- |
| `<nazwa>.mp3` | gotowy miks do odsłuchu, 320 kb/s, ok. −10 LUFS |
| `<nazwa>.wav` | ten sam miks, 16 bit / 44,1 kHz |
| `<nazwa>.mid` | nuty: bas, akordy, pad, lead, arp oraz bębny (GM, kanał 10) |
| `opis.json` | tempo, akordy, sekcje z czasem startu, użyte brzmienia, głośność |
| `stemy/*.flac` | (z `--stemy`) kick, perkusja, bas, akordy, pad, lead, arp, fx, pogłos |

Render trwa ok. 15–40 s na utwór.

## Przykłady w `utwory/`

| Plik | Styl | Tempo, tonacja | Akordy |
| --- | --- | --- | --- |
| `letni-zachod.json` | tropical house: pluck, flet, sub-bas | 108, F-dur | Dm – Bb – F – C |
| `plaza-o-polnocy.json` | house: pianino, supersaw, bas offbeat, własna struktura | 124, A-moll | Am7 – F – C – G |
| `swit-nad-morzem.json` | deep house: organy, marimba, galopujący bas | 120, D-dorycka | Dm7 – G7 |

## Format pliku utworu

Wymagane jest tylko `tytul`. Wszystkie pozostałe pola mają wartości domyślne
wynikające ze stylu.

```json
{
  "tytul": "Letni zachód",
  "styl": "tropical",
  "bpm": 108,
  "tonacja": "F-dur",
  "akordy": ["vi", "IV", "I", "V"],
  "takty_na_akord": 1,
  "septymy": false,
  "seed": 7,
  "swing": 0.06,
  "brzmienia": {"akordy": "pluck", "lead": "flet", "bas": "sub", "pad": "pad", "arp": "marimba"},
  "rytmy": {"akordy": "tropical", "bas": "tropical"},
  "glosnosc_rms_db": -9,
  "struktura": [
    {"nazwa": "intro", "takty": 8, "elementy": ["kick", "hats", "akordy"], "filtr": [350, 5000]},
    {"nazwa": "drop", "takty": 16, "elementy": ["kick", "clap", "bas", "akordy", "lead", "crash"]}
  ]
}
```

- **styl**: `tropical`, `house` albo `deep`. Ustawia domyślne tempo, swing,
  brzmienia i rytmy.
- **tonacja**: `A-moll`, `Am`, `F-dur`, `F#m`, `Bb major`, `Es-dur`,
  `fis-moll`, `D-dorycka`, `G-miksolidyjska`.
- **akordy**: stopnie skali zapisane cyframi rzymskimi (`vi`, `IV`, `i7`,
  `VII`) albo liczbami 1–7. Jakość akordu (dur/moll) wynika z tonacji;
  dopisek `7` dodaje septymę. Przebieg akordów zaczyna się od nowa w każdej
  sekcji.
- **seed**: decyduje o melodii (motyw 2-taktowy, fraza A A A B kończona na
  prymie) i o wzorze perkusjonaliów. Ten sam seed zawsze daje ten sam utwór.
- **brzmienia**:
  - akordy, lead, pad, arp: `pluck`, `marimba`, `pianino`, `organy`,
    `supersaw`, `pad`, `flet`
  - bas: `sub`, `pila`
- **rytmy**:
  - akordy: `pad`, `offbeat`, `house`, `tropical`, `synkopa`
  - bas: `offbeat`, `rolling`, `tropical`, `galop`, `dlugi`
- **struktura**: lista sekcji. Każda ma `takty` i `elementy`, a opcjonalnie
  `filtr: [od_Hz, do_Hz]`, czyli przemiatanie dolnoprzepustowe partii
  melodycznych (otwieranie w buildzie, zamykanie w outro). Bez tego pola
  używana jest struktura intro – build – drop – break – build – drop – outro
  (72 takty).
- **elementy**: `kick`, `clap`, `hats`, `openhat`, `shaker`, `perc`, `bas`,
  `akordy`, `pad`, `lead`, `arp`, `roll` (werbel przyspieszający do dropu),
  `riser` (szum narastający do dropu i cisza na ostatnią ćwierćnutę), `crash`
  (talerz i uderzenie na starcie sekcji).

## Co dzieje się w środku (`silnik.py`)

1. **Kompozycja**: tonacja → akordy z płynnym prowadzeniem głosów → bas
   według wzoru → melodia (motyw dopasowywany do kolejnych akordów; mocne
   części taktu zawsze na dźwiękach akordu) → bębny i perkusjonalia ze
   swingiem.
2. **Synteza**: każdy instrument to syntezator w numpy (supersaw z polyBLEP,
   pluck z obwiednią filtra, flet z wibrato i oddechem, addytywne pianino,
   stopa z opadającą wysokością itd.).
3. **Miks**: filtry sekcji, sidechain od stopy (bas, akordy, pad, lead, arp
   „pompują”), pogłos splotowy, echo ping-pong na leadzie, cisza przed dropem.
4. **Master**: wzmocnienie do docelowego RMS, limiter z wyprzedzeniem (sufit
   −1 dBFS), pomiar LUFS przez ffmpeg.

## Ograniczenia

- Brzmienia są syntetyczne i proste. Rezultat to porządne demo lub szkic
  produkcji, a nie wydawalny master. Do publikacji wczytaj MIDI albo stemy do
  DAW i podmień brzmienia.
- Nie ma wokali. Pipeline robi podkład, topline i strukturę.
- Claude nie słyszy wyniku. Balans jest sprawdzany pomiarami (poziomy śladów,
  rozkład pasm, LUFS, szczyty), a o tym, co brzmi dobrze, decyduje Twój
  odsłuch.
