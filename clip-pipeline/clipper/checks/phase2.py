"""Phase 2 checkpoint verification (plan 6.2)."""

from __future__ import annotations

import json
import shutil
import subprocess
import sys
from pathlib import Path

import numpy as np

from clipper import contracts
from clipper.checks.labels import evaluate, load_labels
from clipper.checks.phase1 import PROJECT_ROOT, Report, make_multicam
from clipper.detect.run import run_detect
from clipper.inspect import plan_preview, plan_strip
from clipper.licenses import audit, forbidden_imports
from clipper.plan.crop import face_positions
from clipper.plan.run import run_plan
from clipper.speakers.run import run_speakers

TOL = 1e-3


def _lrasd_available() -> bool:
    try:
        import onnxruntime  # noqa: F401
        import python_speech_features  # noqa: F401
        return True
    except ImportError:
        return False


def structural(det: dict, sp: dict, plan: dict, cfg: dict) -> list[tuple[str, bool, str]]:
    """Checks 2-6 on one speakers.json + crop_plan.json pair."""
    rows = []
    s_cfg, p_cfg = cfg["speakers"], cfg["plan"]
    rng = sp["range"]
    segs = sp["segments"]
    cuts = [s["start"] for s in det["shots"][1:]]

    # 2. Coverage and minimum duration.
    gaps = [(a["end"], b["start"]) for a, b in zip(segs, segs[1:]) if abs(a["end"] - b["start"]) > TOL]
    covers = abs(segs[0]["start"] - rng["start"]) < TOL and abs(segs[-1]["end"] - rng["end"]) < TOL and not gaps
    short = []
    for k, s in enumerate(segs):
        dur = s["end"] - s["start"]
        at_edge = k == 0 or k == len(segs) - 1 or any(abs(s["start"] - c) < TOL or abs(s["end"] - c) < TOL for c in cuts)
        if dur < s_cfg["min_segment_s"] - TOL and not at_edge:
            short.append((s["index"], round(dur, 2)))
    rows.append(("segments", covers and not short,
                 f"{len(segs)} segments cover {rng['start']}-{rng['end']}s without gaps: {covers}; "
                 f"shorter than {s_cfg['min_segment_s']}s (not at a cut/edge): {short or 'none'}"))

    # 3. Flicker.
    sandwiches = []
    for a, b, c in zip(segs, segs[1:], segs[2:]):
        if a["speaker"] is not None and a["speaker"] == c["speaker"] and b["speaker"] not in (None, a["speaker"]) \
                and b["end"] - b["start"] < s_cfg["min_segment_s"]:
            sandwiches.append(b["index"])
    switches = sum(1 for a, b in zip(segs, segs[1:]) if a["kind"] == b["kind"] == "speaker" and a["speaker"] != b["speaker"])
    minutes = (rng["end"] - rng["start"]) / 60
    raw = [f["raw_active"] for f in sp["frames"]]
    raw_switches = sum(1 for a, b in zip(raw, raw[1:]) if a != b and a is not None and b is not None)
    rows.append(("flicker", not sandwiches,
                 f"A->B->A with B < {s_cfg['min_segment_s']}s: {sandwiches or 'none'}; "
                 f"{switches} speaker switches ({switches / minutes:.1f}/min) vs {raw_switches} in the raw per-frame decision"))

    # 4. Crop validity.
    W, H = plan["source"]["width"], plan["source"]["height"]
    frames = plan["frames"]
    aw, ah = (int(v) for v in plan["crop"]["aspect"].split(":"))
    outside = [f["t"] for f in frames if f["x"] < -TOL or f["y"] < -TOL or f["x"] + f["w"] > W + 0.01 or f["y"] + f["h"] > H + 0.01]
    bad_aspect = [f["t"] for f in frames if abs(f["w"] / f["h"] - aw / ah) > 0.002]
    fps = plan["output"]["fps"]
    first = frames[0]["t"]
    expected = int(np.floor((rng["end"] - first) * fps - 1e-6)) + 1
    dt = np.diff([f["t"] for f in frames])
    regular = bool(np.all(np.abs(dt - 1 / fps) < 2e-3)) if len(dt) else True
    rows.append(("crop validity", not outside and not bad_aspect and len(frames) == expected and regular,
                 f"{len(frames)} frames (expected {expected}) at {fps} fps, regular spacing {regular}; "
                 f"outside the source frame: {len(outside)}; wrong aspect: {len(bad_aspect)}; crop {frames[0]['w']:.0f}x{frames[0]['h']:.0f}"))

    # 5. Smoothness.
    t = np.array([f["t"] for f in frames])
    cx = np.array([f["x"] + f["w"] / 2 for f in frames])
    cy = np.array([f["y"] + f["h"] / 2 for f in frames])
    v = np.hypot(np.diff(cx), np.diff(cy)) * fps / W
    vx = np.diff(cx) * fps / W
    acc = np.abs(np.diff(vx)) * fps
    near_cut = np.zeros(len(t), bool)
    for c in cuts:
        near_cut |= np.abs(t - c) < 2.5 / fps
    v_ok = v[~near_cut[1:]]
    a_ok = acc[~(near_cut[1:-1] | near_cut[2:])]
    vmax = float(v_ok.max()) if v_ok.size else 0.0
    amax = float(a_ok.max()) if a_ok.size else 0.0
    rows.append(("smoothness", vmax <= p_cfg["max_speed"] * 1.01 and amax <= p_cfg["max_accel"],
                 f"max speed {vmax:.3f} frame widths/s (limit {p_cfg['max_speed']}), max acceleration {amax:.2f} W/s^2 "
                 f"(limit {p_cfg['max_accel']}), away from {len(cuts)} cuts; moving on {np.mean(v > 0.02):.0%} of frames"))

    # 6. Framing of the active speaker.
    faces = face_positions(det, t)
    settled = np.concatenate([[True], v < 0.02])
    inside = total = 0
    misses = []
    for k, f in enumerate(frames):
        seg = plan["segments"][f["segment"]]
        if seg["kind"] != "speaker" or not settled[k] or seg["speaker"] not in faces:
            continue
        fx, fy, fw, fh = faces[seg["speaker"]][k]
        if np.isnan(fx):
            continue
        total += 1
        ok = f["x"] - 1 <= fx - fw / 2 and fx + fw / 2 <= f["x"] + f["w"] + 1 and f["y"] - 1 <= fy - fh / 2 and fy + fh / 2 <= f["y"] + f["h"] + 1
        inside += ok
        if not ok and len(misses) < 5:
            misses.append(round(f["t"], 2))
    share = inside / total if total else 0.0
    rows.append(("framing", total > 0 and share >= 0.99,
                 f"speaker's face fully inside the crop on {share:.1%} of {total} settled frames in speaker segments"
                 + (f"; first misses at {misses}" if misses else "")))
    return rows


def run_check(video: str, work: str, cfg: dict, labels: str | None = None, negative: str | None = None,
              start: float | None = None, end: float | None = None) -> int:
    rep = Report()
    work_dir = Path(work)

    # Stages (detections reused when up to date; speakers + plan forced so timings are real).
    try:
        d = run_detect(video, work_dir, cfg, start=start, end=end)
        s = run_speakers(work_dir, cfg, force=True)
        p = run_plan(work_dir, cfg, force=True)
        det = contracts.read(d["path"], "detections")
        sp = contracts.read(s["path"], "speakers")
        plan = contracts.read(p["path"], "crop_plan")
        rep.add(1, "contracts 5.2/5.3", True,
                f"speakers.json ({sp['backend']['name']}, {s['seconds']:.1f}s) and crop_plan.json ({p['seconds']:.1f}s) validate; "
                f"backend notes: {'; '.join(sp['backend'].get('notes', [])) or 'none'}")
    except Exception as exc:  # noqa: BLE001 — the report must show any failure
        rep.add(1, "contracts 5.2/5.3", False, f"{type(exc).__name__}: {exc}")
        rep.print()
        return 1

    for num, (name, ok, detail) in enumerate(structural(det, sp, plan, cfg), start=2):
        rep.add(num, name, ok, detail)

    # 7. Labelled accuracy, for the default backend and (when installed) LR-ASD.
    if labels:
        lab = load_labels(labels)
        ev = evaluate(sp, det, lab)
        ok = ev["framing_accuracy"] >= 0.9 and not ev["interjections_that_moved_frame"]
        rep.add(7, f"labels ({sp['backend']['name']})", ok,
                f"framing accuracy {ev['framing_accuracy']:.1%} (>= 90% required), raw per-frame accuracy {ev['raw_accuracy']:.1%}, "
                f"interjections that moved the frame: {len(ev['interjections_that_moved_frame'])} of {ev['interjections']} "
                f"({ev['frames_scored']} frames scored, 0.5 s around label changes excluded)")
        if _lrasd_available() and sp["backend"]["name"] != "lrasd":
            lw = work_dir / "lrasd"
            lw.mkdir(parents=True, exist_ok=True)
            shutil.copy(d["path"], lw / "detections.json")
            try:
                ls = run_speakers(lw, cfg, backend="lrasd", force=True)
                run_plan(lw, cfg, force=True)
                lsp = contracts.read(ls["path"], "speakers")
                lplan = contracts.read(lw / "crop_plan.json", "crop_plan")
                lev = evaluate(lsp, det, lab)
                bad = [name for name, good, _ in structural(det, lsp, lplan, cfg) if not good]
                ok = lev["framing_accuracy"] >= 0.9 and not lev["interjections_that_moved_frame"] and not bad
                rep.add(7, "labels (lrasd)", ok,
                        f"framing accuracy {lev['framing_accuracy']:.1%}, raw per-frame accuracy {lev['raw_accuracy']:.1%}, "
                        f"interjections that moved the frame: {len(lev['interjections_that_moved_frame'])}; "
                        f"structural checks failing: {bad or 'none'}; {ls['seconds']:.1f}s; {'; '.join(lsp['backend']['notes'])}")
            except Exception as exc:  # noqa: BLE001
                rep.add(7, "labels (lrasd)", False, f"{type(exc).__name__}: {exc}")
        elif not _lrasd_available():
            rep.add(7, "labels (lrasd)", None, "LR-ASD extra not installed (pip install -e '.[lrasd]'); skipped")
    else:
        rep.add(7, "labels", None, "no --labels given; accuracy not measured")

    # 8. Re-runs are skipped.
    s2 = run_speakers(work_dir, cfg)
    p2 = run_plan(work_dir, cfg)
    rep.add(8, "re-run skipped", s2["skipped"] and p2["skipped"],
            f"speakers skipped: {s2['skipped']}, plan skipped: {p2['skipped']}")

    # 9. Licences, including the bundled LR-ASD export.
    findings = audit()
    bad_lic = [f"{f.name} ({f.license})" for f in findings if f.verdict == "forbidden"]
    unknown = [f"{f.name} ({f.license})" for f in findings if f.verdict == "unknown"]
    lic_file = PROJECT_ROOT / "clipper" / "speakers" / "models" / "LICENSE-LR-ASD.txt"
    lrasd_mit = lic_file.is_file() and lic_file.read_text(encoding="utf-8").startswith("MIT License")
    hits = forbidden_imports()
    rep.add(9, "licences", not bad_lic and not hits and lrasd_mit,
            f"{len(findings)} packages/models checked; forbidden: {bad_lic or 'none'}; unknown: {unknown or 'none'}; "
            f"LR-ASD weights licence MIT: {lrasd_mit}; ultralytics imports: {hits or 'none'}")

    # 10. Unit tests.
    proc = subprocess.run([sys.executable, "-m", "pytest", "-q", str(PROJECT_ROOT / "tests")],
                          cwd=PROJECT_ROOT, capture_output=True, text=True)
    tail = (proc.stdout.strip().splitlines() or [proc.stderr.strip()])[-1]
    rep.add(10, "unit tests", proc.returncode == 0, tail)

    # 11. Nobody speaking: how much of a silent, non-talking video gets framed as a speaker.
    if negative:
        nw = work_dir / "negative"
        nd = run_detect(negative, nw, cfg)
        ns = run_speakers(nw, cfg, force=True)
        nsp = contracts.read(ns["path"], "speakers")
        total = nsp["range"]["end"] - nsp["range"]["start"]
        framed = sum(x["end"] - x["start"] for x in nsp["segments"] if x["kind"] == "speaker")
        raw_on = np.mean([f["raw_active"] is not None for f in nsp["frames"]])
        rep.add(11, "nobody speaking", None,
                f"{Path(negative).name}: {framed / total:.0%} of the time framed as someone speaking, raw decision active on "
                f"{raw_on:.0%} of frames ({'; '.join(nsp['backend']['notes'])}); {nd['path']}")

    # 13. Cuts: the same video re-edited as a multi-camera cut (with its audio).
    try:
        mc = make_multicam(video, work_dir / "multicam.mp4", start or 0.0, with_audio=True)
        mw = work_dir / "multicam"
        run_detect(mc, mw, cfg, force=True)
        run_speakers(mw, cfg, force=True)
        run_plan(mw, cfg, force=True)
        mdet = contracts.read(mw / "detections.json", "detections")
        msp = contracts.read(mw / "speakers.json", "speakers")
        mplan = contracts.read(mw / "crop_plan.json", "crop_plan")
        cuts = mplan["cuts"]
        spanning = [x["index"] for x in msp["segments"] if any(x["start"] + TOL < c < x["end"] - TOL for c in cuts)]
        bad = [f"{name}: {detail}" for name, good, detail in structural(mdet, msp, mplan, cfg) if not good]
        fr = mplan["frames"]
        jumps = []
        W = mplan["source"]["width"]
        for a, b in zip(fr, fr[1:]):
            if abs(b["x"] - a["x"]) * mplan["output"]["fps"] / W > cfg["plan"]["max_speed"] * 1.01:
                jumps.append(round(b["t"], 2))
        jumps_at_cuts = all(any(abs(j - c) < 1.5 / mplan["output"]["fps"] for c in cuts) for j in jumps)
        ok = len(cuts) == 3 and not spanning and not bad and jumps_at_cuts
        rep.add(13, "cuts", ok,
                f"cuts {cuts}; segments spanning a cut: {spanning or 'none'}; crop jumps at {jumps or 'none'} "
                f"(only at cuts: {jumps_at_cuts}); structural checks on the multi-camera edit: {bad or 'all pass'}")
    except Exception as exc:  # noqa: BLE001
        rep.add(13, "cuts", False, f"{type(exc).__name__}: {exc}")

    # 12. Visual.
    strip = plan_strip(work_dir, work_dir / "plan_strip.png")
    preview = plan_preview(work_dir, work_dir / "plan_preview.mp4")
    rep.add(12, "visual", None, f"for human review: {strip}, {preview}")

    rep.rows.sort(key=lambda r: r["check"])
    rep.print()
    (work_dir / "phase2_check.json").write_text(json.dumps({"passed": rep.passed, "checks": rep.rows}, indent=2), encoding="utf-8")
    print(f"\nPhase 2 checkpoint: {'PASSED' if rep.passed else 'FAILED'}  (report: {work_dir / 'phase2_check.json'})")
    return 0 if rep.passed else 1
