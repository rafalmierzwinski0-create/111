"""Instrumenty: syntezatory (Faust przez DawDreamer), fortepian z sampli, one-shoty.

Każda funkcja dostaje listę nut (start_s, dlugosc_s, midi, glosnosc 0..1)
i długość całego utworu, a zwraca stereo float32 (2, n).
"""

import contextlib
import os
import sys
import zlib

import dawdreamer as daw
import numpy as np

from . import sample

SR = sample.SR

# Każdy patch definiuje `process` (stereo) z freq/gain/gate jednego głosu.
PATCHE = {
    # ciepły pad w stylu Juno-106: dwie piły + PWM + sub, filtr Moog z wolną obwiednią
    "juno_pad": """
lfo = os.osc(0.23);
env = en.adsr(0.7, 1.5, 0.85, 2.2, gate);
fenv = en.adsr(1.2, 2.5, 0.55, 2.2, gate);
glos(d) = (os.sawtooth(freq*(1+d)) + os.sawtooth(freq*(1-d*0.7)) + 0.6*os.square(freq*(1+d*0.3))*(0.8+0.2*lfo) + 0.35*os.osc(freq*0.5)) * 0.28
        : ve.moog_vcf_2bn(0.2, 500 + fenv*2300 + 300*lfo) : *(env*gain);
process = glos(0.0035), glos(-0.0042);
""",
    # pluck do arpeggiów i hooków: szybko zamykający się filtr
    "pluck": """
env = en.adsr(0.002, 0.45, 0.0, 0.3, gate);
fenv = en.adsr(0.001, 0.16, 0.0, 0.2, gate);
glos(d) = (os.sawtooth(freq*(1+d)) + os.sawtooth(freq*(1-d)) + 0.5*os.square(freq)) * 0.33
        : ve.moog_vcf_2bn(0.35, 220 + fenv*fenv*7500 + freq*0.8) : *(env*gain);
process = glos(0.004), glos(0.0065);
""",
    # szeroki supersaw na lead / akordy w dropie
    "supersaw": """
env = en.adsr(0.006, 0.4, 0.75, 0.35, gate);
fenv = en.adsr(0.002, 0.35, 0.6, 0.35, gate);
s(d) = os.sawtooth(freq*(1+d));
L = (s(0) + s(-0.0045) + s(0.0095) + s(-0.016) + 0.5*os.osc(freq*0.5)) * 0.22;
R = (s(0) + s(0.0045) + s(-0.0095) + s(0.016) + 0.5*os.osc(freq*0.5)) * 0.22;
f = ve.moog_vcf_2bn(0.15, 1800 + fenv*6500);
process = (L : f : *(env*gain)), (R : f : *(env*gain));
""",
    # Rhodes (FM): młoteczek + dzwoniący „tine” + tremolo stereo
    "rhodes": """
ph = os.phasor(1, freq);
idx = en.adsr(0.001, 0.9, 0.12, 0.25, gate);
env = en.adsr(0.001, 3.5, 0.0, 0.35, gate);
mod = os.osc(freq) * (0.25 + 1.3*idx*gain);
nosna = sin(2*ma.PI*ph + mod);
tine = os.osc(freq*14) * en.adsr(0.001, 0.06, 0.0, 0.05, gate) * 0.12;
trem = 0.22*os.osc(4.6);
glos = (nosna + tine) * env * gain * 0.6;
process = glos*(1+trem), glos*(1-trem);
""",
    # organy house (w stylu M1): harmoniczne z krótkim „klikiem”
    "organy": """
env = en.adsr(0.002, 0.3, 0.6, 0.12, gate);
klik = en.adsr(0.001, 0.03, 0.0, 0.02, gate);
glos = (os.osc(freq) + 0.7*os.osc(2*freq) + 0.45*os.osc(3*freq) + 0.3*os.osc(4*freq) + 0.18*os.osc(6*freq) + 0.25*klik*os.osc(8*freq)) * env * gain * 0.32;
process = glos, glos;
""",
    # smyczki: wolny atak, delikatne vibrato
    "smyczki": """
wib = 1 + 0.003*os.osc(5.1);
env = en.adsr(0.45, 0.8, 0.85, 1.4, gate);
s(d) = os.sawtooth(freq*wib*(1+d));
L = (s(0.002) + s(-0.006) + s(0.009)) * 0.3;
R = (s(-0.002) + s(0.006) + s(-0.009)) * 0.3;
f = fi.lowpass(2, 3200);
process = (L : f : *(env*gain)), (R : f : *(env*gain));
""",
    # bas Moog: piła + sinus, filtr z obwiednią, lekkie przesterowanie
    "moog_bas": """
env = en.adsr(0.003, 0.25, 0.8, 0.07, gate);
fenv = en.adsr(0.002, 0.16, 0.2, 0.07, gate);
osc = os.sawtooth(freq)*0.65 + os.osc(freq)*0.7;
process = osc : ve.moog_vcf_2bn(0.4, 110 + freq*1.5 + fenv*1400) : *(1.6) : ma.tanh : *(env*gain) <: _, _;
""",
    # czysty sub (808-owy charakter, krótki „klik” na starcie)
    "sub": """
env = en.adsr(0.004, 0.2, 0.92, 0.08, gate);
osc = os.osc(freq) + 0.12*os.osc(2*freq);
process = osc*1.4 : ma.tanh : *(env*gain) <: _, _;
""",
    # reese: dwie rozstrojone piły, ciemny i szeroki
    "reese": """
env = en.adsr(0.01, 0.3, 0.85, 0.12, gate);
L = (os.sawtooth(freq*1.006) + os.sawtooth(freq*0.996) + os.osc(freq)) * 0.35;
R = (os.sawtooth(freq*0.994) + os.sawtooth(freq*1.004) + os.osc(freq)) * 0.35;
f = ve.moog_vcf_2bn(0.2, 650);
process = (L : f : *(env*gain)), (R : f : *(env*gain));
""",
    # dzwonek FM (ratio 3.5) – „szklane” plucki
    "dzwonek": """
ph = os.phasor(1, freq);
env = en.adsr(0.001, 1.6, 0.0, 0.5, gate);
mod = os.osc(freq*3.5) * 2.2 * en.adsr(0.001, 0.5, 0.0, 0.3, gate);
glos = sin(2*ma.PI*ph + mod) * env * gain * 0.5;
process = glos, glos;
""",
}

# wokale z modelu formantów (pm.SFFormantModelBP): typ głosu, samogłoska 0..4 = a e i o u
for nazwa, typ, samogloska in [("vox_a", 3, 0), ("vox_o", 3, 3), ("vox_u", 3, 4), ("vox_alt", 0, 3)]:
    PATCHE[nazwa] = f"""
wib = 1 + 0.007*os.osc(5.3)*en.adsr(0.35, 0.2, 1, 0.2, gate);
env = en.adsr(0.06, 0.3, 0.85, 0.45, gate);
glos = pm.SFFormantModelBP({typ}, {samogloska}, 0, freq*wib, 0.04) * env * gain * 7;
process = glos, glos;
"""


@contextlib.contextmanager
def cicho():
    """Wycisza komunikaty biblioteki C++ (np. „Steal release voice”) wypisywane na stdout/stderr."""
    sys.stdout.flush()
    zapas = [os.dup(1), os.dup(2)]
    with open(os.devnull, "w") as nic:
        os.dup2(nic.fileno(), 1)
        os.dup2(nic.fileno(), 2)
        try:
            yield
        finally:
            os.dup2(zapas[0], 1)
            os.dup2(zapas[1], 2)
            for z in zapas:
                os.close(z)


WYBRZMIENIE = {"juno_pad": 2.5, "smyczki": 1.6, "rhodes": 3.5, "dzwonek": 1.8}


def polifonia(nuty, wybrzmienie):
    """Ile nut brzmi naraz (z wybrzmieniem) – tyle głosów potrzebuje syntezator."""
    zd = sorted([(s, 1) for s, d, _, _ in nuty] + [(s + d + wybrzmienie, -1) for s, d, _, _ in nuty])
    teraz = najwiecej = 0
    for _, z in zd:
        teraz += z
        najwiecej = max(najwiecej, teraz)
    return najwiecej


PPQN = 960          # rozdzielczość automatyki (przy 120 BPM: ~0,5 ms)
TIKI_NA_S = 2 * PPQN  # silnik DawDreamer pracuje na stałym 120 BPM


def _przydziel_tory(nuty, wybrzmienie, tory):
    """Każda nuta dostaje tor (głos). Wolny tor = poprzednia nuta już wybrzmiała; inaczej najstarszy."""
    koniec = [-1e9] * tory
    przydzial = []
    for start, dl, m, v in sorted(nuty):
        wolne = [i for i in range(tory) if koniec[i] + wybrzmienie <= start]
        i = wolne[0] if wolne else min(range(tory), key=lambda j: koniec[j])
        koniec[i] = start + dl
        przydzial.append((i, start, dl, m, v))
    return przydzial


def syntezator(patch, nuty, n_probek, tory=None):
    """Polifonia przez równoległe tory w jednym programie Faust, sterowane automatyką freq/gain/gate.

    (Wbudowana polifonia DawDreamera albo ucina wybrzmienia, albo każdy głos gra tylko raz.)
    """
    if patch not in PATCHE:
        raise ValueError(f"Nieznane brzmienie „{patch}”. Dostępne: {', '.join(sorted(PATCHE) + ['fortepian'])}")
    wybrz = WYBRZMIENIE.get(patch, 0.6)
    if tory is None:
        tory = max(1, min(24, polifonia(nuty, wybrz) + 1))
    dsp = ('import("stdfaust.lib");\n'
           'tor(i) = environment {\n'
           'freq = hslider("freq%i", 200, 20, 12000, 0.001);\n'
           'gain = hslider("gain%i", 0.5, 0, 1, 0.001);\n'
           'gate = hslider("gate%i", 0, 0, 1, 1);\n'
           + PATCHE[patch] + '\n}.process;\n'
           f'process = par(i, {tory}, tor(i)) :> _, _;\n')
    eng = daw.RenderEngine(SR, 512)
    eng.set_bpm(120)
    p = eng.make_faust_processor("syn")
    with cicho():
        ok = p.set_dsp_string(dsp)
    if not ok:
        raise RuntimeError(f"Błąd kompilacji patcha {patch}")
    n_tik = int(n_probek / SR * TIKI_NA_S) + 2
    czest = np.full((tory, n_tik), 220.0, dtype=np.float32)
    glos = np.zeros((tory, n_tik), dtype=np.float32)
    bramka = np.zeros((tory, n_tik), dtype=np.float32)
    for i, start, dl, m, v in _przydziel_tory(nuty, wybrz, tory):
        a = int(start * TIKI_NA_S)
        b = max(a + 2, int((start + max(dl, 0.02)) * TIKI_NA_S))
        if a >= n_tik:
            continue
        bramka[i, max(0, a - 3):a] = 0  # krótka przerwa, żeby obwiednia wystartowała od nowa
        bramka[i, a:b] = 1
        czest[i, a:] = midi_hz(m)  # wysokość trzymana też w wybrzmieniu
        glos[i, a:] = v
    for i in range(tory):
        p.set_automation(f"/dawdreamer/freq{i}", czest[i], ppqn=PPQN)
        p.set_automation(f"/dawdreamer/gain{i}", glos[i], ppqn=PPQN)
        p.set_automation(f"/dawdreamer/gate{i}", bramka[i], ppqn=PPQN)
    with cicho():
        eng.load_graph([(p, [])])
        eng.render(n_probek / SR)
    out = eng.get_audio()[:, :n_probek]
    if out.shape[1] < n_probek:
        out = np.pad(out, ((0, 0), (0, n_probek - out.shape[1])))
    return out.astype(np.float32)


def midi_hz(m):
    return 440.0 * 2 ** ((m - 69) / 12)


# --------------------------------------------------------------------------
# Fortepian (Salamander Grand Piano, 4 warstwy dynamiki, sample co tercję małą)
# --------------------------------------------------------------------------

_SAMPLE_FORTEPIANU = [21 + 3 * i for i in range(30)]  # A0, C1, D#1, F#1, ... C8


def _nazwa_salamandra(m):
    nazwy = {0: "c", 3: "d#", 6: "f#", 9: "a"}
    return f"{nazwy[m % 12]}{m // 12 - 1}"


def fortepian(nuty, n_probek):
    out = np.zeros((2, n_probek), dtype=np.float32)
    pamiec = {}
    wybrzmienie = int(0.35 * SR)
    for start, dl, m, v in nuty:
        warstwa = 4 if v < 0.45 else 8 if v < 0.7 else 12 if v < 0.9 else 16
        zrodlo = min(_SAMPLE_FORTEPIANU, key=lambda s: abs(s - m))
        klucz = (m, warstwa, round(dl, 2))
        if klucz not in pamiec:
            x = sample.wczytaj(f"piano{warstwa}/{_nazwa_salamandra(zrodlo)}v{warstwa}", normalizuj=False)
            krok = 2 ** ((m - zrodlo) / 12)
            dlugosc = min(int(dl * SR) + wybrzmienie, int(x.shape[1] / krok) - 1)
            pozycje = np.arange(dlugosc) * krok
            y = np.stack([np.interp(pozycje, np.arange(x.shape[1]), x[c]) for c in range(2)])
            puszczenie = max(0, min(int(dl * SR), dlugosc - 1))
            y[:, puszczenie:] *= np.linspace(1, 0, dlugosc - puszczenie) ** 2  # tłumik
            pamiec[klucz] = (y * 2.2).astype(np.float32)
        y = pamiec[klucz] * (0.55 + 0.45 * v)
        p = int(start * SR)
        k = min(n_probek, p + y.shape[1])
        if p < n_probek:
            out[:, p:k] += y[:, : k - p]
    return out


# --------------------------------------------------------------------------
# One-shoty i pętle
# --------------------------------------------------------------------------

def przestroj(x, polton):
    if not polton:
        return x
    krok = 2 ** (polton / 12)
    pozycje = np.arange(int(x.shape[1] / krok)) * krok
    return np.stack([np.interp(pozycje, np.arange(x.shape[1]), x[c]) for c in range(2)]).astype(np.float32)


def one_shot(ident, uderzenia, n_probek, strojenie=0, dlugosc_max=None):
    """uderzenia: (start_s, dlugosc_s, _, glosnosc). Długość nuty ucina sampel (z krótkim wygaszeniem)."""
    x = przestroj(sample.wczytaj(ident), strojenie)
    out = np.zeros((2, n_probek), dtype=np.float32)
    for start, dl, _, v in uderzenia:
        y = x
        if dlugosc_max:
            n = int(max(dl, 0.03) * SR)
            if n < y.shape[1]:
                y = y[:, :n].copy()
                f = min(400, n // 2)
                y[:, -f:] *= np.linspace(1, 0, f)
        p = int(start * SR)
        k = min(n_probek, p + y.shape[1])
        if p < n_probek:
            out[:, p:k] += y[:, : k - p] * v
    return out


def petla(ident, wejscia, n_probek, sekundy_na_takt, takty_petli=1):
    """Pętla (np. break) dociągnięta do tempa utworu przez zmianę prędkości odtwarzania."""
    x = sample.wczytaj(ident)
    cel = int(sekundy_na_takt * takty_petli * SR)
    pozycje = np.linspace(0, x.shape[1] - 1, cel)
    y = np.stack([np.interp(pozycje, np.arange(x.shape[1]), x[c]) for c in range(2)]).astype(np.float32)
    out = np.zeros((2, n_probek), dtype=np.float32)
    for start, dl, _, v in wejscia:
        p, koniec = int(start * SR), int((start + dl) * SR)
        while p < min(koniec, n_probek):
            k = min(n_probek, p + cel, koniec)
            out[:, p:k] += y[:, : k - p] * v
            p += cel
    return out


def tekstura(ident, wejscia, n_probek):
    """Sampel zapętlony z przenikaniem (szum winylu, drony)."""
    x = sample.wczytaj(ident)
    f = min(SR // 2, x.shape[1] // 4)
    out = np.zeros((2, n_probek), dtype=np.float32)
    for start, dl, _, v in wejscia:
        p, koniec = int(start * SR), min(n_probek, int((start + dl) * SR))
        while p < koniec:
            k = min(koniec, p + x.shape[1])
            y = x[:, : k - p].copy()
            y[:, :f] *= np.linspace(0, 1, f)[: y.shape[1]] if y.shape[1] >= f else 1
            out[:, p:k] += y * v
            p += x.shape[1] - f
    return out


def szum(klucz, n):
    return np.random.default_rng(zlib.crc32(repr(klucz).encode())).standard_normal(n).astype(np.float32)
