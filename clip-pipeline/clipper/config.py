"""Configuration: the built-in default.yaml deep-merged with a user file."""

from __future__ import annotations

import copy
from pathlib import Path
from typing import Any

import yaml

DEFAULT_PATH = Path(__file__).parent / "config" / "default.yaml"


def deep_merge(base: dict, override: dict) -> dict:
    out = copy.deepcopy(base)
    for key, value in (override or {}).items():
        if isinstance(value, dict) and isinstance(out.get(key), dict):
            out[key] = deep_merge(out[key], value)
        else:
            out[key] = copy.deepcopy(value)
    return out


def load_config(path: str | Path | None = None, overrides: dict | None = None) -> dict[str, Any]:
    with open(DEFAULT_PATH, encoding="utf-8") as fh:
        cfg = yaml.safe_load(fh)
    if path:
        with open(path, encoding="utf-8") as fh:
            user = yaml.safe_load(fh) or {}
        unknown = _unknown_keys(cfg, user)
        if unknown:
            raise ValueError(f"unknown config keys in {path}: {', '.join(unknown)}")
        cfg = deep_merge(cfg, user)
    if overrides:
        cfg = deep_merge(cfg, overrides)
    return cfg


def _unknown_keys(base: dict, user: dict, prefix: str = "") -> list[str]:
    out = []
    for key, value in user.items():
        name = f"{prefix}{key}"
        if key not in base:
            out.append(name)
        elif isinstance(value, dict) and isinstance(base[key], dict):
            out.extend(_unknown_keys(base[key], value, name + "."))
    return out
