# Implementation plan — vertical clip pipeline

Turns a long horizontal recording (podcast, interview, panel; typically 30–120
minutes) plus its transcript into a short vertical clip (default 60 s, 9:16)
that follows whoever is speaking.

This plan was reconstructed from the three build prompts the project is driven
by. Section numbers are referenced from those prompts, so do not renumber
sections 2, 5 and 6.

---

## 1. Three-phase structure

The pipeline is built in three phases. Each phase ends with a checkpoint
verification, and the next phase starts only after the checkpoint is reported
and confirmed.

| Phase | Name | What it decides | Main output |
| --- | --- | --- | --- |
| 1 | Foundation | Where the faces are, frame by frame, and what their lips are doing | `detections.json` (5.1) |
| 2 | Intelligence | Who is speaking when, and where the 9:16 window should be | `speakers.json` (5.2), `crop_plan.json` (5.3) |
| 3 | Output | Which moment of the long source becomes the clip, and the rendered video | `moment.json` (5.5), `clip.mp4`, `debug.mp4` |

Phases are built in the order 1 → 2 → 3, but at run time the pipeline starts
with Phase 3's moment selection (deliverable 0) so that detection and
active-speaker analysis only run on the selected ~60 s, never on the full
recording:

```
source.mp4 ─┐
            ├─► [select]   moment.json        (Phase 3, deliverable 0)
transcript ─┘        │
                     ▼
            [detect]   detections.json        (Phase 1)  — only the moment's range
                     ▼
            [speakers] speakers.json          (Phase 2)
                     ▼
            [plan]     crop_plan.json         (Phase 2)
                     ▼
            [render]   clip.mp4 (+ debug.mp4) (Phase 3)  — needs only crop_plan.json + source.mp4
```

All stages are also runnable on their own (Phase 1 and Phase 2 accept any
video plus an optional `--start/--end` range), which is how Phases 1 and 2 are
verified before Phase 3 exists.

---

## 2. Hard constraints

These are not preferences. A change that violates one of them is wrong even
if it produces a better-looking clip.

### 2.1 Local processing only — no paid API calls

No paid API, hosted inference endpoint or cloud service may be called anywhere
in the processing chain: not for transcription, detection, active-speaker
detection, moment selection or rendering.

- The only network access the pipeline may perform is the one-time download of
  model files (MediaPipe Face Landmarker, optional LR-ASD weights). Every
  download is pinned to a version and verified against a SHA-256. Models can
  be pre-seeded into the cache for fully offline use.
- An optional LLM backend for moment selection may only talk to a local
  endpoint (`localhost` / `127.0.0.1` / `::1`, e.g. Ollama or llama.cpp
  server). Any other host is refused unless the user explicitly opts in
  (`--allow-hosted-llm` on the command line or `select.llm.allow_hosted: true`
  in the config). The default scorer is the local heuristic.

### 2.2 Licences — permissive only, no AGPL

- Allowed: MIT, BSD, Apache-2.0, ISC, PSF, Zlib, CC0, Unlicense, MIT-CMU,
  and MPL-2.0 (file-level weak copyleft, used unmodified).
- Forbidden anywhere in the project (dependencies, optional extras, model
  weights, copied code): AGPL-3.0, GPL (any version) and any non-commercial
  licence.
- Specifically: **no Ultralytics / YOLO**, even where a well-known reference
  implementation uses it.
- FFmpeg is invoked as an external program (`ffmpeg`/`ffprobe` on `PATH`),
  never linked or bundled.
- `clipper licenses` audits the installed environment and fails on any
  forbidden licence. It runs as part of every phase checkpoint.

### 2.3 Face detection uses MediaPipe

Face detection and landmarks come from MediaPipe Face Landmarker (Apache-2.0
code and model). No other face detector is used.

### 2.4 Landmarks are captured in Phase 1

Phase 1 stores face landmarks, not only bounding boxes. At minimum the full
outer and inner lip contours (40 points) for every face in every analysed
frame. Phase 2's active-speaker detection is built on lip motion and cannot be
built without them.

### 2.5 Stages communicate only through the contracts in section 5

Every stage reads and writes the JSON files defined in section 5, and nothing
else. A stage never imports another stage's internals to get data. Every file
carries `schema_version` and `kind` and is validated against its JSON Schema
(`clipper/schemas/*.schema.json`) when written and when read.

### 2.6 Re-runnable stages

Every stage writes its output into the work directory together with a
fingerprint of its inputs and settings. When a valid output with a matching
fingerprint exists, the stage is skipped (unless `--force`). Re-running the
render must not re-run detection or selection.

### 2.7 Runtime

- Python ≥ 3.10, CPU only must work (GPU optional where a backend supports it).
- Deterministic: the same inputs and config produce the same outputs.
- Output: MP4, H.264 + AAC, 1080×1920 (9:16), source frame rate (capped at 60).

---

## 3. Glossary

- **Source time**: seconds from the start of the source video. Every timestamp
  in every contract is source time, never clip-relative time.
- **Analysed frame**: a source frame that Phase 1 ran detection on (every
  frame, or every n-th frame when the source is above `detect.max_fps`).
- **Track**: one face followed across consecutive analysed frames within one
  shot. Tracks never continue across a shot boundary.
- **Shot boundary**: a hard cut in the source (camera switch in a multi-camera
  edit), detected in Phase 1.
- **Segment**: a span of time with one framing decision (one active speaker,
  or a fallback framing), produced in Phase 2.

---

## 4. Repository layout

```
clip-pipeline/
  IMPLEMENTATION_PLAN.md   this file
  README.md                setup + usage (completed in Phase 3)
  pyproject.toml
  clipper/                 the package; CLI entry point `clipper`
    config/default.yaml    every config option with its default
    detect/                Phase 1
    speakers/              Phase 2 (active-speaker backends + segmentation)
    plan/                  Phase 2 (crop path)
    select/                Phase 3 (moment selection backends)
    render/                Phase 3 (renderer + debug overlay)
    checks/                checkpoint verifications, one per phase
    schemas/               JSON Schemas for every contract in section 5
  scripts/                 fixture download etc.
  tests/                   unit tests (pytest)
  fixtures/                downloaded test media (git-ignored)
  work/                    default work directory (git-ignored)
```

---

## 5. Data contracts

Common rules for every file:

- UTF-8 JSON, top-level object with `schema_version` (semver string, currently
  `"1.0"`) and `kind`.
- Times in seconds (source time), floats rounded to 4 decimals.
- Normalised coordinates are in `[0, 1]` relative to the source frame width
  and height (x to the right, y down). Values may slightly exceed the range
  when a face is partly out of frame. Pixel coordinates are in source pixels.
- `source` block, identical in every file that refers to the source video:

```json
"source": {
  "path": "/abs/path/source.mp4",
  "size_bytes": 123456789,
  "mtime": 1760000000.0,
  "width": 1920, "height": 1080,
  "fps": 29.97, "duration": 5400.0, "frame_count": 161838,
  "has_audio": true
}
```

- `fingerprint`: hex SHA-256 over the stage's inputs (input file
  fingerprints, the source block, the range and the stage's config). Used for
  the re-run check in 2.6.

### 5.1 `detections.json` — Phase 1 output

```json
{
  "schema_version": "1.0",
  "kind": "detections",
  "fingerprint": "…",
  "source": { … },
  "range": { "start": 120.0, "end": 180.0 },
  "analysis": {
    "fps": 29.97,
    "frame_step": 1,
    "detector": "mediapipe.face_landmarker",
    "mediapipe_version": "1.1.0",
    "model": { "name": "face_landmarker.task", "sha256": "…" },
    "num_faces": 4,
    "min_detection_confidence": 0.5,
    "min_presence_confidence": 0.5,
    "min_tracking_confidence": 0.5,
    "analysis_max_width": 1280
  },
  "landmark_spec": {
    "topology": "mediapipe_face_mesh_478",
    "coords": "normalized_xyz",
    "indices": [61, 185, …],
    "groups": {
      "lips_outer": [61, 185, 40, 39, 37, 0, 267, 269, 270, 409, 291, 375, 321, 405, 314, 17, 84, 181, 91, 146],
      "lips_inner": [78, 191, 80, 81, 82, 13, 312, 311, 310, 415, 308, 324, 318, 402, 317, 14, 87, 178, 88, 95],
      "key": [1, 10, 152, 33, 133, 362, 263, 234, 454]
    },
    "blendshapes": ["jawOpen", "mouthClose", "mouthFunnel", "mouthPucker", "mouthSmileLeft", "mouthSmileRight"]
  },
  "shots": [
    { "index": 0, "start": 120.0, "end": 143.52 },
    { "index": 1, "start": 143.52, "end": 180.0 }
  ],
  "frames": [
    {
      "i": 3597,
      "t": 120.0201,
      "shot": 0,
      "faces": [
        {
          "track_id": 0,
          "bbox": [0.1123, 0.2011, 0.0950, 0.2304],
          "landmarks": [[0.1501, 0.3812, -0.0123], …],
          "blendshapes": { "jawOpen": 0.031, "mouthClose": 0.004, … },
          "pose": { "yaw": -12.3, "pitch": 4.1, "roll": 1.0 }
        }
      ]
    }
  ],
  "tracks": [
    {
      "track_id": 0, "shot": 0,
      "start": 120.0201, "end": 143.4870, "num_frames": 704,
      "coverage": 0.99,
      "mean_bbox": [0.1100, 0.2000, 0.0960, 0.2310],
      "spurious": false
    }
  ]
}
```

Field rules:

- `frames` has one entry per analysed frame in the range, in time order,
  including frames with no faces (`"faces": []`). `i` is the source frame
  index, `t` the frame's presentation time in source seconds.
- `landmarks[k]` is the landmark whose MediaPipe index is
  `landmark_spec.indices[k]`. `groups` list MediaPipe indices in contour order
  (lip contours start at the left mouth corner and go clockwise in image
  space: upper lip, then lower lip). Consumers map a group to rows with
  `indices.index(id)`; `clipper.contracts.landmark_group()` does this.
- `indices` always contains every index in every group. With
  `detect.landmarks: full` it is all 478 indices.
- `bbox` is `[x, y, w, h]`, normalised, computed from all 478 landmarks.
- `pose` is head rotation in degrees (yaw: positive = the person turns to
  their left / image right; pitch: positive = looking up; roll: positive =
  head tilted clockwise in the image), from MediaPipe's facial transformation
  matrix. Omitted when unavailable.
- `track_id` is unique within the file. A track belongs to exactly one shot.
  `spurious: true` marks tracks shorter than `detect.min_track_s`. Their faces
  stay in `frames` but Phase 2 ignores them.
- `coverage` = analysed frames where the track has a face / analysed frames
  between its start and end.

### 5.2 `speakers.json` — Phase 2 output (active-speaker timeline)

```json
{
  "schema_version": "1.0",
  "kind": "speakers",
  "fingerprint": "…",
  "source": { … },
  "range": { "start": 120.0, "end": 180.0 },
  "inputs": { "detections": { "path": "detections.json", "fingerprint": "…" } },
  "backend": { "name": "heuristic", "version": "1", "params": { … } },
  "frames": [
    { "i": 3597, "t": 120.0201, "scores": { "0": 0.82, "1": 0.07 }, "raw_active": 0 }
  ],
  "segments": [
    {
      "index": 0, "start": 120.0, "end": 131.2,
      "speaker": 0,
      "kind": "speaker",
      "confidence": 0.78,
      "reason": "track 0 highest mean score (0.71 vs 0.12)"
    },
    {
      "index": 1, "start": 131.2, "end": 133.0,
      "speaker": null, "kind": "hold", "confidence": 0.0,
      "reason": "no face above threshold; holding previous framing"
    }
  ],
  "tracks": [ { "track_id": 0, "speaking_ratio": 0.61, "mean_score": 0.55 } ]
}
```

Field rules:

- `frames[].scores` has a probability-like score in `[0, 1]` per non-spurious
  track visible in that frame (keys are track ids as strings). `raw_active` is
  the per-frame argmax above `speakers.min_score`, or `null`: this is
  the unsmoothed decision, kept for the debug overlay.
- `segments` cover `range` exactly, without gaps or overlaps, in time order.
  Segment boundaries come only from speaker changes and shot boundaries,
  never from fixed intervals.
- `kind`: `speaker` (frame one face: `speaker` is a track id), `hold`
  (nobody clearly speaking: keep the previous framing, `speaker` is null),
  `wide` (fallback: frame the group / frame centre, `speaker` is null).
- Every segment is at least `speakers.min_segment_s` long, except a segment
  cut short by a shot boundary or by the range edges.

### 5.3 `crop_plan.json` — Phase 2 output (complete render instructions)

`crop_plan.json` together with the source video is everything the renderer
needs. The renderer reads no other file.

```json
{
  "schema_version": "1.0",
  "kind": "crop_plan",
  "fingerprint": "…",
  "source": { … },
  "range": { "start": 120.0, "end": 180.0 },
  "inputs": {
    "detections": { "path": "detections.json", "fingerprint": "…" },
    "speakers":   { "path": "speakers.json",   "fingerprint": "…" }
  },
  "output": { "width": 1080, "height": 1920, "fps": 29.97, "video_codec": "libx264", "crf": 18, "preset": "medium", "audio_codec": "aac", "audio_bitrate": "192k" },
  "crop": { "aspect": "9:16", "zoom": 1.0 },
  "frames": [
    { "t": 120.0201, "x": 412.5, "y": 0.0, "w": 607.5, "h": 1080.0, "segment": 0 }
  ],
  "segments": [ { "index": 0, "start": 120.0, "end": 131.2, "speaker": 0, "kind": "speaker" } ],
  "cuts": [143.52]
}
```

Field rules:

- `frames` has exactly one entry per output frame from `range.start` to
  `range.end` at `output.fps`. `t` is the source time of that output frame.
  `x, y, w, h` is the crop rectangle in source pixels. It always lies fully
  inside the source frame and has the output aspect ratio. The renderer crops
  this rectangle and scales it to `output.width × output.height`.
- The path is already smoothed: the renderer does no smoothing, interpolation
  or decision-making of its own.
- `cuts` lists source shot boundaries. These are the only times the crop may
  jump. Everywhere else, the frame-to-frame change is bounded by
  `plan.max_speed` and the transitions between speakers are eased.
- `segments` is a copy of `speakers.json` segments, for the overlay and for
  humans. The renderer does not use it.

### 5.4 Transcript input

The transcript of the full source. Phase 3's moment selection reads it.
Native format:

```json
{
  "schema_version": "1.0",
  "kind": "transcript",
  "language": "en",
  "segments": [
    {
      "start": 12.34, "end": 17.80,
      "text": "So the thing nobody tells you about hiring is this.",
      "speaker": "SPEAKER_00",
      "words": [ { "start": 12.34, "end": 12.51, "word": "So" }, … ]
    }
  ]
}
```

- Times are source time. `segments` are in time order.
- `words` (word-level timestamps) are strongly recommended. They make
  sentence-boundary snapping exact. Without them, sentence times inside a
  segment are interpolated by character position and the selection reports
  reduced boundary precision.
- `speaker` is optional (from diarisation, if the transcriber provides it).
- `clipper transcript import` converts Whisper / WhisperX / faster-whisper
  JSON, SRT and WebVTT into this format. Transcription itself is out of scope
  for the pipeline (any local transcriber works; see 2.1).

### 5.5 `moment.json` — Phase 3 deliverable 0 output

```json
{
  "schema_version": "1.0",
  "kind": "moment",
  "fingerprint": "…",
  "source": { … },
  "inputs": { "transcript": { "path": "transcript.json", "sha256": "…" } },
  "scorer": { "name": "heuristic", "version": "1", "params": { … } },
  "constraints": { "target_s": 60, "min_s": 30, "max_s": 90, "sentence_snapping": "hard" },
  "selected": {
    "start": 1834.12, "end": 1893.40, "duration": 59.28,
    "first_sentence": 412, "last_sentence": 425,
    "text": "…",
    "score": 0.81,
    "reasons": ["hook: opens with a question", "high information density", "complete arc: ends on a conclusion"]
  },
  "candidates": [
    { "start": 1834.12, "end": 1893.40, "score": 0.81, "features": { … } }
  ],
  "rejected": { "not_sentence_aligned": 0, "too_short": 12, "too_long": 3 }
}
```

- `selected.start` is the start of a sentence and `selected.end` the end of a
  sentence (hard requirement, see 6.3). `clip` is the range that is cut:
  `selected` plus small padding (`select.pad_start_s`, `select.pad_end_s`).
  The padding only uses the pause around the moment (at most 80 % of it) and
  never reaches into a neighbouring sentence. Detection, planning and
  rendering run on `clip`.
- `candidates` holds the top `select.keep_candidates` by score, best first.
  `selected` equals `candidates[0]` unless overridden with `--moment START-END`.

---

## 6. Phase specifications

### 6.1 Phase 1 — Foundation

Goal: given a video and an optional time range, produce `detections.json`
(5.1): face boxes, lip and key landmarks, mouth blendshapes, head pose,
stable track ids and shot boundaries for every analysed frame.

Deliverables:

1. Package skeleton: `pyproject.toml`, `clipper` CLI (`python -m clipper`),
   config loading (built-in `default.yaml` deep-merged with `--config`),
   logging.
2. Video I/O (`clipper/video.py`): `ffprobe`-based probe (width, height, fps,
   duration, frame count, rotation) and a frame reader that seeks to
   `--start`, yields `(frame_index, source_time, frame)` and stops at `--end`.
   Frames wider than `detect.analysis_max_width` are downscaled for analysis.
   Normalised output coordinates are unaffected.
3. Model management (`clipper/models.py`): pinned download of the Face
   Landmarker model to the cache directory, SHA-256 verified, with a
   pre-seeding path for offline use.
4. Detector (`clipper/detect/mediapipe_detector.py`): MediaPipe Face
   Landmarker in VIDEO mode, `num_faces` faces, blendshapes and transformation
   matrices on. Converts results into contract faces (bbox from all 478
   points, stored landmark subset, blendshape subset, pose). The landmarker
   is reset at every shot boundary: in VIDEO mode it re-uses the previous
   frame's face regions, which otherwise yields a one-frame ghost face at the
   old position right after a cut.
5. Shot-boundary detector (`clipper/detect/shots.py`): HSV content-difference
   on downscaled frames, threshold `detect.shot_threshold`, minimum shot
   length `detect.min_shot_s`.
6. Tracker (`clipper/detect/tracker.py`): greedy IoU matching with a
   centre-distance fallback, tracks end after `detect.max_gap_s` without a
   match and at every shot boundary. Short tracks are marked `spurious`.
7. Stage runner + CLI: `clipper detect VIDEO [--start S] [--end S]
   [--work DIR] [--config FILE] [--force]` writes
   `WORK/detections.json`, validated against `clipper/schemas/detections.schema.json`,
   and is skipped when the fingerprint matches (2.6).
8. Inspection: `clipper inspect detections` renders a contact sheet (PNG)
   with boxes, track ids, lip contours and key points drawn from the file
   (not from a re-run of the detector).
9. Licence audit: `clipper licenses` (2.2).
10. Unit tests for the tracker, the shot detector, landmark groups and the
    schema.

Checkpoint verification (`clipper check phase1 VIDEO --expect FILE` or
`--expect-faces N`). The expectations file states the number of people and
human-reviewed intervals in which no face is visible (people looking down,
full profile). No landmark detector can or should return lips there, so those
frames are excluded from the detection rate (the unexcluded rate is reported
too):

| # | Check | Pass condition |
| --- | --- | --- |
| 1 | Stage runs | `clipper detect` finishes and writes `detections.json` |
| 2 | Contract | file validates against the 5.1 schema |
| 3 | Frame coverage | one entry per analysed frame, `t` strictly increasing, inside `range` |
| 4 | Detection rate | outside the no-face intervals, ≥ 95 % of analysed frames have at least one face and the most common face count is N |
| 5 | Lip landmarks | every face has all 40 lip points, finite, within `[-0.1, 1.1]` |
| 6 | Track stability | never more than N concurrent tracks (≥ 1 s); no identity swaps (left-to-right order of tracks matches their mean positions); no fragmentation: a new track never starts where another face was visible less than `max_gap_s` earlier in the same shot. Tracks restart only after a face was gone longer than `max_gap_s` |
| 7 | Lip signal | inner-lip aperture (13–14 distance / mouth width) varies over time on at least one track (p95 − p5 ≥ 0.02), so Phase 2 has a usable signal |
| 8 | Re-run | a second `clipper detect` with the same inputs is skipped via the fingerprint |
| 9 | Licences | `clipper licenses` passes and nothing imports `ultralytics` |
| 10 | Unit tests | `pytest` passes |
| 11 | Visual | contact sheet written for human review |
| 12 | Shot boundaries | on a multi-camera edit cut from the same video (wide / close-up / close-up / wide, 5 s each), cuts are found at 5, 10, 15 s ± 1 frame, no track crosses a cut, no one-frame ghost faces at cuts |

The check also reports processing speed (analysed frames per second).

Fixture: `scripts/fetch_fixture.sh` downloads
`head-pose-face-detection-female-and-male.mp4` from
`intel-iot-devkit/sample-videos` (CC BY 4.0): two people, static camera,
768×432, 12 fps, 143 s, silent audio. Expectations:
`tests/data/phase1_fixture.json` (2 people; no face visible in 56.5–63.4 s,
both looking down, and 134.0–140.3 s, both in full profile).

### 6.2 Phase 2 — Intelligence

Goal: from `detections.json`, decide who is speaking when (`speakers.json`,
5.2) and turn that into a smoothed 9:16 crop path (`crop_plan.json`, 5.3).

Deliverables, in this order:

1. **Swappable active-speaker interface** (`clipper/speakers/base.py`): a
   backend receives the detections (and optionally the source path and range
   for audio) and returns per-frame per-track scores in `[0, 1]`. Backends are
   selected with `speakers.backend` / `--asd`.
2. **Heuristic backend (default, built first)**: lip-motion based.
   Per track: inner-lip aperture normalised by mouth width, plus the
   `jawOpen` blendshape. The score comes from the short-window variability of
   that signal (speech is rapid open/close, not a steady open mouth), within
   ± `speakers.window_s`, after removing the track's slow mouth trend (a ~1 s
   moving median), which cancels resting-mouth shape differences and held
   smiles. The score is penalised for strong head yaw and small faces. Optional
   audio gating: when the source has an audio track, frames whose RMS energy
   is below the speech threshold score 0 for everyone (no audio, nobody speaks).
   The noise floor is a low percentile (3rd) of the level. The pauses between
   words exceed 3 % of the time even in continuous speech, so the floor never
   lands on speech. The gate is held open across pauses shorter than about
   0.6 s. A silent (muted) or missing audio track disables gating
   automatically, and the heuristic then works visual-only.
3. **Segmentation on speaker changes**: per-frame argmax → run-length
   segments → enforce `speakers.min_segment_s` (default 2.0 s): a segment
   shorter than the minimum is merged into the neighbour that keeps the
   framing stable (the previous speaker unless the next one dominates), and
   a switch happens only when the new speaker leads by
   `speakers.switch_margin` for at least `speakers.min_segment_s`.
   Short interjections ("yeah", "right") must not move the frame. Shot
   boundaries always split segments. Periods without a clear speaker become
   `hold` (or `wide` when nothing was framed before).
4. **Crop planner** (`clipper/plan/`): per output frame, target = the active
   speaker's face, placed at `plan.face_y` (default 0.38 of the crop height)
   and centred horizontally, using a per-segment robust (median) face position
   so that small head motion does not move the frame. The path is smoothed:
   a dead zone (`plan.dead_zone`) ignores small target changes, transitions
   between speakers ease in and out over `plan.transition_s` (default 0.6 s),
   speed is capped at `plan.max_speed` (fraction of frame width per second),
   and a final zero-phase low-pass removes residual jitter. The crop jumps only
   at `cuts`. The crop is clamped inside the frame.
5. **LR-ASD backend (second)**: wraps LR-ASD (Junhua-Liao/LR-ASD, pinned
   commit `1b6dcd2d`). Its licence must be re-checked when the backend is
   added. If it is not permissive, stop and report instead of adding it.
   Checked when adding the backend: the code and both weight files are MIT.
   To keep PyTorch out of the user's install, the weights are exported once
   to ONNX by `scripts/export_lrasd_onnx.py`, which verifies that
   onnxruntime matches PyTorch within 1e-3 on several input lengths (measured
   ~2e-6). The exports and LR-ASD's licence are committed under
   `clipper/speakers/models/`. Runtime: optional extra `pip install
   .[lrasd]` (onnxruntime, python_speech_features, scipy, all MIT/BSD). Inputs
   are prepared exactly like LR-ASD's demo (25 fps grey 112×112 face crops,
   13-coefficient MFCCs, scores averaged over 1–6 s chunks), but face boxes
   come from Phase 1's tracks: no other face detector. It needs the audio
   track. Default weights: `finetuning_TalkSet`, which the authors report as
   much better on talking-head footage (Columbia F1 96.4 % vs 86.1 %); it
   also clearly wins on the conversation fixture.
6. CLI: `clipper speakers WORK`, `clipper plan WORK`, both with fingerprints
   and `--force`. `clipper inspect plan` renders a preview strip.

Checkpoint verification (`clipper check phase2`):

1. Both files validate against 5.2 / 5.3 schemas.
2. Segments cover the range without gaps/overlaps, and every segment
   respects `min_segment_s` except at shot boundaries or range edges.
3. Flicker: no A→B→A sequence whose B segment is shorter than
   `min_segment_s`. Switches per minute are reported next to the switches
   in the raw per-frame decision. The interjection part of check 7 tests
   the actual failure mode: short real speech by the other person.
4. Crop validity: every rectangle inside the frame, with the correct aspect ratio,
   and one per output frame.
5. Smoothness: away from `cuts`, per-frame speed ≤ `plan.max_speed` and
   no velocity discontinuities (acceleration bounded).
6. Framing: during `speaker` segments, outside transitions, the active
   speaker's face box lies inside the crop on ≥ 99 % of frames.
7. With a labelled fixture (`--labels`): framing accuracy ≥ 90 % and no
   interjection moves the frame, for the heuristic and, when installed, for
   LR-ASD. Framing accuracy compares who is on screen with who should be:
   the turn's speaker, with interjections counting as the surrounding turn and
   silence accepting anything. 0.5 s around each label change is excluded.
   Raw per-frame accuracy is reported too.
8. Re-running `speakers` and `plan` with the same inputs is skipped.
9. Licence audit passes, and the bundled LR-ASD licence is MIT.
10. Unit tests pass.
11. With `--negative VIDEO` (nobody speaks): report how much of it is framed
    as someone speaking (information, not pass/fail).
12. Visual: preview strip and side-by-side preview video for human review.
13. Cuts: the same video re-edited as a multi-camera cut (wide / close-up /
    close-up / wide, with audio). No segment spans a cut, crop jumps happen
    only at cuts, and checks 2–6 pass on it.

Phase 2 fixture: `scripts/make_conversation_fixture.py` builds
`fixtures/conversation.mp4` (50 s, 1920×1080, 30 fps) and
`fixtures/conversation_labels.json`. It uses two real talking-head clips
with their own audio (OpenTalker/video-retalking examples, pinned commit,
downloaded for local testing only, never committed) placed side by side. The
schedule has 9 turns, 2 interjections (0.6 s and 0.5 s) and a 2 s silence;
the listener is held on a still frame with a slow head sway. Clips are picked by
frame timestamp because some are variable frame rate.

### 6.3 Phase 3 — Output

Goal: from the full-length source and its transcript, pick the strongest
moment, run Phases 1–2 on it, and render the final vertical clip plus a debug
overlay, through one command.

Deliverables, in this order:

0. **Moment selection** (`clipper select`), built first, because everything
   else in the phase is easier to test on a 60 s clip than on a 90-minute
   source.
   - Sentence segmentation of the transcript (punctuation + word timings).
   - Candidate generation: every window that starts at a sentence start and
     ends at a sentence end with duration in `[min_s, max_s]`.
     **Sentence-boundary snapping is a hard filter on candidates, not a
     weighted term.** A candidate that does not start and end on a sentence
     boundary is never scored.
   - Swappable scorer interface. **Heuristic scorer is the default and is
     fully local**: hook strength of the opening sentence (questions, strong
     claims, numbers, contrast words), information density, speech rate and
     energy, completeness (ends on a conclusion, not a dangling clause),
     closeness to `target_s`, penalties for filler and for starting with a
     back-reference ("so", "and", "that's why" referring to unseen context).
   - LLM scorer (second): talks to a local OpenAI-compatible or Ollama
     endpoint. It refuses any non-local endpoint unless explicitly opted in
     (2.1). It only re-ranks the heuristic's top candidates and never
     produces boundaries itself.
   - Output `moment.json` (5.5) with the reasons for the choice.
   - As built: word lists for English and Polish (fallback English). Seven
     features: hook, standalone, completeness, density, energy, clean,
     duration. The weighted mean is the score, with non-maximum suppression
     for distinct alternatives. Completeness asks for a concluding line in
     the last 3 sentences or 15 s and for the speaker to stop right after
     (change of speaker or a pause of at least 1 s). Ending on someone
     else's 1–2 word reaction is penalised. These two rules were sharpened
     after the first run on the podcast fixture, where the clip stopped one
     sentence before the punchline.
1. **Renderer** (`clipper render`): reads only `crop_plan.json` and the source
   video, decodes the range, crops/scales each frame exactly as specified and
   muxes the source audio for the same range (ffmpeg). Writes a
   `render_log.json` with the rectangle actually applied per frame.
2. **Debug overlay** (`clipper render --debug`): a real diagnostic. It draws,
   on the full source frame, the actual tracking data from `detections.json`
   (boxes, track ids, lip contours), the actual per-frame active-speaker
   scores and decision from `speakers.json`, the segment, and the crop
   rectangle from the same plan the renderer used. If the overlay does not match
   what the renderer did, it is wrong. The check compares the overlay
   rectangles with `render_log.json`.
3. **CLI orchestration**: `clipper run --video SRC --transcript T --work DIR`
   runs select → detect → speakers → plan → render → overlay. Every stage
   reuses an existing output with a matching fingerprint. `--from STAGE`
   forces a stage and everything after it. Re-running the render must not
   re-run detection or selection. `--offline` blocks every outbound network
   connection (loopback stays allowed for a local LLM). `run_log.json`
   records what ran.
4. **README** written for someone setting this up from scratch: installation
   (Python, ffmpeg, system libraries), model download/offline seeding, the
   transcript format and how to produce one locally, every config option, every
   output file and what it contains, troubleshooting.

Checkpoint verification (`clipper check phase3`): run the full pipeline end
to end with a single command from the full-length source and its transcript,
then verify:

1. `moment.json` validates against 5.5. The selected moment starts on a sentence
   start and ends on a sentence end, checked against an independent
   re-segmentation of the transcript. The padded clip range stays clear of
   neighbouring sentences, and the duration is within `[min_s, max_s]`.
2. The output is MP4 1080×1920, H.264 + AAC, duration = moment duration ± 1
   frame, with audio in sync (same range as the video).
3. `render_log.json` matches `crop_plan.json` frame for frame. The overlay's
   rectangles match `render_log.json`. Pixel check: sampled clip frames match
   the source re-cut with the logged rectangle (PSNR ≥ 30 dB).
4. Re-running the same command performs no work. `--from render` re-runs
   only the render (verified from the stage log).
5. No network access outside the allowed model downloads during the run
   (2.1): the run uses `--offline`. A hosted LLM endpoint is refused before
   any request. A local one (a mock OpenAI-compatible server on 127.0.0.1)
   works, and its re-ranked choice is still sentence-aligned. The licence
   audit passes.
6. Report which moment was selected and why (score, reasons, runner-up).
7. Transcript import: the `.srt` next to the transcript, when present,
   imports to the same sentence boundaries.
8. Unit tests pass.

Phase 3 fixture: `scripts/make_podcast_fixture.py` turns the scripted
two-person podcast `tests/data/podcast_script.json` (about 8 minutes, 131
sentences) into `fixtures/podcast.mp4` plus its transcript (JSON with word
timings, and SRT). It was written after the scorer, with one planted strong
moment and three traps:
- strong content that opens with a back-reference,
- a number-dense passage that ends on an open question,
- a filler-heavy passage.

While someone speaks, their real talking-head clip and its own audio play.
The audio does not say the scripted words. A run on a real full-length
podcast with a real transcript is still needed to judge the scorer on real
content.

---

## 7. Configuration (defaults)

All options live in `clipper/config/default.yaml`. The README (Phase 3)
documents every option. Phase 1 options:

| Key | Default | Meaning |
| --- | --- | --- |
| `detect.num_faces` | 4 | maximum faces per frame |
| `detect.max_fps` | 30 | analyse every n-th frame so that the analysed rate ≤ this |
| `detect.analysis_max_width` | 1280 | downscale wider frames before detection |
| `detect.min_detection_confidence` | 0.5 | MediaPipe face detection threshold |
| `detect.min_presence_confidence` | 0.5 | MediaPipe face presence threshold |
| `detect.min_tracking_confidence` | 0.5 | MediaPipe landmark tracking threshold |
| `detect.landmarks` | `subset` | `subset` (lips + key points) or `full` (all 478) |
| `detect.blendshapes` | 6 mouth shapes | blendshape names to store |
| `detect.iou_threshold` | 0.2 | minimum IoU to continue a track |
| `detect.max_center_dist` | 0.6 | centre-distance fallback, in face widths |
| `detect.max_gap_s` | 1.0 | a track ends after this long without a match |
| `detect.min_track_s` | 0.5 | shorter tracks are marked `spurious` |
| `detect.shot_threshold` | 27.0 | HSV content difference for a hard cut |
| `detect.min_shot_s` | 0.5 | minimum shot length |
| `models.cache_dir` | `~/.cache/clipper/models` | model cache (env `CLIPPER_MODEL_DIR` overrides) |

Phase 2 options:

| Key | Default | Meaning |
| --- | --- | --- |
| `speakers.backend` | `heuristic` | `heuristic` or `lrasd` (CLI `--asd`) |
| `speakers.min_score` | 0.5 | a face counts as speaking in a frame above this score |
| `speakers.min_segment_s` | 2.0 | minimum framing segment (except at cuts / range edges) |
| `speakers.switch_margin` | 0.15 | a new speaker must out-score the current one by this much |
| `speakers.heuristic.*` | see `default.yaml` | mouth-signal weights, windows, logistic, yaw/size penalties, audio gate |
| `speakers.lrasd.weights` | `finetuning_TalkSet` | or `pretrain_AVA` |
| `speakers.lrasd.durations` | `[1,1,1,2,2,2,3,3,4,5,6]` | chunk lengths averaged, as in LR-ASD's demo |
| `plan.aspect` | `9:16` | output aspect ratio |
| `plan.zoom` | 1.0 | 1.0 = crop is the full source height |
| `plan.face_y` | 0.38 | face centre height within the crop (when zoom leaves room) |
| `plan.follow_window_s` | 1.5 | moving median of the speaker's face position |
| `plan.dead_zone` | 0.12 | target changes below this fraction of the crop width are ignored |
| `plan.transition_s` | 0.6 | ease-in-out duration of a move (stretched to respect `max_speed`) |
| `plan.max_speed` | 1.5 | frame widths per second |
| `plan.max_accel` | 8.0 | frame widths per second², verified by the checkpoint |
| `plan.smooth_s` | 0.08 | sigma of the final zero-phase low-pass |
| `plan.output.*` | 1080×1920, H.264 CRF 18, AAC 192k | written into `crop_plan.json` for the renderer |

Phase 3 options (`select.*`: target/min/max duration, padding, the heuristic
feature weights, the local LLM endpoint and the hosted opt-in) are listed with
every other option in README section 6, which is the complete reference.

---

## 8. Risks and open questions

1. **Small faces in wide shots.** Face Landmarker's internal detector is the
   short-range BlazeFace model. Faces that are very small in a wide 4K shot
   may be missed. Mitigation if it shows up on real material: tiled detection
   on the analysis frame (still MediaPipe).
2. **Lip motion ≠ speech.** Chewing, laughing and nodding move lips. The
   heuristic mitigates with variability-based scoring and audio gating.
   Without usable audio it is visual-only: on the silent head-pose fixture
   (nobody speaks) about 8 % of the time was framed as someone speaking.
   LR-ASD (audio-visual) is the stronger option when audio is available.
   It is also slower: about 60 s of CPU for a 50 s, 2-person clip, against
   about 1 s for the heuristic.
3. **Identity across shots.** Tracks restart at every cut. The clip framing
   doesn't need cross-shot identity, but speaker statistics are per track.
4. **Test material.** No real full-length podcast with a transcript was
   reachable from the build environment. The Phase 3 check ran on the
   scripted ~8-minute fixture. Its words are scripted, while its audio and
   video are real speech. The moment scorer has only been judged on that
   text, so validate it on your own episodes. Transcription is out of scope
   (5.4).
6. **Heuristic moment scoring is English/Polish-lexicon based.** Other
   languages fall back to English word lists, so only density, energy,
   completeness punctuation and duration still work. The optional local LLM
   re-ranker is language-agnostic.
5. **Variable frame rate sources.** Timestamps come from the decoder's
   presentation times, so VFR is handled, but the renderer outputs CFR.

---

## 9. Extension: stream mode (`clipper stream`)

Added after Phase 3 for clipping game/IRL stream VODs. The hard constraints in
section 2 apply unchanged: local only, permissive licences, MediaPipe for
faces, and contract files with fingerprints.

- **Moments by hype, not by sentences.** The audio is decoded once for the
  whole VOD and gives three per-second signals:
  - audio loudness over a rolling baseline;
  - the chat's message rate against its own baseline (robust z-score), with
    hype emotes weighted and shifted back by the chat delay;
  - transcript exclamations and laughter.

  Peaks of the weighted mix become clips:
  - pre-roll before the peak for the build-up;
  - extended while the hype lasts;
  - both ends snapped to the quietest nearby block and out of words;
  - several non-overlapping clips per VOD.
- **Layouts.**
  - `stacked`: facecam/avatar on top, gameplay below.
  - `fit`: the whole picture on a blurred background.
  - `gameplay`: a 9:16 game crop.
  - `follow`: the podcast pipeline.

  Facecam detection:
  - looks for faces in overlapping tiles, because a facecam face is too
    small for the landmarker's detector on the full frame;
  - keeps a face that stays in the same place across sampled frames;
  - finds the overlay rectangle from persistent edges;
  - shows tall avatars/faces whole (`contain`) instead of slicing them.
- **Rendering.** One ffmpeg pass per clip, because static layouts need no
  per-frame work. Captions are burned in from transcript word timings (ASS,
  word highlighting).
- **Contracts:** `stream_signals`, `stream_moments`, `stream_plan`,
  `stream_render_log` (`clipper/schemas/`).
- **Check (`clipper check stream`).** Runs on a synthetic 6-minute stream: a
  procedural shooter with a real talking-head facecam, a TwitchDownloader-style
  chat, 4 planted moments and 2 decoys. A VTuber-style variant uses a drawn
  avatar. The check verifies:
  - the planted moments are found and the decoys are not;
  - the facecam overlay is found (IoU 0.98), and the gameplay crop avoids it;
  - the outputs and their cam panel pixels against the source;
  - captions and reuse.
- **Risks.**
  - Real streams have game audio as loud as the streamer, music, and
    multi-hour baselines.
  - The defaults have only been tuned on the synthetic stream, so the first
    real VODs should be reviewed with `signals.png`.
  - Whether a given VTuber avatar is detected as a face is unknown until
    tried; `stream.facecam` covers it.
