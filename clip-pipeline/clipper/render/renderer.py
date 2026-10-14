"""Renderer (plan 6.3 deliverable 1).

Reads crop_plan.json and the source video, and nothing else: no detections,
no speakers, no transcript. Every output frame is the plan's rectangle, cut
from the source frame at the plan's time (sub-pixel accurate, so slow glides
do not step), scaled to the output size. The audio is the source's audio for
exactly the same span. render_log.json records what was actually applied.
"""

from __future__ import annotations

import logging
import subprocess
import time
from fractions import Fraction
from pathlib import Path

import cv2
import numpy as np

from clipper import contracts, video as videolib

log = logging.getLogger(__name__)

RENDERER_VERSION = 1
OUTPUT_NAME = "clip.mp4"
LOG_NAME = "render_log.json"


def crop_frame(image: np.ndarray, x: float, y: float, w: float, h: float, out_w: int, out_h: int) -> np.ndarray:
    """The rectangle (x, y, w, h) in source pixels, scaled to out_w x out_h, with sub-pixel placement."""
    sx, sy = out_w / w, out_h / h
    if sx >= 1.0:
        # Pixel-centre mapping: dst j <- src x + (j + 0.5) / sx - 0.5
        m = np.array([[sx, 0.0, (0.5 - x) * sx - 0.5], [0.0, sy, (0.5 - y) * sy - 0.5]])
        return cv2.warpAffine(image, m, (out_w, out_h), flags=cv2.INTER_LINEAR, borderMode=cv2.BORDER_REPLICATE)
    iw, ih = int(round(w)), int(round(h))
    m = np.array([[1.0, 0.0, -x], [0.0, 1.0, -y]])
    sub = cv2.warpAffine(image, m, (iw, ih), flags=cv2.INTER_LINEAR, borderMode=cv2.BORDER_REPLICATE)
    return cv2.resize(sub, (out_w, out_h), interpolation=cv2.INTER_AREA)


def check_source(plan: dict) -> dict:
    """The source on disk must be the file the plan was made for."""
    now = videolib.probe(plan["source"]["path"])
    if contracts.source_identity(now) != contracts.source_identity(plan["source"]):
        raise videolib.VideoError(f"{plan['source']['path']} changed since crop_plan.json was made; re-run the pipeline")
    return now


def run_render(work: str | Path, force: bool = False, out_name: str = OUTPUT_NAME) -> dict:
    work = Path(work)
    plan_path = work / "crop_plan.json"
    plan = contracts.read(plan_path, "crop_plan")
    out_path, log_path = work / out_name, work / LOG_NAME
    fp = contracts.fingerprint("render", RENDERER_VERSION, plan["fingerprint"], out_name)
    if out_path.is_file() and log_path.is_file() and not force:
        try:
            if contracts.read(log_path, "render_log").get("fingerprint") == fp:
                log.info("render: %s is up to date, skipping (use --force to re-run)", out_path)
                return {"path": out_path, "skipped": True, "seconds": 0.0}
        except (contracts.ContractError, ValueError, OSError) as exc:
            log.warning("render: existing %s is unusable (%s); re-rendering", log_path, exc)

    t0 = time.monotonic()
    src = check_source(plan)
    o = plan["output"]
    frames = plan["frames"]
    fps = Fraction(o["fps"]).limit_denominator(1001)
    first_t = frames[0]["t"]
    duration = len(frames) / float(fps)
    tmp = out_path.with_name(out_path.stem + ".part.mp4")
    cmd = [videolib.require_tool("ffmpeg"), "-v", "error", "-y",
           "-f", "rawvideo", "-pix_fmt", "bgr24", "-s", f"{o['width']}x{o['height']}", "-r", str(fps), "-i", "pipe:0"]
    if src["has_audio"]:
        cmd += ["-ss", f"{first_t:.4f}", "-t", f"{duration:.4f}", "-i", src["path"], "-map", "0:v:0", "-map", "1:a:0",
                "-c:a", o["audio_codec"], "-b:a", o["audio_bitrate"], "-ac", "2"]
    cmd += ["-c:v", o["video_codec"], "-crf", str(o["crf"]), "-preset", o["preset"], "-pix_fmt", "yuv420p",
            "-r", str(fps), "-t", f"{duration:.4f}", "-movflags", "+faststart", str(tmp)]
    enc = subprocess.Popen(cmd, stdin=subprocess.PIPE)

    applied = []
    k = 0
    half = 0.5 / src["fps"]
    last_image = None
    last_t = None
    try:
        for fr in videolib.read_frames(src, max(0.0, first_t - half), plan["range"]["end"] + half):
            while k < len(frames) and frames[k]["t"] <= fr.t + half + 1e-6:
                pf = frames[k]
                # The decoded frame nearest to the plan time (the previous one if it is closer).
                if last_image is not None and abs(last_t - pf["t"]) < abs(fr.t - pf["t"]):
                    image, src_t = last_image, last_t
                else:
                    image, src_t = fr.image, fr.t
                out = crop_frame(image, pf["x"], pf["y"], pf["w"], pf["h"], o["width"], o["height"])
                enc.stdin.write(np.ascontiguousarray(out).tobytes())
                applied.append({"t": pf["t"], "source_t": round(src_t, 4), "x": pf["x"], "y": pf["y"], "w": pf["w"], "h": pf["h"]})
                k += 1
            last_image, last_t = fr.image, fr.t
        while k < len(frames) and last_image is not None:  # stream ended a frame early: repeat the last frame
            pf = frames[k]
            enc.stdin.write(np.ascontiguousarray(crop_frame(last_image, pf["x"], pf["y"], pf["w"], pf["h"], o["width"], o["height"])).tobytes())
            applied.append({"t": pf["t"], "source_t": round(last_t, 4), "x": pf["x"], "y": pf["y"], "w": pf["w"], "h": pf["h"]})
            k += 1
    finally:
        enc.stdin.close()
        code = enc.wait()
    if code != 0:
        raise RuntimeError("ffmpeg failed while encoding the clip")
    tmp.replace(out_path)

    doc = {
        "schema_version": "1.0",
        "kind": "render_log",
        "fingerprint": fp,
        "inputs": {"crop_plan": {"path": str(plan_path), "fingerprint": plan["fingerprint"]}},
        "output": {"path": str(out_path), "width": o["width"], "height": o["height"], "fps": o["fps"],
                   "frames": len(applied), "duration": round(duration, 4), "audio": src["has_audio"]},
        "frames": applied,
    }
    contracts.write(doc, log_path, "render_log")
    elapsed = time.monotonic() - t0
    log.info("render: wrote %s (%d frames, %.2fs) in %.1fs", out_path, len(applied), duration, elapsed)
    return {"path": out_path, "skipped": False, "seconds": elapsed}
