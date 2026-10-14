"""Command-line interface: `clipper <command>` (or `python -m clipper`)."""

from __future__ import annotations

import argparse
import logging
import sys
from pathlib import Path

from clipper import __version__
from clipper.config import load_config


def _common(p: argparse.ArgumentParser) -> None:
    p.add_argument("--config", help="YAML file deep-merged over the defaults")
    p.add_argument("-v", "--verbose", action="store_true")


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="clipper", description="Long horizontal video -> vertical clip that follows the speaker.")
    parser.add_argument("--version", action="version", version=f"clipper {__version__}")
    sub = parser.add_subparsers(dest="command", required=True)

    p = sub.add_parser("detect", help="Phase 1: faces, landmarks, tracks, shots -> WORK/detections.json")
    p.add_argument("video")
    p.add_argument("--start", type=float, help="range start, source seconds (default 0)")
    p.add_argument("--end", type=float, help="range end, source seconds (default: end of video)")
    p.add_argument("--work", default="work", help="work directory (default ./work)")
    p.add_argument("--force", action="store_true", help="re-run even if the output is up to date")
    _common(p)

    p = sub.add_parser("speakers", help="Phase 2: who speaks when -> WORK/speakers.json")
    p.add_argument("work", nargs="?", default="work", help="work directory holding detections.json")
    p.add_argument("--asd", choices=["heuristic", "lrasd"], help="active-speaker backend (default: speakers.backend)")
    p.add_argument("--force", action="store_true")
    _common(p)

    p = sub.add_parser("plan", help="Phase 2: smoothed 9:16 crop path -> WORK/crop_plan.json")
    p.add_argument("work", nargs="?", default="work", help="work directory holding detections.json and speakers.json")
    p.add_argument("--force", action="store_true")
    _common(p)

    p = sub.add_parser("inspect", help="render review images/videos from contract files")
    p.add_argument("what", choices=["detections", "plan"])
    p.add_argument("path", help="detections: the detections.json file; plan: the work directory")
    p.add_argument("--out", help="output path (default: next to the input)")
    p.add_argument("--count", type=int, default=12)
    p.add_argument("--video", action="store_true", help="plan: also write a side-by-side preview video")
    _common(p)

    p = sub.add_parser("licenses", help="audit installed packages and models (plan 2.2)")
    p.add_argument("--all", action="store_true", help="list every package, not only problems")
    _common(p)

    p = sub.add_parser("check", help="run a phase checkpoint verification")
    p.add_argument("phase", choices=["phase1", "phase2"])
    p.add_argument("video")
    p.add_argument("--expect-faces", type=int, help="phase1: number of people visible in the fixture")
    p.add_argument("--expect", help="phase1: fixture expectations JSON (e.g. tests/data/phase1_fixture.json)")
    p.add_argument("--labels", help="phase2: ground-truth speaker labels JSON")
    p.add_argument("--negative", help="phase2: a video in which nobody speaks (false-positive report)")
    p.add_argument("--start", type=float)
    p.add_argument("--end", type=float)
    p.add_argument("--work", help="work directory (default work/check-<phase>)")
    _common(p)
    return parser


def main(argv: list[str] | None = None) -> int:
    args = build_parser().parse_args(argv)
    logging.basicConfig(level=logging.DEBUG if args.verbose else logging.INFO, format="%(levelname)s %(message)s")
    cfg = load_config(args.config)

    if args.command == "detect":
        from clipper.detect.run import run_detect
        res = run_detect(args.video, args.work, cfg, start=args.start, end=args.end, force=args.force)
        print(res["path"])
        return 0

    if args.command == "speakers":
        from clipper.speakers.run import run_speakers
        print(run_speakers(args.work, cfg, backend=args.asd, force=args.force)["path"])
        return 0

    if args.command == "plan":
        from clipper.plan.run import run_plan
        print(run_plan(args.work, cfg, force=args.force)["path"])
        return 0

    if args.command == "inspect":
        if args.what == "detections":
            from clipper.inspect import contact_sheet
            out = args.out or str(Path(args.path).with_suffix(".png"))
            print(contact_sheet(args.path, out, count=args.count))
        else:
            from clipper.inspect import plan_preview, plan_strip
            print(plan_strip(args.path, args.out or Path(args.path) / "plan_strip.png", count=args.count))
            if args.video:
                print(plan_preview(args.path, Path(args.path) / "plan_preview.mp4"))
        return 0

    if args.command == "licenses":
        from clipper.licenses import audit, forbidden_imports
        findings = audit()
        bad = [f for f in findings if f.verdict == "forbidden"]
        unknown = [f for f in findings if f.verdict == "unknown"]
        for f in findings if args.all else bad + unknown:
            print(f"{f.verdict:9}  {f.name} {f.version}: {f.license}")
        hits = forbidden_imports()
        for h in hits:
            print(f"forbidden import in {h}")
        print(f"{len(findings)} checked: {len(bad)} forbidden, {len(unknown)} unknown, {len(hits)} forbidden imports")
        return 1 if bad or hits else 0

    if args.command == "check":
        work = args.work or f"work/check-{args.phase}"
        if args.phase == "phase1":
            from clipper.checks.phase1 import run_check
            return run_check(args.video, work, cfg, expect_faces=args.expect_faces, expect_file=args.expect,
                             start=args.start, end=args.end)
        from clipper.checks.phase2 import run_check as run_check2
        return run_check2(args.video, work, cfg, labels=args.labels, negative=args.negative,
                          start=args.start, end=args.end)
    return 2


if __name__ == "__main__":
    sys.exit(main())
