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

    p = sub.add_parser("run", help="the whole pipeline: source + transcript -> WORK/clip.mp4 (+ debug.mp4)")
    p.add_argument("--video", required=True, help="the full-length source video")
    p.add_argument("--transcript", required=True, help="its transcript (plan 5.4; see `clipper transcript import`)")
    p.add_argument("--work", default="work", help="work directory for every intermediate and output file")
    p.add_argument("--from", dest="from_stage", choices=["select", "detect", "speakers", "plan", "render", "overlay"],
                   help="re-run this stage and every later one; earlier ones are reused")
    p.add_argument("--scorer", choices=["heuristic", "llm"], help="moment scorer (default: select.scorer)")
    p.add_argument("--asd", choices=["heuristic", "lrasd"], help="active-speaker backend (default: speakers.backend)")
    p.add_argument("--moment", help="override the selection, e.g. 1834.1-1893.4 (snapped to sentences)")
    p.add_argument("--no-debug", action="store_true", help="skip the debug overlay video")
    p.add_argument("--allow-hosted-llm", action="store_true", help="explicit opt-in to a non-local LLM endpoint (plan 2.1)")
    p.add_argument("--offline", action="store_true", help="block all network access outside this machine")
    _common(p)

    p = sub.add_parser("stream", help="stream mode: several vertical clips from a game/IRL stream VOD")
    p.add_argument("--video", required=True, help="the stream VOD")
    p.add_argument("--chat", help="chat log (TwitchDownloader / chat-downloader JSON, or timestamped text)")
    p.add_argument("--transcript", help="transcript (enables the speech signal and burned-in captions)")
    p.add_argument("--work", default="work/stream", help="work directory")
    p.add_argument("--count", type=int, help="how many clips (default stream.moments.count)")
    p.add_argument("--layout", choices=["auto", "fit", "stacked", "gameplay", "follow"])
    p.add_argument("--facecam", help="facecam / avatar region x,y,w,h as fractions of the frame, e.g. 0.78,0.62,0.2,0.36")
    p.add_argument("--from", dest="from_stage", choices=["signals", "moments", "layout", "render"],
                   help="re-run this stage and every later one")
    p.add_argument("--offline", action="store_true", help="block all network access outside this machine")
    _common(p)

    p = sub.add_parser("stream-setup", help="frame with a coordinate grid, to read the facecam/avatar region off")
    p.add_argument("video")
    p.add_argument("--out", default="frame_grid.png")
    p.add_argument("--at", type=float, help="time in seconds (default: middle of the video)")
    _common(p)

    p = sub.add_parser("select", help="Phase 3: pick the moment -> WORK/moment.json")
    p.add_argument("--video", required=True)
    p.add_argument("--transcript", required=True)
    p.add_argument("--work", default="work")
    p.add_argument("--scorer", choices=["heuristic", "llm"])
    p.add_argument("--moment", help="override, e.g. 1834.1-1893.4 (snapped to sentences)")
    p.add_argument("--allow-hosted-llm", action="store_true", help="explicit opt-in to a non-local LLM endpoint (plan 2.1)")
    p.add_argument("--force", action="store_true")
    _common(p)

    p = sub.add_parser("render", help="Phase 3: crop_plan.json + source -> WORK/clip.mp4")
    p.add_argument("work", nargs="?", default="work")
    p.add_argument("--force", action="store_true")
    _common(p)

    p = sub.add_parser("overlay", help="Phase 3: debug overlay -> WORK/debug.mp4")
    p.add_argument("work", nargs="?", default="work")
    p.add_argument("--transcript", help="show the spoken sentence")
    p.add_argument("--force", action="store_true")
    _common(p)

    p = sub.add_parser("transcript", help="convert a transcript to the pipeline's format (plan 5.4)")
    p.add_argument("action", choices=["import"])
    p.add_argument("input", help="Whisper / WhisperX / faster-whisper JSON, .srt or .vtt")
    p.add_argument("output", help="where to write the transcript JSON")
    p.add_argument("--language", help="language code, e.g. en or pl (word lists for the heuristic scorer)")
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
    p.add_argument("phase", choices=["phase1", "phase2", "phase3", "stream"])
    p.add_argument("video")
    p.add_argument("--expect-faces", type=int, help="phase1: number of people visible in the fixture")
    p.add_argument("--expect", help="phase1: fixture expectations JSON (e.g. tests/data/phase1_fixture.json)")
    p.add_argument("--labels", help="phase2: ground-truth speaker labels JSON; stream: planted moments JSON")
    p.add_argument("--negative", help="phase2: a video in which nobody speaks (false-positive report)")
    p.add_argument("--transcript", help="phase3 / stream: the transcript of VIDEO")
    p.add_argument("--chat", help="stream: chat log of VIDEO")
    p.add_argument("--avatar", help="stream: a VOD with a VTuber-style avatar instead of a face")
    p.add_argument("--start", type=float)
    p.add_argument("--end", type=float)
    p.add_argument("--work", help="work directory (default work/check-<phase>)")
    _common(p)
    return parser


def _moment(text: str | None) -> tuple[float, float] | None:
    if not text:
        return None
    a, b = text.split("-", 1)
    return float(a), float(b)


def main(argv: list[str] | None = None) -> int:
    args = build_parser().parse_args(argv)
    logging.basicConfig(level=logging.DEBUG if args.verbose else logging.INFO, format="%(levelname)s %(message)s")
    cfg = load_config(args.config)
    if getattr(args, "allow_hosted_llm", False):
        cfg["select"]["llm"]["allow_hosted"] = True
    if getattr(args, "offline", False):
        from clipper import netguard
        netguard.install()

    if args.command == "run":
        from clipper.pipeline import run_pipeline
        res = run_pipeline(args.video, args.transcript, args.work, cfg, from_stage=args.from_stage, scorer=args.scorer,
                           asd=args.asd, moment=_moment(args.moment), debug=not args.no_debug)
        m = res["moment"]
        print(f"clip: {res['clip']}\nmoment: {m['start']:.2f}-{m['end']:.2f}s ({m['duration']:.1f}s), score {m['score']:.3f}")
        for r in m["reasons"]:
            print(f"  - {r}")
        print("stages: " + ", ".join(f"{e['stage']} {'ran' if e['ran'] else 'reused'}" for e in res["stages"]))
        return 0

    if args.command == "stream":
        from clipper.stream.run import run_stream
        cam = [float(v) for v in args.facecam.split(",")] if args.facecam else None
        if cam and len(cam) != 4:
            raise SystemExit("--facecam needs four numbers: x,y,w,h")
        res = run_stream(args.video, args.work, cfg, chat=args.chat, transcript=args.transcript, count=args.count,
                         layout=args.layout, facecam=cam, from_stage=args.from_stage)
        print(f"layout: {res['layout']} ({res['layout_reason']})")
        for c in res["clips"]:
            print(f"#{c['rank']}  {c['start']:8.1f}-{c['end']:8.1f}s ({c['duration']:.0f}s)  score {c['score']:.2f}  {c['output']}")
            for r in c["reasons"]:
                print(f"      - {r}")
        print("stages: " + ", ".join(f"{e['stage']} {'ran' if e['ran'] else 'reused'}" for e in res["stages"]))
        return 0

    if args.command == "stream-setup":
        from clipper.stream.setup import frame_grid
        print(frame_grid(args.video, args.out, args.at))
        return 0

    if args.command == "select":
        from clipper.select.run import run_select
        print(run_select(args.video, args.transcript, args.work, cfg, scorer=args.scorer, moment=_moment(args.moment),
                         force=args.force)["path"])
        return 0

    if args.command == "render":
        from clipper.render.renderer import run_render
        print(run_render(args.work, force=args.force)["path"])
        return 0

    if args.command == "overlay":
        from clipper.render.overlay import run_overlay
        print(run_overlay(args.work, args.transcript, force=args.force)["path"])
        return 0

    if args.command == "transcript":
        import json
        from clipper.transcript import import_file
        doc = import_file(args.input, args.language)
        Path(args.output).write_text(json.dumps(doc, ensure_ascii=False, indent=1), encoding="utf-8")
        print(f"{args.output}: {len(doc['segments'])} segments, language {doc['language']}")
        return 0

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
        if args.phase == "stream":
            from clipper.checks.stream import run_check as run_check_stream
            if not (args.transcript and args.chat and args.labels):
                raise SystemExit("check stream needs --chat, --transcript and --labels")
            return run_check_stream(args.video, args.chat, args.transcript, args.labels, work, avatar=args.avatar,
                                    config_path=args.config)
        if args.phase == "phase3":
            from clipper.checks.phase3 import run_check as run_check3
            if not args.transcript:
                raise SystemExit("check phase3 needs --transcript")
            return run_check3(args.video, args.transcript, work, cfg, config_path=args.config)
        from clipper.checks.phase2 import run_check as run_check2
        return run_check2(args.video, work, cfg, labels=args.labels, negative=args.negative,
                          start=args.start, end=args.end)
    return 2


if __name__ == "__main__":
    sys.exit(main())
