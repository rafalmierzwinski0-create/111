"""Candidates and the swappable scorer interface.

Candidates are generated here, once, for every scorer: windows of whole
sentences, so every candidate starts at a sentence start and ends at a
sentence end. Sentence-boundary snapping is therefore a hard filter, not a
weighted term: a window that would start or end inside a sentence never
exists, whatever any scorer thinks of its content.
"""

from __future__ import annotations

from abc import ABC, abstractmethod
from dataclasses import dataclass, field

from clipper.transcript import Sentence


@dataclass
class Candidate:
    first: int          # index of the first sentence
    last: int           # index of the last sentence (inclusive)
    start: float
    end: float
    text: str
    score: float = 0.0
    features: dict = field(default_factory=dict)
    reasons: list[str] = field(default_factory=list)

    @property
    def duration(self) -> float:
        return self.end - self.start


def generate(sentences: list[Sentence], min_s: float, max_s: float) -> tuple[list[Candidate], dict]:
    """Every run of consecutive whole sentences lasting between min_s and max_s.

    `rejected` counts sentence windows outside the duration bounds (windows
    that would cut a sentence are never formed, so there is nothing to count).
    """
    out: list[Candidate] = []
    rejected = {"too_short": 0, "too_long": 0, "not_sentence_aligned": 0}
    for i, first in enumerate(sentences):
        for j in range(i, len(sentences)):
            d = sentences[j].end - first.start
            if d > max_s:
                rejected["too_long"] += 1
                break
            if d < min_s:
                rejected["too_short"] += 1
                continue
            out.append(Candidate(i, j, first.start, sentences[j].end, " ".join(s.text for s in sentences[i:j + 1])))
    return out, rejected


def overlap(a: Candidate, b: Candidate) -> float:
    inter = max(0.0, min(a.end, b.end) - max(a.start, b.start))
    return inter / min(a.duration, b.duration)


def non_max_suppression(cands: list[Candidate], keep: int, max_overlap: float) -> list[Candidate]:
    """Best-first, dropping candidates that mostly overlap an already kept one."""
    kept: list[Candidate] = []
    for c in sorted(cands, key=lambda c: (-c.score, c.start, c.end)):
        if all(overlap(c, k) <= max_overlap for k in kept):
            kept.append(c)
            if len(kept) >= keep:
                break
    return kept


class Scorer(ABC):
    name = ""
    version = "1"

    def __init__(self, cfg: dict):
        self.cfg = cfg
        self.notes: list[str] = []

    @abstractmethod
    def params(self) -> dict:
        """Settings that influence scores (recorded in moment.json and the fingerprint)."""

    @abstractmethod
    def rank(self, candidates: list[Candidate], sentences: list[Sentence], context: dict) -> list[Candidate]:
        """Set score/features/reasons; return the candidates best first."""


def get_scorer(name: str, cfg: dict) -> Scorer:
    if name == "heuristic":
        from clipper.select.heuristic import HeuristicScorer
        return HeuristicScorer(cfg)
    if name == "llm":
        from clipper.select.llm import LLMScorer
        return LLMScorer(cfg)
    raise ValueError(f"unknown moment scorer {name!r} (choose: heuristic, llm)")
