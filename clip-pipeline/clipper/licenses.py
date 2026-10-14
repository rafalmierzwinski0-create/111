"""Licence audit (plan 2.2): fail on AGPL / GPL / non-commercial anywhere."""

from __future__ import annotations

import importlib.metadata as md
import re
from dataclasses import dataclass
from pathlib import Path

from clipper.models import MODELS

DENY_PACKAGES = {"ultralytics", "ultralytics-thop", "yolov5", "yolov8"}
FORBIDDEN = [r"\bagpl", r"affero", r"(?<!l)gpl\b", r"(?<!lesser )general public license", r"non-?commercial", r"cc-by-nc"]
ALLOWED = [r"\bmit\b", r"mit-0", r"mit-cmu", r"\bbsd", r"0bsd", r"apache", r"\bisc\b", r"\bpsf\b",
           r"python software foundation", r"\bzlib\b", r"cc0", r"unlicense", r"mpl-2\.0", r"mozilla public license",
           r"\bhpnd\b", r"public domain", r"lgpl", r"lesser general public"]
# Phrases that only state compatibility, not the package's own licence.
NEUTRAL = [r"gpl[- ]compatible"]


@dataclass
class Finding:
    name: str
    version: str
    license: str
    verdict: str  # ok | forbidden | unknown


def license_text(dist: md.Distribution) -> str:
    meta = dist.metadata
    classifiers = [c.split("::")[-1].strip() for c in (meta.get_all("Classifier") or []) if c.startswith("License")]
    parts = [meta.get("License-Expression") or ""] + classifiers
    raw = (meta.get("License") or "").strip()
    if raw and len(raw) < 120:
        parts.append(raw)
    elif raw and not any(parts):
        parts.append(raw.splitlines()[0][:120])
    return "; ".join(p for p in parts if p) or "UNKNOWN"


def classify(text: str) -> str:
    low = text.lower()
    for pat in NEUTRAL:
        low = re.sub(pat, "", low)
    if any(re.search(p, low) for p in FORBIDDEN) and not re.search(r"lgpl|lesser general public", low):
        return "forbidden"
    if any(re.search(p, low) for p in ALLOWED):
        return "ok"
    return "unknown"


def audit() -> list[Finding]:
    out = []
    seen = set()
    for dist in md.distributions():
        name = dist.metadata["Name"]
        if not name or name.lower() in seen:
            continue
        seen.add(name.lower())
        text = license_text(dist)
        verdict = "forbidden" if name.lower() in DENY_PACKAGES else classify(text)
        out.append(Finding(name, dist.version, text, verdict))
    for m in MODELS.values():
        out.append(Finding(f"model:{m.name}", m.sha256[:12], m.license, classify(m.license)))
    return sorted(out, key=lambda f: (f.verdict != "forbidden", f.verdict != "unknown", f.name.lower()))


def forbidden_imports(root: Path | None = None) -> list[str]:
    """Source files that import a denied package."""
    root = root or Path(__file__).parent
    hits = []
    pattern = re.compile(r"^\s*(import|from)\s+(" + "|".join(re.escape(p.replace("-", "_")) for p in DENY_PACKAGES) + r")\b", re.M)
    for path in root.rglob("*.py"):
        if pattern.search(path.read_text(encoding="utf-8", errors="replace")):
            hits.append(str(path))
    return hits
