"""Stream moments: peaks of the combined hype signal -> clip windows.

For every peak:
  1. window = [peak - pre_roll_s, peak + post_roll_s]: the build-up matters
     as much as the reaction;
  2. extend the end while the hype stays above half the peak (the reaction
     goes on), up to max_s; extend the start if the clip is shorter than min_s;
  3. snap both ends to the quietest 0.1 s block nearby, and, with a
     transcript, out of any word, so no clip starts or ends mid-word.
Then rank by peak and mean hype, and keep the best `count` that do not overlap.
"""

from __future__ import annotations

from collections import Counter

import numpy as np


def local_peaks(x: np.ndarray, min_value: float, min_gap: int) -> list[int]:
    """Indices of local maxima >= min_value, at least min_gap apart (greedy, highest first)."""
    cand = [k for k in range(len(x)) if x[k] >= min_value and x[k] == x[max(0, k - min_gap):k + min_gap + 1].max()]
    kept: list[int] = []
    for k in sorted(cand, key=lambda k: -x[k]):
        if all(abs(k - j) >= min_gap for j in kept):
            kept.append(k)
    return sorted(kept)


def snap(t: float, lo: float, hi: float, loud_db: np.ndarray | None, step: float, words: list | None) -> float:
    """The quietest 0.1 s block in [lo, hi] (closest to t on ties), moved out of any word."""
    best = t
    if loud_db is not None and len(loud_db):
        a, b = max(0, int(lo / step)), min(len(loud_db), int(np.ceil(hi / step)))
        if b > a:
            seg = loud_db[a:b]
            quiet = np.where(seg <= seg.min() + 1.0)[0]
            best = (a + quiet[np.argmin(np.abs((a + quiet) * step - t))]) * step + step / 2
    if words:
        inside = next((w for w in words if w.start < best < w.end), None)
        if inside:
            best = inside.start if best - inside.start < inside.end - best else inside.end
    return round(best, 2)


def describe(k: int, sig: dict) -> list[str]:
    out = []
    if sig.get("audio_excess") is not None and sig["audio"][k] >= 0.5:
        out.append(f"streamer got loud (+{sig['audio_excess'][k]:.0f} dB over the last minutes)")
    if sig.get("chat") is not None and sig["chat"][k] >= 0.5:
        base = max(np.median(sig["chat_rate"]), 0.2)
        tops: Counter = Counter()
        for c in sig["chat_tokens"][max(0, k - 3):k + 4]:
            tops.update(c)
        emotes = ", ".join(f"{t}×{n}" for t, n in tops.most_common(3))
        out.append(f"chat exploded ({sig['chat_rate'][k] / base:.1f}× its usual rate" + (f"; {emotes}" if emotes else "") + ")")
    if sig.get("speech") is not None and sig["speech"][k] >= 0.5:
        said = sorted({s for x in sig["speech_said"][max(0, k - 2):k + 3] for s in x})
        out.append("said: " + ", ".join(f'"{s}"' for s in said[:3]))
    return out or ["combined hype peak"]


def pick(sig: dict, p: dict, duration: float, words: list | None = None) -> list[dict]:
    hype = sig["combined"]
    peaks = local_peaks(hype, p["min_hype"], int(p["min_gap_s"]))
    loud = sig.get("loudness_db")
    clips = []
    for k in peaks:
        peak_t = k + 0.5
        start, end = peak_t - p["pre_roll_s"], peak_t + p["post_roll_s"]
        j = k
        while j + 1 < len(hype) and hype[j + 1] >= 0.5 * hype[k] and (j + 1) - start < p["max_s"]:
            j += 1
        end = max(end, min(j + 1.0, start + p["max_s"]))
        if end - start < p["min_s"]:
            start = end - p["min_s"]
        start, end = max(0.0, start), min(duration, end)
        start = snap(start, start - 2.0, start + 1.0, loud, 0.1, words)
        end = snap(end, end - 1.0, end + 2.0, loud, 0.1, words)
        a, b = int(start), max(int(start) + 1, int(end))
        mean = float(hype[a:b].mean())
        clips.append({"peak": round(peak_t, 2), "start": start, "end": end, "duration": round(end - start, 2),
                      "hype_peak": round(float(hype[k]), 3), "hype_mean": round(mean, 3),
                      "score": round(0.7 * float(hype[k]) + 0.3 * mean, 4),
                      "signals": {name: round(float(sig[name][k]), 3) for name in ("audio", "chat", "speech")
                                  if sig.get(name) is not None},
                      "reasons": describe(k, sig)})
    clips.sort(key=lambda c: -c["score"])
    kept: list[dict] = []
    for c in clips:
        if all(c["end"] <= o["start"] or c["start"] >= o["end"] for o in kept):
            kept.append(c)
        if len(kept) >= p["count"]:
            break
    for rank, c in enumerate(kept, start=1):
        c["rank"] = rank
    return kept
