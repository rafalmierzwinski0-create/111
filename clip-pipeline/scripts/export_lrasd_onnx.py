#!/usr/bin/env python3
"""Exports LR-ASD (MIT, Junhua-Liao/LR-ASD) to ONNX for the `lrasd` backend.

Run once by a maintainer, in an environment with PyTorch, onnx and
onnxruntime installed. Users of clipper only need onnxruntime: the exported
files are committed under clipper/speakers/models/.

  python scripts/export_lrasd_onnx.py [--repo DIR]

Steps: clone LR-ASD at a pinned commit (or use --repo), check the licence is
still MIT and the weights' SHA-256, load the weights into LR-ASD's own model
code, export audio+visual -> per-frame speaking logit with a dynamic number of
frames, and verify onnxruntime reproduces PyTorch on several input lengths.
"""

from __future__ import annotations

import argparse
import hashlib
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

import numpy as np

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "clipper" / "speakers" / "models"
REPO = "https://github.com/Junhua-Liao/LR-ASD"
COMMIT = "1b6dcd2d8fc2895683de6508ec6294ec47d388ca"
WEIGHTS = {
    "pretrain_AVA": "85e6c77fc981595234790d1e128ebb60352d37726b2445e0ef8891e2512fe9e3",
    "finetuning_TalkSet": "6b4ef53694e874e96cf630198dc479c78aebb3993bbf166aee3d926dfe7d9342",
}


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--repo", help="existing LR-ASD checkout at the pinned commit")
    ap.add_argument("--only", choices=sorted(WEIGHTS), help=argparse.SUPPRESS)
    args = ap.parse_args()

    import torch
    import torch.nn as nn
    import onnxruntime as ort

    tmp = None
    if args.repo:
        repo = Path(args.repo)
    else:
        tmp = Path(tempfile.mkdtemp())
        repo = tmp / "LR-ASD"
        subprocess.run(["git", "clone", "-q", REPO, str(repo)], check=True)
    subprocess.run(["git", "-C", str(repo), "checkout", "-q", COMMIT], check=True)

    licence = (repo / "LICENSE").read_text(encoding="utf-8")
    if not licence.startswith("MIT License"):
        sys.exit("LR-ASD's licence is no longer MIT; stop and review before exporting (plan 6.2)")

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    if not args.only:
        # One process per weight file: torch.export keeps state between exports.
        for name in WEIGHTS:
            subprocess.run([sys.executable, __file__, "--repo", str(repo), "--only", name], check=True)
        shutil.copy(repo / "LICENSE", OUT_DIR / "LICENSE-LR-ASD.txt")
        if tmp:
            shutil.rmtree(tmp)
        return

    sys.path.insert(0, str(repo))
    from model.Model import ASD_Model  # noqa: E402  (LR-ASD's own code)

    class Scorer(nn.Module):
        """LR-ASD audio-visual path + the lossAV classifier: per-frame logit of 'speaking'."""

        def __init__(self):
            super().__init__()
            self.model = ASD_Model()
            self.lossAV = nn.Module()
            self.lossAV.FC = nn.Linear(128, 2)

        def forward(self, audio, video):
            a = self.model.forward_audio_frontend(audio)
            v = self.model.forward_visual_frontend(video)
            out = self.model.forward_audio_visual_backend(a, v)
            return self.lossAV.FC(out)[:, 1]

    for name, digest in [(args.only, WEIGHTS[args.only])]:
        wpath = repo / "weight" / f"{name}.model"
        if sha256(wpath) != digest:
            sys.exit(f"{wpath}: unexpected SHA-256")
        net = Scorer()
        state = torch.load(wpath, map_location="cpu", weights_only=True)
        missing, unexpected = net.load_state_dict({k: v for k, v in state.items() if not k.startswith("lossV.")}, strict=False)
        if missing or unexpected:
            sys.exit(f"weight mismatch: missing {missing}, unexpected {unexpected}")
        net.eval()

        frames = 50
        audio = torch.randn(1, frames * 4, 13)
        video = torch.randint(0, 255, (1, frames, 112, 112)).float()
        out_path = OUT_DIR / f"lrasd_{name}.onnx"
        t = torch.export.Dim("frames", min=4, max=25 * 60)
        torch.onnx.export(net, (audio, video), str(out_path), input_names=["audio_mfcc", "video_gray"],
                          output_names=["speaking_logit"], dynamo=True, external_data=False,
                          dynamic_shapes={"audio": {1: 4 * t}, "video": {1: t}})

        sess = ort.InferenceSession(str(out_path), providers=["CPUExecutionProvider"])
        worst = 0.0
        for n in (25, 50, 113, 150):
            a = np.random.randn(1, 4 * n, 13).astype(np.float32) * 10
            v = np.random.randint(0, 255, (1, n, 112, 112)).astype(np.float32)
            with torch.no_grad():
                ref = net(torch.from_numpy(a), torch.from_numpy(v)).numpy()
            got = sess.run(None, {"audio_mfcc": a, "video_gray": v})[0]
            worst = max(worst, float(np.abs(ref - got).max()))
        if worst > 1e-3:
            sys.exit(f"{out_path}: onnxruntime differs from PyTorch by {worst}")
        print(f"{out_path.name}: max |onnx - torch| = {worst:.2e}, sha256 {sha256(out_path)}")


if __name__ == "__main__":
    main()
