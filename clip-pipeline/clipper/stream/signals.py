"""Whole-VOD hype signals, one value per second (stream mode).

Cheap enough for multi-hour VODs: only the audio is decoded (once), and the
chat log / transcript are text.

  audio   how much louder than the last couple of minutes the stream is right
          now (peak of 0.1 s blocks within the second, in dB over a rolling
          median baseline): the streamer shouting, laughing, screaming.
  chat    messages per second against the chat's own rolling baseline
          (robust z-score), with hype emotes counting extra. Shifted back by
          the typical chat reaction delay so the peak lands on the moment.
  speech  exclamations and laughter in the transcript ("no way", "let's go",
          "nie wierzę", "hahaha"), when a transcript is given.

combined = weighted mean of the available signals, lightly smoothed.
"""

from __future__ import annotations

import re
from collections import Counter

import numpy as np

from clipper import audio

STEP = 0.1  # loudness block (s)

DEFAULT_HYPE_TOKENS = ["kekw", "lul", "lulw", "omegalul", "lmao", "lmfao", "lol", "xd", "pog", "pogchamp", "poggers", "pogu",
                       "w", "ww", "www", "l", "clip", "clipit", "clip it", "monkas", "sadge", "icant", "no way", "omg",
                       "holy", "insane", "?", "??", "???", "💀", "😂", "🤣", "wtf", "nooo", "letsgo", "lets go", "gg", "ez"]

EXCLAMATIONS = ["no way", "oh my god", "omg", "let's go", "lets go", "what the", "holy", "are you kidding", "no no no",
                "yes yes", "oh no", "run", "help", "wait what", "what was that", "insane", "dude",
                "nie wierzę", "o matko", "o boże", "co to było", "nie no", "dawaj", "jest", "uciekaj", "ratunku", "jezu"]
LAUGH = re.compile(r"\b(?:ha){2,}h?\b|\b(?:he){3,}\b|\bxd+\b", re.I)


def rolling_median(x: np.ndarray, window: int) -> np.ndarray:
    """Centred rolling median with edge padding (window in samples)."""
    if len(x) == 0:
        return x
    window = max(1, min(window, len(x)))
    half = window // 2
    padded = np.pad(x, (half, window - half - 1), mode="edge")
    view = np.lib.stride_tricks.sliding_window_view(padded, window)
    out = np.empty(len(x))
    for a in range(0, len(x), 4096):  # chunks keep memory flat on long VODs
        out[a:a + 4096] = np.median(view[a:a + 4096], axis=1)
    return out


def moving_mean(x: np.ndarray, window: int) -> np.ndarray:
    if window <= 1 or len(x) == 0:
        return x.astype(float)
    k = np.ones(window) / window
    return np.convolve(np.pad(x.astype(float), (window // 2, window - window // 2 - 1), mode="edge"), k, mode="valid")


def audio_signal(video_path: str, n_seconds: int, p: dict) -> tuple[np.ndarray | None, np.ndarray | None, np.ndarray | None]:
    """(score per second, excess dB per second, loudness dB per 0.1 s) or Nones without audio."""
    power = audio.level_series(video_path, STEP)
    if power is None:
        return None, None, None
    db = 10 * np.log10(np.maximum(power, 1e-10))
    per = int(round(1 / STEP))
    n = min(n_seconds, len(db) // per)
    blocks = db[: n * per].reshape(n, per)
    peak = blocks.max(axis=1)
    level = 10 * np.log10(np.maximum((10 ** (blocks / 10)).mean(axis=1), 1e-10))
    baseline = rolling_median(level, int(p["audio_baseline_s"]))
    excess = peak - baseline
    score = np.clip((excess - p["audio_excess_min_db"]) / (p["audio_excess_full_db"] - p["audio_excess_min_db"]), 0, 1)
    # Silence is not hype even if the baseline is quieter still.
    score[level < p["audio_floor_db"]] = 0.0
    return np.pad(score, (0, n_seconds - n)), np.pad(excess, (0, n_seconds - n)), db


def chat_signal(messages: list[tuple[float, str]], n_seconds: int, p: dict) -> tuple[np.ndarray, np.ndarray, list[Counter]]:
    """(score per second, smoothed weighted rate per second, top tokens per second)."""
    tokens = [t.lower() for t in p["hype_tokens"]]
    single = {t for t in tokens if " " not in t}
    phrases = [t for t in tokens if " " in t]
    rate = np.zeros(n_seconds)
    tops: list[Counter] = [Counter() for _ in range(n_seconds)]
    delay = p["chat_delay_s"]
    for t, text in messages:
        k = int(t - delay)
        if not 0 <= k < n_seconds:
            continue
        low = text.lower()
        words = set(re.findall(r"[\w?']+|[^\w\s]", low))
        hits = [w for w in words if w in single] + [ph for ph in phrases if ph in low]
        rate[k] += p["hype_weight"] if hits else 1.0
        for h in hits:
            tops[k][h] += 1
    smooth = moving_mean(rate, int(p["chat_smooth_s"]))
    base = rolling_median(smooth, int(p["chat_baseline_s"]))
    mad = rolling_median(np.abs(smooth - base), int(p["chat_baseline_s"]))
    z = (smooth - base) / (1.4826 * mad + 0.5)
    score = np.clip(z / p["chat_z_full"], 0, 1)
    return score, smooth, tops


def speech_signal(words: list, n_seconds: int) -> tuple[np.ndarray, list[list[str]]]:
    """Exclamations / laughter per second from transcript words (clipper.transcript.Word)."""
    score = np.zeros(n_seconds)
    said: list[list[str]] = [[] for _ in range(n_seconds)]
    # Re-join words into a running lowercase text with second indices.
    for k, w in enumerate(words):
        window = " ".join(x.text for x in words[k:k + 4]).lower()
        sec = int(w.start)
        if not 0 <= sec < n_seconds:
            continue
        hit = next((e for e in EXCLAMATIONS if window.startswith(e)), None)
        if hit:
            score[sec] += 1.0
            said[sec].append(hit)
        if LAUGH.search(w.text):
            score[sec] += 1.0
            said[sec].append("laughs")
        if w.text.endswith("!"):
            score[sec] += 0.5
    return np.clip(moving_mean(score, 3) * 1.5, 0, 1), said


def combine(signals: dict[str, np.ndarray | None], weights: dict[str, float], smooth_s: int) -> np.ndarray:
    num = None
    den = 0.0
    for name, s in signals.items():
        if s is None or weights.get(name, 0) <= 0:
            continue
        num = s * weights[name] if num is None else num + s * weights[name]
        den += weights[name]
    if num is None:
        raise ValueError("no usable signal: the VOD has no audio and no chat log or transcript was given")
    return moving_mean(num / den, smooth_s)
