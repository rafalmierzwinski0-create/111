"""Light mode: work from a VOD URL without downloading the whole video.

A 4-hour stream is many GB of video, but finding moments only needs the
audio (~100-200 MB) and the chat log (a few MB). Video is then downloaded
only for the chosen clips, a few short sections. Downloads happen on your
machine, from the platform, with free tools:

  yt-dlp (Unlicense)          video/audio, `--download-sections` for clips
  chat-downloader (MIT)       Twitch and YouTube chat replays

Optional extra: pip install -e '.[fetch]'.

`LocalFetcher` does the same from a file on disk (audio extraction and
section cutting with ffmpeg). It is used by the tests and the stream check,
and is handy to try light mode on a VOD you already have.
"""

from __future__ import annotations

import json
import logging
import shutil
import subprocess
import sys
from pathlib import Path

from clipper.video import require_tool

log = logging.getLogger(__name__)


class FetchError(RuntimeError):
    pass


def _ytdlp() -> list[str]:
    exe = shutil.which("yt-dlp")
    if exe:
        return [exe]
    try:
        import yt_dlp  # noqa: F401
    except ImportError as exc:
        raise FetchError("yt-dlp is needed for --url: pip install -e '.[fetch]'") from exc
    return [sys.executable, "-m", "yt_dlp"]


class YtDlpFetcher:
    def __init__(self, url: str, max_height: int = 1080):
        self.url = url
        self.max_height = max_height

    def _run(self, args: list[str]) -> str:
        cmd = _ytdlp() + ["--no-warnings", "--no-playlist"] + args + [self.url]
        log.info("fetch: %s", " ".join(cmd))
        proc = subprocess.run(cmd, capture_output=True, text=True)
        if proc.returncode != 0:
            raise FetchError(f"yt-dlp failed: {proc.stderr.strip()[-800:]}")
        return proc.stdout

    def info(self) -> dict:
        data = json.loads(self._run(["-J"]))
        if not data.get("duration"):
            raise FetchError("the URL has no duration (is the stream still live? wait for the VOD)")
        return {"id": data.get("id"), "title": data.get("title"), "duration": float(data["duration"]),
                "platform": data.get("extractor_key"), "url": self.url}

    def audio(self, out: Path) -> Path:
        out.parent.mkdir(parents=True, exist_ok=True)
        self._run(["-f", "ba/b", "-x", "--audio-format", "m4a", "-o", str(out.with_suffix(".%(ext)s"))])
        final = out.with_suffix(".m4a")
        if not final.is_file():
            raise FetchError(f"yt-dlp did not produce {final}")
        return final

    def chat(self, out: Path) -> Path | None:
        try:
            from chat_downloader import ChatDownloader
        except ImportError:
            log.warning("fetch: chat-downloader not installed (pip install -e '.[fetch]'); continuing without chat")
            return None
        try:
            chat = ChatDownloader().get_chat(self.url, message_types=["text_message"])
            msgs = [{"time_in_seconds": m.get("time_in_seconds"), "message": m.get("message", "")}
                    for m in chat if m.get("time_in_seconds") is not None]
        except Exception as exc:  # noqa: BLE001 — unsupported site, chat disabled, ...
            log.warning("fetch: no chat replay (%s); continuing without chat", exc)
            return None
        if not msgs:
            return None
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_text(json.dumps(msgs, ensure_ascii=False), encoding="utf-8")
        return out

    def section(self, start: float, end: float, out: Path) -> Path:
        out.parent.mkdir(parents=True, exist_ok=True)
        h = self.max_height
        self._run(["-f", f"bv*[height<={h}]+ba/b[height<={h}]", "--download-sections", f"*{start:.2f}-{end:.2f}",
                   "--force-keyframes-at-cuts", "--merge-output-format", "mp4", "-o", str(out)])
        if not out.is_file():
            raise FetchError(f"yt-dlp did not produce {out}")
        return out


class LocalFetcher:
    """Light mode on a local file: the same steps, done with ffmpeg."""

    def __init__(self, path: str, chat_path: str | None = None):
        self.path = str(Path(path).resolve())
        self.chat_path = chat_path

    def info(self) -> dict:
        from clipper.video import probe
        src = probe(self.path)
        return {"id": Path(self.path).stem, "title": Path(self.path).name, "duration": src["duration"],
                "platform": "local", "url": self.path}

    def audio(self, out: Path) -> Path:
        out = out.with_suffix(".m4a")
        out.parent.mkdir(parents=True, exist_ok=True)
        subprocess.run([require_tool("ffmpeg"), "-v", "error", "-y", "-i", self.path, "-vn", "-c:a", "aac", "-b:a", "96k", str(out)],
                       check=True)
        return out

    def chat(self, out: Path) -> Path | None:
        if not self.chat_path:
            return None
        out.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy(self.chat_path, out)
        return out

    def section(self, start: float, end: float, out: Path) -> Path:
        out.parent.mkdir(parents=True, exist_ok=True)
        subprocess.run([require_tool("ffmpeg"), "-v", "error", "-y", "-ss", f"{start:.3f}", "-i", self.path, "-t", f"{end - start:.3f}",
                        "-c:v", "libx264", "-preset", "veryfast", "-crf", "16", "-c:a", "aac", "-b:a", "192k", str(out)], check=True)
        return out


def make_fetcher(url: str, max_height: int = 1080):
    if Path(url).is_file():
        return LocalFetcher(url)
    return YtDlpFetcher(url, max_height)
