"""Plik utworu (JSON) -> zdarzenia dla każdego instrumentu.

Utwór składa się z:
  instrumenty  nazwa -> brzmienie i ustawienia miksu
  progresje    nazwa -> zapis akordów (patrz teoria.py)
  wzory        nazwa -> co gra dany instrument (akordy, arp, bas, melodia, bębny, pętla, tekstura)
  aranzacja    lista sekcji: ile taktów, które wzory grają, automatyka filtrów i głośności, efekty przejść
"""

import zlib

import numpy as np

from . import teoria

# Zestawy perkusyjne: nazwa logiczna -> sampel
ZESTAWY = {
    "909": {
        "kick": "909/bt3a0d7", "clap": "909/handclp1", "snare": "909/st3t3s7", "rim": "909/rim63",
        "hat": "909/hhcd2", "ohat": "909/hhod6", "ride": "909/rided6", "crash": "909/cshd8",
        "shaker": "808/ma", "tamb": "kit/tambourine-000", "snap": "sp/perc_snap",
    },
    "deep": {
        "kick": "sp/bd_haus", "clap": "909/handclp2", "snare": "sp/sn_dub", "rim": "808/rs",
        "hat": "909/hhcd0", "ohat": "909/hhod4", "ride": "ride/stereo-ride-b", "crash": "909/cshd8",
        "shaker": "808/ma", "tamb": "kit/tambourine-002", "snap": "sp/perc_snap2",
    },
    "garage": {
        "kick": "808/bd2550", "clap": "808/cp", "snare": "sp/sn_dolf", "rim": "808/rs",
        "hat": "808/ch", "ohat": "808/oh25", "ride": "909/rided4", "crash": "909/cshd8",
        "shaker": "808/ma", "tamb": "kit/tambourine-004", "snap": "sp/perc_snap",
    },
}

# Domyślne ustawienia miksu dla bębnów (dB, panorama, pogłos, ...)
MIKS_BEBNOW = {
    "kick": {"glosnosc": 0, "szerokosc": 0, "sidechain_zrodlo": True},
    "clap": {"glosnosc": -7, "poglos": 0.22},
    "snare": {"glosnosc": -7, "poglos": 0.2},
    "rim": {"glosnosc": -13, "pan": 0.25, "poglos": 0.25},
    "hat": {"glosnosc": -14, "pan": 0.15, "hp": 400},
    "ohat": {"glosnosc": -14, "pan": -0.12, "hp": 400},
    "ride": {"glosnosc": -17, "pan": 0.3, "hp": 500},
    "crash": {"glosnosc": -13, "hp": 300, "poglos": 0.1},
    "shaker": {"glosnosc": -19, "pan": -0.35, "hp": 600},
    "tamb": {"glosnosc": -18, "pan": 0.35, "hp": 600},
    "snap": {"glosnosc": -10, "poglos": 0.3},
}

# Domyślne ustawienia instrumentów melodycznych wg brzmienia
MIKS_BRZMIEN = {
    "fortepian": {"glosnosc": -5, "poglos": 0.25, "sidechain": 0.35, "hp": 110, "rejestr": [50, 77]},
    "rhodes": {"glosnosc": -5, "poglos": 0.2, "chorus": 0.25, "sidechain": 0.4, "hp": 110, "rejestr": [50, 77]},
    "organy": {"glosnosc": -7, "poglos": 0.18, "sidechain": 0.5, "hp": 150, "rejestr": [52, 79]},
    "juno_pad": {"glosnosc": -9, "poglos": 0.35, "chorus": 0.6, "sidechain": 0.55, "hp": 170, "szerokosc": 1.4, "rejestr": [50, 77]},
    "smyczki": {"glosnosc": -10, "poglos": 0.4, "sidechain": 0.45, "hp": 180, "szerokosc": 1.3, "rejestr": [52, 81]},
    "pluck": {"glosnosc": -9, "poglos": 0.3, "delay": 0.3, "sidechain": 0.4, "hp": 220, "rejestr": [57, 86]},
    "dzwonek": {"glosnosc": -11, "poglos": 0.35, "delay": 0.3, "sidechain": 0.35, "hp": 250, "rejestr": [60, 88]},
    "supersaw": {"glosnosc": -8, "poglos": 0.28, "delay": 0.15, "sidechain": 0.5, "hp": 200, "szerokosc": 1.3, "rejestr": [55, 84]},
    "moog_bas": {"glosnosc": -4, "sidechain": 0.75, "szerokosc": 0, "rejestr": [28, 45]},
    "sub": {"glosnosc": -4, "sidechain": 0.85, "szerokosc": 0, "rejestr": [26, 40]},
    "reese": {"glosnosc": -6, "sidechain": 0.75, "szerokosc": 0.6, "rejestr": [28, 45]},
    "vox_a": {"glosnosc": -8, "poglos": 0.45, "delay": 0.2, "sidechain": 0.3, "hp": 220},
    "vox_o": {"glosnosc": -8, "poglos": 0.45, "delay": 0.2, "sidechain": 0.3, "hp": 220},
    "vox_u": {"glosnosc": -9, "poglos": 0.5, "delay": 0.15, "sidechain": 0.35, "hp": 200, "rejestr": [55, 79]},
    "vox_alt": {"glosnosc": -9, "poglos": 0.45, "sidechain": 0.35, "hp": 180},
    "petla": {"glosnosc": -8, "hp": 180, "sidechain": 0.2},
    "tekstura": {"glosnosc": -26, "hp": 300, "szerokosc": 1.2},
}

GM = {"kick": 36, "clap": 39, "snare": 38, "rim": 37, "hat": 42, "ohat": 46, "ride": 51,
      "crash": 49, "shaker": 70, "tamb": 54, "snap": 39}


class Utwor:
    def __init__(self, spec):
        self.spec = spec
        self.tytul = spec.get("tytul", "Bez tytułu")
        self.bpm = float(spec["bpm"])
        self.swing = float(spec.get("swing", 0.0))
        self.sek_na_krok = 60 / self.bpm / 4
        self.instrumenty = self._instrumenty()
        self.progresje = {k: teoria.progresja(v) for k, v in spec.get("progresje", {}).items()}
        self.wzory = spec["wzory"]
        self._voicing = {}

    def _instrumenty(self):
        zestaw = ZESTAWY[self.spec.get("zestaw", "909")]
        wynik = {}
        for nazwa, ident in zestaw.items():
            wynik[nazwa] = {"barwa": "sample:" + ident, **MIKS_BEBNOW.get(nazwa, {}), "_bebny": True, "_gm": GM.get(nazwa, 39)}
        for nazwa, cfg in self.spec.get("instrumenty", {}).items():
            barwa = cfg.get("barwa", wynik.get(nazwa, {}).get("barwa"))
            baza = dict(wynik.get(nazwa, {}))
            rodzaj = barwa.split(":")[0]
            if rodzaj in MIKS_BRZMIEN:
                baza.update(MIKS_BRZMIEN[rodzaj])
            elif barwa.startswith("sample:") and nazwa not in wynik:
                baza.update({"glosnosc": -10, "_bebny": True, "_gm": 39})
            baza.update(cfg)
            baza["barwa"] = barwa
            wynik[nazwa] = baza
        return wynik

    # ---------------- wzory ----------------

    def _prog(self, w):
        p = w["progresja"]
        return self.progresje[p] if p in self.progresje else teoria.progresja(p)

    @staticmethod
    def _akord_w(prog, krok):
        akordy, dl = prog
        krok %= dl
        for s, d, ak in akordy:
            if s <= krok < s + d:
                return s, d, ak
        return akordy[-1]

    def _wzor_akordy(self, w, instr):
        prog = self._prog(w)
        cfg = self.instrumenty[instr]
        lo, hi = w.get("rejestr", cfg.get("rejestr", [50, 77]))
        przes = 12 * w.get("oktawa", 0)
        lewa = w.get("lewa_reka", False)
        zd = []
        akordy, dl_prog = prog

        def nuty_akordu(ak):
            ul = teoria.ulozenie(ak, self._voicing.get(instr), lo, hi, bez_prymy=w.get("bez_prymy", True))
            self._voicing[instr] = ul
            nuty = [m + przes for m in ul]
            if lewa:
                bas = 36 + (ak.bas - 36) % 12
                nuty = [bas] + ([bas + 12] if lewa == "oktawa" else []) + nuty
            return nuty

        if "rytm" not in w:
            for s, d, ak in akordy:
                for m in nuty_akordu(ak):
                    zd.append((s, d, m, w.get("glosnosc", 0.8)))
            return {instr: zd}, dl_prog
        traf, dl_r = teoria.rytm(w["rytm"])
        dlugosc = int(np.lcm(dl_prog, dl_r))
        ostatni = None
        for przes_r in range(0, dlugosc, dl_r):
            for krok, dl, znak in traf:
                k = krok + przes_r
                s, d, ak = self._akord_w(prog, k)
                if ostatni is not ak:
                    nuty = nuty_akordu(ak)
                    ostatni = ak
                koniec_akordu = (k // dl_prog) * dl_prog + s + d
                dl = min(dl, koniec_akordu - k)
                for m in nuty:
                    zd.append((k, dl, m, teoria.WAGI.get(znak, 0.85) * w.get("glosnosc", 1.0)))
        return {instr: zd}, dlugosc

    def _wzor_arp(self, w, instr):
        prog = self._prog(w)
        cfg = self.instrumenty[instr]
        lo, hi = w.get("rejestr", cfg.get("rejestr", [57, 86]))
        traf, dl_r = teoria.rytm(w.get("rytm", "x" * 16))
        dl_prog = prog[1]
        dlugosc = int(np.lcm(dl_prog, dl_r))
        oktawy = w.get("oktawy", 2)
        kolejnosc = w.get("kolejnosc", "gora")
        zd, i, ostatni, tony = [], 0, None, []
        for przes_r in range(0, dlugosc, dl_r):
            for krok, dl, znak in traf:
                k = krok + przes_r
                _, _, ak = self._akord_w(prog, k)
                if ak is not ostatni:
                    baza = [lo + (pc - lo) % 12 for pc in ak.klasy()]
                    tony = sorted({m + 12 * o for o in range(oktawy) for m in baza if m + 12 * o <= hi})
                    if kolejnosc == "dol":
                        tony = tony[::-1]
                    elif kolejnosc == "gora_dol":
                        tony = tony + tony[-2:0:-1]
                    ostatni = ak
                m = tony[i % len(tony)]
                i += 1
                zd.append((k, dl * w.get("legato", 0.9), m, teoria.WAGI.get(znak, 0.85) * w.get("glosnosc", 1.0)))
        return {instr: zd}, dlugosc

    def _wzor_bas(self, w, instr):
        if "nuty" in w:
            return self._wzor_melodia(w, instr)
        prog = self._prog(w)
        cfg = self.instrumenty[instr]
        lo = w.get("rejestr", cfg.get("rejestr", [28, 45]))[0]
        traf, dl_r = teoria.rytm(w["rytm"])
        dl_prog = prog[1]
        dlugosc = int(np.lcm(dl_prog, dl_r))
        zd = []
        for przes_r in range(0, dlugosc, dl_r):
            for krok, dl, znak in traf:
                k = krok + przes_r
                s, d, ak = self._akord_w(prog, k)
                z = znak.lower()
                pc = ak.ton(z if z in "357" else "x")
                m = lo + (pc - lo) % 12 + (12 if z == "o" else 0)
                koniec_akordu = (k // dl_prog) * dl_prog + s + d
                glos = 1.0 if znak.isupper() else 0.85
                zd.append((k, min(dl, koniec_akordu - k) * w.get("legato", 0.95), m, glos * w.get("glosnosc", 1.0)))
        return {instr: zd}, dlugosc

    def _wzor_melodia(self, w, instr):
        zd_m, dlugosc = teoria.melodia(w["nuty"])
        tr = w.get("transpozycja", 0)
        zd = [(k, dl * w.get("legato", 0.95), m + tr, v * w.get("glosnosc", 1.0)) for k, dl, nuty, v in zd_m for m in nuty]
        return {instr: zd}, dlugosc

    def _wzor_bebny(self, w):
        wynik, dlugosc = {}, 16
        for instr, zapis in w.items():
            if instr in ("typ", "glosnosc"):
                continue
            if instr not in self.instrumenty:
                raise ValueError(f"Wzór bębnów używa nieznanego instrumentu „{instr}”")
            traf, dl = teoria.rytm(zapis)
            dlugosc = max(dlugosc, dl)
            wynik[instr] = [(k, d, None, teoria.WAGI.get(z, 0.85) * w.get("glosnosc", 1.0)) for k, d, z in traf]
        return wynik, dlugosc

    def wzor(self, nazwa):
        if nazwa not in self.wzory:
            raise ValueError(f"Sekcja używa nieznanego wzoru „{nazwa}”")
        w = self.wzory[nazwa]
        typ = w["typ"]
        if typ == "bebny":
            return self._wzor_bebny(w)
        instr = w.get("instrument", nazwa)
        if instr not in self.instrumenty:
            raise ValueError(f"Wzór „{nazwa}” gra na nieznanym instrumencie „{instr}”")
        if typ in ("petla", "tekstura", "roll"):
            return {instr: [(0, None, typ, w.get("glosnosc", 1.0))]}, None
        return {"akordy": self._wzor_akordy, "arp": self._wzor_arp, "bas": self._wzor_bas,
                "melodia": self._wzor_melodia}[typ](w, instr)

    # ---------------- cały utwór ----------------

    def zdarzenia(self):
        """Słownik instrument -> lista (krok, długość, midi|None, głośność) w krokach od początku utworu."""
        zd = {n: [] for n in self.instrumenty}
        self.sekcje = []
        start = 0
        for sek in self.spec["aranzacja"]:
            kroki = int(sek["takty"] * 16)
            self.sekcje.append({**sek, "start": start, "kroki": kroki})
            for nazwa in sek.get("graja", []):
                wzory, dl = self.wzor(nazwa)
                for instr, lista in wzory.items():
                    if dl is None and lista[0][2] == "roll":
                        zd[instr] += self._roll(start, kroki, lista[0][3])
                        continue
                    if dl is None:  # pętla / tekstura: przez całą sekcję
                        zd[instr].append((start, kroki, None, lista[0][3]))
                        continue
                    for przes in range(0, kroki, dl):
                        for k, d, m, v in lista:
                            if k + przes < kroki:
                                zd[instr].append((start + k + przes, min(d, kroki - k - przes), m, v))
            start += kroki
        self.kroki = start
        return zd

    @staticmethod
    def _roll(start, kroki, glos):
        """Werbel przyspieszający do dropu: ćwierćnuty, ósemki, szesnastki, trzydziestodwójki."""
        zd, cwiartka = [], kroki / 4
        for faza, co in enumerate([4, 2, 1, 0.5]):
            s = faza * cwiartka
            while s < (faza + 1) * cwiartka - 1e-9:
                zd.append((start + s, co, None, glos * (0.3 + 0.7 * (s / kroki) ** 1.3)))
                s += co
        return zd

    def sekundy(self, krok, instr=""):
        """Krok -> sekundy, ze swingiem dla nieparzystych szesnastek i lekką humanizacją."""
        t = krok * self.sek_na_krok
        if abs(krok - round(krok)) < 1e-6 and int(round(krok)) % 2 == 1:
            t += self.swing * self.sek_na_krok
        hum = self.instrumenty.get(instr, {}).get("humanizacja", 0)
        if hum:
            rng = np.random.default_rng(zlib.crc32(f"{instr}{krok:.3f}".encode()))
            t += rng.normal(0, hum / 1000)
        return max(0.0, t)
