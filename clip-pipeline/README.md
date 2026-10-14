# clipper — vertical clip pipeline

Turns a long horizontal recording plus its transcript into a short vertical
(9:16) clip that follows whoever is speaking. Everything runs locally: no paid
APIs, permissive licences only, face detection by MediaPipe.

The design, the data contracts and the phase plan are in
[IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md). This README covers what is
built so far: **Phase 1, Foundation** (faces, lip landmarks, tracks, shots).
It is completed in Phase 3.

## Setup

Requirements: Python ≥ 3.10, FFmpeg (`ffmpeg` and `ffprobe` on `PATH`). On
Linux, MediaPipe also needs the EGL/GLES runtime libraries
(`sudo apt install libegl1 libgles2` on Debian/Ubuntu).

```sh
cd clip-pipeline
python3 -m venv .venv && . .venv/bin/activate
pip install -e '.[dev]'
```

The first run downloads the MediaPipe Face Landmarker model (3.6 MB, Apache-2.0,
SHA-256 pinned) to `~/.cache/clipper/models`. For offline machines, copy
`face_landmarker.task` into that directory (or into `$CLIPPER_MODEL_DIR`).

## Phase 1 usage

```sh
# Faces, landmarks, tracks and shot boundaries for a time range of a video
clipper detect source.mp4 --start 120 --end 180 --work work/

# Contact sheet (PNG) drawn from the result, for a quick visual check
clipper inspect detections work/detections.json

# Licence audit of the installed environment (fails on AGPL/GPL)
clipper licenses
```

`clipper detect` writes `work/detections.json` (contract 5.1 in the plan): for
every analysed frame, each face's box, 40 lip-contour points plus 9 key points,
six mouth blendshapes, head pose, a track id, and the shot it belongs to.
Re-running with the same inputs and settings is skipped. Pass `--force` to redo it.

Options live in `clipper/config/default.yaml`. Override any of them with
`--config my.yaml`, which only needs the keys it changes.

## Checkpoint verification

```sh
./scripts/fetch_fixture.sh   # CC BY 4.0 two-person test video
clipper check phase1 fixtures/head-pose-face-detection-female-and-male.mp4 \
    --expect tests/data/phase1_fixture.json
```

Prints a PASS/FAIL table (plan 6.1) and writes
`work/check-phase1/phase1_check.json` and a contact sheet.

## Tests

```sh
pytest
```

Fixture: `head-pose-face-detection-female-and-male.mp4` from
[intel-iot-devkit/sample-videos](https://github.com/intel-iot-devkit/sample-videos), CC BY 4.0.
