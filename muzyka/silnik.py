"""Silnik pipeline'u: z opisu utworu (JSON) robi nuty, MIDI, ślady i gotowy miks.

Kolejność pracy:
  1. kompozycja  – tonacja, akordy, linia basu, melodia, bębny -> zdarzenia (nuty)
  2. synteza     – każde zdarzenie zamieniane na dźwięk (syntezatory w numpy)
  3. miks        – filtry sekcji, sidechain, pogłos, delay, cisza przed dropem
  4. master      – limiter, głośność, eksport WAV / MP3 / MIDI / stemy
"""

import math
import re
import subprocess
import unicodedata
import zlib

import numpy as np
from scipy import signal
from scipy.io import wavfile
from scipy.ndimage import minimum_filter1d, uniform_filter1d

SR = 44100

# --------------------------------------------------------------------------
# Teoria muzyki
# --------------------------------------------------------------------------

NUTY = {"C": 0, "D": 2, "E": 4, "F": 5, "G": 7, "A": 9, "B": 11, "H": 11}
NAZWY = ["C", "C#", "D", "Eb", "E", "F", "F#", "G", "Ab", "A", "Bb", "B"]
SKALE = {
    "dur": [0, 2, 4, 5, 7, 9, 11],
    "moll": [0, 2, 3, 5, 7, 8, 10],
    "dorycka": [0, 2, 3, 5, 7, 9, 10],
    "miksolidyjska": [0, 2, 4, 5, 7, 9, 10],
}
TRYBY = {
    "": "dur", "dur": "dur", "major": "dur", "maj": "dur",
    "m": "moll", "moll": "moll", "mol": "moll", "minor": "moll", "min": "moll",
    "dorycka": "dorycka", "dorian": "dorycka",
    "miksolidyjska": "miksolidyjska", "mixolydian": "miksolidyjska",
}
RZYMSKIE = {"I": 1, "II": 2, "III": 3, "IV": 4, "V": 5, "VI": 6, "VII": 7}


def parsuj_tonacje(tekst):
    """'A-moll', 'Am', 'F-dur', 'F#m', 'Bb major', 'Es-dur', 'fis-moll' -> (pc, skala)."""
    s = tekst.strip()
    pc = NUTY[s[0].upper()]
    reszta = s[1:]
    if reszta.startswith("#"):
        pc, reszta = pc + 1, reszta[1:]
    elif reszta.lower().startswith("is"):
        pc, reszta = pc + 1, reszta[2:]
    elif reszta.lower().startswith("es"):
        pc, reszta = pc - 1, reszta[2:]
    elif reszta.startswith("b") or reszta.lower().startswith("s"):
        pc, reszta = pc - 1, reszta[1:]
    tryb = TRYBY[reszta.strip(" -_").lower()]
    return pc % 12, tryb


def stopien(akord):
    """'vi', 'IV7', 6 -> (stopień 1..7, czy septyma)."""
    if isinstance(akord, int):
        return akord, False
    rdzen = "".join(ch for ch in akord.upper() if ch in "IV")
    return RZYMSKIE[rdzen], "7" in akord


def midi_hz(m):
    return 440.0 * 2 ** ((m - 69) / 12)


class Tonacja:
    def __init__(self, tekst):
        self.pc, self.tryb = parsuj_tonacje(tekst)
        self.skala = SKALE[self.tryb]
        baza = 60 + self.pc
        self.baza = baza - 12 if baza > 66 else baza  # tonika w okolicy C4..F#4

    def k2midi(self, k):
        """Indeks w skali (0 = tonika, 7 = oktawa wyżej) -> numer MIDI."""
        return self.baza + self.skala[k % 7] + 12 * (k // 7)

    def dzwieki_akordu(self, st, septyma):
        idx = [st - 1, st + 1, st + 3] + ([st + 5] if septyma else [])
        return [(self.pc + self.skala[i % 7] + 12 * (i // 7)) % 12 for i in idx]

    def indeksy_akordu(self, st):
        return {(st - 1) % 7, (st + 1) % 7, (st + 3) % 7}

    def nazwa_akordu(self, st, septyma):
        pcs = self.dzwieki_akordu(st, septyma)
        r = pcs[0]
        tercja, kwinta = (pcs[1] - r) % 12, (pcs[2] - r) % 12
        jakosc = {(4, 7): "", (3, 7): "m", (3, 6): "dim", (4, 8): "aug"}.get((tercja, kwinta), "?")
        if septyma:
            sept = (pcs[3] - r) % 12
            jakosc = {("", 11): "maj7", ("", 10): "7", ("m", 10): "m7", ("dim", 10): "m7b5"}.get((jakosc, sept), jakosc + "7")
        return NAZWY[r] + jakosc


def ulozenie(pcs, poprzednie, lo=52, hi=77):
    """Wybiera przewrót akordu najbliższy poprzedniemu (płynne prowadzenie głosów)."""
    kandydaci = []
    for rot in range(len(pcs)):
        kolejne = pcs[rot:] + pcs[:rot]
        for okt in range(3, 7):
            nuty, ost = [], None
            for p in kolejne:
                m = 12 * okt + p if ost is None else ost + ((p - ost) % 12 or 12)
                nuty.append(m)
                ost = m
            if lo <= nuty[0] and nuty[-1] <= hi:
                kandydaci.append(nuty)
    if not poprzednie:
        return min(kandydaci, key=lambda n: abs(sum(n) / len(n) - 65))
    return min(kandydaci, key=lambda n: sum(min(abs(a - b) for b in poprzednie) for a in n))


# --------------------------------------------------------------------------
# Style i wzory rytmiczne (kroki = szesnastki, 16 na takt)
# --------------------------------------------------------------------------

STYLE = {
    "tropical": {
        "bpm": 108, "swing": 0.06,
        "brzmienia": {"akordy": "pluck", "lead": "flet", "bas": "sub", "pad": "pad", "arp": "marimba"},
        "rytmy": {"akordy": "tropical", "bas": "tropical"},
    },
    "house": {
        "bpm": 124, "swing": 0.1,
        "brzmienia": {"akordy": "pianino", "lead": "supersaw", "bas": "pila", "pad": "pad", "arp": "pluck"},
        "rytmy": {"akordy": "house", "bas": "offbeat"},
    },
    "deep": {
        "bpm": 120, "swing": 0.12,
        "brzmienia": {"akordy": "organy", "lead": "marimba", "bas": "sub", "pad": "pad", "arp": "pluck"},
        "rytmy": {"akordy": "synkopa", "bas": "galop"},
    },
}

WZORY_AKORDOW = {
    "pad": [(0, 16)],
    "offbeat": [(2, 1.5), (6, 1.5), (10, 1.5), (14, 1.5)],
    "house": [(0, 3), (3, 3), (6, 2), (10, 2), (12, 3)],
    "tropical": [(0, 2), (3, 2), (6, 2), (8, 1), (10, 2), (12, 2), (14, 1)],
    "synkopa": [(3, 2), (6, 2), (11, 2), (14, 1.5)],
}

# (krok, długość, interwał w półtonach od prymy)
WZORY_BASU = {
    "offbeat": [(2, 1.5, 0), (6, 1.5, 0), (10, 1.5, 0), (14, 1.5, 0)],
    "rolling": [(s, 0.9, 0) for s in range(16) if s % 4 != 0],
    "tropical": [(0, 2.5, 0), (3, 2, 0), (6, 1, 12), (8, 2.5, 0), (11, 2, 7), (14, 1.5, 12)],
    "galop": [(2, 1, 0), (3, 1, 0), (6, 1, 0), (7, 1, 12), (10, 1, 0), (11, 1, 0), (14, 1, 0), (15, 1, 7)],
    "dlugi": [(0, 15, 0)],
}

RYTMY_MELODII = [
    [0, 3, 6, 8, 10, 16, 19, 22, 24],
    [0, 2, 3, 6, 8, 11, 14, 16, 18, 19, 22],
    [2, 4, 6, 10, 12, 18, 20, 22, 26],
    [0, 3, 6, 10, 12, 14, 16, 19, 22],
    [0, 6, 8, 12, 14, 16, 22, 24, 26, 28],
    [3, 6, 10, 14, 16, 19, 22, 24, 28],
]

WZORY_PERKUSJI = [
    [(3, "wys"), (6, "nis"), (10, "wys"), (11, "wys"), (14, "nis")],
    [(2, "wys"), (5, "nis"), (7, "wys"), (10, "nis"), (13, "wys"), (15, "wys")],
    [(3, "wys"), (7, "nis"), (9, "wys"), (12, "nis"), (14, "wys")],
]

DOMYSLNA_STRUKTURA = [
    {"nazwa": "intro", "takty": 8, "elementy": ["kick", "hats", "shaker", "perc", "akordy"], "filtr": [350, 5000]},
    {"nazwa": "build", "takty": 8, "elementy": ["akordy", "pad", "arp", "shaker", "roll", "riser"], "filtr": [900, 16000]},
    {"nazwa": "drop", "takty": 16, "elementy": ["kick", "clap", "hats", "openhat", "shaker", "perc", "bas", "akordy", "lead", "crash"]},
    {"nazwa": "break", "takty": 8, "elementy": ["pad", "akordy", "lead"], "filtr": [2500, 2500]},
    {"nazwa": "build", "takty": 8, "elementy": ["akordy", "pad", "arp", "lead", "shaker", "roll", "riser"], "filtr": [900, 16000]},
    {"nazwa": "drop", "takty": 16, "elementy": ["kick", "clap", "hats", "openhat", "shaker", "perc", "bas", "akordy", "lead", "arp", "crash"]},
    {"nazwa": "outro", "takty": 8, "elementy": ["kick", "hats", "shaker", "perc", "akordy"], "filtr": [16000, 400]},
]

GM_PERKUSJA = {"kick": 36, "clap": 39, "hats": 42, "openhat": 46, "shaker": 70,
               "perc_wys": 62, "perc_nis": 64, "roll": 38, "crash": 49}


# --------------------------------------------------------------------------
# Kompozycja
# --------------------------------------------------------------------------

class Utwor:
    def __init__(self, spec, seed=None):
        styl = STYLE[spec.get("styl", "house")]
        self.spec = spec
        self.tytul = spec.get("tytul", "Bez tytułu")
        self.bpm = float(spec.get("bpm", styl["bpm"]))
        self.swing = float(spec.get("swing", styl["swing"]))
        self.ton = Tonacja(spec.get("tonacja", "A-moll"))
        self.brzmienia = {**styl["brzmienia"], **spec.get("brzmienia", {})}
        self.rytmy = {**styl["rytmy"], **spec.get("rytmy", {})}
        self.septymy = bool(spec.get("septymy", False))
        self.takty_na_akord = int(spec.get("takty_na_akord", 1))
        self.akordy = [stopien(a) for a in spec.get("akordy", ["i", "VI", "III", "VII"])]
        self.struktura = spec.get("struktura", DOMYSLNA_STRUKTURA)
        self.seed = int(spec.get("seed", 1) if seed is None else seed)
        self.rng = np.random.default_rng(self.seed)
        self.sps = SR * 60 / self.bpm / 4  # próbek na szesnastkę
        self._przygotuj_motyw()

    # ---- melodia: motyw 2-taktowy powtarzany na kolejnych akordach ----
    def _kontur(self, n):
        return list(self.rng.choice([-3, -2, -1, 0, 1, 2, 3], size=n,
                                    p=[.06, .17, .24, .1, .24, .13, .06]))

    def _przygotuj_motyw(self):
        wybor = self.rng.permutation(len(RYTMY_MELODII))
        self.rytm_a, self.rytm_b = RYTMY_MELODII[wybor[0]], RYTMY_MELODII[wybor[1]]
        self.kontur_a, self.kontur_b = self._kontur(len(self.rytm_a)), self._kontur(len(self.rytm_b))
        self.perkusja = WZORY_PERKUSJI[int(self.rng.integers(len(WZORY_PERKUSJI)))]

    def akord_taktu(self, takt_w_sekcji):
        return self.akordy[(takt_w_sekcji // self.takty_na_akord) % len(self.akordy)]

    def _przyciagnij(self, k, dozwolone, preferuj=None):
        kandydaci = [j for j in range(k - 4, k + 5) if j % 7 in dozwolone]
        if preferuj is not None:
            kandydaci = [j for j in kandydaci if j % 7 == preferuj] or kandydaci
        return min(kandydaci, key=lambda j: (abs(j - k), j))

    def _w_zakresie(self, k, lo=65, hi=86):
        while self.ton.k2midi(k) > hi:
            k -= 7
        while self.ton.k2midi(k) < lo:
            k += 7
        return k

    def melodia(self, takty):
        """Fraza 8-taktowa A A A B (B kończy się na prymie akordu)."""
        zdarzenia = []
        kotwica = self._w_zakresie(self._przyciagnij(10, {0, 2, 4}))
        for czesc in range(0, takty, 2):
            ostatnia_we_frazie = (czesc % 8) == 6
            rytm, kontur = (self.rytm_b, self.kontur_b) if ostatnia_we_frazie else (self.rytm_a, self.kontur_a)
            k = kotwica
            for i, krok in enumerate(rytm):
                takt = czesc + krok // 16
                if takt >= takty:
                    break
                st, _ = self.akord_taktu(takt)
                nast = rytm[i + 1] if i + 1 < len(rytm) else 32
                dl = min(nast - krok, 4)
                k = k if i == 0 else k + kontur[i]
                ostatnia = i == len(rytm) - 1
                if ostatnia and ostatnia_we_frazie:
                    k = self._przyciagnij(k, self.ton.indeksy_akordu(st), preferuj=(st - 1) % 7)
                    dl = min(32 - krok, 8)
                elif krok % 8 == 0 or dl >= 3 or i == 0:
                    k = self._przyciagnij(k, self.ton.indeksy_akordu(st))
                k = self._w_zakresie(k)
                akcent = 1.0 if krok % 4 == 0 else 0.85
                zdarzenia.append((czesc * 16 + krok, dl - 0.15, self.ton.k2midi(k), akcent))
        return zdarzenia

    # ---- składanie całego utworu ----
    def zdarzenia(self):
        """Zwraca słownik: ślad -> lista (krok, długość, midi/dźwięk, głośność) w krokach globalnych."""
        sl = {n: [] for n in ["kick", "clap", "hats", "openhat", "shaker", "perc", "roll",
                              "crash", "riser", "bas", "akordy", "pad", "lead", "arp"]}
        self.sekcje = []
        start = 0
        poprzednie = None
        for sek in self.struktura:
            takty, el = int(sek["takty"]), set(sek["elementy"])
            self.sekcje.append({**sek, "start": start, "kroki": takty * 16})
            for t in range(takty):
                baza = start + t * 16
                st, sept = self.akord_taktu(t)
                pcs = self.ton.dzwieki_akordu(st, sept or self.septymy)
                ul = ulozenie(pcs, poprzednie)
                poprzednie = ul
                bas_midi = 29 + (pcs[0] - 29) % 12
                h = lambda: float(self.rng.uniform(0.9, 1.0))
                if "kick" in el:
                    sl["kick"] += [(baza + s, 1, "kick", 1.0) for s in (0, 4, 8, 12)]
                if "clap" in el:
                    sl["clap"] += [(baza + s, 1, "clap", 0.95 * h()) for s in (4, 12)]
                if "hats" in el:
                    for s in range(16):
                        if "openhat" in el and s % 4 == 2:
                            continue
                        v = 0.8 if s % 4 == 2 else (0.55 if s % 2 == 0 else 0.4)
                        sl["hats"].append((baza + s, 1, "hats", v * h()))
                if "openhat" in el:
                    sl["openhat"] += [(baza + s, 2, "openhat", 0.8 * h()) for s in (2, 6, 10, 14)]
                if "shaker" in el:
                    sl["shaker"] += [(baza + s, 1, "shaker", (0.9 if s % 4 == 2 else 0.5) * h()) for s in range(16)]
                if "perc" in el:
                    sl["perc"] += [(baza + s, 1, rodz, 0.85 * h()) for s, rodz in self.perkusja]
                if "bas" in el:
                    for s, dl, interwal in WZORY_BASU[self.rytmy["bas"]]:
                        sl["bas"].append((baza + s, dl, bas_midi + interwal, 0.9 if s % 4 == 0 else 0.8))
                if "akordy" in el:
                    for s, dl in WZORY_AKORDOW[self.rytmy["akordy"]]:
                        for m in ul:
                            sl["akordy"].append((baza + s, dl, m, 0.75 if s % 4 else 0.85))
                if "pad" in el:
                    for m in ul:
                        sl["pad"].append((baza, 16, m - 12 if m > 64 else m, 0.6))
                if "arp" in el:
                    tony = sorted(ul) + [ul[0] + 12]
                    kolej = tony + tony[-2:0:-1]
                    for s in range(16):
                        sl["arp"].append((baza + s, 0.9, kolej[s % len(kolej)] + 12, 0.8 if s % 2 == 0 else 0.55))
            if "lead" in el:
                sl["lead"] += [(start + k, d, m, v) for k, d, m, v in self.melodia(takty)]
            if "roll" in el:
                sl["roll"] += self._roll(start, takty)
            if "riser" in el:
                sl["riser"].append((start, takty * 16, "riser", 1.0))
            if "crash" in el:
                sl["crash"].append((start, 16, "crash", 1.0))
            start += takty * 16
        self.kroki = start
        return sl

    def _roll(self, start, takty):
        kroki, zd = takty * 16, []
        cwiartka = kroki / 4
        for faza, co in enumerate([4, 2, 1, 0.5]):
            s = faza * cwiartka
            while s < (faza + 1) * cwiartka - 1e-9:
                if s < kroki - 4:  # ostatnia ćwiartka taktu = cisza przed dropem
                    zd.append((start + s, co, "roll", 0.25 + 0.75 * (s / kroki) ** 1.5))
                s += co
        return zd


# --------------------------------------------------------------------------
# Synteza
# --------------------------------------------------------------------------

def _los(*klucz):
    return np.random.default_rng(zlib.crc32(repr(klucz).encode()))


def _sos(typ, fc, rzad=2):
    nyq = SR / 2
    if isinstance(fc, (list, tuple)):
        fc = [min(max(f, 20), nyq * 0.95) / nyq for f in fc]
    else:
        fc = min(max(fc, 20), nyq * 0.95) / nyq
    return signal.butter(rzad, fc, typ, output="sos")


def lp(x, fc, rzad=2):
    return signal.sosfilt(_sos("low", fc, rzad), x, axis=-1)


def hp(x, fc, rzad=2):
    return signal.sosfilt(_sos("high", fc, rzad), x, axis=-1)


def bp(x, f1, f2, rzad=2):
    return signal.sosfilt(_sos("band", [f1, f2], rzad), x, axis=-1)


def adsr(bramka, a, d, s, r):
    """Obwiednia: atak, opad (wykładniczy), podtrzymanie, wybrzmienie; długość = bramka + r."""
    bn, an, rn = int(bramka * SR), max(1, int(a * SR)), max(1, int(r * SR))
    n = bn + rn
    t = np.arange(n) / SR
    env = s + (1 - s) * np.exp(-(t - a) / max(d, 1e-3))
    env[:an] = np.linspace(0, 1, an)[: min(an, n)]
    poziom = env[min(bn, n - 1)] if bn > 0 else 0.0
    env[bn:] = poziom * (1 - np.linspace(0, 1, n - bn)) ** 2
    return env


def _polyblep(t, dt):
    out = np.zeros_like(t)
    m = t < dt
    x = t[m] / dt
    out[m] = x + x - x * x - 1
    m = t > 1 - dt
    x = (t[m] - 1) / dt
    out[m] = x * x + x + x + 1
    return out


def pila(f, n, faza=0.0):
    dt = f / SR
    t = (faza + dt * np.arange(n)) % 1.0
    return 2 * t - 1 - _polyblep(t, dt)


def sinus(f, n, faza=0.0):
    return np.sin(2 * np.pi * (f * np.arange(n) / SR + faza))


def supersaw(f, n, glosy, rozstroj, szer, klucz):
    rng = _los(klucz)
    L, R = np.zeros(n), np.zeros(n)
    for v in range(glosy):
        poz = (v / (glosy - 1)) * 2 - 1 if glosy > 1 else 0
        x = pila(f * 2 ** (poz * rozstroj / 1200), n, rng.random())
        kat = (poz * szer + 1) * np.pi / 4
        L += x * np.cos(kat)
        R += x * np.sin(kat)
    return np.stack([L, R]) / math.sqrt(glosy)


def mono(x):
    return np.stack([x, x]) * 0.7071


def barwa(nazwa, m, bramka, vel):
    f = midi_hz(m)
    if nazwa == "pluck":
        env = adsr(bramka, 0.002, 0.2, 0.0, 0.18)
        n = len(env)
        raw = supersaw(f, n, 5, 16, 1.0, ("pluck", m))
        t = np.arange(n) / SR
        jasne, ciemne = lp(raw, min(f * 14, 14000)), lp(raw, f * 2.2)
        fenv = np.exp(-t / 0.07)
        return (ciemne + (jasne - ciemne) * fenv) * env * vel
    if nazwa == "marimba":
        n = int((bramka + 0.6) * SR)
        t = np.arange(n) / SR
        x = (np.sin(2 * np.pi * f * t) * np.exp(-t / 0.35)
             + 0.3 * np.sin(2 * np.pi * 4 * f * t) * np.exp(-t / 0.07)
             + 0.08 * np.sin(2 * np.pi * 9.2 * f * t) * np.exp(-t / 0.02))
        x[: 44] *= np.linspace(0, 1, 44)
        return mono(x * vel)
    if nazwa == "pianino":
        env = adsr(bramka, 0.002, 0.5, 0.25, 0.25)
        n = len(env)
        t = np.arange(n) / SR
        out = np.zeros((2, n))
        for h in range(1, 11):
            if f * h > 16000:
                break
            amp = 1 / h ** 1.25 * np.exp(-t / (1.4 / h ** 0.8))
            for c, dc in enumerate((-2.0, 2.0)):
                out[c] += amp * np.sin(2 * np.pi * f * h * 2 ** (dc / 1200) * t + h)
        return out * env * vel * 0.5
    if nazwa == "organy":
        env = adsr(bramka, 0.004, 0.08, 0.85, 0.1)
        n = len(env)
        x = sum(a * sinus(f * h, n) for h, a in [(1, 1), (2, .55), (3, .35), (4, .2), (6, .12), (8, .08)] if f * h < 16000)
        return mono(lp(x, 5000) * env * vel * 0.6)
    if nazwa == "supersaw":
        env = adsr(bramka, 0.008, 0.25, 0.7, 0.3)
        raw = supersaw(f, len(env), 7, 24, 0.9, ("supersaw", m))
        return lp(raw, min(f * 7, 9000)) * env * vel * 0.8
    if nazwa == "pad":
        env = adsr(bramka, 0.4, 0.6, 0.85, 0.9)
        raw = supersaw(f, len(env), 6, 16, 1.0, ("pad", m))
        return lp(raw, 2000, 2) * env * vel * 0.7
    if nazwa == "flet":
        env = adsr(bramka, 0.045, 0.2, 0.8, 0.12)
        n = len(env)
        t = np.arange(n) / SR
        wib = 1 + 0.004 * np.sin(2 * np.pi * 5.5 * t) * np.clip((t - 0.15) / 0.2, 0, 1)
        faza = 2 * np.pi * np.cumsum(f * wib) / SR
        ton = np.sin(faza) + 0.16 * np.sin(2 * faza) + 0.07 * np.sin(3 * faza)
        szum = bp(_los("flet", m).standard_normal(n), f * 0.9, min(f * 4, 15000))
        chuch = szum * (0.06 + 0.5 * np.exp(-t / 0.03))
        return mono((ton + chuch) * env * vel * 0.8)
    if nazwa == "sub":
        env = adsr(bramka, 0.006, 0.12, 0.9, 0.05)
        n = len(env)
        x = sinus(f, n) + 0.12 * sinus(2 * f, n)
        return mono(np.tanh(1.6 * x) / np.tanh(1.6) * env * vel)
    if nazwa == "pila":
        env = adsr(bramka, 0.003, 0.15, 0.7, 0.05)
        n = len(env)
        t = np.arange(n) / SR
        raw = pila(f, n) * 0.6 + sinus(f, n)
        jasne, ciemne = lp(raw, f * 9), lp(raw, f * 2.5)
        return mono((ciemne + (jasne - ciemne) * np.exp(-t / 0.08)) * env * vel * 0.9)
    raise ValueError(f"Nieznane brzmienie: {nazwa}")


def _szum(klucz, n):
    return _los(klucz).standard_normal(n)


def perkusja(nazwa):
    """Jednostrzałowe próbki bębnów (stereo)."""
    if nazwa == "kick":
        n = int(0.45 * SR)
        t = np.arange(n) / SR
        f = 47 + 120 * np.exp(-t / 0.03)
        cialo = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t / 0.2) * np.clip(1.4 - t * 2.8, 0, 1)
        klik = hp(_szum("kick", n), 3000) * np.exp(-t / 0.003) * 0.25
        x = np.tanh(2.0 * (cialo + klik))
        x[-200:] *= np.linspace(1, 0, 200)
        return mono(x / np.abs(x).max())
    if nazwa == "clap":
        n = int(0.35 * SR)
        t = np.arange(n) / SR
        env = sum(np.where(t >= o, np.exp(-(t - o) / 0.005), 0) for o in (0, 0.011, 0.022))
        env += np.where(t >= 0.03, np.exp(-(t - 0.03) / 0.11), 0) * 0.7
        L = bp(_szum("clapL", n), 900, 4000) * env
        R = bp(_szum("clapR", n), 900, 4000) * env
        x = np.stack([L, R])
        return x / np.abs(x).max()
    if nazwa == "roll":
        n = int(0.25 * SR)
        t = np.arange(n) / SR
        x = 0.5 * np.sin(2 * np.pi * 200 * t) * np.exp(-t / 0.05) + hp(_szum("roll", n), 1500) * np.exp(-t / 0.08)
        return mono(x / np.abs(x).max())
    if nazwa == "hats":
        n = int(0.08 * SR)
        t = np.arange(n) / SR
        x = hp(_szum("hat", n), 7500, 4) * np.exp(-t / 0.022)
        return mono(x / np.abs(x).max())
    if nazwa == "openhat":
        n = int(0.4 * SR)
        t = np.arange(n) / SR
        x = hp(_szum("ohat", n), 6500, 4) * np.exp(-t / 0.13) * np.clip(t / 0.003, 0, 1)
        return mono(x / np.abs(x).max())
    if nazwa == "shaker":
        n = int(0.09 * SR)
        t = np.arange(n) / SR
        x = bp(_szum("shaker", n), 4500, 11000) * np.clip(t / 0.012, 0, 1) * np.exp(-t / 0.03)
        return mono(x / np.abs(x).max())
    if nazwa in ("wys", "nis"):
        n = int(0.3 * SR)
        t = np.arange(n) / SR
        f0 = 420 if nazwa == "wys" else 290
        f = f0 * (1 + 0.25 * np.exp(-t / 0.01))
        x = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t / 0.09)
        x += 0.3 * bp(_szum("conga" + nazwa, n), 1500, 6000) * np.exp(-t / 0.008)
        return mono(x / np.abs(x).max())
    if nazwa == "crash":
        n = int(3.0 * SR)
        t = np.arange(n) / SR
        env = np.exp(-t / 0.9) * np.clip(t / 0.002, 0, 1)
        x = np.stack([lp(hp(_szum("crL", n), 4500), 13000), lp(hp(_szum("crR", n), 4500), 13000)]) * env
        n2 = int(2.0 * SR)
        t2 = np.arange(n2) / SR
        uderzenie = np.sin(2 * np.pi * np.cumsum(40 + 30 * np.exp(-t2 / 0.05)) / SR) * np.exp(-t2 / 0.5)
        x[:, :n2] += mono(uderzenie) * 1.6
        return x / np.abs(x).max()
    raise ValueError(nazwa)


def _biquad(typ, fc, q=0.7071):
    """Współczynniki RBJ (szybkie – do filtrów zmiennych w czasie)."""
    w0 = 2 * np.pi * min(max(fc, 20), SR * 0.45) / SR
    cw, alfa = np.cos(w0), np.sin(w0) / (2 * q)
    if typ == "low":
        b = [(1 - cw) / 2, 1 - cw, (1 - cw) / 2]
    else:  # pasmowy
        b = [alfa, 0.0, -alfa]
    a0 = 1 + alfa
    return np.array([[b[0] / a0, b[1] / a0, b[2] / a0, 1.0, -2 * cw / a0, (1 - alfa) / a0]])


def filtr_zmienny(x, typ, f0, f1, q=0.7071, blok=256):
    """Filtr przemiatany wykładniczo od f0 do f1; x ma kształt (..., n)."""
    n = x.shape[-1]
    plaski = x.reshape(-1, n)
    out = np.empty_like(plaski)
    zi = np.zeros((1, plaski.shape[0], 2))
    for i in range(0, n, blok):
        fc = f0 * (f1 / f0) ** (i / max(n - 1, 1))
        out[:, i:i + blok], zi = signal.sosfilt(_biquad(typ, fc, q), plaski[:, i:i + blok], axis=-1, zi=zi)
    return out.reshape(x.shape)


def riser(n):
    t = np.arange(n) / n
    szum = np.stack([_szum(("riser", c), n) for c in range(2)])
    return filtr_zmienny(szum, "band", 300, 9000, q=1.2) * (t ** 2) * 1.6


# --------------------------------------------------------------------------
# Render, miks, master
# --------------------------------------------------------------------------

BUSY = {
    #          głośność, panorama, pogłos, sidechain
    "kick":    (0.80,  0.0, 0.00, 0.0),
    "clap":    (0.75,  0.0, 0.20, 0.0),
    "roll":    (0.45,  0.0, 0.20, 0.0),
    "hats":    (0.45,  0.25, 0.04, 0.0),
    "openhat": (0.45,  -0.2, 0.06, 0.0),
    "shaker":  (0.30,  -0.4, 0.06, 0.0),
    "perc":    (0.24,  0.35, 0.15, 0.0),
    "crash":   (0.45,  0.0, 0.10, 0.0),
    "riser":   (0.30,  0.0, 0.25, 0.0),
    "bas":     (0.42,  0.0, 0.00, 0.85),
    "akordy":  (0.42,  0.0, 0.28, 0.6),
    "pad":     (0.22,  0.0, 0.35, 0.6),
    "lead":    (0.30,  0.0, 0.28, 0.3),
    "arp":     (0.16,  0.0, 0.35, 0.5),
}
MUZYCZNE = ["bas", "akordy", "pad", "lead", "arp"]


def _dodaj(bus, x, pos):
    N = bus.shape[1]
    if pos >= N:
        return
    k = min(N, pos + x.shape[1])
    bus[:, pos:k] += x[:, : k - pos]


def _panoruj(x, pan):
    """Panorama w miejscu (bez kopiowania długich śladów)."""
    kat = (pan + 1) * np.pi / 4
    x[0] *= np.cos(kat) * math.sqrt(2)
    x[1] *= np.sin(kat) * math.sqrt(2)
    return x


def _delay(x, opozn, fb):
    """y[n] = x[n] + fb*y[n-opozn], liczone blokami długości opóźnienia."""
    y = np.zeros(len(x) + opozn)
    y[: len(x)] = x
    for i in range(opozn, len(y), opozn):
        j = min(len(y), i + opozn)
        y[i:j] += fb * y[i - opozn: j - opozn]
    return y[opozn:]


def _poglos(n_ir, klucz):
    t = np.arange(n_ir) / SR
    ir = np.stack([_szum((klucz, c), n_ir) for c in range(2)]) * np.exp(-t / 0.55)
    ir = lp(ir, 7000)
    ir[:, : int(0.012 * SR)] = 0
    return ir / np.sqrt((ir ** 2).sum(axis=1, keepdims=True))


class Render:
    def __init__(self, utwor):
        self.u = utwor
        self.zd = utwor.zdarzenia()
        self.N = int(utwor.kroki * utwor.sps) + 4 * SR

    def pozycja(self, krok):
        swing = self.u.swing * self.u.sps if abs(krok - round(krok)) < 1e-6 and int(round(krok)) % 2 == 1 else 0
        return int(round(krok * self.u.sps + swing))

    def busy(self):
        busy = {n: np.zeros((2, self.N), dtype=np.float32) for n in BUSY}
        probki = {}
        pamiec = {}
        for nazwa, lista in self.zd.items():
            for krok, dl, co, vel in lista:
                pos = self.pozycja(krok)
                if nazwa in MUZYCZNE:
                    bramka = dl * self.u.sps / SR
                    klucz = (self.u.brzmienia[nazwa], co, round(bramka, 3), round(vel, 2))
                    if klucz not in pamiec:
                        pamiec[klucz] = barwa(*klucz)
                    _dodaj(busy[nazwa], pamiec[klucz], pos)
                elif nazwa == "riser":
                    _dodaj(busy[nazwa], riser(int(dl * self.u.sps)), pos)
                else:
                    if co not in probki:
                        probki[co] = perkusja(co)
                    _dodaj(busy[nazwa], probki[co] * vel, pos)
        return busy

    def miks(self):
        u = self.u
        busy = self.busy()
        # filtry sekcji (zamykanie/otwieranie jak u DJ-a)
        for sek in u.sekcje:
            if "filtr" in sek:
                a, b = self.pozycja(sek["start"]), self.pozycja(sek["start"] + sek["kroki"])
                kawalek = np.stack([busy[n][:, a:b] for n in MUZYCZNE])
                przef = filtr_zmienny(kawalek, "low", *sek["filtr"])
                xf = min(1024, (b - a) // 4)
                rampa = np.linspace(0, 1, xf)
                przef[..., :xf] = kawalek[..., :xf] * (1 - rampa) + przef[..., :xf] * rampa
                for i, n in enumerate(MUZYCZNE):
                    busy[n][:, a:b] = przef[i]
        # sidechain od stopy
        sc = np.zeros(self.N)
        dl = int(min(0.24, 60 / u.bpm * 0.8) * SR)
        t = np.arange(dl) / SR
        ksztalt = np.clip(1 - (t - 0.004) / (dl / SR - 0.004), 0, 1) ** 1.8
        for krok, *_ in self.zd["kick"]:
            p = self.pozycja(krok)
            k = min(self.N, p + dl)
            sc[p:k] = np.maximum(sc[p:k], ksztalt[: k - p])
        # cisza przed dropem: ostatnia ćwierćnuta sekcji z riserem
        cisza = np.ones(self.N)
        for sek in u.sekcje:
            if "riser" in sek["elementy"]:
                b = self.pozycja(sek["start"] + sek["kroki"])
                a = self.pozycja(sek["start"] + sek["kroki"] - 4)
                cisza[a:b] = 0
                cisza[a - 256:a] = np.linspace(1, 0, 256)
        # pogłos i delay
        wysylka = np.zeros((2, self.N))
        delay_in = np.zeros((2, self.N))
        stemy = {}
        sc = sc.astype(np.float32)
        cisza = cisza.astype(np.float32)
        for n, (gl, pan, rev, side) in BUSY.items():
            x = _panoruj(busy.pop(n), pan)
            x *= gl
            if side:
                x *= 1 - side * sc
            if n != "riser":
                x *= cisza
            if n in ("lead", "arp"):
                delay_in += x
            wysylka += x * rev
            stemy[n] = x
        kwarta = int(round(4 * u.sps))
        kropk = int(round(6 * u.sps))
        mono_d = hp(delay_in.mean(axis=0), 400)
        echo = np.stack([_delay(mono_d, kropk, 0.35), _delay(mono_d, kwarta, 0.35)])
        stemy["echo"] = lp(echo - mono_d, 5000) * 0.22
        ir = _poglos(int(2.6 * SR), "rev")
        mokry = signal.oaconvolve(hp(wysylka, 250), ir, axes=1)[:, : self.N]
        stemy["poglos"] = mokry * 0.8 * (1 - 0.5 * sc)
        return stemy



def limiter(x, sufit=0.89, okno_ms=5):
    w = max(3, int(okno_ms / 1000 * SR))
    szczyt = np.abs(x).max(axis=0)
    gr = np.minimum(1.0, sufit / np.maximum(szczyt, 1e-9))
    gr = minimum_filter1d(gr, size=2 * w + 1)
    gr = uniform_filter1d(gr, size=w)
    gr = np.minimum(gr, minimum_filter1d(gr, size=w))
    return np.clip(x * gr, -sufit, sufit)


def master(stemy, cel_rms_db=-9.0):
    mix = sum(stemy.values())
    mix = hp(mix, 28)
    okno = SR // 2
    n = mix.shape[1] // okno
    rms = np.sqrt((mix[:, : n * okno].reshape(2, n, okno) ** 2).mean(axis=(0, 2)))
    glosne = np.percentile(rms, 90)
    zysk = 10 ** (cel_rms_db / 20) / max(glosne, 1e-9)
    return limiter(mix * zysk), zysk


# --------------------------------------------------------------------------
# Eksport
# --------------------------------------------------------------------------

def zapisz_wav(sciezka, x, bity=16):
    x = np.asarray(x).T
    if bity == 16:
        szum = (np.random.default_rng(0).random(x.shape) - np.random.default_rng(1).random(x.shape)) / 32768
        wavfile.write(sciezka, SR, np.clip((x + szum) * 32767, -32768, 32767).astype(np.int16))
    else:
        wavfile.write(sciezka, SR, x.astype(np.float32))


def zapisz_midi(sciezka, utwor, zd):
    import mido

    plik = mido.MidiFile(type=1, ticks_per_beat=480)
    tempo = mido.MidiTrack()
    tempo.append(mido.MetaMessage("set_tempo", tempo=mido.bpm2tempo(utwor.bpm)))
    # nazwy w MIDI muszą być w latin-1, więc bez polskich znaków
    nazwa = unicodedata.normalize("NFKD", utwor.tytul.replace("ł", "l").replace("Ł", "L"))
    tempo.append(mido.MetaMessage("track_name", name=nazwa.encode("ascii", "ignore").decode()))
    plik.tracks.append(tempo)
    na_ticki = lambda k: int(round(k * 120))

    def sciezka_midi(nazwa, kanal, nuty):
        tr = mido.MidiTrack()
        tr.append(mido.MetaMessage("track_name", name=nazwa))
        zd = []
        for start, dl, m, vel in nuty:
            v = max(1, min(127, int(vel * 110)))
            zd.append((na_ticki(start), 1, m, v))
            zd.append((na_ticki(start + dl), 0, m, 0))
        zd.sort()
        teraz = 0
        for t, on, m, v in zd:
            typ = "note_on" if on else "note_off"
            tr.append(mido.Message(typ, note=m, velocity=v, channel=kanal, time=t - teraz))
            teraz = t
        plik.tracks.append(tr)

    for i, n in enumerate(MUZYCZNE):
        if zd[n]:
            sciezka_midi(f"{n} ({utwor.brzmienia[n]})", i, zd[n])
    bebny = []
    for n in ["kick", "clap", "hats", "openhat", "shaker", "perc", "roll", "crash"]:
        for start, dl, co, vel in zd[n]:
            klucz = n if n != "perc" else f"perc_{co}"
            bebny.append((start, min(dl, 1), GM_PERKUSJA[klucz], vel))
    sciezka_midi("bebny", 9, bebny)
    plik.save(sciezka)


def glosnosc_lufs(sciezka):
    wynik = subprocess.run(["ffmpeg", "-hide_banner", "-nostats", "-i", sciezka, "-af",
                            "ebur128=framelog=quiet", "-f", "null", "-"], capture_output=True, text=True)
    znalezione = re.findall(r"I:\s+(-?[\d.]+) LUFS", wynik.stderr)
    return float(znalezione[-1]) if znalezione else None


def czas(sekundy):
    return f"{int(sekundy // 60)}:{int(sekundy % 60):02d}"


def opis(utwor):
    sek_na_krok = utwor.sps / SR
    return {
        "tytul": utwor.tytul,
        "bpm": utwor.bpm,
        "tonacja": utwor.spec.get("tonacja", "A-moll"),
        "seed": utwor.seed,
        "akordy": [utwor.ton.nazwa_akordu(st, s or utwor.septymy) for st, s in utwor.akordy],
        "dlugosc": czas(utwor.kroki * sek_na_krok),
        "sekcje": [{"nazwa": s["nazwa"], "od": czas(s["start"] * sek_na_krok),
                    "takty": s["kroki"] // 16, "elementy": s["elementy"]} for s in utwor.sekcje],
        "brzmienia": utwor.brzmienia,
        "rytmy": utwor.rytmy,
    }
