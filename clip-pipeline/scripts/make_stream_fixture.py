#!/usr/bin/env python3
"""Builds a labelled synthetic game-stream VOD for the stream-mode check.

  fixtures/stream_<cam>.mp4           1920x1080 30 fps: procedural shooter + facecam/avatar
  fixtures/stream_<cam>_chat.json     TwitchDownloader-style chat log
  fixtures/stream_<cam>_transcript.json
  fixtures/stream_<cam>_labels.json   planted moments, decoys, cam rectangle

--cam human   a real talking-head clip (video-retalking example) as the facecam
--cam avatar  a drawn VTuber-style avatar (no human face) whose mouth follows the voice

Planted moments: the streamer gets loud (+12 dB) during heavy action, and chat
explodes with hype emotes ~4 s later. Decoys: a loud in-game explosion with
no chat reaction, and a sub train (chat busy, no emotes, streamer calm).

Usage: python scripts/make_stream_fixture.py [--cam human|avatar] [--duration 360]
"""

from __future__ import annotations

import argparse
import json
import math
import subprocess
import sys
from pathlib import Path

import cv2
import numpy as np

sys.path.insert(0, str(Path(__file__).resolve().parent))
import make_conversation_fixture as conv  # noqa: E402

FIXTURES = conv.FIXTURES
W, H, FPS, SR = 1920, 1080, 30, 48000
CAM = (1400, 560, 480, 480)  # x, y, w, h of the facecam overlay
HYPE = [60.0, 140.0, 215.0, 300.0]
DECOY_EXPLOSION = 100.0
DECOY_SUBTRAIN = 250.0
HYPE_EMOTES = ["KEKW", "OMEGALUL", "NO WAY", "CLIP IT", "LMAO", "💀💀💀", "W", "POGGERS", "??", "ICANT"]
CALM = ["hello", "what game is this", "nice", "hi chat", "how long is the stream", "that gun is good", "lol", "first time here",
        "what rank", "chat is slow", "o7", "gg"]
SUBS = ["welcome!", "thanks for the sub", "hype train", "ty for the gift sub", "welcome to the fam"]


def game_frame(t: float, base: np.ndarray, rng: np.random.Generator, action: float) -> np.ndarray:
    yaw = int(220 * math.sin(t * 0.4) + 90 * math.sin(t * 1.7))
    img = np.roll(base, yaw, axis=1).copy()
    for k in range(5):  # enemies
        ex = int((W / 2) + 600 * math.sin(t * (0.5 + 0.13 * k) + k) + yaw) % W
        ey = 520 + 30 * k
        cv2.rectangle(img, (ex - 18, ey - 60), (ex + 18, ey), (40, 40, 200), -1)
    if action > 0:
        for _ in range(int(6 * action)):
            cx, cy = int(rng.integers(500, 1400)), int(rng.integers(350, 700))
            cv2.circle(img, (cx, cy), int(rng.integers(30, 120)), (0, int(rng.integers(120, 255)), 255), -1)
        if int(t * 10) % 2 == 0:
            img = cv2.addWeighted(img, 0.7, np.full_like(img, (0, 0, 255)), 0.3 * action, 0)
    cv2.line(img, (W // 2 - 20, H // 2), (W // 2 + 20, H // 2), (255, 255, 255), 2)
    cv2.line(img, (W // 2, H // 2 - 20), (W // 2, H // 2 + 20), (255, 255, 255), 2)
    cv2.putText(img, "HP 100   AMMO 30/90", (60, H - 50), cv2.FONT_HERSHEY_SIMPLEX, 1.2, (255, 255, 255), 3)
    return img


def base_world() -> np.ndarray:
    img = np.zeros((H, W, 3), np.uint8)
    for y in range(H // 2):
        img[y] = (200 - y // 6, 150 - y // 8, 90)
    img[H // 2:] = (60, 90, 70)
    for x in range(0, W, 160):
        cv2.line(img, (x, H // 2), (W // 2 + (x - W // 2) * 3, H), (80, 110, 90), 2)
    for k in range(12):
        x = k * 170
        cv2.rectangle(img, (x, 300 + (k * 37) % 120), (x + 120, H // 2), (90 + k * 7, 100, 110), -1)
    return img


def avatar_tile(mouth_open: float, t: float) -> np.ndarray:
    tile = np.full((CAM[3], CAM[2], 3), (40, 30, 30), np.uint8)
    cx, cy = CAM[2] // 2, CAM[3] // 2 + int(6 * math.sin(t * 1.3))
    cv2.ellipse(tile, (cx, cy), (150, 200), 8, 0, 360, (60, 120, 170), -1)       # peanut-ish shell
    cv2.ellipse(tile, (cx, cy + 60), (120, 30), 0, 0, 360, (50, 100, 150), -1)
    for dx in (-55, 55):
        cv2.circle(tile, (cx + dx, cy - 50), 26, (255, 255, 255), -1)
        cv2.circle(tile, (cx + dx + int(6 * math.sin(t)), cy - 50), 11, (30, 30, 30), -1)
    cv2.ellipse(tile, (cx, cy + 40), (50, int(6 + 34 * mouth_open)), 0, 0, 360, (40, 20, 120), -1)
    return tile


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--cam", choices=["human", "avatar"], default="human")
    ap.add_argument("--duration", type=float, default=360.0)
    args = ap.parse_args()
    rng = np.random.default_rng(5)
    dur = args.duration
    n = int(dur * FPS)
    name = f"stream_{args.cam}"
    hype_times = [h for h in HYPE if h + 15 < dur]

    # Streamer voice: the real clip's speech, talking ~60 % of the time, much louder at hype moments.
    clip_path = conv.fetch(*conv.CLIPS["left"])
    frames, clip_t = conv.load_frames(clip_path)
    voice = conv.load_audio(clip_path)
    loop = min(clip_t[-1], len(voice) / SR - 0.05)
    talking = np.zeros(int(dur * SR), bool)
    t = 2.0
    while t < dur:
        seg = rng.uniform(2.5, 7.0)
        talking[int(t * SR):int(min(dur, t + seg) * SR)] = True
        t += seg + rng.uniform(0.8, 3.0)
    gain = np.full(int(dur * SR), 0.5, np.float32)
    for h in hype_times:
        a, b = int((h - 1.5) * SR), int((h + 3.0) * SR)
        talking[a:b] = True
        gain[a:b] = 2.0
    idx = (np.arange(int(dur * SR)) / SR % loop * SR).astype(int)
    track = np.where(talking, voice[np.minimum(idx, len(voice) - 1)], 0.0).astype(np.float32) * gain
    # Game audio: noise bed, gunshots, action bursts, and the decoy explosion.
    track += (rng.standard_normal(len(track)) * 0.01).astype(np.float32)
    for s in np.arange(5.0, dur - 1.0, 2.3):
        a = int(s * SR)
        track[a:a + 2000] += (rng.standard_normal(2000) * 0.08 * np.exp(-np.arange(2000) / 400)).astype(np.float32)
    a = int(DECOY_EXPLOSION * SR)
    if DECOY_EXPLOSION + 2 < dur:
        track[a:a + SR] += (rng.standard_normal(SR) * 0.6 * np.exp(-np.arange(SR) / 12000)).astype(np.float32)
    pcm = (np.clip(track, -1, 1) * 32767).astype(np.int16)
    wav = FIXTURES / f"{name}.wav"
    subprocess.run(["ffmpeg", "-v", "error", "-y", "-f", "s16le", "-ar", str(SR), "-ac", "1", "-i", "pipe:0", str(wav)],
                   input=pcm.tobytes(), check=True)
    env = np.abs(track[: (len(track) // (SR // FPS)) * (SR // FPS)]).reshape(-1, SR // FPS).mean(axis=1)

    base = base_world()
    out = FIXTURES / f"{name}.mp4"
    enc = subprocess.Popen(["ffmpeg", "-v", "error", "-y", "-f", "rawvideo", "-pix_fmt", "bgr24", "-s", f"{W}x{H}", "-r", str(FPS),
                            "-i", "pipe:0", "-i", str(wav), "-c:v", "libx264", "-preset", "veryfast", "-crf", "23",
                            "-pix_fmt", "yuv420p", "-c:a", "aac", "-b:a", "128k", "-shortest", str(out)], stdin=subprocess.PIPE)
    x, y, w, h = CAM
    for k in range(n):
        t = k / FPS
        action = max([max(0.0, 1 - abs(t - hh) / 3.0) for hh in hype_times] + [max(0.0, 1 - abs(t - DECOY_EXPLOSION) / 1.0)])
        img = game_frame(t, base, rng, action)
        if args.cam == "human":
            if talking[min(len(talking) - 1, int(t * SR))]:
                tile = frames[max(0, int(np.searchsorted(clip_t, t % loop, side="right")) - 1)]
            else:
                tile = frames[0]
            tile = cv2.resize(tile, (w, h))
        else:
            tile = avatar_tile(min(1.0, env[min(k, len(env) - 1)] * 12), t)
        img[y:y + h, x:x + w] = tile
        cv2.rectangle(img, (x - 4, y - 4), (x + w + 4, y + h + 4), (255, 255, 255), 4)
        enc.stdin.write(img.tobytes())
    enc.stdin.close()
    if enc.wait() != 0:
        sys.exit("ffmpeg failed")
    wav.unlink()

    comments = []
    t = 0.0
    while t < dur:
        t += rng.exponential(1 / 1.5)
        comments.append((t, str(rng.choice(CALM))))
    for hh in hype_times:
        for _ in range(110):
            comments.append((hh + 4.0 + rng.uniform(0, 9), str(rng.choice(HYPE_EMOTES))))
    for _ in range(100):
        comments.append((DECOY_SUBTRAIN + rng.uniform(0, 10), str(rng.choice(SUBS))))
    comments = sorted(c for c in comments if c[0] < dur)
    chat = {"video": {"title": "synthetic stream"},
            "comments": [{"content_offset_seconds": round(t, 2), "commenter": {"display_name": f"viewer{k % 97}"},
                          "message": {"body": m}} for k, (t, m) in enumerate(comments)]}
    (FIXTURES / f"{name}_chat.json").write_text(json.dumps(chat, ensure_ascii=False), encoding="utf-8")

    # Transcript: generic talk while talking, exclamations at hype moments (the words are scripted).
    filler = "okay so we push left here and check the corner then loot the room and go".split()
    segs, k = [], 0
    edges = np.flatnonzero(np.diff(np.r_[0, talking.astype(int), 0]))
    for a, b in zip(edges[::2] / SR, edges[1::2] / SR):
        hype = next((hh for hh in hype_times if a <= hh <= b), None)
        words = []
        t = a + 0.1
        while t < b - 0.3:
            if hype is not None and hype - 1.5 <= t <= hype + 3.0:
                text = ["NO", "WAY!", "NO", "WAY!", "Oh", "my", "god!", "hahaha"][len(words) % 8]
            else:
                text = filler[k % len(filler)]
                k += 1
            words.append({"start": round(t, 2), "end": round(t + 0.32, 2), "word": text})
            t += 0.38
        if words:
            segs.append({"start": words[0]["start"], "end": words[-1]["end"], "speaker": "STREAMER",
                         "text": " ".join(w["word"] for w in words), "words": words})
    (FIXTURES / f"{name}_transcript.json").write_text(json.dumps(
        {"schema_version": "1.0", "kind": "transcript", "language": "en", "segments": segs}), encoding="utf-8")
    labels = {"video": f"fixtures/{name}.mp4", "cam": args.cam, "cam_box_px": list(CAM), "hype": hype_times,
              "decoys": {"explosion": DECOY_EXPLOSION, "sub_train": DECOY_SUBTRAIN}}
    (FIXTURES / f"{name}_labels.json").write_text(json.dumps(labels, indent=1), encoding="utf-8")
    print(out)


if __name__ == "__main__":
    main()
