"""Segmentation on speaker changes (plan 6.2 deliverable 3).

1. Per frame: the raw decision is the track with the highest score at or
   above `min_score`, else nobody (None).
2. Run-length encode the raw decisions inside each shot.
3. Absorb runs shorter than `min_segment_s`, shortest first, into a
   neighbour: the previous run (the framing already on screen) when there is
   one in the shot, else the next. A short run between two runs of the same
   label joins them. This is what keeps short interjections from moving the
   frame.
4. Switch margin: a speaker run only stands if its speaker out-scores the
   previous speaker over that run by `switch_margin`; otherwise the frame
   stays on the previous speaker.
5. Merge equal neighbours, then label: speaker runs -> `speaker`; runs with
   nobody -> `hold` when the shot already framed someone, else `wide`.

Segment boundaries therefore come only from speaker changes and shot
boundaries, never from fixed intervals.
"""

from __future__ import annotations

from dataclasses import dataclass

import numpy as np


@dataclass
class Run:
    label: int | None
    a: int  # first frame position (inclusive)
    b: int  # last frame position (exclusive)
    shot: int


def raw_decisions(scores: dict[int, np.ndarray], n: int, min_score: float) -> list[int | None]:
    out: list[int | None] = [None] * n
    if not scores:
        return out
    tids = sorted(scores)
    mat = np.vstack([np.nan_to_num(scores[t], nan=-1.0) for t in tids])
    best = mat.argmax(axis=0)
    for k in range(n):
        if mat[best[k], k] >= min_score:
            out[k] = tids[best[k]]
    return out


def _runs(labels: list[int | None], shots: list[int]) -> list[Run]:
    runs: list[Run] = []
    for k, (lab, shot) in enumerate(zip(labels, shots)):
        if runs and runs[-1].label == lab and runs[-1].shot == shot:
            runs[-1].b = k + 1
        else:
            runs.append(Run(lab, k, k + 1, shot))
    return runs


def _merge_equal(runs: list[Run]) -> list[Run]:
    out: list[Run] = []
    for r in runs:
        if out and out[-1].label == r.label and out[-1].shot == r.shot:
            out[-1].b = r.b
        else:
            out.append(Run(r.label, r.a, r.b, r.shot))
    return out


def _mean(scores: dict[int, np.ndarray], tid: int | None, a: int, b: int) -> float:
    if tid is None or tid not in scores:
        return 0.0
    vals = scores[tid][a:b]
    vals = vals[~np.isnan(vals)]
    return float(vals.mean()) if vals.size else 0.0


def segment(times: np.ndarray, shots: list[int], scores: dict[int, np.ndarray], range_start: float, range_end: float,
            shot_starts: dict[int, float], min_score: float, min_segment_s: float, switch_margin: float
            ) -> tuple[list[int | None], list[dict]]:
    """Returns (raw per-frame decisions, segments as contract dicts)."""
    n = len(times)
    raw = raw_decisions(scores, n, min_score)
    # Run boundaries in time: a run covers [time of its first frame, time of the next run's first frame).
    bounds = np.append(times, range_end)

    def start_of(r: Run) -> float:
        return shot_starts.get(r.shot, range_start) if r.a == 0 or shots[r.a - 1] != r.shot else float(times[r.a])

    def end_of(r: Run) -> float:
        return float(bounds[r.b])

    def duration(r: Run) -> float:
        return end_of(r) - start_of(r)

    runs = _runs(raw, shots)

    # 3. Absorb short runs, shortest first.
    while True:
        shot_sizes = {}
        for r in runs:
            shot_sizes[r.shot] = shot_sizes.get(r.shot, 0) + 1
        candidates = [(duration(r), k) for k, r in enumerate(runs) if duration(r) < min_segment_s and shot_sizes[r.shot] > 1]
        if not candidates:
            break
        _, k = min(candidates)
        r = runs[k]
        prev = runs[k - 1] if k > 0 and runs[k - 1].shot == r.shot else None
        nxt = runs[k + 1] if k + 1 < len(runs) and runs[k + 1].shot == r.shot else None
        if prev is not None and nxt is not None and prev.label == nxt.label:
            r.label = prev.label
        elif prev is not None:
            r.label = prev.label
        else:
            r.label = nxt.label
        runs = _merge_equal(runs)

    # 4. Switch margin against the previous speaker in the same shot.
    changed = True
    while changed:
        changed = False
        for k in range(1, len(runs)):
            r, prev = runs[k], runs[k - 1]
            if r.label is None or prev.label is None or prev.shot != r.shot or r.label == prev.label:
                continue
            lead = _mean(scores, r.label, r.a, r.b) - _mean(scores, prev.label, r.a, r.b)
            if lead < switch_margin:
                r.label = prev.label
                runs = _merge_equal(runs)
                changed = True
                break

    # 5. Label.
    segments = []
    framed_in_shot: set[int] = set()
    for r in runs:
        start, end = start_of(r), end_of(r)
        if r.label is not None:
            framed_in_shot.add(r.shot)
            own = _mean(scores, r.label, r.a, r.b)
            others = [(_mean(scores, t, r.a, r.b), t) for t in scores if t != r.label]
            best_other = max(others, default=(0.0, None))
            seg = {"speaker": r.label, "kind": "speaker", "confidence": round(own, 3),
                   "reason": f"track {r.label} mean score {own:.2f}"
                             + (f" vs track {best_other[1]} {best_other[0]:.2f}" if best_other[1] is not None else "")}
        elif r.shot in framed_in_shot:
            seg = {"speaker": None, "kind": "hold", "confidence": 0.0,
                   "reason": "nobody above min_score; holding the previous framing"}
        else:
            seg = {"speaker": None, "kind": "wide", "confidence": 0.0,
                   "reason": "nobody above min_score and nothing framed yet in this shot"}
        segments.append({"index": len(segments), "start": round(start, 4), "end": round(end, 4), **seg})
    return raw, segments
