"""Phase 2 stage: detections.json -> speakers.json (plan 5.2, 6.2)."""

from __future__ import annotations

import logging
import time
from pathlib import Path

import numpy as np

from clipper import contracts
from clipper.speakers.base import ASDContext, get_backend
from clipper.speakers.segment import segment

log = logging.getLogger(__name__)

STAGE_VERSION = 1
OUTPUT_NAME = "speakers.json"


def run_speakers(work: str | Path, cfg: dict, backend: str | None = None, force: bool = False) -> dict:
    work = Path(work)
    det_path = work / "detections.json"
    det = contracts.read(det_path, "detections")
    s = cfg["speakers"]
    asd = get_backend(backend or s["backend"], cfg)
    seg_params = {k: s[k] for k in ("min_score", "min_segment_s", "switch_margin")}
    fp = contracts.fingerprint("speakers", STAGE_VERSION, det["fingerprint"], asd.name, asd.version, asd.params(), seg_params)

    out_path = work / OUTPUT_NAME
    if out_path.is_file() and not force:
        try:
            if contracts.read(out_path, "speakers").get("fingerprint") == fp:
                log.info("speakers: %s is up to date, skipping (use --force to re-run)", out_path)
                return {"path": out_path, "skipped": True, "seconds": 0.0}
        except (contracts.ContractError, ValueError, OSError) as exc:
            log.warning("speakers: existing %s is unusable (%s); re-running", out_path, exc)

    t0 = time.monotonic()
    ctx = ASDContext(det, cfg)
    scores = asd.score(ctx)
    times = ctx.times
    shots = [f["shot"] for f in det["frames"]]
    raw, segments = segment(times, shots, scores, det["range"]["start"], det["range"]["end"],
                            {sh["index"]: sh["start"] for sh in det["shots"]}, **seg_params)

    frames = []
    for k, f in enumerate(det["frames"]):
        sc = {str(tid): round(float(arr[k]), 3) for tid, arr in sorted(scores.items()) if not np.isnan(arr[k])}
        frames.append({"i": f["i"], "t": f["t"], "scores": sc, "raw_active": raw[k]})

    total = det["range"]["end"] - det["range"]["start"]
    tracks = []
    for tid, arr in sorted(scores.items()):
        talk = sum(seg["end"] - seg["start"] for seg in segments if seg["speaker"] == tid)
        vals = arr[~np.isnan(arr)]
        tracks.append({"track_id": tid, "speaking_ratio": round(talk / total, 4),
                       "mean_score": round(float(vals.mean()), 4) if vals.size else 0.0})

    doc = {
        "schema_version": "1.0",
        "kind": "speakers",
        "fingerprint": fp,
        "source": det["source"],
        "range": det["range"],
        "inputs": {"detections": {"path": str(det_path), "fingerprint": det["fingerprint"]}},
        "backend": {"name": asd.name, "version": asd.version, "params": asd.params(), "notes": asd.notes},
        "segmentation": seg_params,
        "frames": frames,
        "segments": segments,
        "tracks": tracks,
    }
    contracts.write(doc, out_path, "speakers")
    elapsed = time.monotonic() - t0
    switches = sum(1 for a, b in zip(segments, segments[1:]) if a["speaker"] != b["speaker"])
    log.info("speakers: wrote %s (%s backend, %d segments, %d switches) in %.1fs; %s",
             out_path, asd.name, len(segments), switches, elapsed, "; ".join(asd.notes))
    return {"path": out_path, "skipped": False, "seconds": elapsed}
