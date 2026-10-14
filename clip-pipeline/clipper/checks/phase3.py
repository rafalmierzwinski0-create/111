"""Phase 3 checkpoint verification (plan 6.3)."""

from __future__ import annotations

import json
import shutil
import subprocess
import sys
import threading
import time
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path

import cv2
import numpy as np

from clipper import audio, contracts, transcript as tr, video as videolib
from clipper.checks.phase1 import PROJECT_ROOT, Report
from clipper.licenses import audit, forbidden_imports
from clipper.render.renderer import crop_frame


def _clipper(args: list[str], config_path: str | None) -> subprocess.CompletedProcess:
    cmd = [sys.executable, "-m", "clipper"] + args + (["--config", config_path] if config_path else [])
    return subprocess.run(cmd, cwd=Path.cwd(), capture_output=True, text=True)


def _ffprobe(path: Path) -> dict:
    out = subprocess.run([videolib.require_tool("ffprobe"), "-v", "error", "-print_format", "json", "-show_streams", str(path)],
                         check=True, capture_output=True, text=True).stdout
    return {s["codec_type"]: s for s in json.loads(out)["streams"]}


def _envelope(x: np.ndarray, sr: int = 16000, step: float = 0.005) -> np.ndarray:
    n = int(sr * step)
    m = len(x) // n * n
    return np.sqrt((x[:m].reshape(-1, n) ** 2).mean(axis=1))


def av_lag(clip: Path, source: str, start: float, duration: float) -> float:
    """Lag (s) of the clip's audio against the source audio of the same span (0 = in sync)."""
    a = audio.load_mono(str(clip), 0.0, duration)
    b = audio.load_mono(source, max(0.0, start - 0.5), start + duration + 0.5)
    if a is None or b is None:
        return float("nan")
    ea, eb = _envelope(a), _envelope(b)
    ea = (ea - ea.mean()) / (ea.std() + 1e-9)
    eb = (eb - eb.mean()) / (eb.std() + 1e-9)
    offset0 = int(round(min(0.5, start) / 0.005))
    best, best_lag = -np.inf, 0
    for lag in range(-80, 81):  # ±0.4 s
        o = offset0 + lag
        if o < 0 or o + len(ea) > len(eb):
            continue
        c = float(np.dot(ea, eb[o:o + len(ea)]))
        if c > best:
            best, best_lag = c, lag
    return best_lag * 0.005


class _MockLLM(BaseHTTPRequestHandler):
    """A local OpenAI-compatible server that rates the last candidate highest."""

    def do_POST(self):  # noqa: N802
        body = json.loads(self.rfile.read(int(self.headers["Content-Length"])))
        prompt = body["messages"][0]["content"]
        ids = [int(x.split("]")[0]) for x in prompt.split("[id ")[1:]]
        ratings = [{"id": i, "score": 10 if i == ids[-1] else 1, "reason": "mock rating"} for i in ids]
        content = json.dumps({"ratings": ratings})
        data = json.dumps({"choices": [{"message": {"content": content}}]}).encode()
        self.send_response(200)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def log_message(self, *args):
        pass


def run_check(video: str, transcript: str, work: str, cfg: dict, config_path: str | None = None) -> int:
    rep = Report()
    work_dir = Path(work)
    if work_dir.exists():
        shutil.rmtree(work_dir)
    work_dir.mkdir(parents=True)
    run_args = ["run", "--video", video, "--transcript", transcript, "--work", str(work_dir), "--offline"]

    # 1. One command, end to end, network blocked.
    t0 = time.monotonic()
    proc = _clipper(run_args, config_path)
    elapsed = time.monotonic() - t0
    (work_dir / "run_stdout.txt").write_text(proc.stdout + proc.stderr, encoding="utf-8")
    if proc.returncode != 0:
        rep.add(1, "end to end", False, f"`clipper {' '.join(run_args)}` failed:\n{proc.stderr[-2000:]}")
        rep.print()
        return 1
    runlog = json.loads((work_dir / "run_log.json").read_text())
    src = videolib.probe(video)
    rep.add(1, "end to end", True,
            f"`clipper {' '.join(run_args)}` finished in {elapsed:.0f}s for a {src['duration'] / 60:.1f}-minute source with the "
            f"network blocked; stages: " + ", ".join(f"{e['stage']} {e['seconds']:.0f}s" for e in runlog["stages"]))

    # 2. Moment: contract, sentence alignment (recomputed independently), duration.
    m = contracts.read(work_dir / "moment.json", "moment")
    sel, clip = m["selected"], m["clip"]
    sents = tr.sentences(tr.load(transcript), cfg["select"]["split_on_speaker_change"])
    i, j = sel["first_sentence"], sel["last_sentence"]
    aligned = abs(sents[i].start - sel["start"]) < 1e-3 and abs(sents[j].end - sel["end"]) < 1e-3
    no_neighbour_words = (i == 0 or clip["start"] >= sents[i - 1].end - 1e-3) and \
        (j + 1 >= len(sents) or clip["end"] <= sents[j + 1].start + 1e-3)
    c = m["constraints"]
    in_bounds = c["min_s"] <= sel["duration"] <= c["max_s"]
    rep.add(2, "moment", aligned and no_neighbour_words and in_bounds,
            f"moment.json validates; starts at sentence {i} start ({sents[i].start:.2f}s) and ends at sentence {j} end "
            f"({sents[j].end:.2f}s): {aligned}; padded clip {clip['start']:.2f}-{clip['end']:.2f}s stays clear of the "
            f"neighbouring sentences: {no_neighbour_words}; duration {sel['duration']:.1f}s within [{c['min_s']}, {c['max_s']}]: {in_bounds}; "
            f"{m['candidates_scored']} sentence-aligned candidates scored, cut-sentence windows formed: {m['rejected']['not_sentence_aligned']}")

    # 3. Output file.
    plan = contracts.read(work_dir / "crop_plan.json", "crop_plan")
    out = work_dir / "clip.mp4"
    st = _ffprobe(out)
    v, a = st.get("video", {}), st.get("audio")
    fps = plan["output"]["fps"]
    expected = len(plan["frames"]) / fps
    vdur = float(v.get("duration", 0))
    adur = float(a["duration"]) if a else float("nan")
    lag = av_lag(out, src["path"], plan["frames"][0]["t"], expected) if a else float("nan")
    ok = (v.get("codec_name") == "h264" and v.get("width") == 1080 and v.get("height") == 1920
          and (a is not None and a.get("codec_name") == "aac") and abs(vdur - expected) <= 1 / fps + 1e-3
          and abs(adur - vdur) <= 1 / fps + 0.03 and abs(lag) <= 1 / fps)
    rep.add(3, "output", ok,
            f"{out.name}: {v.get('codec_name')} {v.get('width')}x{v.get('height')} @ {v.get('r_frame_rate')}, "
            f"{a.get('codec_name') if a else 'no'} audio; video {vdur:.3f}s vs plan {expected:.3f}s; audio {adur:.3f}s; "
            f"audio lag against the source {lag * 1000:+.0f} ms (limit ±{1000 / fps:.0f} ms)")

    # 4. Renderer did exactly the plan; the overlay drew exactly what the renderer did.
    rlog = contracts.read(work_dir / "render_log.json", "render_log")
    olog = contracts.read(work_dir / "overlay_log.json", "overlay_log")
    keys = ("x", "y", "w", "h")
    plan_vs_render = max(max(abs(p[k] - r[k]) for k in keys) for p, r in zip(plan["frames"], rlog["frames"]))
    render_vs_overlay = max(max(abs(r[k] - o[k]) for k in keys) for r, o in zip(rlog["frames"], olog["frames"]))
    counts = (len(plan["frames"]), len(rlog["frames"]), len(olog["frames"]))
    time_err = max(abs(r["source_t"] - r["t"]) for r in rlog["frames"])
    # Pixels: re-cut the source with the logged rectangle and compare with the decoded clip.
    picks = np.linspace(0, len(rlog["frames"]) - 1, 8).round().astype(int)
    cap_c, cap_s = cv2.VideoCapture(str(out)), cv2.VideoCapture(src["path"])
    psnrs = []
    for k in picks:
        cap_c.set(cv2.CAP_PROP_POS_FRAMES, int(k))
        ok_c, got = cap_c.read()
        r = rlog["frames"][k]
        cap_s.set(cv2.CAP_PROP_POS_FRAMES, int(round(r["source_t"] * src["fps"])))
        ok_s, sframe = cap_s.read()
        if ok_c and ok_s:
            want = crop_frame(sframe, r["x"], r["y"], r["w"], r["h"], got.shape[1], got.shape[0])
            mse = float(np.mean((want.astype(float) - got.astype(float)) ** 2))
            psnrs.append(99.0 if mse == 0 else 10 * np.log10(255 ** 2 / mse))
    cap_c.release()
    cap_s.release()
    ok = len(set(counts)) == 1 and plan_vs_render < 0.01 and render_vs_overlay < 0.01 and time_err <= 0.5 / src["fps"] + 1e-3 \
        and psnrs and min(psnrs) >= 30
    rep.add(4, "render = plan = overlay", ok,
            f"frames plan/render/overlay {counts}; max rectangle difference plan vs render {plan_vs_render:.4f}px, "
            f"render vs overlay {render_vs_overlay:.4f}px; source frame time error <= {time_err * 1000:.1f} ms; "
            f"clip frames vs source re-cut with the logged rectangle: PSNR min {min(psnrs) if psnrs else 0:.1f} dB over {len(psnrs)} frames")

    # 5. Reuse.
    t1 = time.monotonic()
    again = _clipper(run_args, config_path)
    t_again = time.monotonic() - t1
    log2 = json.loads((work_dir / "run_log.json").read_text())
    nothing_ran = again.returncode == 0 and not any(e["ran"] for e in log2["stages"])
    rerender = _clipper(run_args + ["--from", "render"], config_path)
    log3 = json.loads((work_dir / "run_log.json").read_text())
    ran3 = [e["stage"] for e in log3["stages"] if e["ran"]]
    only_render = rerender.returncode == 0 and ran3 == ["render", "overlay"]
    rep.add(5, "reuse", nothing_ran and only_render,
            f"same command again: {'nothing re-ran' if nothing_ran else 'stages re-ran: ' + str([e['stage'] for e in log2['stages'] if e['ran']])} "
            f"({t_again:.1f}s); `--from render`: ran {ran3} (select/detect/speakers/plan reused)")

    # 6. Plan 2.1: local only, hosted LLM refused, local LLM works; licences.
    hosted_cfg = work_dir / "hosted_llm.yaml"
    hosted_cfg.write_text("select:\n  scorer: llm\n  llm:\n    endpoint: https://api.openai.com\n    api: openai\n", encoding="utf-8")
    refused = subprocess.run([sys.executable, "-m", "clipper", "select", "--video", video, "--transcript", transcript,
                              "--work", str(work_dir / "llm-hosted"), "--config", str(hosted_cfg), "--force"],
                             capture_output=True, text=True)
    refused_ok = refused.returncode != 0 and "refusing LLM endpoint" in refused.stderr and "offline mode" not in refused.stderr
    server = HTTPServer(("127.0.0.1", 0), _MockLLM)
    threading.Thread(target=server.serve_forever, daemon=True).start()
    local_cfg = work_dir / "local_llm.yaml"
    local_cfg.write_text(f"select:\n  scorer: llm\n  llm:\n    endpoint: http://127.0.0.1:{server.server_port}\n    api: openai\n    model: mock\n",
                         encoding="utf-8")
    local = subprocess.run([sys.executable, "-m", "clipper", "select", "--video", video, "--transcript", transcript,
                            "--work", str(work_dir / "llm-local"), "--config", str(local_cfg), "--force"],
                           capture_output=True, text=True)
    server.shutdown()
    local_ok = False
    local_detail = local.stderr[-300:]
    if local.returncode == 0:
        lm = contracts.read(work_dir / "llm-local" / "moment.json", "moment")
        ls = lm["selected"]
        local_ok = abs(sents[ls["first_sentence"]].start - ls["start"]) < 1e-3 and abs(sents[ls["last_sentence"]].end - ls["end"]) < 1e-3 \
            and any("LLM" in r for r in ls["reasons"])
        local_detail = f"re-ranked to {ls['start']:.1f}-{ls['end']:.1f}s, still sentence-aligned"
    findings = audit()
    bad_lic = [f"{f.name} ({f.license})" for f in findings if f.verdict == "forbidden"]
    hits = forbidden_imports()
    rep.add(6, "local only (2.1) + licences", refused_ok and local_ok and not bad_lic and not hits,
            f"run passed with outbound network blocked (--offline); hosted LLM endpoint refused before any request: {refused_ok}; "
            f"local LLM endpoint (mock server on 127.0.0.1) works: {local_ok} ({local_detail}); "
            f"licences: {len(findings)} checked, forbidden {bad_lic or 'none'}; ultralytics imports: {hits or 'none'}")

    # 7. Transcript import (SRT next to the JSON, when present).
    srt = Path(transcript).with_suffix(".srt")
    if srt.is_file():
        imported = tr.import_file(srt, "en")
        isents = tr.sentences(imported)
        same = len(isents) == len(sents) and all(abs(a.start - b.start) < 0.01 and abs(a.end - b.end) < 0.01 for a, b in zip(isents, sents))
        rep.add(7, "transcript import", same,
                f"{srt.name} imported: {len(imported['segments'])} segments, {len(isents)} sentences "
                f"(JSON: {len(sents)}), same sentence boundaries: {same}; no word timings -> interpolated within segments")

    # 8. Unit tests.
    proc = subprocess.run([sys.executable, "-m", "pytest", "-q", str(PROJECT_ROOT / "tests")], cwd=PROJECT_ROOT,
                          capture_output=True, text=True)
    rep.add(8, "unit tests", proc.returncode == 0, (proc.stdout.strip().splitlines() or [proc.stderr.strip()])[-1])

    # 9. Which moment and why.
    runner = m["candidates"][1] if len(m["candidates"]) > 1 else None
    why = "; ".join(sel["reasons"])
    feats = ", ".join(f"{k} {v:.2f}" for k, v in sel["features"].items())
    detail = (f"{sel['start']:.2f}-{sel['end']:.2f}s ({sel['duration']:.1f}s, clip {clip['start']:.2f}-{clip['end']:.2f}s), "
              f"score {sel['score']:.3f} [{feats}]. Why: {why}. Opens: \"{sents[i].text}\" Ends: \"{sents[j].text}\"")
    if runner:
        diffs = sorted(((sel["features"][k] - runner["features"].get(k, 0)), k) for k in sel["features"])
        detail += (f" Runner-up {runner['start']:.1f}-{runner['end']:.1f}s score {runner['score']:.3f}, mainly weaker on "
                   + ", ".join(f"{k} (-{d:.2f})" for d, k in reversed(diffs[-2:]) if d > 0))
    rep.add(9, "selected moment", None, detail)

    # 10. Visual.
    rep.add(10, "visual", None, f"for human review: {out}, {work_dir / 'debug.mp4'}")

    rep.rows.sort(key=lambda r: r["check"])
    rep.print()
    (work_dir / "phase3_check.json").write_text(json.dumps({"passed": rep.passed, "checks": rep.rows}, indent=2), encoding="utf-8")
    print(f"\nPhase 3 checkpoint: {'PASSED' if rep.passed else 'FAILED'}  (report: {work_dir / 'phase3_check.json'})")
    return 0 if rep.passed else 1
