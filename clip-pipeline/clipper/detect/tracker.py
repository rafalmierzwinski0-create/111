"""Face tracking across analysed frames (plan 6.1 deliverable 6).

Greedy matching: pairs with IoU >= iou_threshold first (highest IoU first),
then a centre-distance fallback for fast motion or low frame rates. Tracks end
after max_gap_s without a match and at every shot boundary.
"""

from __future__ import annotations

from dataclasses import dataclass, field


def iou(a: list[float], b: list[float]) -> float:
    ax1, ay1, bx1, by1 = a[0] + a[2], a[1] + a[3], b[0] + b[2], b[1] + b[3]
    iw = max(0.0, min(ax1, bx1) - max(a[0], b[0]))
    ih = max(0.0, min(ay1, by1) - max(a[1], b[1]))
    inter = iw * ih
    union = a[2] * a[3] + b[2] * b[3] - inter
    return inter / union if union > 0 else 0.0


def center_distance(a: list[float], b: list[float]) -> float:
    """Distance between box centres, in units of the larger box width."""
    dx = (a[0] + a[2] / 2) - (b[0] + b[2] / 2)
    dy = (a[1] + a[3] / 2) - (b[1] + b[3] / 2)
    return (dx * dx + dy * dy) ** 0.5 / max(a[2], b[2], 1e-6)


@dataclass
class _Track:
    track_id: int
    bbox: list[float]
    last_t: float


@dataclass
class Tracker:
    iou_threshold: float = 0.2
    max_center_dist: float = 0.6
    max_gap_s: float = 1.0
    _next_id: int = 0
    _active: list[_Track] = field(default_factory=list)

    def end_all(self) -> None:
        self._active = []

    def update(self, t: float, boxes: list[list[float]]) -> list[int]:
        """Track ids for `boxes` (same order). New ids start new tracks."""
        self._active = [tr for tr in self._active if t - tr.last_t <= self.max_gap_s + 1e-9]

        pairs = []
        for ti, tr in enumerate(self._active):
            for bi, box in enumerate(boxes):
                ov = iou(tr.bbox, box)
                if ov >= self.iou_threshold:
                    pairs.append((0, -ov, ti, bi))
                else:
                    d = center_distance(tr.bbox, box)
                    if d <= self.max_center_dist:
                        pairs.append((1, d, ti, bi))
        pairs.sort()

        ids: list[int | None] = [None] * len(boxes)
        used_tracks: set[int] = set()
        for _, _, ti, bi in pairs:
            if ti in used_tracks or ids[bi] is not None:
                continue
            used_tracks.add(ti)
            tr = self._active[ti]
            tr.bbox, tr.last_t = boxes[bi], t
            ids[bi] = tr.track_id

        for bi, box in enumerate(boxes):
            if ids[bi] is None:
                tr = _Track(self._next_id, box, t)
                self._next_id += 1
                self._active.append(tr)
                ids[bi] = tr.track_id
        return ids  # type: ignore[return-value]
