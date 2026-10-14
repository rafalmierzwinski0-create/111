"""MediaPipe Face Landmarker wrapper (plan 2.3, 6.1 deliverable 4)."""

from __future__ import annotations

import logging
import math
import os

import cv2
import numpy as np

os.environ.setdefault("GLOG_minloglevel", "2")
os.environ.setdefault("TF_CPP_MIN_LOG_LEVEL", "2")

import mediapipe as mp  # noqa: E402
from mediapipe.tasks.python import BaseOptions, vision  # noqa: E402

log = logging.getLogger(__name__)


def rotation_to_pose(matrix: np.ndarray) -> dict:
    """Yaw/pitch/roll in degrees from MediaPipe's facial transformation matrix.

    Signs follow the contract: yaw > 0 when the face turns towards image right,
    pitch > 0 when looking up, roll > 0 when the head tilts clockwise in the image.
    MediaPipe's camera space has y up, hence the flipped pitch and roll (checked
    against the eye-line angle in image space on the fixture).
    """
    r = np.asarray(matrix, dtype=float)[:3, :3]
    sy = math.hypot(r[0, 0], r[1, 0])
    pitch = -math.degrees(math.atan2(r[2, 1], r[2, 2]))
    yaw = math.degrees(math.atan2(-r[2, 0], sy))
    roll = -math.degrees(math.atan2(r[1, 0], r[0, 0]))
    return {"yaw": round(yaw, 1), "pitch": round(pitch, 1), "roll": round(roll, 1)}


class FaceLandmarkDetector:
    """Runs Face Landmarker in VIDEO mode; call `detect` with increasing timestamps."""

    def __init__(self, model_path: str, cfg: dict, indices: list[int]):
        d = cfg["detect"]
        self.indices = np.asarray(indices)
        self.blendshapes = list(d["blendshapes"])
        self._options = vision.FaceLandmarkerOptions(
            base_options=BaseOptions(model_asset_path=str(model_path)),
            running_mode=vision.RunningMode.VIDEO,
            num_faces=int(d["num_faces"]),
            min_face_detection_confidence=float(d["min_detection_confidence"]),
            min_face_presence_confidence=float(d["min_presence_confidence"]),
            min_tracking_confidence=float(d["min_tracking_confidence"]),
            output_face_blendshapes=bool(self.blendshapes),
            output_facial_transformation_matrixes=True,
        )
        self._landmarker = vision.FaceLandmarker.create_from_options(self._options)
        self._last_ms = -1

    def reset(self) -> None:
        """Forget tracked faces. Call at a hard cut: in VIDEO mode MediaPipe
        re-uses the previous frame's face regions, which otherwise produces a
        one-frame ghost face at the old position right after the cut."""
        self._landmarker.close()
        self._landmarker = vision.FaceLandmarker.create_from_options(self._options)
        self._last_ms = -1

    def close(self) -> None:
        if self._landmarker is not None:
            self._landmarker.close()
            self._landmarker = None

    def __enter__(self):
        return self

    def __exit__(self, *exc):
        self.close()

    def detect(self, image_bgr: np.ndarray, t: float) -> list[dict]:
        """Faces in one frame as contract dicts (without track_id)."""
        ms = int(round(t * 1000))
        if ms <= self._last_ms:  # VIDEO mode requires strictly increasing timestamps
            ms = self._last_ms + 1
        self._last_ms = ms
        rgb = np.ascontiguousarray(cv2.cvtColor(image_bgr, cv2.COLOR_BGR2RGB))
        result = self._landmarker.detect_for_video(mp.Image(image_format=mp.ImageFormat.SRGB, data=rgb), ms)

        faces = []
        for k, landmarks in enumerate(result.face_landmarks):
            pts = np.array([(p.x, p.y, p.z) for p in landmarks], dtype=float)
            x0, y0 = pts[:, 0].min(), pts[:, 1].min()
            x1, y1 = pts[:, 0].max(), pts[:, 1].max()
            face = {
                "bbox": [round(float(x0), 4), round(float(y0), 4), round(float(x1 - x0), 4), round(float(y1 - y0), 4)],
                "landmarks": np.round(pts[self.indices], 5).tolist(),
            }
            if self.blendshapes and k < len(result.face_blendshapes):
                scores = {c.category_name: c.score for c in result.face_blendshapes[k]}
                face["blendshapes"] = {name: round(float(scores.get(name, 0.0)), 4) for name in self.blendshapes}
            if k < len(result.facial_transformation_matrixes):
                face["pose"] = rotation_to_pose(result.facial_transformation_matrixes[k])
            faces.append(face)
        return faces
