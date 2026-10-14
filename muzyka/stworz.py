#!/usr/bin/env python3
"""Renderuje utwór z pliku JSON.

    python3 muzyka/stworz.py muzyka/utwory/glebia.json
    python3 muzyka/stworz.py muzyka/utwory/glebia.json --stemy
    python3 muzyka/stworz.py muzyka/utwory/glebia.json --od 32 --takty 16   # szybki podgląd fragmentu

W katalogu wyjściowym powstaje:
    <nazwa>.mp3   gotowy miks do odsłuchu (320 kb/s)
    <nazwa>.wav   ten sam miks, 16 bit / 44,1 kHz
    <nazwa>.mid   wszystkie nuty – do FL Studio / Abletona / Logica
    stemy/*.flac  (z --stemy) każdy instrument osobno, 24 bit
    opis.json     tempo, progresje, sekcje z czasami startu
"""

import argparse
import copy
import json
import os
import re
import sys
import time

import numpy as np

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from silnik import aranzacja, eksport, miks  # noqa: E402


def slug(tekst):
    return re.sub(r"[^a-z0-9]+", "-", eksport.ascii(tekst).lower()).strip("-") or "utwor"


def fragment(spec, od, takty):
    """Wycina takty [od, od+takty) z aranżacji – do szybkich odsłuchów."""
    nowa, poz = [], 0
    for sek in spec["aranzacja"]:
        a, b = max(poz, od), min(poz + sek["takty"], od + takty)
        if a < b:
            s = copy.deepcopy(sek)
            s["takty"] = b - a
            nowa.append(s)
        poz += sek["takty"]
    spec = copy.deepcopy(spec)
    spec["aranzacja"] = nowa
    return spec


PASMA = [(20, 60), (60, 150), (150, 400), (400, 1500), (1500, 5000), (5000, 12000), (12000, 20000)]


def analiza(u, m, sciezki, mix):
    """Pomiary do kontroli miksu: poziomy śladów w najgłośniejszej sekcji i rozkład pasm."""
    sek = max(u.sekcje, key=lambda s: len(s.get("graja", [])))
    a, b = m.p(sek["start"]), m.p(sek["start"] + sek["kroki"])
    calosc = miks.rms_db(sum(sciezki.values())[:, a:b])
    poziomy = {n: round(miks.rms_db(x[:, a:b]) - calosc, 1) for n, x in sciezki.items()}
    poziomy = dict(sorted(((n, v) for n, v in poziomy.items() if v > -60), key=lambda kv: -kv[1]))
    x = mix[:, a:b].mean(axis=0)
    widmo = np.abs(np.fft.rfft(x)) ** 2
    f = np.fft.rfftfreq(len(x), 1 / 44100)
    pasma = {f"{lo}-{hi}": round(float(10 * np.log10(widmo[(f >= lo) & (f < hi)].sum() / widmo.sum())), 1) for lo, hi in PASMA}
    korelacja = float(np.corrcoef(mix[0, a:b], mix[1, a:b])[0, 1])
    return {"sekcja": sek.get("sekcja"), "slady_db": poziomy, "pasma_db": pasma, "korelacja_lr": round(korelacja, 2)}


def renderuj(spec, katalog, stemy=False):
    t0 = time.time()
    u = aranzacja.Utwor(spec)
    m = miks.Miks(u)
    print(f"▶ {u.tytul}: {u.bpm:g} BPM, {len(u.spec['aranzacja'])} sekcji")
    sciezki = m.renderuj()
    mix, info = miks.master(sciezki, spec.get("master", {}).get("lufs", -9.0))

    os.makedirs(katalog, exist_ok=True)
    nazwa = slug(u.tytul)
    wav = os.path.join(katalog, nazwa + ".wav")
    mp3 = os.path.join(katalog, nazwa + ".mp3")
    eksport.wav(wav, mix)
    eksport.mp3(wav, mp3, u.tytul)
    eksport.midi(os.path.join(katalog, nazwa + ".mid"), u, m.zd)
    if stemy:
        skala = 1 / max(abs(sum(sciezki.values())).max(), 1e-9)
        os.makedirs(os.path.join(katalog, "stemy"), exist_ok=True)
        for n, x in sciezki.items():
            eksport.flac(os.path.join(katalog, "stemy", n + ".flac"), x * skala)

    opis = eksport.opis(u)
    opis.update(info)
    opis["analiza"] = analiza(u, m, sciezki, mix)
    with open(os.path.join(katalog, "opis.json"), "w", encoding="utf-8") as f:
        json.dump(opis, f, ensure_ascii=False, indent=2)
    print(f"✔ {opis['dlugosc']}, {info['lufs']} LUFS, kompresor do -{info['kompresor_db']} dB, render {time.time() - t0:.0f} s")
    for s in opis["sekcje"]:
        print(f"    {s['od']:>5}  {s['nazwa']:<10} {s['takty']:>2} t.")
    an = opis["analiza"]
    print(f"    analiza ({an['sekcja']}): " + ", ".join(f"{k} {v}" for k, v in an["slady_db"].items()))
    print("    pasma: " + "  ".join(f"{k}:{v}" for k, v in an["pasma_db"].items()) + f"   korelacja L/R {an['korelacja_lr']}")
    print(f"    → {mp3}")
    return mp3


def main():
    p = argparse.ArgumentParser(description="Pipeline muzyki klubowej / letniej")
    p.add_argument("utwor", help="plik JSON z opisem utworu")
    p.add_argument("--wyjscie", help="katalog wyjściowy (domyślnie muzyka/wyjscie/<nazwa>)")
    p.add_argument("--stemy", action="store_true", help="zapisz każdy instrument osobno (FLAC 24 bit)")
    p.add_argument("--od", type=int, help="renderuj fragment od tego taktu (liczone od 0)")
    p.add_argument("--takty", type=int, default=16, help="długość fragmentu w taktach (z --od)")
    a = p.parse_args()

    with open(a.utwor, encoding="utf-8") as f:
        spec = json.load(f)
    nazwa = slug(spec.get("tytul", "utwor"))
    if a.od is not None:
        spec = fragment(spec, a.od, a.takty)
        nazwa += f"-takty{a.od}-{a.od + a.takty}"
    katalog = a.wyjscie or os.path.join(os.path.dirname(os.path.abspath(__file__)), "wyjscie", nazwa)
    renderuj(spec, katalog, stemy=a.stemy)


if __name__ == "__main__":
    main()
