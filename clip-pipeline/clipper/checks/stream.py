"""Stream-mode checkpoint: `clipper check stream` on the synthetic stream fixtures."""

from __future__ import annotations

import json
import shutil
import subprocess
import sys
import time
from pathlib import Path

import cv2
import numpy as np

from clipper import contracts, video as videolib
from clipper.checks.phase1 import PROJECT_ROOT, Report
from clipper.checks.phase3 import _ffprobe
from clipper.licenses import audit, forbidden_imports


def _run(args: list[str], config_path: str | None) -> subprocess.CompletedProcess:
    return subprocess.run([sys.executable, "-m", "clipper", "stream"] + args + (["--config", config_path] if config_path else []),
                          capture_output=True, text=True)


def _iou(a, b) -> float:
    iw = max(0.0, min(a[0] + a[2], b[0] + b[2]) - max(a[0], b[0]))
    ih = max(0.0, min(a[1] + a[3], b[1] + b[3]) - max(a[1], b[1]))
    inter = iw * ih
    return inter / (a[2] * a[3] + b[2] * b[3] - inter)


def _gray_frames(path: Path) -> list[np.ndarray]:
    cap, out = cv2.VideoCapture(str(path)), []
    while True:
        ok, f = cap.read()
        if not ok:
            break
        out.append(cv2.cvtColor(cv2.resize(f[700:1900], (135, 150)), cv2.COLOR_BGR2GRAY).astype(np.float32))
    cap.release()
    return out


def run_check(video: str, chat: str, transcript: str, labels: str, work: str, avatar: str | None = None,
              config_path: str | None = None) -> int:
    rep = Report()
    lab = json.loads(Path(labels).read_text())
    work_dir = Path(work)
    if work_dir.exists():
        shutil.rmtree(work_dir)
    count = len(lab["hype"])
    args = ["--video", video, "--chat", chat, "--transcript", transcript, "--work", str(work_dir), "--count", str(count), "--offline"]

    t0 = time.monotonic()
    proc = _run(args, config_path)
    elapsed = time.monotonic() - t0
    if proc.returncode != 0:
        rep.add(1, "end to end", False, proc.stderr[-1500:])
        rep.print()
        return 1
    src = videolib.probe(video)
    runlog = json.loads((work_dir / "stream_run_log.json").read_text())
    rep.add(1, "end to end", True, f"`clipper stream {' '.join(args)}` made {len(runlog['clips'])} clips from a "
            f"{src['duration'] / 60:.0f}-minute VOD in {elapsed:.0f}s with the network blocked; stages: "
            + ", ".join(f"{s['stage']} {s['seconds']:.0f}s" for s in runlog["stages"]))

    # 2. The planted moments, with build-up; the decoys are not clips.
    mom = contracts.read(work_dir / "stream_moments.json", "stream_moments")
    clips = mom["clips"]
    found = {h: next((c for c in clips if c["start"] + 8 <= h <= c["end"] - 3), None) for h in lab["hype"]}
    exp, sub = lab["decoys"]["explosion"], lab["decoys"]["sub_train"]
    decoy_hits = [c["rank"] for c in clips if c["start"] <= exp <= c["end"] or c["start"] <= sub + 4 <= c["end"]]
    ok = all(found.values()) and not decoy_hits
    rep.add(2, "moments", ok, "planted: " + ", ".join(f"{h:.0f}s -> " + (f"#{c['rank']} {c['start']:.1f}-{c['end']:.1f}s" if c else "MISSED")
                                                     for h, c in found.items())
            + f"; decoys (game explosion at {exp:.0f}s, sub train at {sub:.0f}s) selected: {decoy_hits or 'none'}")

    # 3. Layout: facecam found where it is, gameplay crop clear of it.
    plan = contracts.read(work_dir / "stream_plan.json", "stream_plan")
    W, H = src["width"], src["height"]
    true_cam = [v / s for v, s in zip(lab["cam_box_px"], (W, H, W, H))]
    det = plan["facecam_detection"]
    regions = {r["name"]: r for r in plan["regions"]}
    if plan["layout"] == "stacked" and det.get("overlay_box"):
        cam_iou = _iou(det["overlay_box"], true_cam)
        g = regions["gameplay"]["src"]
        clear = g[0] + g[2] <= lab["cam_box_px"][0] + 2 or g[0] >= lab["cam_box_px"][0] + lab["cam_box_px"][2] - 2
        c = regions["cam"]["src"]
        inside = c[0] >= lab["cam_box_px"][0] - 2 and c[0] + c[2] <= lab["cam_box_px"][0] + lab["cam_box_px"][2] + 2
        ok = cam_iou >= 0.85 and clear and inside
        detail = (f"layout stacked; facecam overlay found with IoU {cam_iou:.2f} against the true box; cam crop inside it: {inside}; "
                  f"gameplay crop clear of it: {clear}; face found in {det['presence']:.0%} of {det['sampled']} sampled frames")
    else:
        ok, detail = False, f"layout {plan['layout']} ({plan['layout_reason']}); expected stacked with a detected overlay"
    rep.add(3, "layout", ok, detail)

    # 4. Output files: format, duration, captions, and pixels against the plan.
    rlog = contracts.read(work_dir / "stream_render_log.json", "stream_render_log")
    problems, psnrs = [], []
    for rc in rlog["clips"]:
        out = Path(rc["output"])
        st = _ffprobe(out)
        v, a = st.get("video", {}), st.get("audio", {})
        dur = float(v.get("duration", 0))
        if v.get("codec_name") != "h264" or (v.get("width"), v.get("height")) != (1080, 1920) or a.get("codec_name") != "aac":
            problems.append(f"{out.name}: {v.get('codec_name')} {v.get('width')}x{v.get('height')} audio {a.get('codec_name')}")
        if abs(dur - (rc["end"] - rc["start"])) > 0.1:
            problems.append(f"{out.name}: {dur:.2f}s vs {rc['end'] - rc['start']:.2f}s planned")
        if not rc["captions"] or "Dialogue:" not in Path(rc["captions"]).read_text():
            problems.append(f"{out.name}: no captions")
        if plan["layout"] == "stacked":
            cap_c, cap_s = cv2.VideoCapture(str(out)), cv2.VideoCapture(src["path"])
            mid = (rc["end"] - rc["start"]) / 2
            cap_c.set(cv2.CAP_PROP_POS_MSEC, mid * 1000)
            ok_c, frame = cap_c.read()
            t_out = cap_c.get(cv2.CAP_PROP_POS_MSEC) / 1000
            cap_s.set(cv2.CAP_PROP_POS_MSEC, (rc["start"] + t_out) * 1000)
            ok_s, sframe = cap_s.read()
            if ok_c and ok_s:
                x, y, w, h = (int(round(q)) // 2 * 2 for q in regions["cam"]["src"])
                top_h = regions["cam"]["dst"][3]
                want = cv2.resize(sframe[y:y + h, x:x + w], (1080, top_h), interpolation=cv2.INTER_AREA)
                rows = slice(0, int(top_h * 0.8))  # above the caption line
                mse = float(np.mean((want[rows].astype(float) - frame[rows].astype(float)) ** 2))
                psnrs.append(10 * np.log10(255 ** 2 / max(mse, 1e-6)))
            cap_c.release()
            cap_s.release()
    pix_ok = not psnrs or min(psnrs) >= 25
    rep.add(4, "outputs", not problems and pix_ok,
            f"{len(rlog['clips'])} clips, H.264 1080x1920 + AAC, durations as planned, captions burned in; cam panel vs source "
            f"cam crop PSNR min {min(psnrs) if psnrs else float('nan'):.1f} dB" + (f"; problems: {problems}" if problems else ""))

    # 5. Reuse.
    again = _run(args, config_path)
    log2 = json.loads((work_dir / "stream_run_log.json").read_text())
    nothing = again.returncode == 0 and not any(s["ran"] for s in log2["stages"])
    rer = _run(args + ["--from", "render"], config_path)
    log3 = json.loads((work_dir / "stream_run_log.json").read_text())
    only_render = rer.returncode == 0 and [s["stage"] for s in log3["stages"] if s["ran"]] == ["render"]
    rep.add(5, "reuse", nothing and only_render, f"same command again: nothing re-ran: {nothing}; --from render re-ran only render: {only_render}")

    # 6. VTuber-style avatar, and the fit layout.
    if avatar:
        aw = work_dir / "avatar"
        pa = _run(["--video", avatar, "--work", str(aw), "--count", "1", "--offline"], config_path)
        pf = _run(["--video", avatar, "--work", str(aw / "fit"), "--count", "1", "--layout", "fit", "--offline"], config_path)
        if pa.returncode == 0 and pf.returncode == 0:
            ap = contracts.read(aw / "stream_plan.json", "stream_plan")
            st = _ffprobe(Path(json.loads((aw / "fit" / "stream_run_log.json").read_text())["clips"][0]["output"]))
            fit_ok = (st["video"]["width"], st["video"]["height"]) == (1080, 1920)
            rep.add(6, "avatar / fit", fit_ok,
                    f"drawn avatar (no human face): auto chose {ap['layout']} ({ap['layout_reason']}); "
                    f"--layout fit renders 1080x1920: {fit_ok}; no chat or transcript needed (audio only)")
        else:
            rep.add(6, "avatar / fit", False, (pa.stderr or pf.stderr)[-800:])

    # 10. Light mode (--url): audio + chat + only the chosen clips' video; same moments, frame-exact clips.
    lw = work_dir / "light"
    pl = _run(["--url", video, "--chat", chat, "--transcript", transcript, "--work", str(lw), "--count", str(count), "--offline"],
              config_path)
    if pl.returncode == 0:
        lm = contracts.read(lw / "stream_moments.json", "stream_moments")["clips"]
        same = sorted((c["start"], c["end"]) for c in lm) == sorted((c["start"], c["end"]) for c in clips)
        fetched = sum(f.stat().st_size for f in (lw / "source").iterdir())
        full = Path(video).stat().st_size
        a_frames = _gray_frames(Path(json.loads((lw / "stream_run_log.json").read_text())["clips"][0]["output"]))
        ref = next(c for c in rlog["clips"] if (c["start"], c["end"]) == (lm[0]["start"], lm[0]["end"]))
        b_frames = _gray_frames(Path(ref["output"]))
        best = max(range(-3, 4), key=lambda off: np.mean([-((a_frames[k] - b_frames[k + off]) ** 2).mean()
                                                           for k in range(10, min(len(a_frames), len(b_frames)) - 10, 10)]))
        rep.add(10, "light mode (--url)", same and best == 0,
                f"same moments as the full VOD: {same}; fetched {fetched / 1e6:.1f} MB (audio + {len(lm)} clip segments) "
                f"instead of the {full / 1e6:.1f} MB VOD; best frame alignment with the full-mode clip at offset {best} frames")
    else:
        rep.add(10, "light mode (--url)", False, pl.stderr[-800:])

    findings = audit()
    bad = [f.name for f in findings if f.verdict == "forbidden"]
    rep.add(7, "licences", not bad and not forbidden_imports(), f"{len(findings)} checked, forbidden: {bad or 'none'}")
    proc = subprocess.run([sys.executable, "-m", "pytest", "-q", str(PROJECT_ROOT / "tests")], cwd=PROJECT_ROOT, capture_output=True, text=True)
    rep.add(8, "unit tests", proc.returncode == 0, (proc.stdout.strip().splitlines() or [proc.stderr])[-1])
    rep.add(9, "visual", None, f"for human review: {work_dir / 'clips'}, {work_dir / 'signals.png'}, {work_dir / 'layout_preview.png'}")

    rep.rows.sort(key=lambda r: r["check"])
    rep.print()
    (work_dir / "stream_check.json").write_text(json.dumps({"passed": rep.passed, "checks": rep.rows}, indent=2), encoding="utf-8")
    print(f"\nStream checkpoint: {'PASSED' if rep.passed else 'FAILED'}  (report: {work_dir / 'stream_check.json'})")
    return 0 if rep.passed else 1
