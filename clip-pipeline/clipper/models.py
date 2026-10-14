"""Pinned, hash-verified model files (plan 2.1: the only allowed network access)."""

from __future__ import annotations

import hashlib
import logging
import os
import shutil
import tempfile
import urllib.request
from dataclasses import dataclass
from pathlib import Path

log = logging.getLogger(__name__)


@dataclass(frozen=True)
class ModelFile:
    name: str
    url: str
    sha256: str
    license: str


FACE_LANDMARKER = ModelFile(
    name="face_landmarker.task",
    url="https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task",
    sha256="64184e229b263107bc2b804c6625db1341ff2bb731874b0bcc2fe6544e0bc9ff",
    license="Apache-2.0",
)

MODELS = {m.name: m for m in (FACE_LANDMARKER,)}


def cache_dir(cfg: dict) -> Path:
    raw = os.environ.get("CLIPPER_MODEL_DIR") or cfg["models"]["cache_dir"]
    return Path(raw).expanduser()


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with open(path, "rb") as fh:
        for chunk in iter(lambda: fh.read(1 << 20), b""):
            h.update(chunk)
    return h.hexdigest()


def ensure(model: ModelFile, cfg: dict) -> Path:
    """Return the verified local path, downloading once if it is missing."""
    path = cache_dir(cfg) / model.name
    if path.is_file():
        digest = sha256_file(path)
        if digest == model.sha256:
            return path
        raise RuntimeError(f"{path} has SHA-256 {digest}, expected {model.sha256}; delete it to re-download")
    path.parent.mkdir(parents=True, exist_ok=True)
    log.info("downloading %s (one-time) from %s", model.name, model.url)
    with tempfile.NamedTemporaryFile(dir=path.parent, delete=False) as tmp:
        try:
            with urllib.request.urlopen(model.url, timeout=60) as resp:
                shutil.copyfileobj(resp, tmp)
        except OSError as exc:
            os.unlink(tmp.name)
            raise RuntimeError(
                f"could not download {model.name}: {exc}. For offline use, place the file at {path} "
                f"(source: {model.url}, sha256 {model.sha256})"
            ) from exc
    digest = sha256_file(Path(tmp.name))
    if digest != model.sha256:
        os.unlink(tmp.name)
        raise RuntimeError(f"downloaded {model.name} has SHA-256 {digest}, expected {model.sha256}")
    os.replace(tmp.name, path)
    return path
