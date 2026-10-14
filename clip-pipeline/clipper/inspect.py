"""Human-review artefacts drawn from contract files."""

from __future__ import annotations

from pathlib import Path

import cv2
import numpy as np

from clipper import contracts
from clipper.draw import draw_faces, label


def read_frame_at(cap: cv2.VideoCapture, index: int) -> np.ndarray | None:
    cap.set(cv2.CAP_PROP_POS_FRAMES, index)
    ok, img = cap.read()
    return img if ok else None


def contact_sheet(detections_path: str | Path, out_path: str | Path, count: int = 12, columns: int = 4,
                  tile_width: int = 480, video_path: str | None = None) -> Path:
    det = contracts.read(detections_path, "detections")
    frames = det["frames"]
    picks = [frames[round(k * (len(frames) - 1) / max(1, count - 1))] for k in range(min(count, len(frames)))]
    spurious = {t["track_id"] for t in det["tracks"] if t["spurious"]}
    cap = cv2.VideoCapture(video_path or det["source"]["path"])
    tiles = []
    try:
        for fr in picks:
            img = read_frame_at(cap, fr["i"])
            if img is None:
                img = np.zeros((det["source"]["height"], det["source"]["width"], 3), np.uint8)
            draw_faces(img, det, fr, spurious)
            label(img, f"t={fr['t']:.2f}s  frame {fr['i']}  shot {fr['shot']}  faces {len(fr['faces'])}",
                  (6, img.shape[0] - 8), (255, 255, 255), max(0.45, img.shape[1] / 1600))
            th = round(img.shape[0] * tile_width / img.shape[1])
            tiles.append(cv2.resize(img, (tile_width, th), interpolation=cv2.INTER_AREA))
    finally:
        cap.release()
    th = tiles[0].shape[0]
    rows = []
    for r in range(0, len(tiles), columns):
        row = tiles[r:r + columns]
        row += [np.zeros_like(tiles[0])] * (columns - len(row))
        rows.append(np.hstack(row))
    sheet = np.vstack(rows)
    out_path = Path(out_path)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    cv2.imwrite(str(out_path), sheet)
    return out_path
