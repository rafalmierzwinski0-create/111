"""Transcript input (plan 5.4): loading, importing other formats, sentences.

Sentences are the unit of moment selection: a clip must start at a sentence
start and end at a sentence end. A sentence ends at terminal punctuation
(. ! ? …) and, optionally, where the speaker changes.
"""

from __future__ import annotations

import hashlib
import json
import re
from dataclasses import dataclass, field
from pathlib import Path

from clipper import contracts

TERMINAL = re.compile(r"[.!?…]+[\"'”’)\]]*$")
# Tokens that end with a period but do not end a sentence.
ABBREVIATIONS = {"mr.", "mrs.", "ms.", "dr.", "prof.", "st.", "vs.", "etc.", "e.g.", "i.e.", "u.s.", "u.k.", "jr.", "sr.",
                 "inc.", "ltd.", "no.", "approx.", "min.", "max.", "fig.", "p.m.", "a.m."}


@dataclass
class Word:
    start: float
    end: float
    text: str
    speaker: str | None = None


@dataclass
class Sentence:
    index: int
    start: float
    end: float
    text: str
    speaker: str | None
    words: list[Word] = field(default_factory=list)
    terminal: bool = True        # ends with terminal punctuation (False: cut by a speaker change)
    interpolated: bool = False   # word times interpolated (transcript had no word timestamps)


def sha256(path: str | Path) -> str:
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def load(path: str | Path) -> dict:
    return contracts.read(path, "transcript")


def words_of(transcript: dict) -> tuple[list[Word], bool]:
    """All words in time order. Segments without word timestamps get times
    interpolated by character position (returns interpolated=True)."""
    out: list[Word] = []
    interpolated = False
    for seg in transcript["segments"]:
        spk = seg.get("speaker")
        if seg.get("words"):
            for w in seg["words"]:
                text = w["word"].strip()
                if text:
                    out.append(Word(float(w["start"]), float(w["end"]), text, w.get("speaker", spk)))
            continue
        tokens = seg["text"].split()
        if not tokens:
            continue
        interpolated = True
        total = sum(len(t) + 1 for t in tokens)
        span = seg["end"] - seg["start"]
        pos = 0
        for tok in tokens:
            a = seg["start"] + span * pos / total
            pos += len(tok) + 1
            out.append(Word(round(a, 3), round(seg["start"] + span * (pos - 1) / total, 3), tok, spk))
    out.sort(key=lambda w: w.start)
    return out, interpolated


def is_sentence_end(word: str) -> bool:
    low = word.lower()
    if low in ABBREVIATIONS or re.fullmatch(r"(?:[a-z]\.){2,}", low):
        return False
    return bool(TERMINAL.search(word))


def sentences(transcript: dict, split_on_speaker_change: bool = True) -> list[Sentence]:
    words, interpolated = words_of(transcript)
    out: list[Sentence] = []
    cur: list[Word] = []

    def flush(terminal: bool) -> None:
        if cur:
            out.append(Sentence(len(out), cur[0].start, cur[-1].end, " ".join(w.text for w in cur), cur[0].speaker,
                                list(cur), terminal, interpolated))
            cur.clear()

    for k, w in enumerate(words):
        if cur and split_on_speaker_change and w.speaker != cur[-1].speaker and w.speaker is not None:
            flush(terminal=False)
        cur.append(w)
        if is_sentence_end(w.text):
            flush(terminal=True)
    flush(terminal=bool(cur) and is_sentence_end(cur[-1].text))
    return out


# --- importers ---------------------------------------------------------------

def _ts(text: str) -> float:
    text = text.strip().replace(",", ".")
    parts = [float(p) for p in text.split(":")]
    while len(parts) < 3:
        parts.insert(0, 0.0)
    h, m, s = parts
    return h * 3600 + m * 60 + s


def _cues(text: str) -> list[dict]:
    """SRT / WebVTT cues -> segments. A leading "SPEAKER: " or "<v Name>" becomes the speaker."""
    segs = []
    blocks = re.split(r"\n\s*\n", text.replace("\r\n", "\n").strip())
    for block in blocks:
        lines = [ln for ln in block.split("\n") if ln.strip()]
        tline = next((k for k, ln in enumerate(lines) if "-->" in ln), None)
        if tline is None:
            continue
        a, b = lines[tline].split("-->")
        body = " ".join(lines[tline + 1:]).strip()
        speaker = None
        m = re.match(r"<v\s+([^>]+)>(.*)", body)
        if m:
            speaker, body = m.group(1).strip(), m.group(2)
        else:
            m = re.match(r"([A-Z][A-Za-z0-9_ ]{0,30}):\s+(.*)", body)
            if m:
                speaker, body = m.group(1).strip(), m.group(2)
        body = re.sub(r"<[^>]+>", "", body).strip()
        if body:
            seg = {"start": round(_ts(a), 3), "end": round(_ts(b.split()[0]), 3), "text": body}
            if speaker:
                seg["speaker"] = speaker
            segs.append(seg)
    return segs


def import_file(path: str | Path, language: str | None = None) -> dict:
    """Whisper / WhisperX / faster-whisper JSON, SRT or WebVTT -> transcript (5.4)."""
    path = Path(path)
    raw = path.read_text(encoding="utf-8-sig")
    if path.suffix.lower() in (".srt", ".vtt"):
        segments = _cues(raw)
        lang = language
    else:
        data = json.loads(raw)
        if data.get("kind") == "transcript":
            contracts.validate(data, "transcript")
            return data
        src_segments = data["segments"] if isinstance(data, dict) else data
        lang = language or (data.get("language") if isinstance(data, dict) else None)
        segments = []
        for s in src_segments:
            seg = {"start": round(float(s["start"]), 3), "end": round(float(s["end"]), 3), "text": s["text"].strip()}
            if s.get("speaker"):
                seg["speaker"] = str(s["speaker"])
            words = []
            for w in s.get("words") or []:
                if w.get("start") is None or w.get("end") is None:
                    continue  # WhisperX leaves numbers unaligned
                item = {"start": round(float(w["start"]), 3), "end": round(float(w["end"]), 3),
                        "word": (w.get("word") or w.get("text") or "").strip()}
                if w.get("speaker"):
                    item["speaker"] = str(w["speaker"])
                if item["word"]:
                    words.append(item)
            if words:
                seg["words"] = words
            if seg["text"]:
                segments.append(seg)
    segments.sort(key=lambda s: s["start"])
    doc = {"schema_version": "1.0", "kind": "transcript", "language": lang or "und", "segments": segments}
    contracts.validate(doc, "transcript")
    return doc
