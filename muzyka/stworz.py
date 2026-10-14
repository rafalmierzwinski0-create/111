#!/usr/bin/env python3
"""Renderuje utwór z pliku JSON.

    python3 muzyka/stworz.py muzyka/utwory/letni-zachod.json
    python3 muzyka/stworz.py muzyka/utwory/letni-zachod.json --warianty 3
    python3 muzyka/stworz.py muzyka/utwory/letni-zachod.json --seed 12 --stemy

W katalogu wyjściowym powstaje:
    <nazwa>.mp3   gotowy miks do odsłuchu (320 kb/s)
    <nazwa>.wav   ten sam miks, 16 bit / 44,1 kHz
    <nazwa>.mid   wszystkie nuty – do zaimportowania w FL Studio / Ableton / Logic
    stemy/*.flac  (z --stemy) grupy śladów osobno, 24 bit, do dalszego miksu w DAW
    opis.json     tempo, akordy, sekcje z czasami – żeby łatwo mówić „w 1:23 zmień…”
"""

import argparse
import json
import os
import re
import subprocess
import sys
import time
import unicodedata

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import silnik  # noqa: E402


def slug(tekst):
    t = unicodedata.normalize("NFKD", tekst.replace("ł", "l").replace("Ł", "L"))
    t = t.encode("ascii", "ignore").decode()
    return re.sub(r"[^a-z0-9]+", "-", t.lower()).strip("-") or "utwor"


GRUPY_STEMOW = {
    "1-kick": ["kick"],
    "2-perkusja": ["clap", "hats", "openhat", "shaker", "perc"],
    "3-bas": ["bas"],
    "4-akordy": ["akordy"],
    "5-pad": ["pad"],
    "6-lead": ["lead"],
    "7-arp": ["arp"],
    "8-fx": ["riser", "roll", "crash"],
    "9-poglos-echo": ["poglos", "echo"],
}


def renderuj(spec, seed, katalog, stemy=False):
    t0 = time.time()
    u = silnik.Utwor(spec, seed)
    r = silnik.Render(u)
    sciezki = r.miks()
    mix, zysk = silnik.master(sciezki, spec.get("glosnosc_rms_db", -9.0))

    os.makedirs(katalog, exist_ok=True)
    nazwa = slug(u.tytul)
    wav = os.path.join(katalog, nazwa + ".wav")
    mp3 = os.path.join(katalog, nazwa + ".mp3")
    silnik.zapisz_wav(wav, mix)
    subprocess.run(["ffmpeg", "-y", "-loglevel", "error", "-i", wav, "-codec:a", "libmp3lame", "-b:a", "320k",
                    "-metadata", f"title={u.tytul}", "-metadata", f"comment=seed {u.seed}", mp3], check=True)
    silnik.zapisz_midi(os.path.join(katalog, nazwa + ".mid"), u, r.zd)
    if stemy:
        os.makedirs(os.path.join(katalog, "stemy"), exist_ok=True)
        for n, skladniki in GRUPY_STEMOW.items():
            x = sum(sciezki[s] for s in skladniki) * zysk
            if abs(x).max() > 1e-6:
                tmp = os.path.join(katalog, "stemy", n + ".tmp.wav")
                silnik.zapisz_wav(tmp, x, bity=32)
                subprocess.run(["ffmpeg", "-y", "-loglevel", "error", "-i", tmp, "-c:a", "flac",
                                "-sample_fmt", "s32", "-bits_per_raw_sample", "24",
                                os.path.join(katalog, "stemy", n + ".flac")], check=True)
                os.remove(tmp)

    info = silnik.opis(u)
    info["glosnosc_lufs"] = silnik.glosnosc_lufs(wav)
    with open(os.path.join(katalog, "opis.json"), "w", encoding="utf-8") as f:
        json.dump(info, f, ensure_ascii=False, indent=2)

    print(f"✔ {u.tytul} (seed {u.seed}) – {info['dlugosc']}, {u.bpm:g} BPM, {info['tonacja']}, "
          f"{' – '.join(info['akordy'])}, {info['glosnosc_lufs']} LUFS, render {time.time() - t0:.1f} s")
    for s in info["sekcje"]:
        print(f"    {s['od']:>5}  {s['nazwa']:<8} {s['takty']:>2} t.  {', '.join(s['elementy'])}")
    print(f"    → {mp3}")
    return mp3


def main():
    p = argparse.ArgumentParser(description="Pipeline muzyki klubowej / letniej")
    p.add_argument("utwor", help="plik JSON z opisem utworu")
    p.add_argument("--seed", type=int, help="nadpisuje seed z pliku (inna melodia i perkusja)")
    p.add_argument("--warianty", type=int, default=1, help="ile wersji z kolejnymi seedami wyrenderować")
    p.add_argument("--wyjscie", default=None, help="katalog wyjściowy (domyślnie muzyka/wyjscie/<nazwa>)")
    p.add_argument("--stemy", action="store_true", help="zapisz też osobne ślady (FLAC 24 bit) do DAW")
    a = p.parse_args()

    with open(a.utwor, encoding="utf-8") as f:
        spec = json.load(f)
    baza = a.seed if a.seed is not None else int(spec.get("seed", 1))
    korzen = a.wyjscie or os.path.join(os.path.dirname(os.path.abspath(__file__)), "wyjscie",
                                       slug(spec.get("tytul", "utwor")))
    for i in range(a.warianty):
        seed = baza + i
        katalog = korzen if a.warianty == 1 else f"{korzen}-seed{seed}"
        renderuj(spec, seed, katalog, stemy=a.stemy)


if __name__ == "__main__":
    main()
