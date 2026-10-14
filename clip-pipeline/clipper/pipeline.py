"""`clipper run`: the whole pipeline with one command (plan 6.3 deliverable 3).

select -> detect -> speakers -> plan -> render -> overlay

Every stage skips itself when its output exists with a matching fingerprint
(inputs + settings), so re-running only redoes what changed. `--from STAGE`
forces that stage and every later one. Re-rendering never re-runs detection
or selection. run_log.json records what ran and what was reused.
"""

from __future__ import annotations

import json
import logging
import time
from pathlib import Path

from clipper import contracts

log = logging.getLogger(__name__)

STAGES = ["select", "detect", "speakers", "plan", "render", "overlay"]


def run_pipeline(video: str, transcript: str, work: str | Path, cfg: dict, from_stage: str | None = None,
                 scorer: str | None = None, asd: str | None = None, moment: tuple[float, float] | None = None,
                 debug: bool = True) -> dict:
    from clipper.detect.run import run_detect
    from clipper.plan.run import run_plan
    from clipper.render.overlay import run_overlay
    from clipper.render.renderer import run_render
    from clipper.select.run import run_select
    from clipper.speakers.run import run_speakers

    work = Path(work)
    work.mkdir(parents=True, exist_ok=True)
    if from_stage and from_stage not in STAGES:
        raise ValueError(f"--from must be one of {STAGES}")
    first_forced = STAGES.index(from_stage) if from_stage else len(STAGES)
    forced = lambda stage: STAGES.index(stage) >= first_forced

    entries = []
    t_all = time.monotonic()

    def record(stage: str, res: dict) -> None:
        entries.append({"stage": stage, "ran": not res["skipped"], "seconds": round(res["seconds"], 2), "output": str(res["path"])})
        log.info("run: %-8s %s (%.1fs)", stage, "reused" if res["skipped"] else "done", res["seconds"])

    record("select", run_select(video, transcript, work, cfg, scorer=scorer, moment=moment, force=forced("select")))
    m = contracts.read(work / "moment.json", "moment")
    clip = m["clip"]
    record("detect", run_detect(video, work, cfg, start=clip["start"], end=clip["end"], force=forced("detect")))
    record("speakers", run_speakers(work, cfg, backend=asd, force=forced("speakers")))
    record("plan", run_plan(work, cfg, force=forced("plan")))
    record("render", run_render(work, force=forced("render")))
    if debug:
        record("overlay", run_overlay(work, transcript, force=forced("overlay")))

    summary = {"video": str(Path(video).resolve()), "transcript": str(Path(transcript).resolve()),
               "from": from_stage, "seconds": round(time.monotonic() - t_all, 2), "stages": entries,
               "moment": {k: m["selected"][k] for k in ("start", "end", "duration", "score", "reasons")},
               "clip": str(work / "clip.mp4")}
    (work / "run_log.json").write_text(json.dumps(summary, indent=2), encoding="utf-8")
    return summary
