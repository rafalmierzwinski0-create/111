"""Which MediaPipe face-mesh landmarks the contract stores (plan 5.1)."""

from __future__ import annotations

# Contours start at the left mouth corner (image left) and go clockwise in
# image space: along the upper lip, then back along the lower lip.
LIPS_OUTER = [61, 185, 40, 39, 37, 0, 267, 269, 270, 409, 291, 375, 321, 405, 314, 17, 84, 181, 91, 146]
LIPS_INNER = [78, 191, 80, 81, 82, 13, 312, 311, 310, 415, 308, 324, 318, 402, 317, 14, 87, 178, 88, 95]
# Nose tip, forehead, chin, eye corners (right eye outer/inner, left eye
# inner/outer as seen by the subject), cheek edges.
KEY = [1, 10, 152, 33, 133, 362, 263, 234, 454]

GROUPS = {"lips_outer": LIPS_OUTER, "lips_inner": LIPS_INNER, "key": KEY}
NUM_LANDMARKS = 478

# Points used for mouth metrics.
UPPER_LIP_INNER = 13
LOWER_LIP_INNER = 14
MOUTH_CORNER_LEFT = 61
MOUTH_CORNER_RIGHT = 291


def stored_indices(mode: str) -> list[int]:
    if mode == "full":
        return list(range(NUM_LANDMARKS))
    if mode == "subset":
        out: list[int] = []
        for group in GROUPS.values():
            out.extend(i for i in group if i not in out)
        return out
    raise ValueError(f"detect.landmarks must be 'subset' or 'full', got {mode!r}")
