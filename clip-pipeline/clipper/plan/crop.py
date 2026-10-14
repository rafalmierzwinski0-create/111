"""The 9:16 crop path: who to frame (from segments) and how the frame moves.

The frame glides, it does not snap:

1. Target per output frame. In a `speaker` segment: the speaker's face
   centre, followed through a moving median over `follow_window_s` so that
   head motion does not move the frame. In `hold`: the last target. In
   `wide`: the centre of all visible faces (frame centre without faces).
2. Retargeting with a dead zone: the camera only gets a new goal when the
   segment changes or when the target leaves the dead zone around the current
   goal (`dead_zone`, a fraction of the crop width).
3. Each new goal is reached with a cosine ease-in-out. It lasts
   `transition_s`, or longer when that would exceed `max_speed`.
4. Speed clamp (`max_speed`, fraction of frame width per second), then a
   zero-phase Gaussian low-pass (`smooth_s`) that removes the corner where a
   new transition interrupts a running one.
5. Clamp the rectangle inside the frame.

Every step runs per shot: at a cut the camera starts at the new target with no
transition, which is the only place the crop may jump.
"""

from __future__ import annotations

import math

import numpy as np


def crop_size(width: int, height: int, aspect_w: int, aspect_h: int, zoom: float) -> tuple[float, float]:
    h = height / zoom
    w = h * aspect_w / aspect_h
    if w > width:
        w = width
        h = w * aspect_h / aspect_w
    return w, h


def face_positions(detections: dict, out_times: np.ndarray) -> dict[int, np.ndarray]:
    """Per track: (n_out, 4) array of face centre x, centre y, width, height in source pixels; NaN where absent."""
    w, h = detections["source"]["width"], detections["source"]["height"]
    step_s = 1.0 / detections["analysis"]["fps"]
    samples: dict[int, list[tuple[float, list[float]]]] = {}
    for f in detections["frames"]:
        for face in f["faces"]:
            samples.setdefault(face["track_id"], []).append((f["t"], face["bbox"]))
    out = {}
    for tid, rows in samples.items():
        t = np.array([r[0] for r in rows])
        b = np.array([r[1] for r in rows], dtype=float)
        vals = np.column_stack([(b[:, 0] + b[:, 2] / 2) * w, (b[:, 1] + b[:, 3] / 2) * h, b[:, 2] * w, b[:, 3] * h])
        res = np.full((len(out_times), 4), np.nan)
        # Interpolate inside the track's visible stretches only: a gap longer
        # than two analysis frames means the face was not there.
        gaps = np.where(np.diff(t) > 2.5 * step_s)[0]
        pieces = np.split(np.arange(len(t)), gaps + 1)
        for piece in pieces:
            t0, t1 = t[piece[0]] - step_s / 2, t[piece[-1]] + step_s / 2
            sel = (out_times >= t0) & (out_times <= t1)
            for c in range(4):
                res[sel, c] = np.interp(out_times[sel], t[piece], vals[piece, c])
        out[tid] = res
    return out


def moving_median(x: np.ndarray, half: int) -> np.ndarray:
    out = x.copy()
    for k in range(len(x)):
        win = x[max(0, k - half):k + half + 1]
        win = win[~np.isnan(win)]
        out[k] = np.median(win) if win.size else np.nan
    return out


def gaussian_smooth(x: np.ndarray, sigma: float) -> np.ndarray:
    """Zero-phase Gaussian low-pass with edge padding (sigma in samples)."""
    if sigma <= 0 or len(x) < 3:
        return x.copy()
    radius = int(math.ceil(3 * sigma))
    kernel = np.exp(-0.5 * (np.arange(-radius, radius + 1) / sigma) ** 2)
    kernel /= kernel.sum()
    padded = np.pad(x, radius, mode="edge")
    return np.convolve(padded, kernel, mode="valid")


def eased_path(goals: list[tuple[int, float]], n: int, fps: float, transition_s: float, max_speed_px: float,
               start_value: float) -> np.ndarray:
    """Position per frame given (frame, goal) retarget events. Cosine ease from the current position."""
    pos = np.full(n, start_value, dtype=float)
    events = sorted(goals)
    for e, (k0, goal) in enumerate(events):
        k_end_limit = events[e + 1][0] if e + 1 < len(events) else n
        from_v = pos[k0 - 1] if k0 > 0 else start_value
        delta = goal - from_v
        # A cosine ease peaks at pi/2 * delta / T; stretch T so that stays within max_speed.
        dur_s = max(transition_s, math.pi / 2 * abs(delta) / max_speed_px) if max_speed_px > 0 else transition_s
        dur = max(1, int(round(dur_s * fps)))
        for k in range(k0, k_end_limit):
            u = min(1.0, (k - k0 + 1) / dur)
            pos[k] = from_v + delta * (1 - math.cos(math.pi * u)) / 2
    return pos


def clamp_speed(x: np.ndarray, max_step: float) -> np.ndarray:
    out = x.copy()
    for k in range(1, len(out)):
        d = out[k] - out[k - 1]
        if abs(d) > max_step:
            out[k] = out[k - 1] + math.copysign(max_step, d)
    return out


def plan_axis(target: np.ndarray, seg_ids: np.ndarray, fps: float, dead_zone_px: float, transition_s: float,
              max_speed_px: float, smooth_s: float) -> np.ndarray:
    """One axis of the camera path inside one shot."""
    n = len(target)
    goals: list[tuple[int, float]] = []
    goal = target[0]
    for k in range(1, n):
        new_segment = seg_ids[k] != seg_ids[k - 1]
        if (new_segment and abs(target[k] - goal) > 0.5) or abs(target[k] - goal) > dead_zone_px:
            goal = target[k]
            goals.append((k, goal))
    path = eased_path(goals, n, fps, transition_s, max_speed_px, target[0])
    path = clamp_speed(path, max_speed_px / fps)
    return gaussian_smooth(path, smooth_s * fps)
