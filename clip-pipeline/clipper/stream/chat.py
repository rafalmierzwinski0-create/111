"""Chat logs -> list of (seconds from VOD start, message text).

Supported:
  - TwitchDownloader JSON: {"comments": [{"content_offset_seconds", "message": {"body"}}]}
  - chat-downloader JSON (Twitch / YouTube / Kick): [{"time_in_seconds", "message"}]
  - generic JSON list: [{"t" | "time" | "offset", "text" | "message"}]
  - text lines: "[0:12:34] user: message" or "00:12:34 user: message"
"""

from __future__ import annotations

import json
import re
from pathlib import Path

LINE = re.compile(r"^\s*\[?\s*((?:\d+:)?\d{1,2}:\d{2}(?:[.,]\d+)?)\s*\]?\s+([^:\s][^:]{0,40}):\s?(.*)$")


def _seconds(text: str) -> float:
    parts = [float(p.replace(",", ".")) for p in text.split(":")]
    total = 0.0
    for p in parts:
        total = total * 60 + p
    return total


def _user(it: dict) -> str:
    for key in ("commenter", "author", "user"):
        v = it.get(key)
        if isinstance(v, dict):
            v = v.get("name") or v.get("login") or v.get("display_name")
        if v:
            return str(v).lower()
    return ""


def _from_items(items: list, ignore_users: set[str]) -> list[tuple[float, str]]:
    out = []
    for it in items:
        if not isinstance(it, dict):
            continue
        if ignore_users and _user(it) in ignore_users:
            continue
        t = it.get("content_offset_seconds", it.get("time_in_seconds", it.get("t", it.get("time", it.get("offset")))))
        msg = it.get("message", it.get("text", it.get("body", "")))
        if isinstance(msg, dict):
            msg = msg.get("body", "")
        if t is None:
            continue
        try:
            t = float(t) if not isinstance(t, str) or ":" not in t else _seconds(t)
        except ValueError:
            continue
        if t >= 0:
            out.append((t, str(msg)))
    return out


def load_chat(path: str | Path, ignore_users: list[str] | None = None,
              ignore_patterns: list[str] | None = None) -> list[tuple[float, str]]:
    """Messages as (seconds, text), without bots (`ignore_users`) and notices (`ignore_patterns`, regexes)."""
    path = Path(path)
    users = {u.lower() for u in ignore_users or []}
    patterns = [re.compile(p, re.I) for p in ignore_patterns or []]
    raw = path.read_text(encoding="utf-8-sig", errors="replace")
    data = None
    if raw.lstrip()[:1] in ("{", "["):
        try:
            data = json.loads(raw)
        except json.JSONDecodeError:
            data = None  # e.g. a text log whose lines start with "[0:01:05]"
    if data is not None:
        items = data.get("comments", data.get("messages", [])) if isinstance(data, dict) else data
        out = _from_items(items, users)
    else:
        out = []
        for line in raw.splitlines():
            m = LINE.match(line)
            if m and m.group(2).strip().lower() not in users:
                out.append((_seconds(m.group(1)), m.group(3)))
    if patterns:
        out = [(t, m) for t, m in out if not any(p.search(m) for p in patterns)]
    if not out:
        raise ValueError(f"{path}: no chat messages recognised (see clipper/stream/chat.py for supported formats)")
    return sorted(out)
