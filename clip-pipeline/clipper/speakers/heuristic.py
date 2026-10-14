"""Heuristic active-speaker backend: lip motion, optionally gated by audio (plan 6.2 deliverable 2).

For every face of a track:
  mouth = inner-lip aperture / mouth width  +  jaw_weight * jawOpen blendshape
The mouth signal of someone talking oscillates (syllables open and close the
mouth several times per second). Someone listening, smiling or holding the
mouth open has a mouth signal that is steady. So the score is driven by the
*variability* of the signal in a short window, not by how open the mouth is:

  articulation(t) = standard deviation of `mouth` within ±window_s
                    after removing the track's slow trend (a ~1 s moving median)

Removing the slow trend is the per-track normalisation: different resting
mouth shapes, a held smile or a slowly opening jaw do not count. The score is
then a logistic function of articulation, multiplied by penalties for strong
head yaw and for small faces, and by the speech gate when the audio track is
usable (no speech energy -> nobody is speaking).
"""

from __future__ import annotations

import numpy as np

from clipper import audio
from clipper.contracts import landmark_group
from clipper.detect.landmarks import LOWER_LIP_INNER, MOUTH_CORNER_LEFT, MOUTH_CORNER_RIGHT, UPPER_LIP_INNER
from clipper.speakers.base import ASDBackend, ASDContext, track_series


def moving_stat(t: np.ndarray, x: np.ndarray, half_window: float, fn) -> np.ndarray:
    """fn over the samples of x whose time is within ±half_window of each t (t sorted)."""
    lo = np.searchsorted(t, t - half_window, side="left")
    hi = np.searchsorted(t, t + half_window, side="right")
    return np.array([fn(x[a:b]) for a, b in zip(lo, hi)])


def mouth_signal(faces: list[dict], det: dict, jaw_weight: float) -> np.ndarray:
    pos = {idx: k for k, idx in enumerate(det["landmark_spec"]["indices"])}
    w, h = det["source"]["width"], det["source"]["height"]
    out = np.empty(len(faces))
    for k, face in enumerate(faces):
        pts = np.asarray(face["landmarks"], dtype=float)[:, :2] * (w, h)
        gap = np.linalg.norm(pts[pos[UPPER_LIP_INNER]] - pts[pos[LOWER_LIP_INNER]])
        width = np.linalg.norm(pts[pos[MOUTH_CORNER_LEFT]] - pts[pos[MOUTH_CORNER_RIGHT]])
        jaw = face.get("blendshapes", {}).get("jawOpen", 0.0)
        out[k] = (gap / width if width > 0 else 0.0) + jaw_weight * jaw
    return out


class HeuristicBackend(ASDBackend):
    name = "heuristic"
    version = "1"

    def params(self) -> dict:
        return dict(self.cfg["speakers"]["heuristic"])

    def score(self, ctx: ASDContext) -> dict[int, np.ndarray]:
        p = self.params()
        det = ctx.detections
        times = ctx.times
        landmark_group(det, "lips_inner")  # fails early if the detections lack lip landmarks

        gate = None
        if p["audio_gating"]:
            samples = ctx.audio()
            if samples is None:
                self.notes.append("no audio stream; gating disabled")
            else:
                level = audio.rms_db(samples, times, det["range"]["start"], window_s=p["audio_window_s"])
                gate, note = audio.speech_gate(level, p["silence_db"], p["speech_margin_db"], p["speech_floor_db"],
                                               times=times, hold_s=p["gate_hold_s"],
                                               noise_percentile=p["noise_percentile"])
                self.notes.append(note)
        else:
            self.notes.append("audio gating off by config")

        out: dict[int, np.ndarray] = {}
        for tid, series in track_series(det).items():
            idx = np.array([k for k, _ in series])
            faces = [f for _, f in series]
            t = times[idx]
            m = mouth_signal(faces, det, p["jaw_weight"])
            trend = moving_stat(t, m, p["trend_window_s"] / 2, np.median)
            articulation = moving_stat(t, m - trend, p["window_s"], lambda x: float(np.std(x)) if len(x) >= 3 else 0.0)
            s = 1.0 / (1.0 + np.exp(-(articulation - p["articulation_mid"]) / p["articulation_scale"]))

            yaw = np.array([abs(f.get("pose", {}).get("yaw", 0.0)) for f in faces])
            s *= np.clip(1.0 - (yaw - p["yaw_full"]) / (p["yaw_zero"] - p["yaw_full"]), 0.0, 1.0)
            height = np.array([f["bbox"][3] for f in faces])
            s *= np.clip(height / p["min_face_height"], 0.0, 1.0)
            if gate is not None:
                s *= gate[idx]

            scores = np.full(len(times), np.nan)
            scores[idx] = np.clip(s, 0.0, 1.0)
            out[tid] = scores
        return out
