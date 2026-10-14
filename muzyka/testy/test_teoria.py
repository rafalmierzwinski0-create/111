"""Testy zapisu muzycznego: python3 -m pytest muzyka/testy  (albo: python3 muzyka/testy/test_teoria.py)"""

import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from silnik import aranzacja, teoria  # noqa: E402

UTWORY = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "utwory")


def test_nuty():
    assert teoria.nuta("A4") == 69
    assert teoria.nuta("C#5") == 73
    assert teoria.nuta("Bb3") == 58
    assert teoria.nuta("H4") == 71


def test_akordy():
    assert sorted(teoria.Akord("F#m9").klasy()) == sorted([6, 9, 1, 4, 8])
    assert teoria.Akord("Ebmaj9/G").bas == 7
    assert teoria.Akord("C#7sus4").ton("3") == 6  # brak tercji -> kwarta
    assert teoria.Akord("Am9").ton("5") == 4


def test_voicing_bez_prymy_i_ze_slashem():
    v = teoria.ulozenie(teoria.Akord("Ebmaj9/G"), None, 55, 79)
    klasy = {m % 12 for m in v}
    assert 3 in klasy and 7 not in klasy  # Eb zostaje, G gra bas
    v = teoria.ulozenie(teoria.Akord("Am9"), None, 55, 79)
    assert 9 not in {m % 12 for m in v}  # pryma A w basie


def test_progresja_i_dlugosci():
    akordy, dl = teoria.progresja("Am9 | Gsus4:8 G:8 | C D E F")
    assert dl == 48
    assert [(s, d) for s, d, _ in akordy][:3] == [(0, 16), (16, 8), (24, 8)]


def test_rytm():
    traf, dl = teoria.rytm("x--.o-..|....x---")
    assert dl == 16
    assert [t[:2] for t in traf] == [[0, 3], [4, 2], [12, 4]]


def test_melodia_pilnuje_taktow():
    zd, dl = teoria.melodia(["C5:4 r:4 E5,G5:8", "A5:16"])
    assert dl == 32 and zd[1][2] == [76, 79]
    try:
        teoria.melodia(["C5:4 D5:4", "E5:16"])
    except ValueError as e:
        assert "Takt 1" in str(e)
    else:
        raise AssertionError("powinien być błąd długości taktu")


def test_utwory_sie_skladaja():
    for plik in sorted(os.listdir(UTWORY)):
        with open(os.path.join(UTWORY, plik), encoding="utf-8") as f:
            u = aranzacja.Utwor(json.load(f))
        zd = u.zdarzenia()
        assert u.kroki > 0 and sum(len(v) for v in zd.values()) > 100, plik


if __name__ == "__main__":
    for nazwa, f in list(globals().items()):
        if nazwa.startswith("test_"):
            f()
            print("ok", nazwa)
