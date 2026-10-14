"""Phase 1 stage: video (+ range) -> detections.json (plan 5.1, 6.1)."""

from __future__ import annotations

import logging
import math
import time
from pathlib import Path

import mediapipe as mp

from clipper import contracts, models, video
from clipper.detect.landmarks import GROUPS, stored_indices
from clipper.detect.mediapipe_detector import FaceLandmarkDetector
from clipper.detect.shots import ShotDetector
from clipper.detect.tracker import Tracker

log = logging.getLogger(__name__)

STAGE_VERSION = 2
OUTPUT_NAME = "detections.json"


def frame_step(fps: float, max_fps: float) -> int:
    return max(1, math.ceil(fps / max_fps - 1e-6))


def run_detect(video_path: str | Path, work: str | Path, cfg: dict, start: float | None = None,
               end: float | None = None, force: bool = False) -> dict:
    """Run the stage. Returns {"path", "skipped", "frames", "seconds"}."""
    d = cfg["detect"]
    source = video.probe(video_path)
    start, end = video.resolve_range(source, start, end)
    model = models.ensure(models.FACE_LANDMARKER, cfg)
    step = frame_step(source["fps"], d["max_fps"])
    indices = stored_indices(d["landmarks"])
    fp = contracts.fingerprint("detections", STAGE_VERSION, contracts.source_identity(source),
                               [start, end], d, models.FACE_LANDMARKER.sha256)

    out_path = Path(work) / OUTPUT_NAME
    if out_path.is_file() and not force:
        try:
            if contracts.read(out_path, "detections").get("fingerprint") == fp:
                log.info("detect: %s is up to date, skipping (use --force to re-run)", out_path)
                return {"path": out_path, "skipped": True, "frames": None, "seconds": 0.0}
        except (contracts.ContractError, ValueError, OSError) as exc:
            log.warning("detect: existing %s is unusable (%s); re-running", out_path, exc)

    tracker = Tracker(iou_threshold=d["iou_threshold"], max_center_dist=d["max_center_dist"], max_gap_s=d["max_gap_s"])
    shots_det = ShotDetector(threshold=d["shot_threshold"], min_shot_s=d["min_shot_s"])
    shots = [{"index": 0, "start": start, "end": end}]
    frames: list[dict] = []

    t0 = time.monotonic()
    last_report = t0
    with FaceLandmarkDetector(model, cfg, indices) as det:
        for fr in video.read_frames(source, start, end, step=step, max_width=d["analysis_max_width"]):
            if shots_det.update(fr.t, fr.image):
                shots[-1]["end"] = fr.t
                shots.append({"index": len(shots), "start": fr.t, "end": end})
                tracker.end_all()
                det.reset()
            faces = det.detect(fr.image, fr.t)
            ids = tracker.update(fr.t, [f["bbox"] for f in faces])
            faces = [{"track_id": tid, **face} for tid, face in sorted(zip(ids, faces), key=lambda p: p[0])]
            frames.append({"i": fr.index, "t": fr.t, "shot": len(shots) - 1, "faces": faces})
            now = time.monotonic()
            if now - last_report > 10:
                last_report = now
                log.info("detect: %.1f / %.1f s (%.1f frames/s)", fr.t - start, end - start, len(frames) / (now - t0))
    elapsed = time.monotonic() - t0
    if not frames:
        raise video.VideoError(f"no frames decoded from {source['path']} in {start}..{end}")

    doc = {
        "schema_version": "1.0",
        "kind": "detections",
        "fingerprint": fp,
        "source": source,
        "range": {"start": start, "end": end},
        "analysis": {
            "fps": round(source["fps"] / step, 4),
            "frame_step": step,
            "detector": "mediapipe.face_landmarker",
            "mediapipe_version": mp.__version__,
            "model": {"name": models.FACE_LANDMARKER.name, "sha256": models.FACE_LANDMARKER.sha256},
            "num_faces": d["num_faces"],
            "min_detection_confidence": d["min_detection_confidence"],
            "min_presence_confidence": d["min_presence_confidence"],
            "min_tracking_confidence": d["min_tracking_confidence"],
            "analysis_max_width": d["analysis_max_width"],
        },
        "landmark_spec": {
            "topology": "mediapipe_face_mesh_478",
            "coords": "normalized_xyz",
            "indices": indices,
            "groups": GROUPS,
            "blendshapes": list(d["blendshapes"]),
        },
        "shots": shots,
        "frames": frames,
        "tracks": summarize_tracks(frames, source["fps"] / step, d["min_track_s"]),
    }
    contracts.write(doc, out_path, "detections")
    log.info("detect: wrote %s (%d frames, %d tracks, %d shots) in %.1fs",
             out_path, len(frames), len(doc["tracks"]), len(shots), elapsed)
    return {"path": out_path, "skipped": False, "frames": len(frames), "seconds": elapsed}


def summarize_tracks(frames: list[dict], analysis_fps: float, min_track_s: float) -> list[dict]:
    times = [f["t"] for f in frames]
    acc: dict[int, dict] = {}
    for f in frames:
        for face in f["faces"]:
            a = acc.setdefault(face["track_id"], {"shot": f["shot"], "ts": [], "boxes": []})
            a["ts"].append(f["t"])
            a["boxes"].append(face["bbox"])
    out = []
    for tid in sorted(acc):
        a = acc[tid]
        first, last = a["ts"][0], a["ts"][-1]
        span = sum(1 for t in times if first <= t <= last)
        duration = last - first + 1.0 / analysis_fps
        n = len(a["boxes"])
        out.append({
            "track_id": tid,
            "shot": a["shot"],
            "start": first,
            "end": last,
            "num_frames": n,
            "coverage": round(n / span, 4),
            "mean_bbox": [round(sum(b[k] for b in a["boxes"]) / n, 4) for k in range(4)],
            "spurious": duration < min_track_s,
        })
    return out
