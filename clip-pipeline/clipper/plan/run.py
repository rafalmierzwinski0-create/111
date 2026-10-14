"""Phase 2 stage: detections.json + speakers.json -> crop_plan.json (plan 5.3, 6.2)."""

from __future__ import annotations

import logging
import math
import time
from pathlib import Path

import numpy as np

from clipper import contracts
from clipper.plan.crop import crop_size, face_positions, moving_median, plan_axis

log = logging.getLogger(__name__)

STAGE_VERSION = 1
OUTPUT_NAME = "crop_plan.json"


def output_times(source: dict, start: float, end: float, fps_out: float) -> np.ndarray:
    """Source time of every output frame: aligned to source frames, at fps_out."""
    first = math.ceil(start * source["fps"] - 1e-6) / source["fps"]
    n = int(math.floor((end - first) * fps_out - 1e-6)) + 1
    return np.round(first + np.arange(max(n, 0)) / fps_out, 4)


def _fill(x: np.ndarray, fallback: float) -> np.ndarray:
    """Forward fill NaNs, then back fill, then fallback."""
    out = x.copy()
    last = np.nan
    for k in range(len(out)):
        if np.isnan(out[k]):
            out[k] = last
        else:
            last = out[k]
    nxt = np.nan
    for k in range(len(out) - 1, -1, -1):
        if np.isnan(out[k]):
            out[k] = nxt
        else:
            nxt = out[k]
    out[np.isnan(out)] = fallback
    return out


def run_plan(work: str | Path, cfg: dict, force: bool = False) -> dict:
    work = Path(work)
    det_path, sp_path = work / "detections.json", work / "speakers.json"
    det = contracts.read(det_path, "detections")
    sp = contracts.read(sp_path, "speakers")
    if sp["inputs"]["detections"]["fingerprint"] != det["fingerprint"]:
        raise contracts.ContractError(f"{sp_path} was made from a different detections.json; re-run `clipper speakers`")
    p = cfg["plan"]
    fp = contracts.fingerprint("crop_plan", STAGE_VERSION, det["fingerprint"], sp["fingerprint"], p)

    out_path = work / OUTPUT_NAME
    if out_path.is_file() and not force:
        try:
            if contracts.read(out_path, "crop_plan").get("fingerprint") == fp:
                log.info("plan: %s is up to date, skipping (use --force to re-run)", out_path)
                return {"path": out_path, "skipped": True, "seconds": 0.0}
        except (contracts.ContractError, ValueError, OSError) as exc:
            log.warning("plan: existing %s is unusable (%s); re-running", out_path, exc)

    t0 = time.monotonic()
    src = det["source"]
    W, H = src["width"], src["height"]
    aw, ah = (int(v) for v in p["aspect"].split(":"))
    cw, ch = crop_size(W, H, aw, ah, p["zoom"])
    fps = min(src["fps"], p["output"]["max_fps"])
    rng = det["range"]
    times = output_times(src, rng["start"], rng["end"], fps)
    n = len(times)

    segments = sp["segments"]
    seg_starts = np.array([s["start"] for s in segments])
    seg_of = np.clip(np.searchsorted(seg_starts, times, side="right") - 1, 0, len(segments) - 1)
    shot_starts = np.array([s["start"] for s in det["shots"]])
    shot_of = np.clip(np.searchsorted(shot_starts, times, side="right") - 1, 0, len(shot_starts) - 1)
    faces = face_positions(det, times)

    # 1. Target crop centre per output frame (NaN = keep the previous target).
    tx = np.full(n, np.nan)
    ty = np.full(n, np.nan)
    half_follow = max(1, int(round(p["follow_window_s"] * fps / 2)))
    for s_idx, seg in enumerate(segments):
        sel = np.where(seg_of == s_idx)[0]
        if sel.size == 0:
            continue
        if seg["kind"] == "speaker" and seg["speaker"] in faces:
            f = faces[seg["speaker"]][sel]
            fx = moving_median(f[:, 0], half_follow)
            fy = moving_median(f[:, 1], half_follow)
            tx[sel] = fx
            ty[sel] = fy - p["face_y"] * ch + ch / 2
        elif seg["kind"] == "wide":
            for k in sel:
                cxs = [arr[k, 0] for arr in faces.values() if not np.isnan(arr[k, 0])]
                tx[k] = float(np.mean(cxs)) if cxs else W / 2
                ty[k] = H / 2
        # hold: stays NaN and is forward-filled below

    # 2-5. Path per shot.
    x = np.empty(n)
    y = np.empty(n)
    for shot in np.unique(shot_of):
        sel = np.where(shot_of == shot)[0]
        sx = np.clip(_fill(tx[sel], W / 2), cw / 2, W - cw / 2)
        sy = np.clip(_fill(ty[sel], H / 2), ch / 2, H - ch / 2)
        kw = dict(fps=fps, transition_s=p["transition_s"], max_speed_px=p["max_speed"] * W, smooth_s=p["smooth_s"])
        x[sel] = plan_axis(sx, seg_of[sel], dead_zone_px=p["dead_zone"] * cw, **kw)
        y[sel] = plan_axis(sy, seg_of[sel], dead_zone_px=p["dead_zone"] * ch, **kw)
    left = np.clip(x - cw / 2, 0, W - cw)
    top = np.clip(y - ch / 2, 0, H - ch)

    frames = [{"t": float(times[k]), "x": round(float(left[k]), 2), "y": round(float(top[k]), 2),
               "w": round(cw, 2), "h": round(ch, 2), "segment": int(seg_of[k])} for k in range(n)]
    o = p["output"]
    doc = {
        "schema_version": "1.0",
        "kind": "crop_plan",
        "fingerprint": fp,
        "source": src,
        "range": rng,
        "inputs": {"detections": {"path": str(det_path), "fingerprint": det["fingerprint"]},
                   "speakers": {"path": str(sp_path), "fingerprint": sp["fingerprint"]}},
        "output": {"width": o["width"], "height": o["height"], "fps": round(fps, 4), "video_codec": o["video_codec"],
                   "crf": o["crf"], "preset": o["preset"], "audio_codec": o["audio_codec"], "audio_bitrate": o["audio_bitrate"]},
        "crop": {"aspect": p["aspect"], "zoom": p["zoom"]},
        "params": {k: p[k] for k in ("face_y", "follow_window_s", "dead_zone", "transition_s", "max_speed", "max_accel", "smooth_s")},
        "frames": frames,
        "segments": [{k: s[k] for k in ("index", "start", "end", "speaker", "kind")} for s in segments],
        "cuts": [s["start"] for s in det["shots"][1:]],
    }
    contracts.write(doc, out_path, "crop_plan")
    elapsed = time.monotonic() - t0
    log.info("plan: wrote %s (%d frames at %.2f fps, crop %.0fx%.0f) in %.1fs", out_path, n, fps, cw, ch, elapsed)
    return {"path": out_path, "skipped": False, "seconds": elapsed}
