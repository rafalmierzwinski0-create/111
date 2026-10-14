"""LR-ASD active-speaker backend (plan 6.2 deliverable 5).

LR-ASD (Junhua Liao et al., CVPR 2023 / IJCV 2025; MIT licence, code and
weights at github.com/Junhua-Liao/LR-ASD, pinned commit 1b6dcd2d) is an
audio-visual model: it looks at a face crop sequence and the audio and scores
whether that face is the one producing the speech.

The network runs through onnxruntime from an ONNX export of the original
weights (scripts/export_lrasd_onnx.py, verified against PyTorch to ~1e-6), so
users do not need PyTorch. Inputs are prepared exactly like LR-ASD's
Columbia_test.py demo, except that face boxes come from Phase 1's MediaPipe
tracks: no other face detector is used (plan 2.3).

  - 25 fps face crops: box side 2*s with s = max(w, h) / 2, median-filtered
    (13 frames), padded by crop_scale, resized to 224, grey, centre 112x112
  - audio: 16 kHz mono int16, MFCC (13 coefficients, 25 ms window, 10 ms step)
  - scores averaged over chunk lengths `durations`, as in the demo
  - logit -> sigmoid, so 0.5 is the demo's decision threshold
"""

from __future__ import annotations

import hashlib
import logging
from pathlib import Path

import cv2
import numpy as np

from clipper import video as videolib
from clipper.speakers.base import ASDBackend, ASDContext, track_series

log = logging.getLogger(__name__)

MODEL_DIR = Path(__file__).parent / "models"
MODELS = {
    "pretrain_AVA": ("lrasd_pretrain_AVA.onnx", "00b14e9904b87cc1f9121f3b08ce082a01ba5e727df694ae809efb112d8c8717"),
    "finetuning_TalkSet": ("lrasd_finetuning_TalkSet.onnx", "ab6a8adfef0ac53fb5ab4a07aa4879350a26c546099fabf382b2ee9a333666b7"),
}
FPS = 25
MIN_CHUNK_FRAMES = 4  # the ONNX graph's lower bound on frames per call


def _require():
    try:
        import onnxruntime  # noqa: F401
        import python_speech_features  # noqa: F401
        from scipy import signal  # noqa: F401
    except ImportError as exc:
        raise RuntimeError("the lrasd backend needs the optional extra: pip install -e '.[lrasd]'") from exc


class LRASDBackend(ASDBackend):
    name = "lrasd"
    version = "1"

    def __init__(self, cfg: dict):
        super().__init__(cfg)
        _require()
        p = self.params()
        if p["weights"] not in MODELS:
            raise ValueError(f"speakers.lrasd.weights must be one of {sorted(MODELS)}")
        fname, digest = MODELS[p["weights"]]
        path = MODEL_DIR / fname
        actual = hashlib.sha256(path.read_bytes()).hexdigest()
        if actual != digest:
            raise RuntimeError(f"{path} has SHA-256 {actual}, expected {digest}")
        import onnxruntime as ort
        opts = ort.SessionOptions()
        opts.log_severity_level = 3
        self.session = ort.InferenceSession(str(path), opts, providers=["CPUExecutionProvider"])

    def params(self) -> dict:
        return dict(self.cfg["speakers"]["lrasd"])

    def score(self, ctx: ASDContext) -> dict[int, np.ndarray]:
        import python_speech_features
        from scipy import signal

        det = ctx.detections
        samples = ctx.audio()
        if samples is None:
            raise RuntimeError("the lrasd backend needs an audio track; use --asd heuristic for silent video")
        pcm = np.round(samples * 32768.0).clip(-32768, 32767)  # LR-ASD's MFCCs are computed on int16 values
        rng = det["range"]
        times = ctx.times
        src = det["source"]
        W, H = src["width"], src["height"]
        p = self.params()
        cs = p["crop_scale"]

        # 25 fps box series per track, interpolated over short gaps like LR-ASD's tracker.
        plans = {}
        for tid, series in track_series(det).items():
            t = np.array([times[k] for k, _ in series])
            boxes = np.array([f["bbox"] for _, f in series], dtype=float) * (W, H, W, H)
            grid = np.arange(np.ceil(t[0] * FPS), np.floor(t[-1] * FPS) + 1) / FPS
            if len(grid) < MIN_CHUNK_FRAMES:
                continue
            cx = np.interp(grid, t, boxes[:, 0] + boxes[:, 2] / 2)
            cy = np.interp(grid, t, boxes[:, 1] + boxes[:, 3] / 2)
            s = np.interp(grid, t, np.maximum(boxes[:, 2], boxes[:, 3]) / 2)
            plans[tid] = {"grid": grid, "x": signal.medfilt(cx, 13), "y": signal.medfilt(cy, 13),
                          "s": signal.medfilt(s, 13), "crops": [None] * len(grid)}

        # One decoding pass: give each 25 fps grid time the nearest source frame.
        for fr in videolib.read_frames(src, rng["start"], rng["end"]):
            for pl in plans.values():
                g = pl["grid"]
                lo = np.searchsorted(g, fr.t - 0.5 / src["fps"])
                hi = np.searchsorted(g, fr.t + 0.5 / src["fps"])
                for j in range(lo, hi):
                    if pl["crops"][j] is None:
                        pl["crops"][j] = self._crop(fr.image, pl["x"][j], pl["y"][j], pl["s"][j], cs)

        out: dict[int, np.ndarray] = {}
        for tid, pl in plans.items():
            crops = pl["crops"]
            for j in range(len(crops)):  # frames not decoded (end of stream): repeat the neighbour
                if crops[j] is None:
                    crops[j] = crops[j - 1] if j > 0 and crops[j - 1] is not None else np.full((112, 112), 110, np.uint8)
            vid = np.stack(crops).astype(np.float32)
            a0 = int(round((pl["grid"][0] - rng["start"]) * 16000))
            a1 = int(round((pl["grid"][-1] + 1.0 / FPS - rng["start"]) * 16000))
            mfcc = python_speech_features.mfcc(pcm[max(0, a0):a1], 16000, numcep=13, winlen=0.025, winstep=0.010)
            n = min(len(vid), (len(mfcc) - len(mfcc) % 4) // 4)
            if n < MIN_CHUNK_FRAMES:
                continue
            logits = self._scores(mfcc[: n * 4].astype(np.float32), vid[:n], p["durations"])
            prob = 1.0 / (1.0 + np.exp(-logits))
            grid = pl["grid"][:n]
            scores = np.full(len(times), np.nan)
            idx = [k for k, _ in track_series(det)[tid]]
            for k in idx:
                j = int(round((times[k] - grid[0]) * FPS))
                if 0 <= j < n:
                    scores[k] = prob[j]
            out[tid] = scores
        self.notes.append(f"LR-ASD {p['weights']} via onnxruntime; {len(out)} tracks scored")
        return out

    @staticmethod
    def _crop(image: np.ndarray, x: float, y: float, s: float, cs: float) -> np.ndarray:
        bsi = int(s * (1 + 2 * cs))
        frame = np.pad(image, ((bsi, bsi), (bsi, bsi), (0, 0)), "constant", constant_values=(110, 110))
        my, mx = y + bsi, x + bsi
        face = frame[int(my - s):int(my + s * (1 + 2 * cs)), int(mx - s * (1 + cs)):int(mx + s * (1 + cs))]
        face = cv2.cvtColor(cv2.resize(face, (224, 224)), cv2.COLOR_BGR2GRAY)
        return face[56:168, 56:168]

    def _scores(self, mfcc: np.ndarray, vid: np.ndarray, durations: list[int]) -> np.ndarray:
        n = len(vid)
        all_scores = []
        for d in durations:
            step = d * FPS
            bounds = list(range(0, n, step)) + [n]
            if len(bounds) > 2 and bounds[-1] - bounds[-2] < MIN_CHUNK_FRAMES:
                bounds.pop(-2)  # fold a too-short tail into the previous chunk
            scores = []
            for a, b in zip(bounds, bounds[1:]):
                logit = self.session.run(None, {"audio_mfcc": mfcc[None, a * 4:b * 4], "video_gray": vid[None, a:b]})[0]
                scores.append(logit)
            all_scores.append(np.concatenate(scores))
        return np.mean(all_scores, axis=0)
