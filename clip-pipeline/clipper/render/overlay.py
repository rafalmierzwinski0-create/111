"""Debug overlay (plan 6.3 deliverable 2): a diagnostic, not a decoration.

Left: the full source frame with the pipeline's actual data drawn on it:
  - every face from detections.json (box, track id, lip contours, key points)
  - the per-frame active-speaker score of each face and the raw decision
    from speakers.json ("RAW" tag), the framed speaker (thick green box)
  - the crop rectangle from crop_plan.json, the plan the renderer used
Right: the frame actually decoded from the rendered clip.mp4, so any
mismatch between plan and render is visible.
Bottom: the segment timeline (one colour per speaker; hold/wide grey), the
raw decision strip under it, the current time, and the transcript sentence
being spoken.

overlay_log.json records the rectangle drawn on every frame; the Phase 3
checkpoint compares it with render_log.json frame by frame.
"""

from __future__ import annotations

import logging
import subprocess
import time
from fractions import Fraction
from pathlib import Path

import cv2
import numpy as np

from clipper import contracts, transcript as tr, video as videolib
from clipper.draw import draw_faces, label, track_color

log = logging.getLogger(__name__)

OVERLAY_VERSION = 1
OUTPUT_NAME = "debug.mp4"
LOG_NAME = "overlay_log.json"
PANEL_H = 720
BAR_H = 130


def _timeline(width: int, plan: dict, sp: dict) -> np.ndarray:
    bar = np.full((BAR_H, width, 3), 24, np.uint8)
    t0, t1 = plan["range"]["start"], plan["range"]["end"]
    xs = lambda t: int(round((t - t0) / (t1 - t0) * (width - 20))) + 10
    for seg in plan["segments"]:
        color = track_color(seg["speaker"]) if seg["kind"] == "speaker" else (90, 90, 90)
        cv2.rectangle(bar, (xs(seg["start"]), 10), (xs(seg["end"]) - 1, 34), color, -1)
        if xs(seg["end"]) - xs(seg["start"]) > 40:
            cv2.putText(bar, f"#{seg['speaker']}" if seg["kind"] == "speaker" else seg["kind"], (xs(seg["start"]) + 4, 28),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.45, (0, 0, 0), 1, cv2.LINE_AA)
    for a, b in zip(sp["frames"], sp["frames"][1:] + [None]):
        end = b["t"] if b else t1
        if a["raw_active"] is not None:
            cv2.rectangle(bar, (xs(a["t"]), 38), (max(xs(a["t"]), xs(end) - 1), 46), track_color(a["raw_active"]), -1)
    for c in plan["cuts"]:
        cv2.line(bar, (xs(c), 6), (xs(c), 50), (255, 255, 255), 1)
    label(bar, "segments (framing)  /  raw per-frame decision  /  white lines: cuts", (10, 66), (200, 200, 200), 0.45)
    return bar


def run_overlay(work: str | Path, transcript_path: str | Path | None = None, force: bool = False) -> dict:
    work = Path(work)
    det = contracts.read(work / "detections.json", "detections")
    sp = contracts.read(work / "speakers.json", "speakers")
    plan = contracts.read(work / "crop_plan.json", "crop_plan")
    rlog = contracts.read(work / "render_log.json", "render_log")
    clip_path = Path(rlog["output"]["path"])
    out_path, log_path = work / OUTPUT_NAME, work / LOG_NAME
    sentences = tr.sentences(tr.load(transcript_path)) if transcript_path else []
    fp = contracts.fingerprint("overlay", OVERLAY_VERSION, det["fingerprint"], sp["fingerprint"], plan["fingerprint"],
                               rlog["fingerprint"], tr.sha256(transcript_path) if transcript_path else None)
    if out_path.is_file() and log_path.is_file() and not force:
        try:
            if contracts.read(log_path, "overlay_log").get("fingerprint") == fp:
                log.info("overlay: %s is up to date, skipping (use --force to re-run)", out_path)
                return {"path": out_path, "skipped": True, "seconds": 0.0}
        except (contracts.ContractError, ValueError, OSError) as exc:
            log.warning("overlay: existing %s is unusable (%s); re-rendering", log_path, exc)

    t0 = time.monotonic()
    src = plan["source"]
    W, H = src["width"], src["height"]
    scale = PANEL_H / H
    lw = int(round(W * scale)) // 2 * 2
    o = plan["output"]
    rw = int(round(PANEL_H * o["width"] / o["height"])) // 2 * 2
    total_w = lw + rw
    bar = _timeline(total_w, plan, sp)
    det_t = np.array([f["t"] for f in det["frames"]])
    spurious = {t["track_id"] for t in det["tracks"] if t["spurious"]}
    fps = Fraction(o["fps"]).limit_denominator(1001)
    frames = plan["frames"]
    tmp = out_path.with_name(out_path.stem + ".part.mp4")
    cmd = [videolib.require_tool("ffmpeg"), "-v", "error", "-y", "-f", "rawvideo", "-pix_fmt", "bgr24",
           "-s", f"{total_w}x{PANEL_H + BAR_H}", "-r", str(fps), "-i", "pipe:0", "-i", str(clip_path),
           "-map", "0:v:0", "-map", "1:a:0?", "-c:a", "copy", "-c:v", "libx264", "-preset", "veryfast", "-crf", "22",
           "-pix_fmt", "yuv420p", str(tmp)]
    enc = subprocess.Popen(cmd, stdin=subprocess.PIPE)
    clip = cv2.VideoCapture(str(clip_path))
    drawn = []
    k = 0
    half = 0.5 / src["fps"]
    try:
        for fr in videolib.read_frames(src, max(0.0, frames[0]["t"] - half), plan["range"]["end"] + half):
            while k < len(frames) and frames[k]["t"] <= fr.t + half + 1e-6:
                pf = frames[k]
                k += 1
                left = cv2.resize(fr.image, (lw, PANEL_H), interpolation=cv2.INTER_AREA)
                di = int(np.clip(np.searchsorted(det_t, pf["t"] + 1e-6) - 1, 0, len(det_t) - 1))
                dframe, sframe = det["frames"][di], sp["frames"][di]
                seg = plan["segments"][pf["segment"]]
                draw_faces(left, det, dframe, spurious)
                for face in dframe["faces"]:
                    bx, by, bw, bh = face["bbox"]
                    tid = face["track_id"]
                    p0, p1 = (int(bx * lw), int(by * PANEL_H)), (int((bx + bw) * lw), int((by + bh) * PANEL_H))
                    if seg["kind"] == "speaker" and seg["speaker"] == tid:
                        cv2.rectangle(left, (p0[0] - 3, p0[1] - 3), (p1[0] + 3, p1[1] + 3), (0, 230, 0), 3)
                    sc = sframe["scores"].get(str(tid))
                    text = f"score {sc:.2f}" if sc is not None else "no score"
                    if sframe["raw_active"] == tid:
                        text += "  RAW"
                    label(left, text, (p0[0], p1[1] + 18), (255, 255, 255), 0.5)
                rx0, ry0 = pf["x"] * scale, pf["y"] * scale
                cv2.rectangle(left, (int(round(rx0)), int(round(ry0))),
                              (int(round(rx0 + pf["w"] * scale)), int(round(ry0 + pf["h"] * scale))), (255, 255, 255), 2)
                drawn.append({"t": pf["t"], "x": pf["x"], "y": pf["y"], "w": pf["w"], "h": pf["h"]})
                label(left, f"t={pf['t']:.2f}s  segment {seg['index']}: {seg['kind']}"
                            + (f" #{seg['speaker']}" if seg["speaker"] is not None else "")
                            + f"  raw: {'-' if sframe['raw_active'] is None else '#' + str(sframe['raw_active'])}",
                      (8, 24), (255, 255, 255), 0.6)

                ok, out_frame = clip.read()
                right = cv2.resize(out_frame, (rw, PANEL_H), interpolation=cv2.INTER_AREA) if ok \
                    else np.zeros((PANEL_H, rw, 3), np.uint8)
                label(right, "rendered clip.mp4", (6, 22), (255, 255, 255), 0.5)

                b = bar.copy()
                t_rel = (pf["t"] - plan["range"]["start"]) / (plan["range"]["end"] - plan["range"]["start"])
                x_now = int(round(t_rel * (total_w - 20))) + 10
                cv2.line(b, (x_now, 4), (x_now, 52), (0, 0, 255), 2)
                sent = next((s for s in sentences if s.start - 0.05 <= pf["t"] <= s.end + 0.05), None)
                if sent:
                    text = sent.text if len(sent.text) <= 150 else sent.text[:147] + "..."
                    label(b, f"[{sent.speaker or '?'}] {text}", (10, 100), (255, 255, 255), 0.55)
                enc.stdin.write(np.ascontiguousarray(np.vstack([np.hstack([left, right]), b])).tobytes())
    finally:
        clip.release()
        enc.stdin.close()
        code = enc.wait()
    if code != 0:
        raise RuntimeError("ffmpeg failed while encoding the debug overlay")
    tmp.replace(out_path)
    doc = {"schema_version": "1.0", "kind": "overlay_log", "fingerprint": fp,
           "inputs": {"crop_plan": {"fingerprint": plan["fingerprint"]}, "render_log": {"fingerprint": rlog["fingerprint"]}},
           "output": {"path": str(out_path), "frames": len(drawn)}, "frames": drawn}
    contracts.write(doc, log_path, "overlay_log")
    elapsed = time.monotonic() - t0
    log.info("overlay: wrote %s (%d frames) in %.1fs", out_path, len(drawn), elapsed)
    return {"path": out_path, "skipped": False, "seconds": elapsed}
