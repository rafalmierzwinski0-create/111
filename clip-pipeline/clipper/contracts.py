"""Reading, writing and validating the data contracts (plan section 5)."""

from __future__ import annotations

import hashlib
import json
import os
from functools import lru_cache
from pathlib import Path
from typing import Any

import jsonschema

from clipper import SCHEMA_VERSION

SCHEMA_DIR = Path(__file__).parent / "schemas"


class ContractError(ValueError):
    pass


@lru_cache(maxsize=None)
def schema(kind: str) -> dict:
    path = SCHEMA_DIR / f"{kind}.schema.json"
    with open(path, encoding="utf-8") as fh:
        return json.load(fh)


def validate(doc: dict, kind: str) -> None:
    if doc.get("kind") != kind:
        raise ContractError(f"expected kind {kind!r}, got {doc.get('kind')!r}")
    validator = jsonschema.Draft202012Validator(schema(kind))
    errors = sorted(validator.iter_errors(doc), key=lambda e: list(e.absolute_path))
    if errors:
        lines = [f"  at /{'/'.join(map(str, e.absolute_path))}: {e.message}" for e in errors[:10]]
        more = f"\n  … and {len(errors) - 10} more" if len(errors) > 10 else ""
        raise ContractError(f"{kind} does not match its schema:\n" + "\n".join(lines) + more)


def write(doc: dict, path: str | Path, kind: str) -> Path:
    """Validate and write atomically, so a crash never leaves a half file that looks valid."""
    doc.setdefault("schema_version", SCHEMA_VERSION)
    validate(doc, kind)
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(path.suffix + ".tmp")
    with open(tmp, "w", encoding="utf-8") as fh:
        json.dump(doc, fh, separators=(",", ":"), ensure_ascii=False)
    os.replace(tmp, path)
    return path


def read(path: str | Path, kind: str) -> dict:
    with open(path, encoding="utf-8") as fh:
        doc = json.load(fh)
    major = str(doc.get("schema_version", "")).split(".")[0]
    if major != SCHEMA_VERSION.split(".")[0]:
        raise ContractError(f"{path}: schema_version {doc.get('schema_version')!r} is not compatible with {SCHEMA_VERSION}")
    validate(doc, kind)
    return doc


def fingerprint(*parts: Any) -> str:
    """SHA-256 over a canonical JSON rendering of the stage's inputs and settings."""
    blob = json.dumps(parts, sort_keys=True, separators=(",", ":"), default=str)
    return hashlib.sha256(blob.encode("utf-8")).hexdigest()


def source_identity(source: dict) -> dict:
    """The parts of a source block that identify the file (used in fingerprints)."""
    return {k: source[k] for k in ("path", "size_bytes", "mtime", "width", "height", "fps", "frame_count")}


def landmark_group(detections: dict, group: str) -> list[int]:
    """Row positions in a face's `landmarks` array for a landmark group, in contour order."""
    spec = detections["landmark_spec"]
    position = {idx: k for k, idx in enumerate(spec["indices"])}
    return [position[idx] for idx in spec["groups"][group]]
