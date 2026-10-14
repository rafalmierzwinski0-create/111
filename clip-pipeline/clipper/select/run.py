"""Phase 3 deliverable 0 stage: source + transcript -> moment.json (plan 5.5)."""

from __future__ import annotations

import logging
import time
from pathlib import Path

import numpy as np

from clipper import audio, contracts, transcript as tr, video
from clipper.select.base import Candidate, generate, get_scorer, non_max_suppression

log = logging.getLogger(__name__)

STAGE_VERSION = 2
OUTPUT_NAME = "moment.json"
LOUDNESS_STEP_S = 0.1


def sentence_loudness(source_path: str, sentences: list[tr.Sentence]) -> np.ndarray | None:
    power = audio.level_series(source_path, LOUDNESS_STEP_S)
    if power is None:
        return None
    out = np.full(len(sentences), np.nan)
    for k, s in enumerate(sentences):
        a, b = int(s.start / LOUDNESS_STEP_S), max(int(s.start / LOUDNESS_STEP_S) + 1, int(np.ceil(s.end / LOUDNESS_STEP_S)))
        seg = power[a:b]
        if seg.size:
            out[k] = 10 * np.log10(max(float(seg.mean()), 1e-12))
    return out


def clip_range(sentences: list[tr.Sentence], first: int, last: int, duration: float, pad_start: float, pad_end: float
               ) -> tuple[float, float]:
    """Padding goes into the pause around the moment, never into a neighbouring sentence."""
    start, end = sentences[first].start, sentences[last].end
    room_before = start - (sentences[first - 1].end if first > 0 else 0.0)
    room_after = (sentences[last + 1].start if last + 1 < len(sentences) else duration) - end
    return round(max(0.0, start - min(pad_start, 0.8 * room_before)), 3), round(min(duration, end + min(pad_end, 0.8 * room_after)), 3)


def _cand_dict(c: Candidate) -> dict:
    return {"start": round(c.start, 3), "end": round(c.end, 3), "duration": round(c.duration, 3),
            "first_sentence": c.first, "last_sentence": c.last, "score": c.score, "features": c.features,
            "reasons": c.reasons, "text": c.text}


def run_select(video_path: str | Path, transcript_path: str | Path, work: str | Path, cfg: dict,
               scorer: str | None = None, moment: tuple[float, float] | None = None, force: bool = False) -> dict:
    s = cfg["select"]
    source = video.probe(video_path)
    doc_t = tr.load(transcript_path)
    t_sha = tr.sha256(transcript_path)
    scorer_obj = get_scorer(scorer or s["scorer"], cfg)
    fp = contracts.fingerprint("moment", STAGE_VERSION, contracts.source_identity(source), t_sha,
                               {k: v for k, v in s.items() if k not in ("heuristic", "llm")},
                               scorer_obj.name, scorer_obj.params(), list(moment) if moment else None)
    out_path = Path(work) / OUTPUT_NAME
    if out_path.is_file() and not force:
        try:
            if contracts.read(out_path, "moment").get("fingerprint") == fp:
                log.info("select: %s is up to date, skipping (use --force to re-run)", out_path)
                return {"path": out_path, "skipped": True, "seconds": 0.0}
        except (contracts.ContractError, ValueError, OSError) as exc:
            log.warning("select: existing %s is unusable (%s); re-running", out_path, exc)

    t0 = time.monotonic()
    sentences = tr.sentences(doc_t, s["split_on_speaker_change"])
    if not sentences:
        raise ValueError(f"{transcript_path} has no words")
    if sentences[-1].end > source["duration"] + 1.0:
        raise ValueError(f"transcript runs to {sentences[-1].end:.1f}s but the video is {source['duration']:.1f}s long; "
                         "is it the transcript of this video?")
    cands, rejected = generate(sentences, s["min_s"], s["max_s"])
    if not cands:
        raise ValueError(f"no run of whole sentences lasts between {s['min_s']} and {s['max_s']} s")
    context = {"language": doc_t.get("language"),
               "sentence_loudness": sentence_loudness(source["path"], sentences) if s["loudness"] and source["has_audio"] else None}
    ranked = scorer_obj.rank(cands, sentences, context)
    kept = non_max_suppression(ranked, s["keep_candidates"], s["nms_overlap"])

    if moment:  # manual override, still snapped to whole sentences
        a, b = moment
        first = min(range(len(sentences)), key=lambda k: abs(sentences[k].start - a))
        last = min(range(first, len(sentences)), key=lambda k: abs(sentences[k].end - b))
        chosen = next((c for c in ranked if c.first == first and c.last == last), None) or \
            Candidate(first, last, sentences[first].start, sentences[last].end,
                      " ".join(x.text for x in sentences[first:last + 1]), reasons=["manual override (--moment)"])
        chosen.reasons = ["manual override (--moment), snapped to sentence boundaries"] + chosen.reasons
    else:
        chosen = kept[0]
    c_start, c_end = clip_range(sentences, chosen.first, chosen.last, source["duration"], s["pad_start_s"], s["pad_end_s"])

    doc = {
        "schema_version": "1.0",
        "kind": "moment",
        "fingerprint": fp,
        "source": source,
        "inputs": {"transcript": {"path": str(Path(transcript_path).resolve()), "sha256": t_sha}},
        "scorer": {"name": scorer_obj.name, "version": scorer_obj.version, "params": scorer_obj.params(), "notes": scorer_obj.notes},
        "constraints": {"target_s": s["target_s"], "min_s": s["min_s"], "max_s": s["max_s"], "sentence_snapping": "hard"},
        "transcript_stats": {"sentences": len(sentences), "language": doc_t.get("language", "und"),
                             "word_timestamps": not sentences[0].interpolated,
                             "duration": round(sentences[-1].end - sentences[0].start, 3)},
        "selected": _cand_dict(chosen),
        "clip": {"start": c_start, "end": c_end},
        "candidates": [_cand_dict(c) for c in kept],
        "candidates_scored": len(cands),
        "rejected": rejected,
    }
    contracts.write(doc, out_path, "moment")
    elapsed = time.monotonic() - t0
    log.info("select: %s -> %.1f-%.1fs (%.1fs, score %.3f) from %d candidates over %d sentences in %.1fs",
             scorer_obj.name, chosen.start, chosen.end, chosen.duration, chosen.score, len(cands), len(sentences), elapsed)
    return {"path": out_path, "skipped": False, "seconds": elapsed}
