import numpy as np

from clipper.audio import rms_db, speech_gate
from clipper.checks.labels import desired_framing, evaluate


def test_rms_db_and_gate():
    sr = 16000
    t = np.arange(4 * sr) / sr
    x = np.where((t > 1) & (t < 2), 0.3 * np.sin(2 * np.pi * 220 * t), 0.0005 * np.random.default_rng(0).standard_normal(len(t)))
    times = np.arange(0, 4, 0.04)
    level = rms_db(x.astype(np.float32), times, 0.0, 0.05, sr)
    gate, note = speech_gate(level, -60, 12, -45)
    assert gate[(times > 1.2) & (times < 1.8)].min() == 1.0
    assert gate[(times > 2.5)].max() == 0.0
    assert "threshold" in note


def test_gate_stays_open_for_nearly_continuous_speech():
    times = np.arange(0, 60, 0.04)
    level = np.full(len(times), -20.0)
    level[::20] = -65.0  # 5 % of windows are pauses between words
    gate, _ = speech_gate(level, -60, 12, -45)
    assert gate[level > -30].min() == 1.0 and gate[level < -60].max() == 0.0


def test_gate_disabled_for_digital_silence_and_hold_bridges_pauses():
    times = np.arange(0, 2, 0.04)
    assert speech_gate(np.full(len(times), -91.0), -60, 12, -45)[0] is None
    level = np.full(len(times), -70.0)
    level[:20] = -20
    level[25:] = -20  # 0.2 s pause between words
    gate, _ = speech_gate(level, -60, 12, -45, times=times, hold_s=0.3)
    assert gate.min() == 1.0


LABELS = {"intervals": [
    {"start": 0, "end": 6, "speaker": "left", "kind": "turn"},
    {"start": 6, "end": 6.5, "speaker": "right", "kind": "interjection"},
    {"start": 6.5, "end": 12, "speaker": "left", "kind": "turn"},
    {"start": 12, "end": 14, "speaker": None, "kind": "silence"},
]}


def test_desired_framing():
    assert desired_framing(LABELS, 3) == "left"
    assert desired_framing(LABELS, 6.2) == "left"
    assert desired_framing(LABELS, 13) is True


def test_evaluate_counts_interjection_that_moved_the_frame():
    det = {"tracks": [{"track_id": 0, "mean_bbox": [0.2, 0.3, 0.1, 0.2]}, {"track_id": 1, "mean_bbox": [0.7, 0.3, 0.1, 0.2]}]}
    frames = [{"t": k / 10, "raw_active": 0} for k in range(140)]
    good = {"frames": frames, "segments": [{"index": 0, "start": 0, "end": 14, "speaker": 0, "kind": "speaker"}]}
    bad = {"frames": frames, "segments": [
        {"index": 0, "start": 0, "end": 6, "speaker": 0, "kind": "speaker"},
        {"index": 1, "start": 6, "end": 8, "speaker": 1, "kind": "speaker"},
        {"index": 2, "start": 8, "end": 14, "speaker": 0, "kind": "speaker"}]}
    assert evaluate(good, det, LABELS)["framing_accuracy"] == 1.0
    ev = evaluate(bad, det, LABELS)
    assert ev["interjections_that_moved_frame"] == [6] and ev["framing_accuracy"] < 1.0
