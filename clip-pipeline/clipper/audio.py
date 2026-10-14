"""Audio extraction (ffmpeg) and speech-energy gating."""

from __future__ import annotations

import subprocess

import numpy as np

from clipper.video import require_tool

SAMPLE_RATE = 16000


def load_mono(path: str, start: float, end: float, sample_rate: int = SAMPLE_RATE) -> np.ndarray | None:
    """Mono float32 samples in [-1, 1] for [start, end), or None when there is no audio stream."""
    cmd = [require_tool("ffmpeg"), "-v", "error", "-ss", f"{start:.4f}", "-i", path, "-t", f"{end - start:.4f}",
           "-vn", "-ac", "1", "-ar", str(sample_rate), "-f", "s16le", "-acodec", "pcm_s16le", "pipe:1"]
    proc = subprocess.run(cmd, capture_output=True)
    if proc.returncode != 0:
        if b"does not contain any stream" in proc.stderr or b"matches no streams" in proc.stderr:
            return None
        raise RuntimeError(f"ffmpeg audio extraction failed: {proc.stderr.decode(errors='replace').strip()}")
    pcm = np.frombuffer(proc.stdout, dtype=np.int16)
    if pcm.size == 0:
        return None
    return pcm.astype(np.float32) / 32768.0


def rms_db(samples: np.ndarray, times: np.ndarray, start: float, window_s: float = 0.05,
           sample_rate: int = SAMPLE_RATE) -> np.ndarray:
    """RMS level in dBFS of a window centred on each time in `times` (source seconds)."""
    half = max(1, int(window_s * sample_rate / 2))
    csum = np.concatenate([[0.0], np.cumsum(samples.astype(np.float64) ** 2)])
    centres = np.round((times - start) * sample_rate).astype(int)
    lo = np.clip(centres - half, 0, len(samples))
    hi = np.clip(centres + half, 0, len(samples))
    n = np.maximum(hi - lo, 1)
    power = (csum[hi] - csum[lo]) / n
    return 10 * np.log10(np.maximum(power, 1e-12))


def speech_gate(level_db: np.ndarray, silence_db: float, margin_db: float, floor_db: float,
                times: np.ndarray | None = None, hold_s: float = 0.0, noise_percentile: float = 3.0
                ) -> tuple[np.ndarray | None, str]:
    """Per-time speech presence in [0, 1], or None when the track carries no usable audio.

    The noise floor is a low percentile of the level (3rd by default: the
    short pauses between words take more than 3 % of the time even when
    someone talks without a break, so the floor never lands on speech).
    Speech is anything at
    least `margin_db` above it (and above `floor_db`), with a 6 dB soft ramp.
    A track whose loudest moment is below `silence_db` is treated as having no
    audio at all (muted export), and gating is disabled. With `hold_s`, the
    gate stays open across pauses between words shorter than about 2 * hold_s.
    """
    if level_db.size == 0 or np.percentile(level_db, 99) < silence_db:
        return None, "audio track is silent; gating disabled"
    noise = float(np.percentile(level_db, noise_percentile))
    threshold = max(noise + margin_db, floor_db)
    gate = np.clip((level_db - threshold) / 6.0 + 0.5, 0.0, 1.0)
    if hold_s > 0 and times is not None:
        lo = np.searchsorted(times, times - hold_s, side="left")
        hi = np.searchsorted(times, times + hold_s, side="right")
        gate = np.array([gate[a:b].max() for a, b in zip(lo, hi)])
    return gate, f"noise floor {noise:.1f} dBFS, speech threshold {threshold:.1f} dBFS"


def level_series(path: str, step_s: float = 0.1, sample_rate: int = SAMPLE_RATE) -> np.ndarray | None:
    """Mean power per `step_s` block over the whole file, streamed (works for multi-hour sources).

    Returns power (not dB) per block, or None without an audio stream.
    """
    cmd = [require_tool("ffmpeg"), "-v", "error", "-i", path, "-vn", "-ac", "1", "-ar", str(sample_rate),
           "-f", "s16le", "-acodec", "pcm_s16le", "pipe:1"]
    proc = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    block = int(step_s * sample_rate)
    out: list[float] = []
    rest = np.zeros(0, np.float32)
    while True:
        chunk = proc.stdout.read(block * 2 * 600)  # one minute at the default step
        if not chunk:
            break
        x = np.concatenate([rest, np.frombuffer(chunk, dtype=np.int16).astype(np.float32) / 32768.0])
        whole = len(x) // block * block
        out.extend((x[:whole].reshape(-1, block) ** 2).mean(axis=1).tolist())
        rest = x[whole:]
    proc.wait()
    if not out:
        return None
    return np.asarray(out)
