# clipper — vertical clip pipeline

Turns a long horizontal recording plus its transcript into a short vertical
(9:16) clip that follows whoever is speaking. Everything runs locally: no paid
APIs, permissive licences only, face detection by MediaPipe.

The design, the data contracts and the phase plan are in
[IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md). This README covers what is
built so far: **Phase 1, Foundation** (faces, lip landmarks, tracks, shots)
and **Phase 2, Intelligence** (who speaks when, and the smoothed 9:16 crop
path). It is completed in Phase 3.

## Setup

Requirements: Python ≥ 3.10, FFmpeg (`ffmpeg` and `ffprobe` on `PATH`). On
Linux, MediaPipe also needs the EGL/GLES runtime libraries
(`sudo apt install libegl1 libgles2` on Debian/Ubuntu).

```sh
cd clip-pipeline
python3 -m venv .venv && . .venv/bin/activate
pip install -e '.[dev]'
pip install -e '.[lrasd]'   # optional: the LR-ASD active-speaker backend (onnxruntime, no PyTorch)
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

## Phase 2 usage

```sh
clipper speakers work/              # who speaks when -> work/speakers.json (heuristic backend)
clipper speakers work/ --asd lrasd  # same with the LR-ASD audio-visual model
clipper plan work/                  # smoothed 9:16 crop path -> work/crop_plan.json
clipper inspect plan work/ --video  # crop strip PNG + side-by-side preview video
```

- `speakers.json`: a score per face per frame, the raw per-frame decision,
  and the framing segments. A segment is one speaker, `hold` (nobody clearly
  speaking, keep the framing) or `wide`. Segments only change on speaker
  changes and cuts, and are at least 2 s long. Short interjections do not
  move the frame.
- `crop_plan.json`: one crop rectangle per output frame (source pixels, 9:16),
  already smoothed: the frame glides between speakers (ease-in-out, speed
  limited) and only jumps at the source's own cuts. Together with the source
  video this is everything the Phase 3 renderer needs.

The heuristic backend reads lip motion (and gates it with the audio's speech
energy when there is audio). The LR-ASD backend is an audio-visual network
(MIT licence) run through onnxruntime. It is more precise at turn boundaries
and slower.

## Checkpoint verification

```sh
./scripts/fetch_fixture.sh   # CC BY 4.0 two-person test video
clipper check phase1 fixtures/head-pose-face-detection-female-and-male.mp4 \
    --expect tests/data/phase1_fixture.json
```

Prints a PASS/FAIL table (plan 6.1) and writes
`work/check-phase1/phase1_check.json` and a contact sheet.

```sh
python scripts/make_conversation_fixture.py   # labelled two-person conversation
clipper check phase2 fixtures/conversation.mp4 --labels fixtures/conversation_labels.json \
    --negative fixtures/head-pose-face-detection-female-and-male.mp4
```

Prints the plan 6.2 table and writes `work/check-phase2/phase2_check.json`,
a crop strip and a preview video.

## Tests

```sh
pytest
```

Fixtures: `head-pose-face-detection-female-and-male.mp4` from
[intel-iot-devkit/sample-videos](https://github.com/intel-iot-devkit/sample-videos), CC BY 4.0;
the conversation is assembled from
[OpenTalker/video-retalking](https://github.com/OpenTalker/video-retalking) example clips (local testing only).

LR-ASD: Junhua Liao et al., *LR-ASD: Lightweight and Robust Network for Active
Speaker Detection* (IJCV 2025), MIT licence; see
`clipper/speakers/models/LICENSE-LR-ASD.txt`.
