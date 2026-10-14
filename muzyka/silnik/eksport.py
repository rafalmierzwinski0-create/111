"""Zapis wyników: WAV, MP3, MIDI, stemy FLAC, opis sekcji."""

import os
import subprocess
import unicodedata

import numpy as np
from scipy.io import wavfile

from . import teoria
from .brzmienia import SR


def ascii(tekst):
    t = unicodedata.normalize("NFKD", tekst.replace("ł", "l").replace("Ł", "L"))
    return t.encode("ascii", "ignore").decode()


def wav(sciezka, x, bity=16):
    x = np.asarray(x).T
    if bity == 16:
        rng = np.random.default_rng(0)
        dither = (rng.random(x.shape) - rng.random(x.shape)) / 32768
        wavfile.write(sciezka, SR, np.clip((x + dither) * 32767, -32768, 32767).astype(np.int16))
    else:
        wavfile.write(sciezka, SR, x.astype(np.float32))


def mp3(wav_sciezka, mp3_sciezka, tytul):
    subprocess.run(["ffmpeg", "-y", "-loglevel", "error", "-i", wav_sciezka, "-codec:a", "libmp3lame",
                    "-b:a", "320k", "-metadata", f"title={tytul}", mp3_sciezka], check=True)


def flac(sciezka, x):
    tmp = sciezka + ".tmp.wav"
    wav(tmp, x, bity=32)
    subprocess.run(["ffmpeg", "-y", "-loglevel", "error", "-i", tmp, "-c:a", "flac", "-sample_fmt", "s32",
                    "-bits_per_raw_sample", "24", sciezka], check=True)
    os.remove(tmp)


def midi(sciezka, utwor, zdarzenia):
    import mido

    plik = mido.MidiFile(type=1, ticks_per_beat=480)
    t0 = mido.MidiTrack()
    t0.append(mido.MetaMessage("track_name", name=ascii(utwor.tytul)))
    t0.append(mido.MetaMessage("set_tempo", tempo=mido.bpm2tempo(utwor.bpm)))
    plik.tracks.append(t0)
    kanal = 0
    bebny = []
    for nazwa, lista in zdarzenia.items():
        cfg = utwor.instrumenty[nazwa]
        if not lista:
            continue
        if cfg.get("_bebny"):
            bebny += [(k, min(d, 1), cfg.get("_gm", 39), v) for k, d, _, v in lista]
            continue
        if any(m is None for _, _, m, _ in lista):  # pętle i tekstury nie mają nut
            continue
        _sciezka(plik, f"{nazwa} ({cfg['barwa']})", kanal, lista)
        kanal = kanal + 1 if kanal + 1 != 9 else 10
    if bebny:
        _sciezka(plik, "bebny", 9, bebny)
    plik.save(sciezka)


def _sciezka(plik, nazwa, kanal, lista):
    import mido

    tr = mido.MidiTrack()
    tr.append(mido.MetaMessage("track_name", name=ascii(nazwa)))
    zd = []
    for k, d, m, v in lista:
        t = int(round(k * 120))
        zd.append((t, 1, m, max(1, min(127, int(v * 110)))))
        zd.append((t + max(1, int(round(d * 120))), 0, m, 0))
    zd.sort(key=lambda e: (e[0], e[1]))
    teraz = 0
    for t, on, m, v in zd:
        tr.append(mido.Message("note_on" if on else "note_off", note=int(m), velocity=v, channel=kanal, time=t - teraz))
        teraz = t
    plik.tracks.append(tr)


def czas(s):
    return f"{int(s // 60)}:{int(s % 60):02d}"


def opis(utwor):
    sek = utwor.sek_na_krok
    return {
        "tytul": utwor.tytul,
        "bpm": utwor.bpm,
        "dlugosc": czas(utwor.kroki * sek),
        "progresje": {k: utwor.spec["progresje"][k] for k in utwor.spec.get("progresje", {})},
        "sekcje": [{"nazwa": s.get("sekcja", "?"), "od": czas(s["start"] * sek), "takty": s["kroki"] // 16,
                    "graja": s.get("graja", []), "fx": s.get("fx", [])} for s in utwor.sekcje],
        "instrumenty": {k: v["barwa"] for k, v in utwor.instrumenty.items()},
    }


def nazwa_nuty(m):
    return teoria.nazwa_nuty(m)
