"""Drawing contract data onto frames (contact sheets now, the debug overlay in Phase 3).

Everything drawn here comes from the contract files, never from a re-run of a
stage, so what you see is what the pipeline decided.
"""

from __future__ import annotations

import cv2
import numpy as np

from clipper.contracts import landmark_group

# Distinct, colour-blind friendly-ish BGR colours per track id.
PALETTE = [(255, 158, 31), (14, 127, 255), (44, 160, 44), (40, 39, 214), (189, 103, 148),
           (75, 86, 140), (194, 119, 227), (127, 127, 127), (34, 189, 188), (207, 190, 23)]


def track_color(track_id: int) -> tuple[int, int, int]:
    return PALETTE[track_id % len(PALETTE)]


def label(img: np.ndarray, text: str, org: tuple[int, int], color, scale: float = 0.5) -> None:
    x, y = org
    (w, h), base = cv2.getTextSize(text, cv2.FONT_HERSHEY_SIMPLEX, scale, 1)
    cv2.rectangle(img, (x, y - h - base - 2), (x + w + 4, y + 2), (0, 0, 0), -1)
    cv2.putText(img, text, (x + 2, y - base + 1), cv2.FONT_HERSHEY_SIMPLEX, scale, color, 1, cv2.LINE_AA)


def draw_faces(img: np.ndarray, detections: dict, frame: dict, spurious: set[int] | None = None) -> None:
    """Boxes, track ids, lip contours and key points of one `frames[]` entry, in place."""
    h, w = img.shape[:2]
    scale = max(0.4, w / 1600)
    groups = {g: landmark_group(detections, g) for g in ("lips_outer", "lips_inner", "key")}
    for face in frame["faces"]:
        tid = face["track_id"]
        color = (128, 128, 128) if spurious and tid in spurious else track_color(tid)
        x, y, bw, bh = face["bbox"]
        p0 = (int(round(x * w)), int(round(y * h)))
        p1 = (int(round((x + bw) * w)), int(round((y + bh) * h)))
        cv2.rectangle(img, p0, p1, color, max(1, int(2 * scale)))
        pts = np.asarray(face["landmarks"], dtype=float)[:, :2] * (w, h)
        for g, closed_color in (("lips_outer", color), ("lips_inner", (255, 255, 255))):
            poly = np.round(pts[groups[g]]).astype(np.int32)
            cv2.polylines(img, [poly], True, closed_color, 1, cv2.LINE_AA)
        for k in groups["key"]:
            cv2.circle(img, tuple(np.round(pts[k]).astype(int)), max(1, int(2 * scale)), color, -1, cv2.LINE_AA)
        text = f"#{tid}"
        if "pose" in face:
            text += f" y{face['pose']['yaw']:+.0f}"
        if "blendshapes" in face and "jawOpen" in face["blendshapes"]:
            text += f" jaw{face['blendshapes']['jawOpen']:.2f}"
        label(img, text, (p0[0], max(14, p0[1] - 4)), color, 0.45 * scale)
