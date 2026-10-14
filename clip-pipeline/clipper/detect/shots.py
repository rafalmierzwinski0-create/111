"""Hard-cut detection (plan 6.1 deliverable 5).

Content difference in HSV space between consecutive analysed frames, the same
measure PySceneDetect's ContentDetector uses: the mean absolute change of
hue, saturation and value on a small downscaled frame, averaged over the three
channels (0-255 scale).
"""

from __future__ import annotations

import cv2
import numpy as np

ANALYSIS_WIDTH = 160


def content_frame(image_bgr: np.ndarray) -> np.ndarray:
    h, w = image_bgr.shape[:2]
    small = cv2.resize(image_bgr, (ANALYSIS_WIDTH, max(1, round(h * ANALYSIS_WIDTH / w))), interpolation=cv2.INTER_AREA)
    return cv2.cvtColor(small, cv2.COLOR_BGR2HSV).astype(np.int16)


def content_difference(a: np.ndarray, b: np.ndarray) -> float:
    return float(np.abs(a - b).mean())


class ShotDetector:
    def __init__(self, threshold: float, min_shot_s: float):
        self.threshold = threshold
        self.min_shot_s = min_shot_s
        self._prev: np.ndarray | None = None
        self._shot_start: float | None = None
        self.last_score = 0.0

    def update(self, t: float, image_bgr: np.ndarray) -> bool:
        """True when the frame at `t` starts a new shot (never for the first frame)."""
        cur = content_frame(image_bgr)
        prev, self._prev = self._prev, cur
        if prev is None or prev.shape != cur.shape:
            self._shot_start = t
            self.last_score = 0.0
            return False
        self.last_score = content_difference(prev, cur)
        if self.last_score >= self.threshold and t - self._shot_start >= self.min_shot_s:
            self._shot_start = t
            return True
        return False
