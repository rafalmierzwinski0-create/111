#!/usr/bin/env python3
"""Builds the long-form podcast fixture for the Phase 3 end-to-end check.

tests/data/podcast_script.json is a scripted two-person podcast. This script
turns it into:

  fixtures/podcast.mp4                1920x1080, 30 fps, several minutes
  fixtures/podcast_transcript.json    transcript (plan 5.4) with word timings
  fixtures/podcast_transcript.srt     the same as SRT (to exercise the importer)
  fixtures/podcast_labels.json        who speaks when (left/right)

Each line is timed at a natural speaking rate. While a person speaks, their
real talking-head clip plays (looped) with its own audio, so active-speaker
detection sees genuine audio-visual speech. The words of the transcript are
the script, not what the audio says: the fixture tests the pipeline's
mechanics and the moment scorer's reading of the text, not transcription.

Usage: python scripts/make_podcast_fixture.py   (after make_conversation_fixture.py has fetched the clips)
"""

from __future__ import annotations

import json
import math
import re
import subprocess
import sys
from pathlib import Path

import numpy as np

sys.path.insert(0, str(Path(__file__).resolve().parent))
import make_conversation_fixture as conv  # noqa: E402

ROOT = conv.ROOT
FIXTURES = conv.FIXTURES
SCRIPT = ROOT / "tests" / "data" / "podcast_script.json"
WORDS_PER_S = 2.6
SENTENCE_GAP = 0.3
TURN_GAP = 0.5


def split_sentences(text: str) -> list[str]:
    return [s for s in re.split(r"(?<=[.!?])\s+", text.strip()) if s]


def timeline(script: dict) -> tuple[list[dict], list[dict], float]:
    """Transcript segments (one per sentence, with words) and speaking intervals."""
    t = 1.0
    segments, intervals = [], []
    for speaker, text in script["lines"]:
        for k, sent in enumerate(split_sentences(text)):
            words = sent.split()
            dur = max(0.6, len(words) / WORDS_PER_S)
            start, end = t, t + dur
            total = sum(len(w) + 1 for w in words)
            pos, wlist = 0, []
            for w in words:
                a = start + dur * pos / total
                pos += len(w) + 1
                wlist.append({"start": round(a, 3), "end": round(start + dur * (pos - 1) / total, 3), "word": w})
            segments.append({"start": round(start, 3), "end": round(end, 3), "text": sent, "speaker": speaker, "words": wlist})
            intervals.append({"start": round(start, 3), "end": round(end, 3), "speaker": script["speakers"][speaker]})
            t = end + SENTENCE_GAP
        t += TURN_GAP - SENTENCE_GAP
    return segments, intervals, t + 1.0


def srt_time(x: float) -> str:
    h, rem = divmod(x, 3600)
    m, s = divmod(rem, 60)
    return f"{int(h):02d}:{int(m):02d}:{s:06.3f}".replace(".", ",")


def main() -> None:
    script = json.loads(SCRIPT.read_text(encoding="utf-8"))
    segments, intervals, duration = timeline(script)
    clips = {side: conv.fetch(*spec) for side, spec in conv.CLIPS.items()}
    frames = {side: conv.load_frames(p) for side, p in clips.items()}
    audio = {side: conv.load_audio(p) for side, p in clips.items()}
    loop = {side: min(frames[side][1][-1], len(audio[side]) / conv.SR - 0.05) for side in clips}
    FPS, SR = conv.FPS, conv.SR
    n = int(round(duration * FPS))

    # Each speaker has a clock that only runs while they talk, so their clip's
    # picture and sound stay in sync across turns.
    rng = np.random.default_rng(11)
    track = (rng.standard_normal(int(duration * SR) + SR) * 10 ** (-62 / 20)).astype(np.float32)
    clock = {"left": 0.0, "right": 0.0}
    speaking_at: list[tuple[str, float] | None] = [None] * n
    for iv in intervals:
        side = iv["speaker"]
        k0, k1 = int(math.ceil(iv["start"] * FPS)), int(math.ceil(iv["end"] * FPS))
        for k in range(k0, min(k1, n)):
            speaking_at[k] = (side, (clock[side] + k / FPS - iv["start"]) % loop[side])
        a0, a1 = int(iv["start"] * SR), int(iv["end"] * SR)
        idx = ((clock[side] + np.arange(a1 - a0) / SR) % loop[side] * SR).astype(int)
        track[a0:a1] += audio[side][np.minimum(idx, len(audio[side]) - 1)]
        clock[side] += iv["end"] - iv["start"]

    out = FIXTURES / "podcast.mp4"
    wav = FIXTURES / "podcast.wav"
    pcm = (np.clip(track[:int(duration * SR)], -1, 1) * 32767).astype(np.int16)
    subprocess.run(["ffmpeg", "-v", "error", "-y", "-f", "s16le", "-ar", str(SR), "-ac", "1", "-i", "pipe:0", str(wav)],
                   input=pcm.tobytes(), check=True)
    enc = subprocess.Popen(["ffmpeg", "-v", "error", "-y", "-f", "rawvideo", "-pix_fmt", "bgr24", "-s", f"{conv.W}x{conv.H}",
                            "-r", str(FPS), "-i", "pipe:0", "-i", str(wav), "-c:v", "libx264", "-preset", "veryfast",
                            "-crf", "22", "-pix_fmt", "yuv420p", "-c:a", "aac", "-b:a", "128k", "-shortest", str(out)],
                           stdin=subprocess.PIPE)
    bg = conv.background()
    for k in range(n):
        t = k / FPS
        img = bg.copy()
        for side in ("left", "right"):
            clip, clip_t = frames[side]
            now = speaking_at[k]
            if now and now[0] == side:
                tile = clip[max(0, int(np.searchsorted(clip_t, now[1], side="right")) - 1)]
                dx = dy = 0
            else:
                tile = clip[0]
                phase = 0.0 if side == "left" else 1.7
                dx = int(round(6 * math.sin(2 * math.pi * 0.13 * t + phase)))
                dy = int(round(4 * math.sin(2 * math.pi * 0.09 * t + phase + 1.0)))
            x, y = conv.TILE_POS[side]
            img[y + dy:y + dy + conv.TILE, x + dx:x + dx + conv.TILE] = tile
        enc.stdin.write(img.tobytes())
    enc.stdin.close()
    if enc.wait() != 0:
        sys.exit("ffmpeg encoding failed")
    wav.unlink()

    transcript = {"schema_version": "1.0", "kind": "transcript", "language": "en", "segments": segments}
    (FIXTURES / "podcast_transcript.json").write_text(json.dumps(transcript, indent=1), encoding="utf-8")
    srt = "\n".join(f"{k + 1}\n{srt_time(s['start'])} --> {srt_time(s['end'])}\n{s['speaker']}: {s['text']}\n"
                    for k, s in enumerate(segments))
    (FIXTURES / "podcast_transcript.srt").write_text(srt, encoding="utf-8")
    labels = {"video": str(out.relative_to(ROOT)), "sides": {"left": "x < 0.5", "right": "x >= 0.5"},
              "intervals": [{**iv, "kind": "turn"} for iv in intervals],
              "notes": "speaker = whose clip and audio play; script lines are timed at 2.6 words/s"}
    (FIXTURES / "podcast_labels.json").write_text(json.dumps(labels, indent=1), encoding="utf-8")
    print(f"{out} ({duration:.1f}s, {len(segments)} sentences)")


if __name__ == "__main__":
    main()
