"""Scoring speakers.json against a labelled fixture (plan 6.2 check 7)."""

from __future__ import annotations

import json
from pathlib import Path

import numpy as np


def load_labels(path: str | Path) -> dict:
    return json.loads(Path(path).read_text(encoding="utf-8"))


def side_of_tracks(detections: dict) -> dict[int, str]:
    """Labels name people by side ("left" / "right"); map track ids by mean box centre."""
    return {t["track_id"]: ("left" if t["mean_bbox"][0] + t["mean_bbox"][2] / 2 < 0.5 else "right")
            for t in detections["tracks"]}


def interval_at(labels: dict, t: float) -> dict | None:
    for iv in labels["intervals"]:
        if iv["start"] <= t < iv["end"]:
            return iv
    return None


def desired_framing(labels: dict, t: float) -> str | None | bool:
    """Who should be on screen: the turn's speaker; for an interjection the
    surrounding turn's speaker; True (anything is fine) during silence."""
    ivs = labels["intervals"]
    for k, iv in enumerate(ivs):
        if iv["start"] <= t < iv["end"]:
            if iv["kind"] == "turn":
                return iv["speaker"]
            if iv["kind"] == "interjection":
                prev = next((p for p in reversed(ivs[:k]) if p["kind"] == "turn"), None)
                return prev["speaker"] if prev else True
            return True
    return True


def near_boundary(labels: dict, t: float, margin: float) -> bool:
    return any(abs(t - iv["start"]) < margin or abs(t - iv["end"]) < margin for iv in labels["intervals"][1:])


def evaluate(speakers: dict, detections: dict, labels: dict, margin: float = 0.5) -> dict:
    side = side_of_tracks(detections)
    seg_starts = np.array([s["start"] for s in speakers["segments"]])
    raw_ok = raw_n = frame_ok = frame_n = 0
    for f in speakers["frames"]:
        t = f["t"]
        if near_boundary(labels, t, margin):
            continue
        iv = interval_at(labels, t)
        truth = iv["speaker"] if iv else None
        raw_n += 1
        raw_ok += side.get(f["raw_active"]) == truth
        want = desired_framing(labels, t)
        if want is True:
            continue
        seg = speakers["segments"][int(np.searchsorted(seg_starts, t, side="right")) - 1]
        shown = side.get(seg["speaker"]) if seg["kind"] == "speaker" else None
        if seg["kind"] == "hold":
            # A hold keeps the previous speaker on screen.
            prev = next((s for s in reversed(speakers["segments"][:seg["index"]]) if s["kind"] == "speaker"), None)
            shown = side.get(prev["speaker"]) if prev else None
        frame_n += 1
        frame_ok += shown == want

    # Interjections must not move the frame to the interjector.
    moved = []
    for iv in labels["intervals"]:
        if iv["kind"] != "interjection":
            continue
        for s in speakers["segments"]:
            if s["kind"] == "speaker" and side.get(s["speaker"]) == iv["speaker"] \
                    and s["start"] < iv["end"] + margin and s["end"] > iv["start"] - margin \
                    and not any(t["kind"] == "turn" and t["speaker"] == iv["speaker"]
                                and s["start"] < t["end"] and s["end"] > t["start"] for t in labels["intervals"]):
                moved.append(iv["start"])
    return {
        "raw_accuracy": raw_ok / max(1, raw_n),
        "framing_accuracy": frame_ok / max(1, frame_n),
        "interjections": sum(1 for iv in labels["intervals"] if iv["kind"] == "interjection"),
        "interjections_that_moved_frame": moved,
        "frames_scored": raw_n,
    }
