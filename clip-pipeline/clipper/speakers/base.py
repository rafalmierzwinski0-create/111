"""The swappable active-speaker interface (plan 6.2 deliverable 1).

A backend turns Phase 1 detections (plus, optionally, the source audio) into a
score in [0, 1] per frame per track: how likely it is that this face is the
one speaking. Everything downstream (segmentation, crop planning) only sees
these scores, so backends can be swapped with `speakers.backend` / `--asd`.
"""

from __future__ import annotations

from abc import ABC, abstractmethod
from dataclasses import dataclass, field

import numpy as np

from clipper import audio


@dataclass
class ASDContext:
    detections: dict
    cfg: dict
    _audio: np.ndarray | None = field(default=None, repr=False)
    _audio_loaded: bool = False

    @property
    def source(self) -> dict:
        return self.detections["source"]

    @property
    def times(self) -> np.ndarray:
        return np.array([f["t"] for f in self.detections["frames"]])

    def audio(self) -> np.ndarray | None:
        """Mono 16 kHz samples of the detections' range (None without an audio stream)."""
        if not self._audio_loaded:
            rng = self.detections["range"]
            self._audio = audio.load_mono(self.source["path"], rng["start"], rng["end"]) if self.source.get("has_audio") else None
            self._audio_loaded = True
        return self._audio


class ASDBackend(ABC):
    name: str = ""
    version: str = "1"

    def __init__(self, cfg: dict):
        self.cfg = cfg
        self.notes: list[str] = []

    @abstractmethod
    def params(self) -> dict:
        """Settings that influence the scores (recorded in speakers.json and the fingerprint)."""

    @abstractmethod
    def score(self, ctx: ASDContext) -> dict[int, np.ndarray]:
        """Per non-spurious track: array aligned with detections["frames"], score in [0, 1], NaN where not visible."""


def track_series(detections: dict) -> dict[int, list[tuple[int, dict]]]:
    """Non-spurious tracks: list of (frame position, face)."""
    spurious = {t["track_id"] for t in detections["tracks"] if t["spurious"]}
    out: dict[int, list[tuple[int, dict]]] = {}
    for k, f in enumerate(detections["frames"]):
        for face in f["faces"]:
            if face["track_id"] not in spurious:
                out.setdefault(face["track_id"], []).append((k, face))
    return out


def get_backend(name: str, cfg: dict) -> ASDBackend:
    if name == "heuristic":
        from clipper.speakers.heuristic import HeuristicBackend
        return HeuristicBackend(cfg)
    if name == "lrasd":
        from clipper.speakers.lrasd import LRASDBackend
        return LRASDBackend(cfg)
    raise ValueError(f"unknown active-speaker backend {name!r} (choose: heuristic, lrasd)")
