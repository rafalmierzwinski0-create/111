"""Biblioteka sampli: pobieranie z rejestru npm, indeks i wczytywanie.

Identyfikator sampla to „źródło/nazwa” bez rozszerzenia, małymi literami, np.
  909/hhcd2, 808/bd0075, sp/bd_haus, sp/loop_amen, kit/tambourine-000, ride/stereo-ride-a

Licencje: Sonic Pi – CC0, fortepian Salamander – CC BY 3.0 (Alexander Holm),
TR-808/909 – nagrania bez ograniczeń licencyjnych (machines.hyperreal.org).
"""

import os
import subprocess
import tarfile

import numpy as np

SR = 44100
KATALOG = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "sample")

PAKIETY = {
    "sp": ("supersonic-scsynth-samples@0.91.0", "package/samples"),
    "909": ("@fluid-music/tr-909@0.0.4", "package/TR909all"),
    "808": ("@fluid-music/tr-808@0.0.2", "package/TR808WAV"),
    "kit": ("@fluid-music/kit@4.0.3", "package/media"),
    "ride": ("@fluid-music/rides@0.0.2", "package/audio"),
    "piano4": ("@audio-samples/piano-mp3-velocity4@1.0.5", "package/audio"),
    "piano8": ("@audio-samples/piano-mp3-velocity8@1.0.5", "package/audio"),
    "piano12": ("@audio-samples/piano-mp3-velocity12@1.0.5", "package/audio"),
    "piano16": ("@audio-samples/piano-mp3-velocity16@1.0.5", "package/audio"),
}
ROZSZERZENIA = (".wav", ".flac", ".mp3", ".aiff")


def pobierz():
    """Ściąga paczki przez `npm pack` (bez uruchamiania skryptów) i rozpakowuje."""
    tmp = os.path.join(KATALOG, "_npm")
    os.makedirs(tmp, exist_ok=True)
    for zrodlo, (pakiet, _) in PAKIETY.items():
        cel = os.path.join(KATALOG, zrodlo)
        if os.path.isdir(cel):
            continue
        print(f"  pobieram {pakiet} …")
        wynik = subprocess.run(["npm", "pack", "--silent", "--pack-destination", tmp, pakiet],
                               capture_output=True, text=True, check=True)
        archiwum = os.path.join(tmp, wynik.stdout.strip().splitlines()[-1])
        with tarfile.open(archiwum) as tar:
            tar.extractall(cel, filter="data")
        os.remove(archiwum)


_indeks = None


def indeks():
    global _indeks
    if _indeks is None:
        if not all(os.path.isdir(os.path.join(KATALOG, z)) for z in PAKIETY):
            print("Brak biblioteki sampli – pobieram (jednorazowo, ok. 80 MB):")
            pobierz()
        _indeks = {}
        for zrodlo, (_, podkatalog) in PAKIETY.items():
            for kat, _, pliki in os.walk(os.path.join(KATALOG, zrodlo, podkatalog)):
                for p in pliki:
                    nazwa, ext = os.path.splitext(p)
                    if ext.lower() in ROZSZERZENIA:
                        _indeks.setdefault(f"{zrodlo}/{nazwa.lower()}", os.path.join(kat, p))
    return _indeks


def dekoduj(sciezka):
    """Dowolny plik audio -> float32 (2, n) przy 44,1 kHz."""
    wynik = subprocess.run(["ffmpeg", "-v", "error", "-i", sciezka, "-f", "f32le", "-ac", "2", "-ar", str(SR), "-"],
                           capture_output=True, check=True)
    return np.frombuffer(wynik.stdout, dtype=np.float32).reshape(-1, 2).T.copy()


_pamiec = {}


def wczytaj(ident, normalizuj=True):
    """Sampel po identyfikatorze (z pamięcią podręczną .npy)."""
    klucz = (ident, normalizuj)
    if klucz in _pamiec:
        return _pamiec[klucz]
    idx = indeks()
    if ident.lower() not in idx:
        podobne = [k for k in idx if ident.split("/")[-1].lower()[:4] in k][:8]
        raise KeyError(f"Nie ma sampla „{ident}”. Podobne: {', '.join(podobne) or '—'}")
    cache = os.path.join(KATALOG, "cache", ident.lower().replace("/", "__") + ".npy")
    if os.path.exists(cache):
        x = np.load(cache)
    else:
        x = dekoduj(idx[ident.lower()])
        # przytnij ciszę na początku
        prog = np.abs(x).max() * 0.01
        start = int(np.argmax(np.abs(x).max(axis=0) > prog))
        x = x[:, max(0, start - 16):]
        os.makedirs(os.path.dirname(cache), exist_ok=True)
        np.save(cache, x)
    if normalizuj:
        x = x / max(np.abs(x).max(), 1e-9)
    _pamiec[klucz] = x.astype(np.float32)
    return _pamiec[klucz]


def lista(prefiks=""):
    return sorted(k for k in indeks() if k.startswith(prefiks))
