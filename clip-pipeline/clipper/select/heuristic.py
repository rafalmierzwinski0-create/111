"""Local heuristic moment scorer (plan 6.3 deliverable 0). No network, no model.

Each candidate (a run of whole sentences) gets features in [0, 1]:

  hook          the opening grabs attention: a question, a strong claim
                ("most people", "the biggest mistake"), a number, "you",
                a short punchy first sentence
  standalone    the opening does not depend on unseen context: not "And...",
                "So...", "That's why...", "Yeah, exactly..."
  completeness  it ends: terminal punctuation, a concluding phrase in the last
                three sentences / 15 s, and the speaker stops right after
                (change of speaker or a long pause), so the punchline is in
  density       share of content words and numbers per minute
  energy        speech rate and loudness relative to the whole source
  clean         few filler words
  duration      closeness to the target duration

score = weighted mean of the features (weights in select.heuristic.weights).
"""

from __future__ import annotations

import re

import numpy as np

from clipper.select.base import Candidate, Scorer
from clipper.select.lexicon import lexicon
from clipper.transcript import Sentence

WORD = re.compile(r"[\w'’-]+", re.UNICODE)
NUMBER = re.compile(r"\d|\b(?:one|two|three|four|five|six|seven|eight|nine|ten|hundred|thousand|million|billion|percent|"
                    r"jeden|dwa|trzy|cztery|pięć|sześć|siedem|osiem|dziewięć|dziesięć|sto|tysiąc|milion|miliard|procent)\b")


def tokens(text: str) -> list[str]:
    return [t.lower().strip("'’-") for t in WORD.findall(text)]


def contains(text_lower: str, phrase: str) -> bool:
    return re.search(r"(?<!\w)" + re.escape(phrase) + r"(?!\w)", text_lower) is not None


class SentenceStats:
    def __init__(self, s: Sentence, lex: dict):
        low = s.text.lower()
        toks = tokens(s.text)
        self.n = max(1, len(toks))
        self.content = sum(1 for t in toks if t not in lex["stopwords"] and len(t) > 2)
        self.numbers = len(NUMBER.findall(low))
        self.fillers = sum(len(re.findall(r"(?<!\w)" + re.escape(f) + r"(?!\w)", low)) for f in lex["fillers"])
        self.question = s.text.rstrip().endswith("?")
        first = toks[0] if toks else ""
        self.response_first = first in lex["response_first"]
        self.backref = first in lex["backref_first"] or any(low.startswith(p) for p in lex["backref_phrases"])
        self.pronoun_first = first in lex["pronoun_first"]
        hooks = [h for h in lex["hooks"] if contains(low, h)]
        self.hook_words = hooks
        hook = 0.0
        if self.question:
            hook += 0.5
        hook += min(0.6, 0.3 * len(hooks))
        if self.numbers:
            hook += 0.2
        if "you" in toks or "ty" in toks or "ci" in toks or "cię" in toks:
            hook += 0.1
        if self.n <= 14:
            hook += 0.15
        self.hook = min(1.0, hook)
        self.conclusion = any(contains(low, c) for c in lex["conclusions"])
        self.terminal = s.terminal
        self.duration = max(0.1, s.end - s.start)


class HeuristicScorer(Scorer):
    name = "heuristic"
    version = "2"

    def params(self) -> dict:
        return dict(self.cfg["select"]["heuristic"])

    def rank(self, candidates: list[Candidate], sentences: list[Sentence], context: dict) -> list[Candidate]:
        p = self.params()
        sel = self.cfg["select"]
        lex = lexicon(context.get("language"))
        stats = [SentenceStats(s, lex) for s in sentences]
        n = len(sentences)

        # Prefix sums for window features.
        def prefix(values):
            return np.concatenate([[0.0], np.cumsum(values)])
        words = prefix([st.n for st in stats])
        content = prefix([st.content for st in stats])
        numbers = prefix([st.numbers for st in stats])
        fillers = prefix([st.fillers for st in stats])
        speech_s = prefix([st.duration for st in stats])
        loud = context.get("sentence_loudness")  # dB per sentence, or None
        loud_p = prefix(np.nan_to_num(loud, nan=0.0)) if loud is not None else None

        rates = np.array([st.n / st.duration for st in stats])
        median_rate = float(np.median(rates)) if n else 1.0
        if loud is not None:
            lo, hi = np.nanpercentile(loud, 10), np.nanpercentile(loud, 90)
            loud_span = max(hi - lo, 1.0)

        w = p["weights"]
        target, mn, mx = sel["target_s"], sel["min_s"], sel["max_s"]
        for c in candidates:
            i, j = c.first, c.last
            f = {}
            first, second = stats[i], stats[i + 1] if i + 1 <= j else None
            f["hook"] = max(first.hook, 0.6 * second.hook if second else 0.0)

            standalone = 1.0
            if first.backref:
                standalone = 0.15
            elif first.response_first:
                standalone = 0.3
            elif first.pronoun_first:
                standalone = 0.6
            if i > 0 and sentences[i - 1].speaker == sentences[i].speaker and sentences[i].start - sentences[i - 1].end < 0.15:
                standalone *= 0.85  # starts with no breath after the previous sentence
            f["standalone"] = standalone

            last = stats[j]
            comp = 0.4 if last.terminal and not last.question else (0.1 if last.terminal else 0.0)
            # A concluding phrase near the end: in the last three sentences or the last 15 s.
            tail = [k for k in range(i, j + 1) if k >= j - 2 or sentences[k].start >= c.end - 15.0]
            if any(stats[k].conclusion for k in tail):
                comp += 0.3
            # What follows: the clip should end where the speaker stops, not where they pause for breath.
            gap = sentences[j + 1].start - sentences[j].end if j + 1 < n else float("inf")
            if j + 1 >= n or sentences[j + 1].speaker != sentences[j].speaker or gap >= 1.0:
                comp += 0.3
            elif gap >= 0.5:
                comp += 0.15
            # Ending on someone else's one- or two-word reaction ("Wow.", "Right.") is not an ending.
            if j > i and last.n <= 2 and sentences[j].speaker != sentences[j - 1].speaker:
                comp -= 0.3
            f["completeness"] = float(np.clip(comp, 0.0, 1.0))

            nw = words[j + 1] - words[i]
            ratio = (content[j + 1] - content[i]) / max(1.0, nw)
            per_min = (numbers[j + 1] - numbers[i]) / max(c.duration / 60, 1e-6)
            f["density"] = float(np.clip(0.6 * (ratio - 0.3) / 0.35 + 0.4 * min(1.0, per_min / 3.0), 0.0, 1.0))

            rate = nw / max(speech_s[j + 1] - speech_s[i], 1e-6)
            energy = float(np.clip((rate / median_rate - 0.8) / 0.4, 0.0, 1.0))
            if loud is not None:
                mean_loud = (loud_p[j + 1] - loud_p[i]) / (j - i + 1)
                energy = 0.5 * energy + 0.5 * float(np.clip((mean_loud - lo) / loud_span, 0.0, 1.0))
            f["energy"] = energy

            f["clean"] = float(np.clip(1.0 - ((fillers[j + 1] - fillers[i]) / max(1.0, nw)) / 0.08, 0.0, 1.0))
            f["duration"] = float(np.clip(1.0 - abs(c.duration - target) / max(target - mn, mx - target), 0.0, 1.0))

            c.features = {k: round(v, 3) for k, v in f.items()}
            c.score = round(sum(w[k] * f[k] for k in w) / sum(w.values()), 4)
            c.reasons = self._reasons(f, first, sentences[i])
        return sorted(candidates, key=lambda c: (-c.score, c.start))

    @staticmethod
    def _reasons(f: dict, first: SentenceStats, s: Sentence) -> list[str]:
        out = []
        if f["hook"] >= 0.6:
            why = []
            if first.question:
                why.append("opens with a question")
            if first.hook_words:
                why.append("strong claim (" + ", ".join(f'"{h}"' for h in first.hook_words[:3]) + ")")
            if first.numbers:
                why.append("a number up front")
            out.append("hook: " + (", ".join(why) or "short, direct opening"))
        if f["standalone"] >= 0.9:
            out.append("stands alone: the first sentence needs no earlier context")
        elif f["standalone"] <= 0.3:
            out.append(f'weak start: opens with "{s.text.split()[0]}" (refers back to earlier context)')
        if f["completeness"] >= 0.8:
            out.append("complete arc: a concluding line near the end, and the speaker finishes there")
        if f["density"] >= 0.6:
            out.append("high information density")
        if f["energy"] >= 0.7:
            out.append("lively delivery (fast and loud relative to the episode)")
        if f["clean"] < 0.6:
            out.append("many filler words")
        return out
