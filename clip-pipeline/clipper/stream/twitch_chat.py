"""Twitch VOD chat replay through Twitch's public web GraphQL endpoint.

The same request TwitchDownloader makes (persisted query
VideoCommentsByOffsetOrCursor, the web player's public client id). Pages of
comments are fetched by time offset (cursor paging needs an integrity token);
the VOD is split into time chunks fetched in parallel, so a long VOD with a
busy chat takes minutes, not an hour.

Output: TwitchDownloader-style JSON ({"comments": [{"content_offset_seconds",
"message": {"body"}}]}) that clipper.stream.chat reads.
"""

from __future__ import annotations

import json
import logging
import re
import time
import urllib.error
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

log = logging.getLogger(__name__)

GQL = "https://gql.twitch.tv/gql"
CLIENT_ID = "kimne78kx3ncx6brgo4mv6wki5h1ko"  # Twitch web player's public client id
QUERY_HASH = "b70a3591ff0f4e0313d126c6a1502d79a1c02baebb288227c582044aa76adf6a"
VOD_ID = re.compile(r"twitch\.tv/(?:[^/]+/)?videos?/(\d+)")


def vod_id(url: str) -> str | None:
    m = VOD_ID.search(url)
    return m.group(1) if m else None


def _page(video: str, offset: int, attempts: int = 5) -> dict:
    """One page of comments starting at `offset` seconds.

    Paging by cursor is behind an integrity check for anonymous clients, so
    pages are always requested by time offset.
    """
    body = json.dumps([{"operationName": "VideoCommentsByOffsetOrCursor",
                        "variables": {"videoID": video, "contentOffsetSeconds": int(offset)},
                        "extensions": {"persistedQuery": {"version": 1, "sha256Hash": QUERY_HASH}}}]).encode()
    for k in range(attempts):
        req = urllib.request.Request(GQL, data=body, headers={"Client-ID": CLIENT_ID, "Content-Type": "application/json"})
        try:
            with urllib.request.urlopen(req, timeout=30) as resp:
                data = json.loads(resp.read())[0]
            comments = (data.get("data") or {}).get("video", {}).get("comments")
            if comments is not None:
                return comments
            log.debug("twitch chat: unexpected reply %s", str(data)[:200])
        except (urllib.error.URLError, TimeoutError, json.JSONDecodeError) as exc:
            log.debug("twitch chat: %s", exc)
        time.sleep(1.5 * (k + 1))
    raise RuntimeError("Twitch did not return chat comments (the VOD may have chat replay disabled)")


def _chunk(video: str, start: float, end: float, stats: dict) -> dict[str, tuple[float, str]]:
    """Comments in [start, end), keyed by id. Each page covers a few seconds; the next
    request starts at the last second seen. A second with more messages than one page
    holds is counted in stats["capped"] (the excess is not retrievable by offset)."""
    out: dict[str, tuple[float, str]] = {}
    offset = int(start)
    while offset < end:
        edges = _page(video, offset).get("edges") or []
        if not edges:
            break
        last = offset
        for edge in edges:
            node = edge["node"]
            t = float(node["contentOffsetSeconds"])
            last = max(last, int(t))
            if start <= t < end:
                text = "".join(f.get("text", "") for f in (node.get("message") or {}).get("fragments", []))
                out[node["id"]] = (t, text)
        if last <= offset:
            stats["capped"] = stats.get("capped", 0) + 1
            last = offset + 1
        offset = last
    return out


def download(url: str, duration: float, out: Path, workers: int = 6, chunk_s: float = 300.0) -> Path:
    video = vod_id(url)
    if not video:
        raise ValueError(f"not a Twitch VOD URL: {url}")
    bounds = [(a, min(duration + 1, a + chunk_s)) for a in range(0, int(duration) + 1, int(chunk_s))]
    t0 = time.monotonic()
    stats: dict = {}
    with ThreadPoolExecutor(max_workers=workers) as pool:
        parts = list(pool.map(lambda b: _chunk(video, b[0], b[1], stats), bounds))
    merged: dict[str, tuple[float, str]] = {}
    for part in parts:
        merged.update(part)
    msgs = sorted(merged.values())
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps({"video": {"id": video}, "comments": [
        {"content_offset_seconds": t, "message": {"body": m}} for t, m in msgs]}, ensure_ascii=False), encoding="utf-8")
    log.info("twitch chat: %d messages in %.0fs (%d seconds busier than one page)", len(msgs), time.monotonic() - t0,
             stats.get("capped", 0))
    return out
