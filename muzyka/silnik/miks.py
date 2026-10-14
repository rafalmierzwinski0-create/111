"""Render instrumentów, tor efektów, automatyka sekcji, sidechain, pogłos, master."""

import math

import dawdreamer as daw
import numpy as np
import pyloudnorm
from scipy import signal
from scipy.ndimage import minimum_filter1d, uniform_filter1d

from . import brzmienia
from .brzmienia import SR

# --------------------------------------------------------------------------
# Filtry
# --------------------------------------------------------------------------


def _sos(typ, fc, rzad=2):
    return signal.butter(rzad, min(max(fc, 20), SR * 0.45) / (SR / 2), typ, output="sos")


def lp(x, fc, rzad=2):
    return signal.sosfilt(_sos("low", fc, rzad), x, axis=-1).astype(np.float32)


def hp(x, fc, rzad=2):
    return signal.sosfilt(_sos("high", fc, rzad), x, axis=-1).astype(np.float32)


def _biquad(typ, fc, q=0.7071):
    w0 = 2 * np.pi * min(max(fc, 20), SR * 0.45) / SR
    cw, alfa = np.cos(w0), np.sin(w0) / (2 * q)
    if typ == "low":
        b = [(1 - cw) / 2, 1 - cw, (1 - cw) / 2]
    elif typ == "high":
        b = [(1 + cw) / 2, -(1 + cw), (1 + cw) / 2]
    else:
        b = [alfa, 0.0, -alfa]
    a0 = 1 + alfa
    return np.array([[b[0] / a0, b[1] / a0, b[2] / a0, 1.0, -2 * cw / a0, (1 - alfa) / a0]])


def filtr_zmienny(x, typ, f0, f1, q=0.7071, blok=256):
    """Filtr przemiatany wykładniczo od f0 do f1 (automatyka filtra)."""
    n = x.shape[-1]
    plaski = x.reshape(-1, n)
    out = np.empty_like(plaski)
    zi = np.zeros((1, plaski.shape[0], 2))
    for i in range(0, n, blok):
        fc = f0 * (f1 / f0) ** (i / max(n - 1, 1))
        out[:, i:i + blok], zi = signal.sosfilt(_biquad(typ, fc, q), plaski[:, i:i + blok], axis=-1, zi=zi)
    return out.reshape(x.shape)


# --------------------------------------------------------------------------
# Efekty
# --------------------------------------------------------------------------


def chorus(x, ilosc):
    """Chorus w stylu Juno: dwie modulowane linie opóźniające w przeciwfazie."""
    n = x.shape[1]
    t = np.arange(n, dtype=np.float64) / SR
    mono = x.mean(axis=0)
    idx = np.arange(n, dtype=np.float64)
    out = np.empty_like(x)
    for c, faza in enumerate((0.0, np.pi)):
        opozn = (7.0 + 2.8 * np.sin(2 * np.pi * 0.52 * t + faza)) * SR / 1000
        mokry = np.interp(idx - opozn, idx, mono)
        out[c] = x[c] * (1 - 0.35 * ilosc) + mokry * 0.75 * ilosc
    return out


def szerokosc(x, w):
    m = (x[0] + x[1]) * 0.5
    s = (x[0] - x[1]) * 0.5 * w
    return np.stack([m + s, m - s])


def panorama(x, pan):
    kat = (pan + 1) * np.pi / 4
    x[0] *= np.cos(kat) * math.sqrt(2)
    x[1] *= np.sin(kat) * math.sqrt(2)
    return x


def przester(x, ilosc):
    k = 1 + 6 * ilosc
    return (np.tanh(x * k) / k * (1 + ilosc)).astype(np.float32)


def _delay(x, opozn, fb):
    y = np.zeros(len(x) + opozn, dtype=np.float32)
    y[: len(x)] = x
    for i in range(opozn, len(y), opozn):
        j = min(len(y), i + opozn)
        y[i:j] += fb * y[i - opozn: j - opozn]
    return y[opozn:]


def ping_pong(x, sek_na_krok, fb=0.38):
    mono = hp(x.mean(axis=0), 350)
    kropkowana = int(round(6 * sek_na_krok * SR))
    cwierc = int(round(4 * sek_na_krok * SR))
    return lp(np.stack([_delay(mono, kropkowana, fb), _delay(mono, cwierc, fb * 0.9)]) - mono, 4500)


def faust_efekt(x, dsp):
    eng = daw.RenderEngine(SR, 512)
    pb = eng.make_playback_processor("pb", x.astype(np.float32))
    fx = eng.make_faust_processor("fx")
    with brzmienia.cicho():
        ok = fx.set_dsp_string(dsp)
        eng.load_graph([(pb, []), (fx, ["pb"])])
        eng.render(x.shape[1] / SR)
    if not ok:
        raise RuntimeError("Błąd kompilacji efektu Faust")
    out = eng.get_audio()
    return out[:, : x.shape[1]].astype(np.float32)


def poglos(x, czas=3.0, predelay=25, ton=6500):
    """Pogłos zita-rev1 (algorytmiczny, wysokiej jakości)."""
    dsp = f'import("stdfaust.lib"); process = re.zita_rev1_stereo({predelay}, 180, {ton}, {czas * 0.8}, {czas}, 48000);'
    return faust_efekt(hp(x, 250), dsp)


def rms_db(x):
    return 20 * np.log10(np.sqrt(np.mean(np.square(x, dtype=np.float64))) + 1e-12)


def poziom_aktywny(x):
    """RMS tylko tam, gdzie instrument gra (okna 50 ms powyżej -60 dB)."""
    okno = SR // 20
    n = x.shape[1] // okno
    if n == 0:
        return -120.0
    e = np.mean(np.square(x[:, : n * okno].reshape(2, n, okno), dtype=np.float64), axis=(0, 2))
    aktywne = e[e > 1e-6]
    return 10 * np.log10(aktywne.mean()) if len(aktywne) else -120.0


# --------------------------------------------------------------------------
# Render utworu
# --------------------------------------------------------------------------


class Miks:
    def __init__(self, utwor):
        self.u = utwor
        self.zd = utwor.zdarzenia()
        self.ogon = 6.0
        self.N = int(utwor.kroki * utwor.sek_na_krok * SR + self.ogon * SR)

    def p(self, krok):
        return int(round(self.u.sekundy(krok) * SR))

    def _render_instrumentu(self, nazwa, cfg, lista):
        u = self.u
        barwa = cfg["barwa"]
        nuty = [(u.sekundy(k, nazwa), d * u.sek_na_krok, m, v) for k, d, m, v in lista]
        if barwa.startswith("sample:"):
            return brzmienia.one_shot(barwa[7:], nuty, self.N, cfg.get("strojenie", 0), cfg.get("ucinaj", False))
        if barwa.startswith("petla:"):
            return brzmienia.petla(barwa[6:], nuty, self.N, 16 * u.sek_na_krok, cfg.get("takty_petli", 1))
        if barwa.startswith("tekstura:"):
            return brzmienia.tekstura(barwa[9:], nuty, self.N)
        if barwa == "fortepian":
            return brzmienia.fortepian(nuty, self.N)
        return brzmienia.syntezator(barwa, nuty, self.N)

    def _efekty_sekcji(self):
        """Riser, odwrócony talerz, uderzenie – ślad „fx”."""
        fx = np.zeros((2, self.N), dtype=np.float32)
        for sek in self.u.sekcje:
            a, b = self.p(sek["start"]), self.p(sek["start"] + sek["kroki"])
            efekty = sek.get("fx", [])
            if "riser" in efekty:
                n = b - a
                t = np.linspace(0, 1, n, dtype=np.float32)
                sz = np.stack([brzmienia.szum(("riser", c, a), n) for c in range(2)])
                fx[:, a:b] += filtr_zmienny(sz, "band", 250, 9000, q=1.4) * t ** 2.2 * 0.9
            if "odwrocony" in efekty:
                cr = brzmienia.sample.wczytaj(self.u.instrumenty["crash"]["barwa"][7:])[:, ::-1]
                n = min(cr.shape[1], b, int(16 * self.u.sek_na_krok * SR))
                fx[:, b - n:b] += cr[:, -n:] * 0.7 * np.linspace(0, 1, n) ** 1.5
            if "crash" in efekty and "crash" in self.u.instrumenty:
                cr = brzmienia.sample.wczytaj(self.u.instrumenty["crash"]["barwa"][7:])
                k = min(self.N, a + cr.shape[1])
                fx[:, a:k] += cr[:, : k - a] * 0.55
            if "uderzenie" in efekty:
                boom = brzmienia.sample.wczytaj("sp/misc_cineboom")
                k = min(self.N, a + boom.shape[1])
                fx[:, a:k] += boom[:, : k - a] * 0.8
        return fx

    def _maska_ciszy(self):
        m = np.ones(self.N, dtype=np.float32)
        for sek in self.u.sekcje:
            if "cisza" in sek.get("fx", []):
                b = self.p(sek["start"] + sek["kroki"])
                a = self.p(sek["start"] + sek["kroki"] - sek.get("cisza_kroki", 4))
                m[a:b] = 0
                m[max(0, a - 200):a] *= np.linspace(1, 0, a - max(0, a - 200))
        return m

    def _sidechain(self):
        sc = np.zeros(self.N, dtype=np.float32)
        dl = int(min(0.26, 60 / self.u.bpm * 0.85) * SR)
        t = np.arange(dl) / SR
        ksztalt = (np.clip(1 - (t - 0.003) / (dl / SR - 0.003), 0, 1) ** 1.6).astype(np.float32)
        for nazwa, cfg in self.u.instrumenty.items():
            if cfg.get("sidechain_zrodlo"):
                for k, *_ in self.zd[nazwa]:
                    p = self.p(k)
                    e = min(self.N, p + dl)
                    sc[p:e] = np.maximum(sc[p:e], ksztalt[: e - p])
        return sc

    def _automatyka(self, nazwa, cfg, x):
        for sek in self.u.sekcje:
            a, b = self.p(sek["start"]), self.p(sek["start"] + sek["kroki"])
            for klucz, typ in (("filtr", "low"), ("hp", "high")):
                auto = sek.get(klucz, {})
                zakres = auto.get(nazwa) or (auto.get("bebny") if cfg.get("_bebny") else auto.get("*"))
                if zakres and b > a:
                    kawalek = x[:, a:b]
                    y = filtr_zmienny(kawalek, typ, *zakres)
                    xf = min(1024, (b - a) // 4)
                    r = np.linspace(0, 1, xf, dtype=np.float32)
                    y[:, :xf] = kawalek[:, :xf] * (1 - r) + y[:, :xf] * r
                    x[:, a:b] = y
            gl = sek.get("glosnosc", {}).get(nazwa) or sek.get("glosnosc", {}).get("*")
            if gl:
                x[:, a:b] *= (10 ** (np.linspace(gl[0], gl[1], b - a) / 20)).astype(np.float32)
        return x

    def renderuj(self, log=print):
        u = self.u
        sc = self._sidechain()
        cisza = self._maska_ciszy()
        wysylka_rev = np.zeros((2, self.N), dtype=np.float32)
        wysylka_del = np.zeros((2, self.N), dtype=np.float32)
        stemy = {}
        for nazwa, cfg in u.instrumenty.items():
            lista = self.zd[nazwa]
            if not lista:
                continue
            x = self._render_instrumentu(nazwa, cfg, lista)
            poziom = poziom_aktywny(x)
            if poziom < -100:
                continue
            x *= 10 ** ((-20 - poziom) / 20)  # wyrównanie: każdy ślad startuje z -20 dB RMS
            if cfg.get("hp"):
                x = hp(x, cfg["hp"])
            if cfg.get("lp"):
                x = lp(x, cfg["lp"])
            if cfg.get("przester"):
                x = przester(x, cfg["przester"])
            if cfg.get("chorus"):
                x = chorus(x, cfg["chorus"])
            x = self._automatyka(nazwa, cfg, x)
            if "szerokosc" in cfg:
                x = szerokosc(x, cfg["szerokosc"])
            x = panorama(x, cfg.get("pan", 0.0))
            x *= 10 ** (cfg.get("glosnosc", -6) / 20)
            if cfg.get("sidechain"):
                x *= 1 - cfg["sidechain"] * sc
            x *= cisza
            wysylka_rev += x * cfg.get("poglos", 0.0)
            wysylka_del += x * cfg.get("delay", 0.0)
            stemy[nazwa] = x
            log(f"    · {nazwa:<10} {cfg['barwa']}")
        fx = self._efekty_sekcji()
        if np.abs(fx).max() > 0:
            fx = fx / max(np.abs(fx).max(), 1e-9) * 10 ** (-8 / 20)
            stemy["fx"] = fx
            wysylka_rev += fx * 0.25
        ust = u.spec.get("poglos", {})
        if np.abs(wysylka_del).max() > 0:
            echo = ping_pong(wysylka_del, u.sek_na_krok, ust.get("echo_fb", 0.38))
            stemy["echo"] = echo
            wysylka_rev += echo * 0.3
        mokry = poglos(wysylka_rev, ust.get("czas", 3.0), ust.get("predelay", 25), ust.get("ton", 6500))
        stemy["poglos"] = mokry * ust.get("poziom", 1.0) * (1 - 0.5 * sc)
        return stemy


# --------------------------------------------------------------------------
# Master
# --------------------------------------------------------------------------


def kompresor(x, prog_db=-14.0, ratio=2.0, atak=0.01, puszczenie=0.15):
    """Kompresor szyny (sklejenie miksu)."""
    poziom = np.mean(x ** 2, axis=0)
    a_att, a_rel = math.exp(-1 / (atak * SR)), math.exp(-1 / (puszczenie * SR))
    # jednobiegunowe wygładzenie (wspólna stała – wektorowo przez lfilter), potem szybki atak przez max
    wolny = signal.lfilter([1 - a_rel], [1, -a_rel], poziom)
    szybki = signal.lfilter([1 - a_att], [1, -a_att], poziom)
    db = 10 * np.log10(np.maximum(np.maximum(wolny, szybki), 1e-12))
    redukcja = np.maximum(0, db - prog_db) * (1 - 1 / ratio)
    return (x * 10 ** (-redukcja / 20)).astype(np.float32), float(redukcja.max())


def limiter(x, sufit=0.89, okno_ms=4):
    w = max(3, int(okno_ms / 1000 * SR))
    szczyt = np.abs(x).max(axis=0)
    gr = np.minimum(1.0, sufit / np.maximum(szczyt, 1e-9))
    gr = minimum_filter1d(gr, size=2 * w + 1)
    gr = uniform_filter1d(gr, size=w)
    gr = np.minimum(gr, minimum_filter1d(gr, size=w))
    return np.clip(x * gr, -sufit, sufit).astype(np.float32)


def lufs(x):
    return pyloudnorm.Meter(SR).integrated_loudness(x.T.astype(np.float64))


def master(stemy, cel_lufs=-9.0):
    mix = hp(sum(stemy.values()), 25)
    mix /= max(np.abs(mix).max(), 1e-9) * 2  # szczyt -6 dBFS przed kompresorem
    mix, gr = kompresor(mix, prog_db=rms_db(mix) + 4, ratio=2.0)
    zysk = 1.0
    for _ in range(4):
        wynik = limiter(mix * zysk)
        teraz = lufs(wynik)
        if abs(teraz - cel_lufs) < 0.25:
            break
        zysk *= 10 ** ((cel_lufs - teraz) / 20)
    return wynik, {"lufs": round(teraz, 1), "kompresor_db": round(gr, 1)}
