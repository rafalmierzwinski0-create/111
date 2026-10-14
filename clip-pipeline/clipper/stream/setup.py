"""`clipper stream-setup`: a frame with a 5 % coordinate grid, to read the facecam / avatar region off."""

from __future__ import annotations

import cv2

from clipper.video import probe


def frame_grid(video: str, out: str, at: float | None = None) -> str:
    src = probe(video)
    cap = cv2.VideoCapture(src["path"])
    cap.set(cv2.CAP_PROP_POS_MSEC, (at if at is not None else src["duration"] / 2) * 1000)
    ok, img = cap.read()
    cap.release()
    if not ok:
        raise RuntimeError(f"could not read a frame from {video}")
    h, w = img.shape[:2]
    for k in range(1, 20):
        major = k % 2 == 0
        color = (0, 255, 255) if major else (0, 140, 140)
        x, y = int(w * k / 20), int(h * k / 20)
        cv2.line(img, (x, 0), (x, h), color, 2 if major else 1)
        cv2.line(img, (0, y), (w, y), color, 2 if major else 1)
        if major:
            cv2.putText(img, f"{k / 20:.1f}", (x + 4, 24), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 255), 2)
            cv2.putText(img, f"{k / 20:.1f}", (4, y - 6), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 255), 2)
    cv2.putText(img, "facecam = x, y (top-left corner), w, h  as fractions; e.g. --facecam 0.78,0.62,0.20,0.36",
                (20, h - 20), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (255, 255, 255), 2)
    cv2.imwrite(out, img)
    return out
