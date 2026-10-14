"""Phase 1 checkpoint verification (plan 6.1)."""

from __future__ import annotations

import json
import math
import statistics
import subprocess
import sys
from collections import Counter
from pathlib import Path

import numpy as np

from clipper import contracts, video as videolib
from clipper.detect.landmarks import LOWER_LIP_INNER, MOUTH_CORNER_LEFT, MOUTH_CORNER_RIGHT, UPPER_LIP_INNER
from clipper.detect.run import run_detect
from clipper.inspect import contact_sheet
from clipper.licenses import audit, forbidden_imports

PROJECT_ROOT = Path(__file__).resolve().parents[2]


class Report:
    def __init__(self):
        self.rows: list[dict] = []

    def add(self, num: int, name: str, ok: bool | None, detail: str) -> None:
        self.rows.append({"check": num, "name": name, "result": {True: "PASS", False: "FAIL", None: "INFO"}[ok], "detail": detail})

    @property
    def passed(self) -> bool:
        return all(r["result"] != "FAIL" for r in self.rows)

    def print(self) -> None:
        width = max(len(r["name"]) for r in self.rows)
        for r in self.rows:
            print(f"{r['check']:>3}  {r['result']:4}  {r['name']:<{width}}  {r['detail']}")


def in_intervals(t: float, intervals: list[list[float]]) -> bool:
    return any(a <= t <= b for a, b in intervals)


def mouth_aperture(face: dict, pos: dict[int, int], width: int, height: int) -> float:
    pts = np.asarray(face["landmarks"], dtype=float)[:, :2] * (width, height)
    gap = np.linalg.norm(pts[pos[UPPER_LIP_INNER]] - pts[pos[LOWER_LIP_INNER]])
    mouth = np.linalg.norm(pts[pos[MOUTH_CORNER_LEFT]] - pts[pos[MOUTH_CORNER_RIGHT]])
    return float(gap / mouth) if mouth > 0 else 0.0


def run_check(video: str, work: str, cfg: dict, expect_faces: int | None = None, expect_file: str | None = None,
              start: float | None = None, end: float | None = None) -> int:
    no_face: list[list[float]] = []
    if expect_file:
        spec = json.loads(Path(expect_file).read_text(encoding="utf-8"))
        expect_faces = expect_faces or spec["expect_faces"]
        no_face = spec.get("no_face_intervals", [])
    if not expect_faces:
        raise SystemExit("check phase1 needs --expect-faces or --expect")

    rep = Report()
    work_dir = Path(work)

    # 1. Stage runs (forced, so the timing is a real one).
    try:
        res = run_detect(video, work_dir, cfg, start=start, end=end, force=True)
        speed = res["frames"] / res["seconds"] if res["seconds"] else float("nan")
        rep.add(1, "stage runs", True, f"{res['frames']} frames in {res['seconds']:.1f}s ({speed:.1f} analysed frames/s)")
    except Exception as exc:  # noqa: BLE001 — the report must show any failure
        rep.add(1, "stage runs", False, f"{type(exc).__name__}: {exc}")
        rep.print()
        return 1

    # 2. Contract.
    try:
        det = contracts.read(res["path"], "detections")
        rep.add(2, "contract 5.1", True, f"{res['path']} validates (schema_version {det['schema_version']})")
    except Exception as exc:  # noqa: BLE001
        rep.add(2, "contract 5.1", False, str(exc))
        rep.print()
        return 1

    frames = det["frames"]
    src, rng, an = det["source"], det["range"], det["analysis"]
    W, H = src["width"], src["height"]

    # 3. Frame coverage.
    expected = math.ceil((rng["end"] - rng["start"]) * src["fps"] / an["frame_step"] - 1e-6)
    times = [f["t"] for f in frames]
    increasing = all(b > a for a, b in zip(times, times[1:]))
    inside = all(rng["start"] - 1e-3 <= t < rng["end"] for t in times)
    ok = abs(len(frames) - expected) <= 1 and increasing and inside
    rep.add(3, "frame coverage", ok, f"{len(frames)} entries, expected {expected}±1; increasing={increasing}; inside range={inside}")

    # 4. Detection rate.
    counts = Counter(len(f["faces"]) for f in frames)
    any_face_all = sum(1 for f in frames if f["faces"]) / len(frames)
    visible = [f for f in frames if not in_intervals(f["t"], no_face)]
    excluded = len(frames) - len(visible)
    any_face = sum(1 for f in visible if f["faces"]) / max(1, len(visible))
    exact = sum(1 for f in visible if len(f["faces"]) == expect_faces) / max(1, len(visible))
    modal = Counter(len(f["faces"]) for f in visible).most_common(1)[0][0] if visible else None
    ok = any_face >= 0.95 and modal == expect_faces
    rep.add(4, "detection rate", ok,
            f"frames with >=1 face: {any_face:.1%} of {len(visible)} frames where faces are visible "
            f"({excluded} frames in human-reviewed no-face intervals excluded; {any_face_all:.1%} of all frames); "
            f"exactly {expect_faces} faces: {exact:.1%}; modal count {modal}; histogram {dict(sorted(counts.items()))}")

    # 5. Lip landmarks.
    lips = contracts.landmark_group(det, "lips_outer") + contracts.landmark_group(det, "lips_inner")
    n_faces = bad = 0
    for f in frames:
        for face in f["faces"]:
            n_faces += 1
            pts = np.asarray(face["landmarks"], dtype=float)[lips]
            if pts.shape != (40, 3) or not np.isfinite(pts).all() or pts[:, :2].min() < -0.1 or pts[:, :2].max() > 1.1:
                bad += 1
    rep.add(5, "lip landmarks", n_faces > 0 and bad == 0, f"{n_faces} faces, {len(lips)} lip points each, {bad} invalid")

    # 6. Track stability.
    long_tracks = {t["track_id"]: t for t in det["tracks"] if not t["spurious"] and t["end"] - t["start"] >= 1.0}
    max_concurrent = max((sum(1 for fc in f["faces"] if fc["track_id"] in long_tracks) for f in frames), default=0)
    centers = {tid: t["mean_bbox"][0] + t["mean_bbox"][2] / 2 for tid, t in long_tracks.items()}
    swaps = 0
    for f in frames:
        faces = [fc for fc in f["faces"] if fc["track_id"] in long_tracks]
        by_frame = [fc["track_id"] for fc in sorted(faces, key=lambda fc: fc["bbox"][0] + fc["bbox"][2] / 2)]
        if by_frame != sorted(by_frame, key=lambda tid: centers[tid]):
            swaps += 1
    # A new track is a fragmentation if a face was visible at the same place just
    # before it started (the tracker should have continued that track).
    gap_s = cfg["detect"]["max_gap_s"]
    fragments = []
    last_seen: dict[int, tuple[float, list[float], int]] = {}
    started: set[int] = set()
    for f in frames:
        for fc in f["faces"]:
            tid = fc["track_id"]
            if tid not in started:
                started.add(tid)
                for other, (t_last, box, shot) in last_seen.items():
                    same_place = abs((box[0] + box[2] / 2) - (fc["bbox"][0] + fc["bbox"][2] / 2)) < box[2]
                    if other != tid and same_place and f["t"] - t_last < gap_s and shot == f["shot"]:
                        fragments.append((tid, other, f["t"]))
            last_seen[tid] = (f["t"], fc["bbox"], f["shot"])
    per_shot = Counter(t["shot"] for t in long_tracks.values())
    ok = max_concurrent == expect_faces and swaps == 0 and not fragments
    rep.add(6, "track stability", ok,
            f"{len(long_tracks)} tracks >=1s (per shot {dict(per_shot)}), max concurrent {max_concurrent} (expected {expect_faces}), "
            f"identity swaps {swaps}, fragmentations while visible {len(fragments)}; tracks restart only after a face "
            f"was gone > {gap_s}s: " + ", ".join(f"#{t['track_id']} {t['start']:.1f}-{t['end']:.1f}s" for t in long_tracks.values()))

    # 7. Lip signal.
    pos = {idx: k for k, idx in enumerate(det["landmark_spec"]["indices"])}
    per_track: dict[int, list[float]] = {}
    for f in frames:
        for fc in f["faces"]:
            if fc["track_id"] in long_tracks:
                per_track.setdefault(fc["track_id"], []).append(mouth_aperture(fc, pos, W, H))
    spreads = {tid: float(np.percentile(v, 95) - np.percentile(v, 5)) for tid, v in per_track.items()}
    ok = any(s >= 0.02 for s in spreads.values())
    rep.add(7, "lip signal", ok, "aperture p95-p5 per track: " + ", ".join(f"#{k} {v:.3f}" for k, v in spreads.items())
            + (f"; median aperture {statistics.median([x for v in per_track.values() for x in v]):.3f}" if per_track else ""))

    # 8. Re-run is skipped.
    res2 = run_detect(video, work_dir, cfg, start=start, end=end, force=False)
    rep.add(8, "re-run skipped", res2["skipped"], "second run reused detections.json via fingerprint" if res2["skipped"] else "second run re-processed")

    # 9. Licences.
    findings = audit()
    bad_lic = [f"{f.name} ({f.license})" for f in findings if f.verdict == "forbidden"]
    unknown = [f"{f.name} ({f.license})" for f in findings if f.verdict == "unknown"]
    hits = forbidden_imports()
    rep.add(9, "licences", not bad_lic and not hits,
            f"{len(findings)} packages/models checked; forbidden: {bad_lic or 'none'}; unknown: {unknown or 'none'}; "
            f"ultralytics imports: {hits or 'none'}")

    # 10. Unit tests.
    proc = subprocess.run([sys.executable, "-m", "pytest", "-q", str(PROJECT_ROOT / "tests")],
                          cwd=PROJECT_ROOT, capture_output=True, text=True)
    tail = (proc.stdout.strip().splitlines() or [proc.stderr.strip()])[-1]
    rep.add(10, "unit tests", proc.returncode == 0, tail)

    # 12. Shot boundaries, on a synthetic multi-camera edit cut from the same video.
    rep_row = shot_boundary_check(video, work_dir, cfg, start or 0.0)
    rep.add(12, "shot boundaries", *rep_row)

    # 11. Visual.
    sheet = contact_sheet(res["path"], work_dir / "detections_sheet.png", video_path=video)
    rep.add(11, "visual", None, f"contact sheet for human review: {sheet}")

    rep.rows.sort(key=lambda r: r["check"])
    rep.print()
    (work_dir / "phase1_check.json").write_text(json.dumps({"passed": rep.passed, "checks": rep.rows}, indent=2), encoding="utf-8")
    print(f"\nPhase 1 checkpoint: {'PASSED' if rep.passed else 'FAILED'}  (report: {work_dir / 'phase1_check.json'})")
    return 0 if rep.passed else 1


def make_multicam(video: str, out: Path, offset: float, with_audio: bool = False) -> Path:
    """Wide / right-half close-up / left-half close-up / wide, 5 s each: hard cuts at 5, 10, 15 s."""
    src = videolib.probe(video)
    if src["duration"] - offset < 20:
        raise ValueError("video shorter than 20 s after the offset; cannot build the multi-camera test")
    w, h = src["width"] // 2 * 2, src["height"] // 2 * 2
    cw, ch = w // 2 // 2 * 2, h // 2 // 2 * 2
    seg = lambda k, crop: (f"[0:v]trim={offset + 5 * k}:{offset + 5 * k + 5},setpts=PTS-STARTPTS"
                           f"{',' + crop if crop else ''},scale={w}:{h},setsar=1[s{k}]")
    graph = ";".join([seg(0, ""), seg(1, f"crop={cw}:{ch}:{w - cw}:{h // 10}"), seg(2, f"crop={cw}:{ch}:0:{h // 8}"), seg(3, "")])
    graph += ";[s0][s1][s2][s3]concat=n=4:v=1:a=0[v]"
    cmd = [videolib.require_tool("ffmpeg"), "-v", "error", "-y", "-i", video, "-filter_complex", graph, "-map", "[v]"]
    if with_audio and src.get("has_audio"):
        cmd += ["-map", "0:a", "-af", f"atrim={offset}:{offset + 20},asetpts=PTS-STARTPTS", "-c:a", "aac"]
    cmd += ["-r", str(src["fps"]), "-c:v", "libx264", "-pix_fmt", "yuv420p", "-t", "20", str(out)]
    out.parent.mkdir(parents=True, exist_ok=True)
    subprocess.run(cmd, check=True)
    return out


def shot_boundary_check(video: str, work_dir: Path, cfg: dict, offset: float) -> tuple[bool, str]:
    try:
        out = make_multicam(video, work_dir / "multicam.mp4", offset)
    except ValueError as exc:
        return False, str(exc)
    res = run_detect(out, work_dir / "multicam", cfg, force=True)
    det = contracts.read(res["path"], "detections")
    cuts = [s["start"] for s in det["shots"][1:]]
    frame = 1.0 / det["source"]["fps"] * det["analysis"]["frame_step"]
    cuts_ok = len(cuts) == 3 and all(abs(c - e) <= frame + 1e-6 for c, e in zip(cuts, (5.0, 10.0, 15.0)))
    shot_of = {s["index"]: s for s in det["shots"]}
    crossing = [t["track_id"] for t in det["tracks"]
                if not (shot_of[t["shot"]]["start"] - 1e-6 <= t["start"] and t["end"] < shot_of[t["shot"]]["end"])]
    ghosts = [t["track_id"] for t in det["tracks"] if t["spurious"] and any(abs(t["start"] - c) < 1e-6 for c in cuts)]
    ok = cuts_ok and not crossing and not ghosts
    return ok, (f"cuts at {[round(c, 3) for c in cuts]} (expected 5, 10, 15 ±1 frame); tracks crossing a cut: "
                f"{crossing or 'none'}; one-frame ghost faces at cuts: {ghosts or 'none'}; "
                f"{len(det['tracks'])} tracks over {len(det['shots'])} shots")
