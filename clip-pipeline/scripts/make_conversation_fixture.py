#!/usr/bin/env python3
"""Builds the labelled two-person conversation fixture for the Phase 2 checkpoint.

Two real talking-head clips (people actually speaking, with their own audio)
from OpenTalker/video-retalking (Apache-2.0 repository, pinned commit) are
placed side by side in a 1920x1080 "podcast" frame. A schedule decides who
speaks when: the speaker's clip plays with its audio, the listener is held on
a still frame with a slow head sway. The schedule includes short
interjections ("yeah") and a silence, so the ground truth is known exactly:

  fixtures/conversation.mp4
  fixtures/conversation_labels.json

The clips are downloaded for local testing only and are not committed.

Usage: python scripts/make_conversation_fixture.py
"""

from __future__ import annotations

import hashlib
import json
import math
import subprocess
import sys
import urllib.request
from pathlib import Path

import cv2
import numpy as np

ROOT = Path(__file__).resolve().parents[1]
FIXTURES = ROOT / "fixtures"
COMMIT = "d32e8e58248255e2d243eeaf3cba545dbe505ca8"
CLIPS = {
    "left": ("examples/face/1.mp4", "60fb69b90af2edbcec762530bfab8be5be2753bca9f3f897005772b544313f94"),
    "right": ("examples/face/4.mp4", "414be3ad61f09432adfbc4933e0163539e94f74fc445c2b9114f8c64e38a480e"),
}
W, H, FPS, SR = 1920, 1080, 30, 48000
TILE = 760
TILE_POS = {"left": (140, 200), "right": (1020, 200)}

# (start, end, speaker, kind, clip offset in seconds). The speaker's clip plays from the offset.
SCHEDULE = [
    (0.0, 6.0, "left", "turn", 0.0),
    (6.0, 12.0, "right", "turn", 0.0),
    (12.0, 15.0, "left", "turn", 1.0),
    (15.0, 15.6, "right", "interjection", 2.0),
    (15.6, 21.0, "left", "turn", 0.5),
    (21.0, 23.0, None, "silence", 0.0),
    (23.0, 29.0, "right", "turn", 0.4),
    (29.0, 29.5, "left", "interjection", 3.0),
    (29.5, 35.0, "right", "turn", 0.6),
    (35.0, 41.0, "left", "turn", 0.0),
    (41.0, 44.0, "right", "turn", 3.0),
    (44.0, 50.0, "left", "turn", 0.0),
]


def fetch(rel: str, sha: str) -> Path:
    dest = FIXTURES / "src" / rel.replace("/", "_")
    if not dest.is_file():
        dest.parent.mkdir(parents=True, exist_ok=True)
        url = f"https://raw.githubusercontent.com/OpenTalker/video-retalking/{COMMIT}/{rel}"
        with urllib.request.urlopen(url, timeout=60) as resp:
            dest.write_bytes(resp.read())
    digest = hashlib.sha256(dest.read_bytes()).hexdigest()
    if digest != sha:
        sys.exit(f"{dest}: SHA-256 {digest}, expected {sha}")
    return dest


def load_frames(path: Path) -> tuple[list[np.ndarray], np.ndarray]:
    """Frames and their presentation times. Some clips are variable frame rate,
    so frames are picked by timestamp, never by index * nominal fps."""
    cap = cv2.VideoCapture(str(path))
    frames, times = [], []
    while True:
        ok, img = cap.read()
        if not ok:
            break
        times.append(cap.get(cv2.CAP_PROP_POS_MSEC) / 1000.0)
        side = min(img.shape[:2])
        y0, x0 = (img.shape[0] - side) // 2, (img.shape[1] - side) // 2
        frames.append(cv2.resize(img[y0:y0 + side, x0:x0 + side], (TILE, TILE), interpolation=cv2.INTER_AREA))
    cap.release()
    t = np.array(times)
    return frames, t - t[0]


def load_audio(path: Path) -> np.ndarray:
    pcm = subprocess.run(["ffmpeg", "-v", "error", "-i", str(path), "-vn", "-ac", "1", "-ar", str(SR), "-f", "s16le", "pipe:1"],
                         check=True, capture_output=True).stdout
    return np.frombuffer(pcm, dtype=np.int16).astype(np.float32) / 32768.0


def background() -> np.ndarray:
    grad = np.linspace(70, 40, H, dtype=np.float32)[:, None, None]
    return np.broadcast_to(grad * np.array([1.0, 1.05, 1.1], np.float32), (H, W, 3)).astype(np.uint8).copy()


def main() -> None:
    clips = {side: fetch(*spec) for side, spec in CLIPS.items()}
    frames = {side: load_frames(p) for side, p in clips.items()}
    audio = {side: load_audio(p) for side, p in clips.items()}
    duration = SCHEDULE[-1][1]
    n = int(round(duration * FPS))
    bg = background()

    out = FIXTURES / "conversation.mp4"
    wav = FIXTURES / "conversation.wav"
    rng = np.random.default_rng(7)
    track = (rng.standard_normal(int(duration * SR)) * 10 ** (-62 / 20)).astype(np.float32)  # room tone, about -62 dBFS
    for start, end, side, _, offset in SCHEDULE:
        if side is None:
            continue
        a, b = int(start * SR), int(end * SR)
        src = audio[side][int(offset * SR):int(offset * SR) + (b - a)]
        track[a:a + len(src)] += src
    pcm = (np.clip(track, -1, 1) * 32767).astype(np.int16)
    subprocess.run(["ffmpeg", "-v", "error", "-y", "-f", "s16le", "-ar", str(SR), "-ac", "1", "-i", "pipe:0", str(wav)],
                   input=pcm.tobytes(), check=True)

    enc = subprocess.Popen(["ffmpeg", "-v", "error", "-y", "-f", "rawvideo", "-pix_fmt", "bgr24", "-s", f"{W}x{H}",
                            "-r", str(FPS), "-i", "pipe:0", "-i", str(wav), "-c:v", "libx264", "-preset", "medium",
                            "-crf", "20", "-pix_fmt", "yuv420p", "-c:a", "aac", "-b:a", "128k", "-shortest", str(out)],
                           stdin=subprocess.PIPE)
    for k in range(n):
        t = k / FPS
        start, end, speaker, _, offset = next(s for s in SCHEDULE if s[0] <= t < s[1])
        img = bg.copy()
        for side in ("left", "right"):
            clip, clip_t = frames[side]
            if side == speaker:
                tau = offset + t - start
                if tau > clip_t[-1] + 0.1:
                    sys.exit(f"schedule runs past the end of the {side} clip ({tau:.2f}s > {clip_t[-1]:.2f}s)")
                tile = clip[max(0, int(np.searchsorted(clip_t, tau, side="right")) - 1)]
                dx = dy = 0
            else:
                tile = clip[0]
                phase = 0.0 if side == "left" else 1.7
                dx = int(round(6 * math.sin(2 * math.pi * 0.13 * t + phase)))
                dy = int(round(4 * math.sin(2 * math.pi * 0.09 * t + phase + 1.0)))
            x, y = TILE_POS[side]
            img[y + dy:y + dy + TILE, x + dx:x + dx + TILE] = tile
        enc.stdin.write(img.tobytes())
    enc.stdin.close()
    if enc.wait() != 0:
        sys.exit("ffmpeg encoding failed")
    wav.unlink()

    labels = {
        "video": str(out.relative_to(ROOT)),
        "sides": {"left": "x < 0.5", "right": "x >= 0.5"},
        "intervals": [{"start": s, "end": e, "speaker": sp, "kind": kind} for s, e, sp, kind, _ in SCHEDULE],
        "notes": "speaker = who is audibly talking. Interjections are real speech but too short to deserve a cut: "
                 "the desired framing stays on the surrounding speaker. During silence any framing is acceptable.",
    }
    (FIXTURES / "conversation_labels.json").write_text(json.dumps(labels, indent=2), encoding="utf-8")
    print(out)


if __name__ == "__main__":
    main()
