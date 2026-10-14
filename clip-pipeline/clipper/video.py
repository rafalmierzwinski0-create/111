"""Video probing (ffprobe) and frame reading (OpenCV)."""

from __future__ import annotations

import json
import shutil
import subprocess
from dataclasses import dataclass
from fractions import Fraction
from pathlib import Path
from typing import Iterator

import cv2
import numpy as np


class VideoError(RuntimeError):
    pass


def require_tool(name: str) -> str:
    path = shutil.which(name)
    if not path:
        raise VideoError(f"{name} not found on PATH; install FFmpeg (https://ffmpeg.org/download.html)")
    return path


def probe(path: str | Path) -> dict:
    """The `source` block of the contracts (plan section 5)."""
    path = Path(path).resolve()
    if not path.is_file():
        raise VideoError(f"no such file: {path}")
    cmd = [require_tool("ffprobe"), "-v", "error", "-print_format", "json", "-show_streams", "-show_format", str(path)]
    try:
        info = json.loads(subprocess.run(cmd, check=True, capture_output=True, text=True).stdout)
    except subprocess.CalledProcessError as exc:
        raise VideoError(f"ffprobe could not read {path}: {exc.stderr.strip()}") from exc
    video = next((s for s in info.get("streams", []) if s.get("codec_type") == "video"), None)
    if video is None:
        raise VideoError(f"{path} has no video stream")
    has_audio = any(s.get("codec_type") == "audio" for s in info.get("streams", []))

    fps = float(Fraction(video.get("avg_frame_rate") or "0/1") or Fraction(video.get("r_frame_rate") or "0/1"))
    if fps <= 0:
        raise VideoError(f"{path}: cannot determine frame rate")
    duration = float(video.get("duration") or info.get("format", {}).get("duration") or 0)
    frame_count = int(video.get("nb_frames") or round(duration * fps))

    rotation = 0
    for side in video.get("side_data_list", []) or []:
        if "rotation" in side:
            rotation = int(side["rotation"])
    rotation = int(video.get("tags", {}).get("rotate", rotation)) % 360
    width, height = int(video["width"]), int(video["height"])
    if rotation in (90, 270):
        width, height = height, width

    stat = path.stat()
    return {
        "path": str(path),
        "size_bytes": stat.st_size,
        "mtime": round(stat.st_mtime, 3),
        "width": width,
        "height": height,
        "fps": round(fps, 4),
        "duration": round(duration, 4),
        "frame_count": frame_count,
        "has_audio": has_audio,
    }


@dataclass
class Frame:
    index: int          # source frame index
    t: float            # presentation time, source seconds
    image: np.ndarray   # BGR, possibly downscaled for analysis


def resolve_range(source: dict, start: float | None, end: float | None) -> tuple[float, float]:
    start = 0.0 if start is None else float(start)
    end = source["duration"] if end is None else min(float(end), source["duration"])
    if not 0 <= start < end:
        raise VideoError(f"invalid range {start}..{end} for a {source['duration']:.2f}s video")
    return round(start, 4), round(end, 4)


def read_frames(source: dict, start: float, end: float, step: int = 1, max_width: int | None = None) -> Iterator[Frame]:
    """Yield every `step`-th frame whose presentation time is in [start, end).

    Times come from the decoder (CAP_PROP_POS_MSEC), so variable frame rate
    sources keep correct timestamps.
    """
    cap = cv2.VideoCapture(source["path"])
    if not cap.isOpened():
        raise VideoError(f"OpenCV cannot open {source['path']}")
    try:
        fps = source["fps"]
        if start > 0:
            # Seek a little early and decode forward: container seeks land on
            # keyframes and some backends overshoot.
            cap.set(cv2.CAP_PROP_POS_MSEC, max(0.0, start - 1.0) * 1000.0)
        n = 0
        last_t = -1.0
        while True:
            ok = cap.grab()
            if not ok:
                break
            t = cap.get(cv2.CAP_PROP_POS_MSEC) / 1000.0
            if t <= last_t:  # some backends report 0 for the first frames after a seek
                t = last_t + 1.0 / fps
            last_t = t
            if t < start - 1e-6:
                continue
            if t >= end - 1e-6:
                break
            if n % step == 0:
                ok, image = cap.retrieve()
                if not ok:
                    break
                if max_width and image.shape[1] > max_width:
                    scale = max_width / image.shape[1]
                    image = cv2.resize(image, (max_width, round(image.shape[0] * scale)), interpolation=cv2.INTER_AREA)
                yield Frame(index=int(round(t * fps)), t=round(t, 4), image=image)
            n += 1
    finally:
        cap.release()
