"""Download a time section of an HLS VOD (e.g. Twitch) by fetching only the segments it needs.

A VOD playlist is a list of ~10 s segments. For a 30 s clip out of a 7-hour
stream, only 4-5 segments are fetched (in parallel, with Python's HTTP client,
which honours HTTPS_PROXY), joined with their init segment (fMP4) or as-is
(MPEG-TS), and cut to the exact range with a local ffmpeg re-encode.
"""

from __future__ import annotations

import re
import subprocess
import tempfile
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
from urllib.parse import urljoin

from clipper.video import require_tool

UA = {"User-Agent": "Mozilla/5.0 (clipper)"}


def _get(url: str, attempts: int = 4) -> bytes:
    last = None
    for _ in range(attempts):
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=60) as resp:
                return resp.read()
        except OSError as exc:
            last = exc
    raise RuntimeError(f"could not download {url}: {last}")


def parse_playlist(text: str, url: str) -> list[tuple[float, float, str, str | None]]:
    """(start, end, segment url, init-segment url or None) for every media segment."""
    segs = []
    t, dur, init = 0.0, None, None
    for line in text.splitlines():
        line = line.strip()
        if line.startswith("#EXT-X-MAP:"):
            m = re.search(r'URI="([^"]+)"', line)
            init = urljoin(url, m.group(1)) if m else None
        elif line.startswith("#EXTINF:"):
            dur = float(line[8:].split(",")[0])
        elif line and not line.startswith("#") and dur is not None:
            segs.append((t, t + dur, urljoin(url, line), init))
            t += dur
            dur = None
    return segs


def download_section(playlist_url: str, start: float, end: float, out: Path, workers: int = 8) -> Path:
    segs = parse_playlist(_get(playlist_url).decode("utf-8", "replace"), playlist_url)
    sel = [s for s in segs if s[1] > start and s[0] < end]
    if not sel:
        raise RuntimeError(f"no HLS segments cover {start:.1f}-{end:.1f}s (VOD is {segs[-1][1] if segs else 0:.0f}s)")
    urls = sorted({s[3] for s in sel if s[3]}) + [s[2] for s in sel]
    with ThreadPoolExecutor(max_workers=workers) as pool:
        data = dict(zip(urls, pool.map(_get, urls)))
    out.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(dir=out.parent) as tmp:
        # One file per run of segments sharing an init segment; MPEG-TS needs none.
        parts, cur, cur_init = [], None, object()
        for k, (_, _, url, init) in enumerate(sel):
            if init != cur_init:
                cur = Path(tmp) / f"part{len(parts)}{'.mp4' if init else '.ts'}"
                parts.append(cur)
                with open(cur, "wb") as fh:
                    if init:
                        fh.write(data[init])
                cur_init = init
            with open(cur, "ab") as fh:
                fh.write(data[url])
        if len(parts) == 1:
            src = ["-i", str(parts[0])]
        else:
            lst = Path(tmp) / "list.txt"
            lst.write_text("".join(f"file '{p}'\n" for p in parts))
            src = ["-f", "concat", "-safe", "0", "-i", str(lst)]
        cmd = [require_tool("ffmpeg"), "-v", "error", "-y", "-ss", f"{start - sel[0][0]:.3f}"] + src + \
              ["-t", f"{end - start:.3f}", "-c:v", "libx264", "-preset", "veryfast", "-crf", "16", "-c:a", "aac", "-b:a", "192k",
               "-movflags", "+faststart", str(out)]
        subprocess.run(cmd, check=True)
    return out
