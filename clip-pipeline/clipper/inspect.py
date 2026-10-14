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


def plan_preview(work: str | Path, out_path: str | Path, height: int = 540, with_audio: bool = True) -> Path:
    """Side-by-side preview video: the source with faces, scores and the crop
    rectangle on the left, the 9:16 crop on the right. Everything is drawn from
    detections.json, speakers.json and crop_plan.json."""
    import subprocess

    from clipper import video as videolib
    from clipper.draw import track_color

    work = Path(work)
    det = contracts.read(work / "detections.json", "detections")
    sp = contracts.read(work / "speakers.json", "speakers")
    plan = contracts.read(work / "crop_plan.json", "crop_plan")
    src = plan["source"]
    W, H = src["width"], src["height"]
    scale = height / H
    lw = int(round(W * scale)) // 2 * 2
    ow, oh = plan["output"]["width"], plan["output"]["height"]
    rw = int(round(height * ow / oh)) // 2 * 2
    det_t = np.array([f["t"] for f in det["frames"]])
    segs = plan["segments"]
    out_path = Path(out_path)
    out_path.parent.mkdir(parents=True, exist_ok=True)

    cmd = [videolib.require_tool("ffmpeg"), "-v", "error", "-y", "-f", "rawvideo", "-pix_fmt", "bgr24",
           "-s", f"{lw + rw}x{height}", "-r", str(plan["output"]["fps"]), "-i", "pipe:0"]
    if with_audio and src.get("has_audio"):
        cmd += ["-ss", str(plan["frames"][0]["t"]), "-t", str(plan["range"]["end"] - plan["frames"][0]["t"]),
                "-i", src["path"], "-map", "0:v", "-map", "1:a", "-c:a", "aac", "-b:a", "96k"]
    cmd += ["-c:v", "libx264", "-preset", "veryfast", "-crf", "23", "-pix_fmt", "yuv420p", "-shortest", str(out_path)]
    enc = subprocess.Popen(cmd, stdin=subprocess.PIPE)

    frames = plan["frames"]
    k = 0
    for fr in videolib.read_frames(src, plan["range"]["start"], plan["range"]["end"]):
        while k < len(frames) and frames[k]["t"] <= fr.t + 0.5 / src["fps"]:
            pf = frames[k]
            k += 1
            img = fr.image
            x0, y0, cw, ch = int(round(pf["x"])), int(round(pf["y"])), int(round(pf["w"])), int(round(pf["h"]))
            crop = cv2.resize(img[y0:y0 + ch, x0:x0 + cw], (rw, height), interpolation=cv2.INTER_AREA)
            left = cv2.resize(img, (lw, height), interpolation=cv2.INTER_AREA)
            di = int(np.clip(np.searchsorted(det_t, pf["t"] + 1e-6) - 1, 0, len(det_t) - 1))
            seg = segs[pf["segment"]]
            scores = sp["frames"][di]["scores"]
            for face in det["frames"][di]["faces"]:
                bx, by, bw, bh = face["bbox"]
                p0 = (int(bx * lw), int(by * height))
                p1 = (int((bx + bw) * lw), int((by + bh) * height))
                active = seg["kind"] == "speaker" and seg["speaker"] == face["track_id"]
                cv2.rectangle(left, p0, p1, (0, 220, 0) if active else track_color(face["track_id"]), 3 if active else 1)
                sc = scores.get(str(face["track_id"]))
                label(left, f"#{face['track_id']} {sc:.2f}" if sc is not None else f"#{face['track_id']}",
                      (p0[0], max(14, p0[1] - 4)), (255, 255, 255), 0.45)
            cv2.rectangle(left, (int(pf["x"] * scale), int(pf["y"] * scale)),
                          (int((pf["x"] + pf["w"]) * scale), int((pf["y"] + pf["h"]) * scale)), (255, 255, 255), 2)
            raw = sp["frames"][di]["raw_active"]
            label(left, f"t={pf['t']:.2f}s seg {seg['index']} {seg['kind']}"
                        + (f" #{seg['speaker']}" if seg["speaker"] is not None else "")
                        + f"  raw {'-' if raw is None else '#' + str(raw)}", (6, height - 8), (255, 255, 255), 0.5)
            enc.stdin.write(np.ascontiguousarray(np.hstack([left, crop])).tobytes())
    enc.stdin.close()
    if enc.wait() != 0:
        raise RuntimeError("ffmpeg failed writing the preview")
    return out_path


def plan_strip(work: str | Path, out_path: str | Path, count: int = 10, height: int = 400) -> Path:
    """PNG strip of 9:16 crops at evenly spaced times, labelled with time and segment."""
    work = Path(work)
    plan = contracts.read(work / "crop_plan.json", "crop_plan")
    frames = plan["frames"]
    picks = [frames[round(k * (len(frames) - 1) / max(1, count - 1))] for k in range(min(count, len(frames)))]
    cap = cv2.VideoCapture(plan["source"]["path"])
    fps = plan["source"]["fps"]
    tiles = []
    try:
        for pf in picks:
            img = read_frame_at(cap, int(round(pf["t"] * fps)))
            if img is None:
                continue
            x0, y0, cw, ch = int(round(pf["x"])), int(round(pf["y"])), int(round(pf["w"])), int(round(pf["h"]))
            tile = cv2.resize(img[y0:y0 + ch, x0:x0 + cw], (int(round(height * cw / ch)), height), interpolation=cv2.INTER_AREA)
            seg = plan["segments"][pf["segment"]]
            label(tile, f"{pf['t']:.1f}s {seg['kind']}" + (f" #{seg['speaker']}" if seg["speaker"] is not None else ""),
                  (4, height - 6), (255, 255, 255), 0.45)
            tiles.append(tile)
    finally:
        cap.release()
    out_path = Path(out_path)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    cv2.imwrite(str(out_path), np.hstack(tiles))
    return out_path
