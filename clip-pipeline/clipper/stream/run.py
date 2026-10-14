"""`clipper stream`: several vertical clips from a stream VOD in one command.

Stages, each reused when its output exists with a matching fingerprint:
  signals  whole-VOD hype signals (audio decoded once)       -> stream_signals.json
  moments  the best non-overlapping clips around hype peaks  -> stream_moments.json
  layout   facecam detection and the vertical layout          -> stream_plan.json (+ layout_preview.png)
  render   one MP4 per clip, captions burned in if available  -> clips/clip_NN.mp4 (+ signals.png)
"""

from __future__ import annotations

import json
import logging
import time
from pathlib import Path

import cv2
import numpy as np

from clipper import contracts, transcript as tr, video as videolib
from clipper.stream import captions, layout as lay, moments, render, signals
from clipper.stream.chat import load_chat

log = logging.getLogger(__name__)

STAGES = ["signals", "moments", "layout", "render"]
VERSION = 1


def _sha(path: str | None) -> str | None:
    return tr.sha256(path) if path else None


def _cached(path: Path, kind: str, fp: str, force: bool) -> dict | None:
    if path.is_file() and not force:
        try:
            doc = contracts.read(path, kind)
            if doc.get("fingerprint") == fp:
                return doc
        except (contracts.ContractError, ValueError, OSError):
            pass
    return None


def stage_signals(src: dict, work: Path, cfg: dict, chat: str | None, transcript: str | None, force: bool) -> tuple[dict, bool]:
    p = cfg["stream"]["signals"]
    fp = contracts.fingerprint("stream_signals", VERSION, contracts.source_identity(src), _sha(chat), _sha(transcript), p)
    path = work / "stream_signals.json"
    doc = _cached(path, "stream_signals", fp, force)
    if doc:
        return doc, False
    n = int(np.ceil(src["duration"]))
    a, excess, loud = signals.audio_signal(src["path"], n, p) if src["has_audio"] else (None, None, None)
    c = rate = tokens = None
    if chat:
        c, rate, tokens = signals.chat_signal(load_chat(chat), n, p)
    sp = said = None
    if transcript:
        words, _ = tr.words_of(tr.load(transcript))
        sp, said = signals.speech_signal(words, n)
    combined = signals.combine({"audio": a, "chat": c, "speech": sp}, p["weights"], int(p["smooth_s"]))
    r = lambda x, d=3: None if x is None else [round(float(v), d) for v in x]
    doc = {"schema_version": "1.0", "kind": "stream_signals", "fingerprint": fp, "source": src,
           "inputs": {"chat": chat and str(Path(chat).resolve()), "transcript": transcript and str(Path(transcript).resolve())},
           "params": p, "seconds": n,
           "audio": r(a), "audio_excess": r(excess, 1), "chat": r(c), "chat_rate": r(rate, 2),
           "chat_tokens": [dict(t.most_common(5)) for t in tokens] if tokens else None,
           "speech": r(sp), "speech_said": said, "combined": r(combined),
           "loudness_db": r(loud, 1)}
    contracts.write(doc, path, "stream_signals")
    return doc, True


def stage_moments(sig: dict, work: Path, cfg: dict, transcript: str | None, count: int | None, force: bool) -> tuple[dict, bool]:
    p = dict(cfg["stream"]["moments"])
    if count:
        p["count"] = count
    fp = contracts.fingerprint("stream_moments", VERSION, sig["fingerprint"], p)
    path = work / "stream_moments.json"
    doc = _cached(path, "stream_moments", fp, force)
    if doc:
        return doc, False
    arr = {k: (np.array(v) if isinstance(v, list) and k != "chat_tokens" and k != "speech_said" else v) for k, v in sig.items()}
    if sig.get("chat_tokens") is not None:
        from collections import Counter
        arr["chat_tokens"] = [Counter(t) for t in sig["chat_tokens"]]
    words = tr.words_of(tr.load(transcript))[0] if transcript else None
    clips = moments.pick(arr, p, sig["source"]["duration"], words)
    if not clips:
        raise ValueError("no hype peak above stream.moments.min_hype; lower it or check the signals (signals.png)")
    doc = {"schema_version": "1.0", "kind": "stream_moments", "fingerprint": fp, "source": sig["source"],
           "inputs": {"signals": {"fingerprint": sig["fingerprint"]}}, "params": p, "clips": clips}
    contracts.write(doc, path, "stream_moments")
    return doc, True


def stage_layout(src: dict, mom: dict, work: Path, cfg: dict, layout: str | None, facecam: list[float] | None,
                 force: bool) -> tuple[dict, bool]:
    s = cfg["stream"]
    requested = layout or s["layout"]
    cam_norm = facecam or s["facecam"]
    fp = contracts.fingerprint("stream_plan", VERSION, mom["fingerprint"], requested, cam_norm,
                               {k: v for k, v in s.items() if k not in ("signals", "moments")})
    path = work / "stream_plan.json"
    doc = _cached(path, "stream_plan", fp, force)
    if doc:
        return doc, False
    detection = {"method": "set in config" if cam_norm else "not needed"}
    face_box, example, overlay = None, None, None
    if not cam_norm and requested in ("auto", "stacked"):
        frames, example, edges = lay.sample_faces(src["path"], cfg, s["detect_samples"])
        face_box, info = lay.find_facecam(frames, s["detect_min_presence"])
        if face_box:
            overlay, oinfo = lay.overlay_box(face_box, edges)
            info.update(oinfo)
        detection = {"method": "MediaPipe faces in a fixed position (tiled)", **info, "face_box": face_box, "overlay_box": overlay}
        if requested == "stacked" and face_box is None:
            raise ValueError("layout stacked needs a facecam: none was detected (VTuber avatars often are not). "
                             "Set stream.facecam: [x, y, w, h] (fractions of the frame) or use --layout fit")
    chosen, why = lay.choose_layout(requested, face_box, cam_norm)
    regions = lay.plan_layout(src, s, chosen, face_box, cam_norm, overlay)
    doc = {"schema_version": "1.0", "kind": "stream_plan", "fingerprint": fp, "source": src,
           "inputs": {"moments": {"fingerprint": mom["fingerprint"]}},
           "layout": regions["layout"], "layout_reason": why, "facecam_detection": detection,
           "regions": regions["regions"], "output": s["output"], "captions": s["captions"],
           "clips": [{k: c[k] for k in ("rank", "start", "end", "duration", "score", "reasons")} for c in mom["clips"]]}
    contracts.write(doc, path, "stream_plan")
    if example is None:
        cap = cv2.VideoCapture(src["path"])
        cap.set(cv2.CAP_PROP_POS_MSEC, mom["clips"][0]["peak"] * 1000)
        example = cap.read()[1]
        cap.release()
    if example is not None:
        layout_preview(example, doc, work / "layout_preview.png")
    return doc, True


def layout_preview(img: np.ndarray, plan: dict, out: Path) -> None:
    """The source frame with the regions drawn, next to a mock-up of the vertical frame."""
    W = img.shape[1]
    left = img.copy()
    colors = {"cam": (0, 200, 255), "gameplay": (0, 255, 0), "background": (180, 180, 180), "stream": (255, 255, 0)}
    for r in plan["regions"]:
        x, y, w, h = (int(round(v)) for v in r["src"])
        cv2.rectangle(left, (x, y), (x + w, y + h), colors.get(r["name"], (255, 255, 255)), max(2, W // 400))
        cv2.putText(left, r["name"], (x + 8, y + 40), cv2.FONT_HERSHEY_SIMPLEX, max(1.0, W / 1600), colors.get(r["name"], (255, 255, 255)), 2)
    ow, oh = plan["output"]["width"], plan["output"]["height"]
    canvas = np.zeros((oh, ow, 3), np.uint8)
    for r in plan["regions"]:
        x, y, w, h = (int(round(v)) for v in r["src"])
        dx, dy, dw, dh = (int(v) for v in r["dst"])
        part = cv2.resize(img[y:y + h, x:x + w], (dw, dh), interpolation=cv2.INTER_AREA)
        if r.get("mode") == "contain":
            fw = int(round(w * dh / h))
            bg = (cv2.GaussianBlur(part, (0, 0), 12) * 0.8).astype(np.uint8)
            fg = cv2.resize(img[y:y + h, x:x + w], (min(fw, dw), dh), interpolation=cv2.INTER_AREA)
            x0 = (dw - fg.shape[1]) // 2
            bg[:, x0:x0 + fg.shape[1]] = fg
            part = bg
        if r.get("blur"):
            part = cv2.GaussianBlur(part, (0, 0), r["blur"])
            part = (part * 0.75).astype(np.uint8)
        canvas[dy:dy + dh, dx:dx + dw] = part
    scale = left.shape[0] / oh
    right = cv2.resize(canvas, (int(ow * scale), left.shape[0]))
    cv2.imwrite(str(out), np.hstack([left, right]))


def signals_png(sig: dict, mom: dict, out: Path, width: int = 1800) -> None:
    rows = [(k, sig.get(k)) for k in ("audio", "chat", "speech", "combined") if sig.get(k) is not None]
    rh, pad = 110, 30
    img = np.full((pad + rh * len(rows) + 40, width, 3), 22, np.uint8)
    n = sig["seconds"]
    xs = lambda t: int(60 + (width - 80) * t / max(n, 1))
    for c in mom["clips"]:
        cv2.rectangle(img, (xs(c["start"]), pad - 8), (max(xs(c["end"]), xs(c["start"]) + 2), img.shape[0] - 30), (60, 90, 60), -1)
        cv2.putText(img, f"#{c['rank']}", (xs(c["start"]), pad - 12), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (140, 230, 140), 1)
    colors = {"audio": (255, 170, 60), "chat": (80, 160, 255), "speech": (200, 120, 255), "combined": (255, 255, 255)}
    for i, (name, vals) in enumerate(rows):
        y0 = pad + i * rh
        v = np.array(vals, float)
        cols = width - 80
        edges = (np.arange(cols + 1) * len(v) / cols).astype(int)
        top = np.array([v[edges[x]:max(edges[x + 1], edges[x] + 1)].max() for x in range(cols)])
        for x, val in enumerate(top):
            cv2.line(img, (60 + x, y0 + rh - 10), (60 + x, int(y0 + rh - 10 - val * (rh - 20))), colors[name], 1)
        cv2.putText(img, name, (4, y0 + rh // 2), cv2.FONT_HERSHEY_SIMPLEX, 0.45, colors[name], 1)
    for m in range(0, n, 600 if n > 3600 else 60):
        cv2.putText(img, f"{m // 60}m", (xs(m), img.shape[0] - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.4, (160, 160, 160), 1)
    cv2.imwrite(str(out), img)


def stage_render(src: dict, plan: dict, mom: dict, sig: dict, work: Path, cfg: dict, transcript: str | None,
                 force: bool) -> tuple[dict, bool]:
    fp = contracts.fingerprint("stream_render", VERSION, plan["fingerprint"], _sha(transcript))
    path = work / "stream_render_log.json"
    doc = _cached(path, "stream_render_log", fp, force)
    if doc and all(Path(c["output"]).is_file() for c in doc["clips"]):
        return doc, False
    words = tr.words_of(tr.load(transcript))[0] if transcript else None
    out_clips = []
    for clip in plan["clips"]:
        out = work / "clips" / f"clip_{clip['rank']:02d}.mp4"
        if plan["layout"] == "follow":
            out_clips.append(_render_follow(src, clip, work, cfg, out))
            continue
        ass_path = None
        c = plan["captions"]
        if words and c["enabled"]:
            y_frac = c["y"] if c["y"] is not None else _caption_y(plan)
            ass = captions.build_ass(words, clip["start"], clip["end"], plan["output"]["width"], plan["output"]["height"], c, y_frac)
            if ass:
                ass_path = work / "clips" / f"clip_{clip['rank']:02d}.ass"
                ass_path.parent.mkdir(parents=True, exist_ok=True)
                ass_path.write_text(ass, encoding="utf-8")
        render.render_clip(src, plan, clip, out, ass_path)
        out_clips.append({"rank": clip["rank"], "output": str(out), "start": clip["start"], "end": clip["end"],
                          "captions": str(ass_path) if ass_path else None, "filter": render.filter_graph(plan, ass_path)})
    signals_png(sig, mom, work / "signals.png")
    doc = {"schema_version": "1.0", "kind": "stream_render_log", "fingerprint": fp, "layout": plan["layout"],
           "regions": plan["regions"], "clips": out_clips}
    contracts.write(doc, path, "stream_render_log")
    return doc, True


def _caption_y(plan: dict) -> float:
    oh = plan["output"]["height"]
    if plan["layout"] == "stacked":
        return plan["regions"][0]["dst"][3] / oh  # on the seam between cam and gameplay
    if plan["layout"] == "fit":
        r = plan["regions"][1]["dst"]
        return min(0.88, (r[1] + r[3]) / oh + 0.08)  # just under the stream picture
    return 0.72


def _render_follow(src: dict, clip: dict, work: Path, cfg: dict, out: Path) -> dict:
    from clipper.detect.run import run_detect
    from clipper.plan.run import run_plan
    from clipper.render.renderer import run_render
    from clipper.speakers.run import run_speakers
    sub = work / f"follow_{clip['rank']:02d}"
    run_detect(src["path"], sub, cfg, start=clip["start"], end=clip["end"])
    run_speakers(sub, cfg)
    run_plan(sub, cfg)
    run_render(sub)
    out.parent.mkdir(parents=True, exist_ok=True)
    (sub / "clip.mp4").replace(out)
    return {"rank": clip["rank"], "output": str(out), "start": clip["start"], "end": clip["end"], "captions": None,
            "filter": "podcast pipeline (follow the speaker)"}


def run_stream(video: str, work: str | Path, cfg: dict, chat: str | None = None, transcript: str | None = None,
               count: int | None = None, layout: str | None = None, facecam: list[float] | None = None,
               from_stage: str | None = None) -> dict:
    work = Path(work)
    work.mkdir(parents=True, exist_ok=True)
    first = STAGES.index(from_stage) if from_stage else len(STAGES)
    forced = lambda st: STAGES.index(st) >= first
    src = videolib.probe(video)
    log_rows = []

    def timed(name, fn):
        t0 = time.monotonic()
        doc, ran = fn()
        log_rows.append({"stage": name, "ran": ran, "seconds": round(time.monotonic() - t0, 2)})
        log.info("stream: %-8s %s (%.1fs)", name, "done" if ran else "reused", log_rows[-1]["seconds"])
        return doc

    sig = timed("signals", lambda: stage_signals(src, work, cfg, chat, transcript, forced("signals")))
    mom = timed("moments", lambda: stage_moments(sig, work, cfg, transcript, count, forced("moments")))
    plan = timed("layout", lambda: stage_layout(src, mom, work, cfg, layout, facecam, forced("layout")))
    rlog = timed("render", lambda: stage_render(src, plan, mom, sig, work, cfg, transcript, forced("render")))
    summary = {"video": src["path"], "layout": plan["layout"], "layout_reason": plan["layout_reason"], "stages": log_rows,
               "clips": [{**c, "output": next(r["output"] for r in rlog["clips"] if r["rank"] == c["rank"])} for c in plan["clips"]]}
    (work / "stream_run_log.json").write_text(json.dumps(summary, indent=2, ensure_ascii=False), encoding="utf-8")
    return summary
