"""Teoria i zapis: nuty, akordy z rozszerzeniami, voicingi, zapis rytmów i melodii.

Zapisy używane w plikach utworów:

  nuta        C#5, Bb3, H4 (= B4), F5
  akord       Fmaj9, F#m11, C#7sus4, Bb6/9, Am9/G, E7, Dsus2, Cadd9
  progresja   "F#m9 | Dmaj9 | Bm11 | C#7sus4 C#m7"   – takty rozdzielone „|”,
              kilka akordów w takcie dzieli go po równo, albo z długością: "Bb6:12 C:4"
  rytm        "x--.x--.x-......"  – 16 znaków na takt (szesnastki):
              X x o g = uderzenie (akcent / zwykłe / ciche / ghost), - = przedłużenie, . = pauza
              w basie zamiast x można pisać: o (oktawa wyżej), 5 (kwinta), 3 (tercja), 7 (septyma)
  melodia     "r:4 C#5:4 E5:4 F#5:4 | G#5:10 r:2 F#5:2 E5:2"
              nuta:długość w szesnastkach, r = pauza, A4,C5:8 = dwudźwięk, ! = akcent, ? = cicho
"""

import re

NUTY = {"C": 0, "D": 2, "E": 4, "F": 5, "G": 7, "A": 9, "B": 11, "H": 11}
NAZWY = ["C", "C#", "D", "Eb", "E", "F", "F#", "G", "Ab", "A", "Bb", "B"]

# interwały od prymy (półtony)
JAKOSCI = {
    "": [0, 4, 7], "m": [0, 3, 7], "5": [0, 7], "dim": [0, 3, 6], "aug": [0, 4, 8],
    "sus2": [0, 2, 7], "sus4": [0, 5, 7], "sus": [0, 5, 7],
    "6": [0, 4, 7, 9], "m6": [0, 3, 7, 9], "6/9": [0, 4, 7, 9, 14], "m6/9": [0, 3, 7, 9, 14],
    "add9": [0, 4, 7, 14], "madd9": [0, 3, 7, 14],
    "7": [0, 4, 7, 10], "maj7": [0, 4, 7, 11], "m7": [0, 3, 7, 10], "mmaj7": [0, 3, 7, 11],
    "m7b5": [0, 3, 6, 10], "dim7": [0, 3, 6, 9], "7sus4": [0, 5, 7, 10], "7sus2": [0, 2, 7, 10],
    "9": [0, 4, 7, 10, 14], "maj9": [0, 4, 7, 11, 14], "m9": [0, 3, 7, 10, 14], "9sus4": [0, 5, 7, 10, 14],
    "11": [0, 7, 10, 14, 17], "m11": [0, 3, 7, 10, 14, 17], "maj7#11": [0, 4, 7, 11, 18],
    "13": [0, 4, 7, 10, 14, 21], "m13": [0, 3, 7, 10, 14, 21], "maj13": [0, 4, 7, 11, 14, 21],
    "6sus4": [0, 5, 7, 9],
}
ALIASY = {"min": "m", "-": "m", "M7": "maj7", "Maj7": "maj7", "min7": "m7", "-7": "m7", "M9": "maj9", "min9": "m9"}

WAGI = {"X": 1.0, "x": 0.85, "o": 0.62, "g": 0.38}


def nuta(tekst):
    m = re.fullmatch(r"([A-Ha-h])([#b]?)(-?\d)", tekst.strip())
    if not m:
        raise ValueError(f"Nie rozumiem nuty „{tekst}” (przykłady: C#5, Bb3, F4)")
    pc = NUTY[m[1].upper()] + {"#": 1, "b": -1, "": 0}[m[2]]
    return 12 * (int(m[3]) + 1) + pc


def nazwa_nuty(m):
    return f"{NAZWY[m % 12]}{m // 12 - 1}"


class Akord:
    def __init__(self, symbol):
        m = re.fullmatch(r"([A-H])([#b]?)([^/]*)(?:/([A-H][#b]?))?", symbol.strip())
        if not m:
            raise ValueError(f"Nie rozumiem akordu „{symbol}”")
        jakosc = ALIASY.get(m[3], m[3])
        if jakosc not in JAKOSCI:
            raise ValueError(f"Nieznany rodzaj akordu „{m[3]}” w „{symbol}”. Znane: {', '.join(k or 'dur' for k in JAKOSCI)}")
        self.symbol = symbol
        self.pryma = (NUTY[m[1]] + {"#": 1, "b": -1, "": 0}[m[2]]) % 12
        self.interwaly = JAKOSCI[jakosc]
        self.bas = self.pryma if not m[4] else (NUTY[m[4][0]] + {"#": 1, "b": -1, "": 0}[m[4][1:]]) % 12

    def klasy(self):
        return [(self.pryma + i) % 12 for i in self.interwaly]

    def ton(self, rodzaj):
        """Ton akordu do linii basu: x = bas, 3 = tercja, 5 = kwinta, 7 = septyma."""
        if rodzaj == "x":
            return self.bas
        szukane = {"3": (3, 4, 5, 2), "5": (7, 6, 8), "7": (10, 11, 9)}[rodzaj]
        for s in szukane:
            if s in self.interwaly:
                return (self.pryma + s) % 12
        return (self.pryma + {"3": 4, "5": 7, "7": 10}[rodzaj]) % 12


def progresja(tekst):
    """'Am9 | Dm9 | G6:12 G:4' -> lista (krok, długość, Akord), długość w krokach."""
    wynik, krok = [], 0
    for takt in [t for t in tekst.split("|") if t.strip()]:
        tokeny = takt.split()
        z_dl = [t for t in tokeny if ":" in t]
        reszta = 16 - sum(float(t.split(":")[1]) for t in z_dl)
        bez = len(tokeny) - len(z_dl)
        for t in tokeny:
            sym, _, dl = t.partition(":")
            dl = float(dl) if dl else reszta / bez
            wynik.append((krok, dl, Akord(sym)))
            krok += dl
        if abs(krok % 16) > 1e-6:
            raise ValueError(f"Takt „{takt.strip()}” nie sumuje się do 16 szesnastek")
    return wynik, int(round(krok))


def ulozenie(ak, poprzednie, lo, hi, bez_prymy=True, srodek=None):
    """Voicing akordu: bez prymy (gra ją bas), max 4 głosy, płynne prowadzenie głosów."""
    klasy = ak.klasy()
    if bez_prymy and len(klasy) >= 4:
        # prymę (albo dźwięk basu w akordzie ze slashem) gra bas – nie dublujemy jej
        klasy = [k for k in klasy if k != ak.bas] if ak.bas in klasy else klasy[1:]
    if len(klasy) > 4:  # 11 / 13: wyrzuć kwintę
        piata = (ak.pryma + 7) % 12
        klasy = [k for k in klasy if k != piata][:4]
    kandydaci = []
    for rot in range(len(klasy)):
        kolej = klasy[rot:] + klasy[:rot]
        for okt in range(2, 8):
            nuty, ost = [], None
            for p in kolej:
                m = 12 * okt + p if ost is None else ost + ((p - ost) % 12 or 12)
                nuty.append(m)
                ost = m
            warianty = [nuty]
            if len(nuty) >= 4:  # drop-2: szerszy, „droższy” voicing
                d2 = sorted(nuty[:-2] + [nuty[-2] - 12] + nuty[-1:])
                warianty.append(d2)
            for w in warianty:
                if lo <= w[0] and w[-1] <= hi:
                    kandydaci.append(w)
    if not kandydaci:
        raise ValueError(f"Akord {ak.symbol} nie mieści się w rejestrze {lo}–{hi}")
    if not poprzednie:
        cel = srodek if srodek is not None else (lo + hi) / 2
        return min(kandydaci, key=lambda n: abs(sum(n) / len(n) - cel))
    def koszt(n):
        ruch = sum(min(abs(a - b) for b in poprzednie) for a in n)
        return ruch + 0.5 * abs(n[-1] - poprzednie[-1])
    return min(kandydaci, key=koszt)


def rytm(tekst):
    """'x--.o-..' -> (lista [krok, długość, znak], długość wzoru w krokach)."""
    s = re.sub(r"[\s|]", "", tekst)
    trafienia, aktywne = [], None
    for i, ch in enumerate(s):
        if ch == "-":
            if aktywne is not None:
                aktywne[1] += 1
        elif ch == ".":
            aktywne = None
        else:
            aktywne = [i, 1, ch]
            trafienia.append(aktywne)
    return trafienia, len(s)


def melodia(tekst):
    """Zapis melodii -> (lista (krok, długość, [midi], głośność), długość w krokach)."""
    if isinstance(tekst, list):
        tekst = " | ".join(tekst)
    zdarzenia, krok = [], 0.0
    takty = tekst.split("|")
    for nr, takt in enumerate(takty, 1):
        poczatek = krok
        for tok in takt.split():
            glos = 0.85
            if tok.endswith("!"):
                glos, tok = 1.0, tok[:-1]
            elif tok.endswith("?"):
                glos, tok = 0.6, tok[:-1]
            co, _, dl = tok.partition(":")
            if not dl:
                raise ValueError(f"Brak długości w „{tok}” (np. C5:4)")
            dl = float(dl)
            if co.lower() != "r":
                zdarzenia.append((krok, dl, [nuta(n) for n in co.split(",")], glos))
            krok += dl
        if len(takty) > 1 and abs(krok - poczatek - 16) > 1e-6:
            raise ValueError(f"Takt {nr} melodii ma {krok - poczatek:g} szesnastek zamiast 16: „{takt.strip()}”")
    return zdarzenia, int(-(-krok // 16) * 16)
