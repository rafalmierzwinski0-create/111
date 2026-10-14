"""Optional LLM re-ranker (plan 6.3 deliverable 0, second scorer).

It only re-ranks the heuristic's best non-overlapping candidates. It never
invents boundaries, so the sentence-boundary hard filter still holds.

Plan 2.1 forbids paid API calls in the processing chain: the endpoint must be
local (localhost, 127.0.0.1, ::1), e.g. Ollama or a llama.cpp /
OpenAI-compatible server on this machine. Any other host is refused unless
the user explicitly opts in with `--allow-hosted-llm` or
`select.llm.allow_hosted: true`.
"""

from __future__ import annotations

import ipaddress
import json
import logging
import re
import urllib.request
from urllib.parse import urlparse

from clipper.select.base import Candidate, Scorer, non_max_suppression
from clipper.select.heuristic import HeuristicScorer
from clipper.transcript import Sentence

log = logging.getLogger(__name__)

LOCAL_HOSTS = {"localhost", "127.0.0.1", "::1"}


class HostedEndpointRefused(RuntimeError):
    pass


def is_local(url: str) -> bool:
    host = (urlparse(url).hostname or "").lower()
    if host in LOCAL_HOSTS or host.endswith(".localhost"):
        return True
    try:
        return ipaddress.ip_address(host).is_loopback
    except ValueError:
        return False


def check_endpoint(url: str, allow_hosted: bool) -> None:
    if not urlparse(url).scheme in ("http", "https") or not urlparse(url).hostname:
        raise ValueError(f"select.llm.endpoint is not a URL: {url!r}")
    if not is_local(url) and not allow_hosted:
        raise HostedEndpointRefused(
            f"refusing LLM endpoint {url}: it is not on this machine, and plan 2.1 forbids paid/hosted API calls in "
            "the processing chain. Use a local server (e.g. Ollama at http://127.0.0.1:11434), or opt in explicitly "
            "with --allow-hosted-llm / select.llm.allow_hosted: true.")


PROMPT = """You pick the best moment of a long podcast to publish as a short vertical clip.
Rate each candidate from 0 to 10 for: a strong hook in the first seconds, standing alone without earlier context,
a complete thought with a satisfying ending, and how interesting it is to a general audience.
Answer with JSON only: {{"ratings": [{{"id": <id>, "score": <0-10>, "reason": "<one short sentence>"}}, ...]}}

{candidates}"""


class LLMScorer(Scorer):
    name = "llm"
    version = "1"

    def __init__(self, cfg: dict):
        super().__init__(cfg)
        p = self.params()
        check_endpoint(p["endpoint"], bool(p["allow_hosted"]))
        self.base = HeuristicScorer(cfg)

    def params(self) -> dict:
        return dict(self.cfg["select"]["llm"])

    def rank(self, candidates: list[Candidate], sentences: list[Sentence], context: dict) -> list[Candidate]:
        p = self.params()
        ranked = self.base.rank(candidates, sentences, context)
        top = non_max_suppression(ranked, p["top_k"], self.cfg["select"]["nms_overlap"])
        listing = "\n\n".join(f"[id {k}] ({c.duration:.0f}s) {c.text}" for k, c in enumerate(top))
        reply = self._chat(PROMPT.format(candidates=listing))
        ratings = self._parse(reply, len(top))
        weight = float(p["weight"])
        for k, c in enumerate(top):
            score, reason = ratings.get(k, (None, None))
            c.features = {**c.features, "heuristic_score": c.score, "llm_score": score}
            if score is not None:
                c.score = round((1 - weight) * c.score + weight * score / 10.0, 4)
                c.reasons = [f"LLM ({p['model']}): {reason}"] + c.reasons
        self.notes.append(f"LLM {p['model']} at {p['endpoint']} re-ranked the top {len(top)} heuristic candidates "
                          f"({len(ratings)} rated)")
        top_ids = {id(c) for c in top}
        rest = [c for c in ranked if id(c) not in top_ids]
        return sorted(top, key=lambda c: -c.score) + rest

    def _chat(self, prompt: str) -> str:
        p = self.params()
        check_endpoint(p["endpoint"], bool(p["allow_hosted"]))  # again, right before any request
        base = p["endpoint"].rstrip("/")
        if p["api"] == "ollama":
            url, body = f"{base}/api/chat", {"model": p["model"], "stream": False, "format": "json",
                                             "messages": [{"role": "user", "content": prompt}],
                                             "options": {"temperature": 0}}
        else:
            url, body = f"{base}/v1/chat/completions", {"model": p["model"], "temperature": 0,
                                                        "messages": [{"role": "user", "content": prompt}]}
        req = urllib.request.Request(url, data=json.dumps(body).encode(), headers={"Content-Type": "application/json"})
        with urllib.request.urlopen(req, timeout=p["timeout_s"]) as resp:
            data = json.loads(resp.read())
        if p["api"] == "ollama":
            return data["message"]["content"]
        return data["choices"][0]["message"]["content"]

    @staticmethod
    def _parse(reply: str, n: int) -> dict[int, tuple[float, str]]:
        m = re.search(r"\{.*\}", reply, re.S)
        if not m:
            log.warning("LLM reply had no JSON; keeping heuristic order")
            return {}
        try:
            items = json.loads(m.group(0)).get("ratings", [])
        except json.JSONDecodeError:
            log.warning("LLM reply was not valid JSON; keeping heuristic order")
            return {}
        out = {}
        for it in items:
            try:
                k, s = int(it["id"]), float(it["score"])
            except (KeyError, TypeError, ValueError):
                continue
            if 0 <= k < n:
                out[k] = (min(10.0, max(0.0, s)), str(it.get("reason", "")).strip())
        return out
