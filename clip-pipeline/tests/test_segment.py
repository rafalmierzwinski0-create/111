import numpy as np

from clipper.speakers.segment import raw_decisions, segment

FPS = 25


def make_scores(pattern, n_tracks=2):
    """pattern: list of (seconds, active_track or None); active track gets 0.9, others 0.05."""
    n = int(sum(d for d, _ in pattern) * FPS)
    scores = {t: np.full(n, 0.05) for t in range(n_tracks)}
    k = 0
    for dur, who in pattern:
        m = int(dur * FPS)
        if who is not None:
            scores[who][k:k + m] = 0.9
        k += m
    times = np.arange(n) / FPS
    return times, scores


def run(pattern, shots=None, **kw):
    times, scores = make_scores(pattern)
    shots = shots if shots is not None else [0] * len(times)
    params = dict(min_score=0.5, min_segment_s=2.0, switch_margin=0.15)
    params.update(kw)
    shot_starts = {s: float(times[shots.index(s)]) for s in set(shots)}
    return segment(times, shots, scores, 0.0, len(times) / FPS, shot_starts, **params)


def test_raw_decision_threshold():
    raw = raw_decisions({0: np.array([0.9, 0.4, np.nan]), 1: np.array([0.1, 0.45, 0.6])}, 3, 0.5)
    assert raw == [0, None, 1]


def test_interjection_does_not_switch():
    _, segs = run([(6, 0), (0.6, 1), (6, 0)])
    assert [(s["kind"], s["speaker"]) for s in segs] == [("speaker", 0)]


def test_real_turns_switch_at_the_change():
    _, segs = run([(6, 0), (6, 1), (6, 0)])
    assert [s["speaker"] for s in segs] == [0, 1, 0]
    assert abs(segs[1]["start"] - 6.0) < 1e-6 and abs(segs[2]["start"] - 12.0) < 1e-6


def test_segments_cover_range_and_respect_minimum():
    _, segs = run([(3, 0), (1.0, 1), (0.3, None), (4, 1), (1.5, 0), (5, 1)])
    assert segs[0]["start"] == 0.0 and abs(segs[-1]["end"] - 14.8) < 1e-6
    for a, b in zip(segs, segs[1:]):
        assert abs(a["end"] - b["start"]) < 1e-9
    assert all(s["end"] - s["start"] >= 2.0 - 1e-9 for s in segs[1:-1])


def test_long_silence_becomes_hold_after_a_speaker():
    _, segs = run([(5, 0), (3, None), (5, 1)])
    assert [s["kind"] for s in segs] == ["speaker", "hold", "speaker"]
    assert segs[1]["speaker"] is None


def test_silence_before_anyone_spoke_is_wide():
    _, segs = run([(3, None), (5, 0)])
    assert segs[0]["kind"] == "wide"


def test_switch_margin_keeps_current_speaker_when_lead_is_small():
    times = np.arange(10 * FPS) / FPS
    a = np.full(len(times), 0.7)
    b = np.full(len(times), 0.2)
    b[5 * FPS:] = 0.75  # barely ahead of 0.7
    _, segs = segment(times, [0] * len(times), {0: a, 1: b}, 0.0, 10.0, {0: 0.0},
                      min_score=0.5, min_segment_s=2.0, switch_margin=0.15)
    assert [s["speaker"] for s in segs] == [0]


def test_shot_boundary_always_splits():
    pattern = [(4, 0), (4, 0)]
    times, _ = make_scores(pattern)
    shots = [0] * (4 * FPS) + [1] * (4 * FPS)
    _, segs = run(pattern, shots=shots)
    assert [s["start"] for s in segs] == [0.0, 4.0]
