# clipper — vertical clips from long recordings

`clipper` takes a long horizontal recording (podcast, interview, panel) and
its transcript, finds the strongest ~60-second moment, and renders it as a
vertical 9:16 clip (1080×1920) that follows whoever is speaking. The frame
glides between speakers and never cuts on a short "yeah".

Everything runs on your machine. There are no paid APIs or cloud calls, only
permissive licences (no AGPL/GPL, no Ultralytics), and faces come from
MediaPipe. The design, data contracts and phase plan are in
[IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md).

```
source.mp4 + transcript.json
   │
   ├─ select    pick the moment (whole sentences only)           → moment.json
   ├─ detect    faces, lip landmarks, tracks, cuts (that moment)  → detections.json
   ├─ speakers  who speaks when, framing segments                 → speakers.json
   ├─ plan      smoothed 9:16 crop rectangle per frame            → crop_plan.json
   ├─ render    crop + scale + audio                              → clip.mp4, render_log.json
   └─ overlay   diagnostic video of every decision                → debug.mp4, overlay_log.json
```

Detection and speaker analysis only run on the selected moment, so a 90-minute
source costs about the same as a 60-second one, plus decoding the audio once.

There are two modes:

- **Podcast mode** (`clipper run`, sections 2–7): long conversations. Picks
  the best ~60 s *by what is said* and follows the speaker.
- **Stream mode** (`clipper stream`, section 11): game and IRL stream VODs.
  Picks several clips *by what happens*: the streamer gets loud, chat
  explodes, someone yells "no way". Lays each clip out as facecam/avatar on
  top and gameplay below, with captions burned in.

---

## 1. Installation

### Requirements

- **Python 3.10 or newer.**
- **FFmpeg** (`ffmpeg` and `ffprobe` on your `PATH`):
  - macOS: `brew install ffmpeg`
  - Debian/Ubuntu: `sudo apt install ffmpeg`
  - Windows: `winget install ffmpeg`
- **Linux only:** MediaPipe needs the EGL/GLES runtime: `sudo apt install libegl1 libgles2`.
- A CPU is enough. Expect roughly 1–2 minutes per 60 s clip at 1080p.

### Install

```sh
git clone <this repository> && cd <repository>/clip-pipeline
python3 -m venv .venv
. .venv/bin/activate                # Windows: .venv\Scripts\activate
pip install -e .                    # the pipeline
pip install -e '.[lrasd]'           # optional: LR-ASD audio-visual speaker detection (onnxruntime, no PyTorch)
pip install -e '.[dev]'             # optional: tests
clipper --version
```

### Models

| Model | Used by | Where it comes from |
| --- | --- | --- |
| MediaPipe Face Landmarker `face_landmarker.task` (3.6 MB, Apache-2.0) | detect | Downloaded once on first use to `~/.cache/clipper/models/`. SHA-256 pinned in `clipper/models.py`. |
| LR-ASD, ONNX export of the official weights (MIT) | `--asd lrasd` | Bundled in `clipper/speakers/models/`. Nothing to download. |

**Offline machines:** download
`https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task`
elsewhere and copy it to `~/.cache/clipper/models/face_landmarker.task`, or
point `CLIPPER_MODEL_DIR` at the folder holding it. Its SHA-256 must be
`64184e229b263107bc2b804c6625db1341ff2bb731874b0bcc2fe6544e0bc9ff`.

---

## 2. Quick start

```sh
# 1. a transcript of the whole recording (see section 3)
clipper transcript import episode.srt transcript.json --language en

# 2. everything, one command
clipper run --video episode.mp4 --transcript transcript.json --work out/episode42
```

It prints the chosen moment and why, and writes `out/episode42/clip.mp4`
(the clip) and `out/episode42/debug.mp4` (the diagnostic video).

Run the same command again and nothing is recomputed. Change one setting and
only the stages that depend on it run again (section 4).

---

## 3. The transcript

`clipper` does not transcribe. Any **local** transcriber works; its output
just needs converting. Word-level timestamps are strongly recommended,
because they make the clip start and end exactly at sentence boundaries.

| Tool (local, open source) | How to get word timestamps | Import |
| --- | --- | --- |
| [faster-whisper](https://github.com/SYSTRAN/faster-whisper) (MIT) | `word_timestamps=True`, dump the segments to JSON | `clipper transcript import out.json transcript.json` |
| [WhisperX](https://github.com/m-bain/whisperX) (BSD) | default; add diarisation for speaker labels | `clipper transcript import out.json transcript.json` |
| [openai-whisper](https://github.com/openai/whisper) (MIT) | `--word_timestamps True --output_format json` | `clipper transcript import out.json transcript.json` |
| whisper.cpp (MIT) / anything that writes subtitles | `.srt` or `.vtt` | `clipper transcript import out.srt transcript.json` |

Use `--language pl` (or `en`, ...) on import. The language selects the word
lists the moment scorer uses for hooks, fillers and back-references. English
and Polish are built in, and other languages fall back to English lists.
Subtitle lines that start with `NAME:` or WebVTT `<v Name>` become speaker
labels.

Native format (plan 5.4), all times in seconds from the start of the video:

```json
{
  "schema_version": "1.0", "kind": "transcript", "language": "en",
  "segments": [
    {"start": 12.34, "end": 17.80, "speaker": "SPEAKER_00",
     "text": "So the thing nobody tells you about hiring is this.",
     "words": [{"start": 12.34, "end": 12.51, "word": "So"}, ...]}
  ]
}
```

`speaker` and `words` are optional. Without `words`, sentence times are
interpolated inside each segment by character position.

---

## 4. Running the pipeline

```sh
clipper run --video SRC --transcript T [--work DIR] [options]
```

| Option | Effect |
| --- | --- |
| `--work DIR` | Where every intermediate and output file goes (default `./work`). One directory per source. |
| `--from STAGE` | Re-run `STAGE` and every later stage. Stages: `select`, `detect`, `speakers`, `plan`, `render`, `overlay`. Earlier stages are reused. |
| `--moment 1834.1-1893.4` | Choose the moment yourself. It is still snapped to whole sentences. |
| `--asd heuristic\|lrasd` | Active-speaker backend (default `speakers.backend`). |
| `--scorer heuristic\|llm` | Moment scorer (default `select.scorer`). |
| `--no-debug` | Skip `debug.mp4`. |
| `--offline` | Block every network connection that leaves the machine. The model must already be cached. |
| `--allow-hosted-llm` | Explicitly allow a non-local LLM endpoint (see 4.3). |
| `--config FILE` | YAML deep-merged over the defaults; only the keys you change (section 6). |
| `-v` | Debug logging. |

### 4.1 Re-using intermediate files

Every stage writes its output with a **fingerprint** of its inputs and
settings. When the output exists and the fingerprint matches, the stage is
skipped. So:

- `clipper run ...` twice: the second run does nothing.
- Change `plan.transition_s`: only `plan`, `render` and `overlay` run.
- `--from render`: re-renders without re-running selection, detection or
  speaker detection.
- `--moment ...` or a changed `select.*` setting: selection runs again, and
  detection runs again only if the moment's time range changed.

`run_log.json` lists, for every stage, whether it ran or was reused.

### 4.2 Running stages one by one

```sh
clipper select   --video SRC --transcript T --work W   # -> W/moment.json
clipper detect   SRC --start 120 --end 180 --work W    # -> W/detections.json (any range)
clipper speakers W [--asd lrasd]                       # -> W/speakers.json
clipper plan     W                                     # -> W/crop_plan.json
clipper render   W                                     # -> W/clip.mp4 + W/render_log.json
clipper overlay  W [--transcript T]                    # -> W/debug.mp4 + W/overlay_log.json
clipper inspect  detections W/detections.json          # contact sheet PNG
clipper inspect  plan W --video                        # crop strip PNG + side-by-side preview
clipper licenses                                       # licence audit of the environment
```

Every stage command takes `--force` to ignore the fingerprint.

### 4.3 Moment selection with a local LLM (optional)

The default scorer is a local heuristic. You can let a **local** LLM re-rank
the heuristic's top candidates. It never chooses boundaries itself, so clips
still start and end on whole sentences. With [Ollama](https://ollama.com):

```sh
ollama pull llama3.1:8b
clipper run --video SRC --transcript T --scorer llm
```

Any OpenAI-compatible server on your machine also works (llama.cpp
`server`, LM Studio):

```yaml
# llm.yaml
select:
  llm:
    endpoint: http://127.0.0.1:8080
    api: openai
    model: local-model
```

Endpoints that are not on this machine are **refused** (plan 2.1: no paid or
hosted API calls in the processing chain). This applies to
`https://api.openai.com` and also to another computer on your network. To
use one anyway you must opt in explicitly with `--allow-hosted-llm` or
`select.llm.allow_hosted: true`.

---

## 5. Output files

All in the work directory. The JSON files are the stage contracts (plan section
5). Each carries `schema_version`, `kind` and `fingerprint`, and is
validated against `clipper/schemas/*.schema.json` whenever it is written or
read.

| File | Written by | Contents |
| --- | --- | --- |
| `moment.json` | select | The chosen moment: `selected` (start/end on sentence boundaries, first/last sentence index, text, score, per-feature scores, human-readable `reasons`). `clip` (the padded range that is cut, never reaching into a neighbouring sentence). `candidates` (the top non-overlapping alternatives, best first). Number of candidates scored, scorer settings, transcript hash. |
| `detections.json` | detect | For every analysed frame of the clip range: each face's box, 40 lip-contour points + 9 key points (MediaPipe face-mesh indices listed in `landmark_spec`), 6 mouth blendshapes, head pose (yaw/pitch/roll), a `track_id`, and the shot. Also: `shots` (hard cuts in the source), `tracks` (span, coverage, mean box, `spurious` flag). |
| `speakers.json` | speakers | Per frame: a speaking score in [0, 1] per face and the raw per-frame decision. `segments`: the framing decisions covering the range. Each is `speaker` (frame track N), `hold` (nobody clearly speaking: keep the framing) or `wide`, with confidence and a reason. Segments change only on speaker changes and cuts and last ≥ 2 s. Backend name and settings. |
| `crop_plan.json` | plan | Everything the renderer needs: output size, frame rate and codecs, and one crop rectangle (`x, y, w, h` in source pixels) per output frame, already smoothed. Also the segments (for humans and the overlay) and the source's cut times. |
| `clip.mp4` | render | **The result.** H.264 + AAC, 1080×1920, source frame rate, audio from the same span of the source. |
| `render_log.json` | render | The rectangle and source frame time the renderer actually applied to every output frame. |
| `debug.mp4` | overlay | The diagnostic video (section 7). |
| `overlay_log.json` | overlay | The rectangle the overlay drew on every frame. It must equal `render_log.json`. |
| `run_log.json` | run | Which stages ran or were reused and how long they took, the moment, and the clip path. |

---

## 6. Configuration reference

All options with their defaults live in `clipper/config/default.yaml`. Pass
`--config my.yaml` with only the keys you want to change (unknown keys are an
error, so typos do not go unnoticed):

```yaml
plan:
  transition_s: 0.8
select:
  target_s: 45
```

### `models`

| Key | Default | Meaning |
| --- | --- | --- |
| `models.cache_dir` | `~/.cache/clipper/models` | Model cache. The env `CLIPPER_MODEL_DIR` overrides it. |

### `select`: choosing the moment

| Key | Default | Meaning |
| --- | --- | --- |
| `scorer` | `heuristic` | `heuristic` (local) or `llm` (a local LLM re-ranks the heuristic's best). |
| `target_s` | 60 | Ideal clip length (s). |
| `min_s` / `max_s` | 30 / 90 | Candidates outside these lengths are not considered. |
| `split_on_speaker_change` | true | A change of speaker also ends a sentence. |
| `pad_start_s` / `pad_end_s` | 0.25 / 0.5 | Breathing room before the first and after the last word. It only uses the pause there and never reaches into another sentence. |
| `keep_candidates` | 10 | How many alternatives `moment.json` lists. |
| `nms_overlap` | 0.5 | An alternative overlapping a better one by more than this share is not listed. |
| `loudness` | true | Use the source's audio loudness in the `energy` feature. |
| `heuristic.weights.hook` | 0.25 | Opening grabs attention: question, strong claim ("most people", "the biggest mistake"), number, "you", short first sentence. |
| `heuristic.weights.standalone` | 0.20 | Opening needs no earlier context. Penalises "And…", "So…", "That's why…", "Yeah, exactly…" and openings that start with a pronoun. |
| `heuristic.weights.completeness` | 0.20 | Ends with terminal punctuation, has a concluding line in the last 3 sentences / 15 s, and the speaker stops there. Not an ending: someone else's one-word reaction. |
| `heuristic.weights.density` | 0.12 | Share of content words and numbers per minute. |
| `heuristic.weights.energy` | 0.08 | Speech rate and loudness relative to the whole episode. |
| `heuristic.weights.clean` | 0.07 | Few filler words ("um", "you know", "yyy", "jakby"). |
| `heuristic.weights.duration` | 0.08 | Closeness to `target_s`. |
| `llm.endpoint` | `http://127.0.0.1:11434` | Local server URL. Non-local hosts are refused unless `allow_hosted`. |
| `llm.api` | `ollama` | `ollama` or `openai` (any OpenAI-compatible server). |
| `llm.model` | `llama3.1:8b` | Model name on that server. |
| `llm.allow_hosted` | false | Explicit opt-in to a non-local endpoint (same as `--allow-hosted-llm`). |
| `llm.top_k` | 5 | How many heuristic candidates the LLM re-ranks. |
| `llm.weight` | 0.6 | Final score = (1 − weight) · heuristic + weight · LLM / 10. |
| `llm.timeout_s` | 120 | Request timeout. |

Sentence-boundary snapping is not an option. It is a hard rule: windows that
would start or end inside a sentence are never formed.

### `detect`: faces (Phase 1)

| Key | Default | Meaning |
| --- | --- | --- |
| `num_faces` | 4 | Maximum faces per frame. |
| `max_fps` | 30 | Analyse every n-th frame so the analysed rate is at most this. |
| `analysis_max_width` | 1280 | Wider frames are downscaled before detection (coordinates stay exact, they are normalised). |
| `min_detection_confidence` / `min_presence_confidence` / `min_tracking_confidence` | 0.5 | MediaPipe Face Landmarker thresholds. |
| `landmarks` | `subset` | `subset` (lips + key points) or `full` (all 478; larger files). |
| `blendshapes` | 6 mouth shapes | Blendshape names to store. |
| `iou_threshold` | 0.2 | Minimum box overlap to continue a track. |
| `max_center_dist` | 0.6 | Fallback match by centre distance, in face widths. |
| `max_gap_s` | 1.0 | A track ends after this long without its face. |
| `min_track_s` | 0.5 | Shorter tracks are marked `spurious` and ignored later. |
| `shot_threshold` | 27.0 | HSV content difference that counts as a hard cut. |
| `min_shot_s` | 0.5 | Minimum shot length. |

### `speakers`: who speaks when (Phase 2)

| Key | Default | Meaning |
| --- | --- | --- |
| `backend` | `heuristic` | `heuristic` (lip motion + audio gate, fast) or `lrasd` (audio-visual network, more precise at turn boundaries, ~1 s of CPU per second of clip per face). |
| `min_score` | 0.5 | A face counts as speaking in a frame above this score. |
| `min_segment_s` | 2.0 | No framing segment is shorter (except at cuts and range edges). This is what stops flicker on short interjections. |
| `switch_margin` | 0.15 | A new speaker must out-score the current one by this much. |
| `heuristic.jaw_weight` | 0.5 | Weight of the `jawOpen` blendshape in the mouth signal. |
| `heuristic.window_s` | 0.4 | Half-width of the articulation window (s). |
| `heuristic.trend_window_s` | 1.0 | Slow mouth trend removed first (held smiles, resting shape). |
| `heuristic.articulation_mid` / `articulation_scale` | 0.03 / 0.008 | Logistic mapping from articulation to score. |
| `heuristic.yaw_full` / `yaw_zero` | 35 / 70 | Head yaw (degrees) where the score starts to drop / reaches 0. |
| `heuristic.min_face_height` | 0.08 | Faces smaller than this share of the frame height are penalised. |
| `heuristic.audio_gating` | true | Zero all scores where the audio has no speech energy. |
| `heuristic.audio_window_s` | 0.2 | RMS window for the gate. |
| `heuristic.silence_db` | −60 | A track whose loudest moment is below this is treated as having no audio (gating off). |
| `heuristic.speech_margin_db` / `speech_floor_db` | 12 / −45 | Speech is this far above the noise floor and at least this loud. |
| `heuristic.gate_hold_s` | 0.3 | Keeps the gate open across pauses between words. |
| `heuristic.noise_percentile` | 3 | Noise floor = this percentile of the level. |
| `lrasd.weights` | `finetuning_TalkSet` | Or `pretrain_AVA`. |
| `lrasd.durations` | `[1,1,1,2,2,2,3,3,4,5,6]` | Chunk lengths (s) whose scores are averaged, as in LR-ASD's demo. |
| `lrasd.crop_scale` | 0.40 | Face-crop padding, as in LR-ASD's demo. |

### `plan`: the crop path (Phase 2)

| Key | Default | Meaning |
| --- | --- | --- |
| `aspect` | `9:16` | Output aspect ratio. |
| `zoom` | 1.0 | 1.0 = the crop is the full source height; 1.5 = tighter. |
| `face_y` | 0.38 | Where the face centre sits in the crop height (only when `zoom` > 1 leaves room). |
| `follow_window_s` | 1.5 | Moving median of the speaker's face position: head motion does not move the frame. |
| `dead_zone` | 0.12 | Target changes below this share of the crop width are ignored. |
| `transition_s` | 0.6 | Ease-in-out duration of a move between speakers (stretched if `max_speed` requires it). |
| `max_speed` | 1.5 | Maximum camera speed, source frame widths per second. |
| `max_accel` | 8.0 | Acceleration bound (frame widths/s²) the checkpoint verifies. |
| `smooth_s` | 0.08 | Sigma of the final zero-phase low-pass (s). |
| `output.width` / `output.height` | 1080 / 1920 | Output size. |
| `output.max_fps` | 60 | Output frame rate = source frame rate, capped here. |
| `output.video_codec` / `crf` / `preset` | `libx264` / 18 / `medium` | Video encoding. |
| `output.audio_codec` / `audio_bitrate` | `aac` / `192k` | Audio encoding. |

---

## 7. Reading `debug.mp4`

`debug.mp4` is a diagnostic. Everything on it comes from the pipeline's files,
not from a re-run:

- **Left:** the full source frame.
  - Each face: box and `#track` id, lip contours (inner contour white), key
    points, head yaw and `jawOpen`.
  - Under each face: its speaking score for this frame. `RAW` marks the raw
    per-frame decision.
  - The **thick green box** is the face currently framed (the segment's
    speaker).
  - The **white rectangle** is the crop from `crop_plan.json`.
- **Right:** the frame decoded from the finished `clip.mp4`. If it does not
  show what the white rectangle frames, something is wrong. The Phase 3
  checkpoint verifies this frame by frame.
- **Bottom:**
  - The segment timeline (one colour per speaker; `hold`/`wide` grey).
  - The raw per-frame decision strip under it, where short coloured blips
    are interjections the segmentation ignored.
  - Cuts as white lines, the current time in red.
  - The transcript sentence being spoken.

---

## 8. Verification

Each phase has a checkpoint that prints a PASS/FAIL table (plan section 6):

```sh
pip install -e '.[dev,lrasd]'
./scripts/fetch_fixture.sh                       # CC BY 4.0 two-person video (Phase 1)
python scripts/make_conversation_fixture.py      # labelled 50 s conversation (Phase 2)
python scripts/make_podcast_fixture.py           # ~8 min scripted podcast + transcript (Phase 3)

clipper check phase1 fixtures/head-pose-face-detection-female-and-male.mp4 --expect tests/data/phase1_fixture.json
clipper check phase2 fixtures/conversation.mp4 --labels fixtures/conversation_labels.json \
    --negative fixtures/head-pose-face-detection-female-and-male.mp4
clipper check phase3 fixtures/podcast.mp4 --transcript fixtures/podcast_transcript.json
pytest
```

The Phase 3 check runs the whole pipeline with one command, from the long
source and its transcript, with the network blocked. It then verifies:

- the moment is sentence-aligned;
- the output format, duration and A/V sync;
- that the renderer, the plan and the overlay agree frame by frame and pixel
  by pixel;
- re-use of intermediate files;
- that a hosted LLM endpoint is refused and a local one works.

It finishes by reporting which moment was chosen and why.

The podcast fixture's words are scripted. The audio is real speech from the
talking-head clips, but it does not say those words. It tests the pipeline and
the scorer's reading of the text, not transcription.

---

## 9. Troubleshooting

| Symptom | Fix |
| --- | --- |
| `libEGL.so.1: cannot open shared object file` | Linux: `sudo apt install libegl1 libgles2`. |
| `ffprobe not found on PATH` | Install FFmpeg (section 1). |
| `could not download face_landmarker.task` | Offline or behind a proxy: seed the model cache by hand (section 1). |
| `refusing LLM endpoint ...` | Working as intended (plan 2.1). Use a local server, or opt in with `--allow-hosted-llm`. |
| `transcript runs to ...s but the video is ...s long` | The transcript belongs to another file, or its times are not in seconds from the start. |
| `the lrasd backend needs the optional extra` | `pip install -e '.[lrasd]'`. |
| `the lrasd backend needs an audio track` | Use `--asd heuristic` for silent video. |
| Clip frames the listener during long monologues | Check `debug.mp4`. If the raw strip is mostly grey, the speaker's lips are not visible enough (profile, small face). Try `--asd lrasd` or lower `speakers.min_score`. |
| The frame moves too slowly or too fast | `plan.transition_s`, `plan.max_speed`. |
| It cuts to short reactions | Raise `speakers.min_segment_s` or `speakers.switch_margin`. |
| A changed setting did not take effect | Stages re-run only when their inputs or settings change. Use `--from STAGE` to force. |

---

## 10. Licences and credits

- This pipeline: MIT.
- MediaPipe and the Face Landmarker model: Apache-2.0 (Google).
- LR-ASD code and weights: MIT. Junhua Liao et al., *LR-ASD: Lightweight and
  Robust Network for Active Speaker Detection*, IJCV 2025. The ONNX exports
  in `clipper/speakers/models/` are made with `scripts/export_lrasd_onnx.py`.
  See `LICENSE-LR-ASD.txt` there.
- Dependencies: numpy, OpenCV, PyYAML, jsonschema, onnxruntime,
  python_speech_features, scipy, all permissive. `clipper licenses` audits
  the installed environment and fails on AGPL/GPL.
- FFmpeg is called as an external program, never linked.
- Test fixtures (downloaded, not committed):
  `head-pose-face-detection-female-and-male.mp4` from intel-iot-devkit/sample-videos (CC BY 4.0),
  and talking-head example clips from OpenTalker/video-retalking.


---

## 11. Stream mode: clips from game streams

```sh
clipper stream --video vod.mp4 --chat chat.json [--transcript t.json] --work out/vod1 [--count 8] [--config profiles/<streamer>.yaml]
```

It writes `out/vod1/clips/clip_01.mp4`, `clip_02.mp4` and so on, best first,
all 1080×1920 and ready for Shorts. It also writes `signals.png` (why those
moments) and `layout_preview.png` (what goes where).

### 11.1 Light mode: give it the link, not the VOD

A multi-hour VOD is many GB, but finding the moments only needs the
**audio** (a 4-hour stream is ~100–200 MB) and the **chat** (a few MB).
With `--url`, `clipper` downloads exactly that, picks the moments, and then
downloads the **video of the chosen clips only** (each clip plus 2 s of
margin). In total that is typically 1–3 % of the VOD's size.

```sh
pip install -e '.[fetch]'      # yt-dlp (Unlicense) + chat-downloader (MIT)
clipper stream --url https://www.twitch.tv/videos/<id> --config profiles/theburntpeanut.yaml --work out/vod1
clipper stream --url "https://www.youtube.com/watch?v=<id>" --work out/vod2
```

- Chat replay is fetched automatically for Twitch and YouTube. For other
  sites, pass `--chat`. Without chat, audio (and a transcript) still work.
- The stream must be over (a VOD, not live).
- Everything downloads on your machine, straight from the platform.
- Downloads land in `WORK/source/` and are reused on re-runs. Delete the
  folder when you are done.
- `stream.fetch.max_height` (default 1080) caps the clip resolution.
- Clip boundaries are cut exactly (yt-dlp `--force-keyframes-at-cuts`). The
  check verifies that light-mode clips match full-VOD clips frame for frame.

### 11.2 Getting the VOD and the chat yourself

| Platform | VOD | Chat |
| --- | --- | --- |
| Twitch | [TwitchDownloader](https://github.com/lay295/TwitchDownloader) (MIT): `TwitchDownloaderCLI videodownload --id <vod id> -o vod.mp4` | `TwitchDownloaderCLI chatdownload --id <vod id> -o chat.json` |
| YouTube | [yt-dlp](https://github.com/yt-dlp/yt-dlp): `yt-dlp -f "bv*[height<=1080]+ba" -o vod.mp4 <url>` | [chat-downloader](https://github.com/xenova/chat-downloader) (MIT): `chat_downloader <url> --output chat.json` |
| Kick / other | any downloader | chat-downloader JSON, or a text log with `[h:mm:ss] user: message` lines |

The chat is optional, but it is the best signal there is, so use it when you
can. Twitch VODs expire, so download soon after the stream. A transcript is
also optional. With one you get burned-in captions and a "said: no way"
signal. Any local Whisper works (section 3).

### 11.3 How moments are found

One value per second over the whole VOD (`signals.png` shows them):

- **audio**: how much louder than the last two minutes the stream is. The
  streamer shouting, laughing or screaming.
- **chat**: messages per second against the chat's own normal rate. Messages
  with hype emotes (KEKW, OMEGALUL, "NO WAY", "CLIP IT", 💀 …) count double.
  Shifted back by the chat's reaction delay.
- **speech** (with a transcript): "no way", "let's go", "oh my god",
  laughter, "nie wierzę", "co to było" …

The weighted mix's peaks become clips:

- Each clip starts **12 s before** the peak, so the build-up is in.
- Each clip runs on while the hype lasts, between 15 and 55 s.
- Clips start and end on the quietest moment nearby, never mid-word.
- The best `count` clips that do not overlap are kept.

A loud explosion in the game with no reaction in chat, or a busy chat
during a sub train with a calm streamer, scores below real moments.

### 11.4 Layouts

| Layout | What you get | When `auto` picks it |
| --- | --- | --- |
| `stacked` | Facecam or avatar on top (35 % of the height), gameplay below, captions on the seam. | A face sits in the same spot across the VOD (facecam). A tall avatar or face is shown whole (`contain`) instead of being sliced. |
| `fit` | The whole stream picture, full width, on a blurred background. Shows everything wherever it is. | No facecam found. |
| `gameplay` | A 9:16 crop of the game only (crosshair centred). | Never; use `--layout gameplay`. |
| `follow` | The podcast pipeline's speaker-following crop. | The camera is the main picture (IRL / just chatting). |

The facecam is found automatically. Frames are sampled across the VOD, faces
are searched in overlapping tiles (a facecam face is small), and the cam's
rectangle is found from edges that stay put while the game changes, so the
gameplay crop avoids it.

If it guesses wrong, or a VTuber avatar is not seen as a face, set it once:

```sh
clipper stream-setup vod.mp4              # writes frame_grid.png with a 5 % grid
clipper stream --video vod.mp4 --chat chat.json --facecam 0.78,0.62,0.20,0.36
```

You can also put `facecam: [0.78, 0.62, 0.20, 0.36]` in the streamer's
profile.

### 11.5 Streamer profiles

`profiles/theburntpeanut.yaml` is a starting profile for TheBurntPeanut:

- extraction shooters, a big chat, and the peanut avatar;
- 8 clips per VOD, a longer pre-roll for fights, and extra emotes;
- layout `auto`, which uses `stacked` when the avatar is detected and `fit`
  otherwise. Fill in `facecam` once you have read his avatar's position off
  `frame_grid.png`.

Copy the profile for other streamers.

### 11.6 Re-running

The same fingerprint rules as podcast mode apply (section 4.1). Stages are
`fetch` (light mode), `signals`, `moments`, `segments` (light mode), `layout`
and `render`, and `--from STAGE` forces one.
For example, change the captions style and run `--from render`: the VOD's
audio is not decoded again.

### 11.7 Output files (stream mode)

| File | Contents |
| --- | --- |
| `source/audio.m4a`, `source/chat.json`, `source/seg_NN.mp4` | Light mode downloads: the stream's audio, its chat replay, and each clip's video with margins. |
| `fetch.json`, `segments.json` | Light mode: what was downloaded from where, and each segment's offset in the VOD. |
| `clips/clip_NN.mp4` | The clips, best first. H.264 + AAC, 1080×1920. |
| `clips/clip_NN.ass` | The captions burned into that clip (ASS subtitles; edit and `--from render` to re-burn). |
| `stream_signals.json` | Per second: audio, chat and speech scores, the combined hype, top chat tokens. Loudness per 0.1 s. |
| `stream_moments.json` | The chosen clips: peak, start/end, score, per-signal values, and reasons ("chat exploded (11.9× its usual rate; KEKW×10)"). |
| `stream_plan.json` | The layout, why it was chosen, the facecam detection details, and the source → output regions. |
| `stream_render_log.json` | The ffmpeg filter graph used for each clip. |
| `stream_run_log.json` | What ran, timings, and the clip list. |
| `signals.png` | The signals over the whole VOD, with the chosen clips shaded. |
| `layout_preview.png` | A source frame with the regions drawn, next to the resulting vertical frame. |

### 11.8 Stream options (`stream.*`)

| Key | Default | Meaning |
| --- | --- | --- |
| `layout` | `auto` | `auto`, `fit`, `stacked`, `gameplay` or `follow`. |
| `facecam` | null | `[x, y, w, h]` (fractions of the frame) of the facecam / avatar; enables `stacked`. |
| `cam_height` | 0.35 | `stacked`: share of the output height for the cam panel. |
| `cam_scale` | 2.6 | `stacked`, face found but no overlay border: cam panel height in face heights. |
| `gameplay_x` | 0.5 | Horizontal centre of the gameplay crop. |
| `gameplay_zoom` | 1.0 | `stacked`: 1.0 = full source height; 1.3 = 30 % closer. |
| `fit_y` / `fit_blur` | 0.6 / 30 | `fit`: vertical position of the picture, background blur. |
| `detect_samples` / `detect_min_presence` | 40 / 0.4 | Frames sampled for facecam detection; share of them a face must appear in, in the same place. |
| `signals.weights` | audio 0.45, chat 0.40, speech 0.15 | Mix of the signals (renormalised over the ones you have). |
| `signals.audio_baseline_s` | 120 | Rolling loudness baseline. |
| `signals.audio_excess_min_db` / `audio_excess_full_db` | 4 / 16 | dB above baseline where the audio score starts / is full. |
| `signals.audio_floor_db` | −50 | Quieter than this is never hype. |
| `signals.chat_delay_s` | 4 | Chat reacts this long after the moment. |
| `signals.chat_smooth_s` / `chat_baseline_s` | 5 / 300 | Chat-rate smoothing and baseline window. |
| `signals.chat_z_full` | 6 | Robust z-score at which chat counts fully. |
| `signals.hype_weight` | 2 | A message with a hype token counts this many messages. |
| `signals.hype_tokens` | Twitch emotes and slang | Emotes / words that mark hype (lower-case match). |
| `signals.smooth_s` | 3 | Smoothing of the combined signal. |
| `moments.count` | 5 | Clips per VOD (`--count`). |
| `moments.min_hype` | 0.35 | Peaks below this are not clips. |
| `moments.min_gap_s` | 45 | Peaks closer than this are one moment. |
| `moments.pre_roll_s` / `post_roll_s` | 12 / 8 | Seconds before / after the peak. |
| `moments.min_s` / `max_s` | 15 / 55 | Clip length bounds. |
| `captions.enabled` | true | Burn in captions when a transcript is given. |
| `captions.words_per_line` | 3 | Words shown at once. |
| `captions.font` / `size` / `outline` | DejaVu Sans / 78 / 6 | Look (any installed font). |
| `captions.color` / `highlight` | FFFFFF / FFE135 | Text colour and the colour of the word being spoken (RRGGBB). |
| `captions.uppercase` | true | All caps. |
| `captions.y` | null | Vertical position (fraction); null = where the layout leaves room. |
| `fetch.max_height` | 1080 | Light mode: download clip video at most this tall. |
| `output.*` | 1080×1920, H.264 CRF 20, AAC 192k, ≤ 60 fps | Encoding. |

### 11.9 Verification

```sh
python scripts/make_stream_fixture.py --cam human                   # 6 min synthetic stream, planted moments + decoys
python scripts/make_stream_fixture.py --cam avatar --duration 120   # VTuber-style drawn avatar
clipper check stream fixtures/stream_human.mp4 --chat fixtures/stream_human_chat.json \
    --transcript fixtures/stream_human_transcript.json --labels fixtures/stream_human_labels.json \
    --avatar fixtures/stream_avatar.mp4
```

The check verifies the following:

- all planted moments are found with their build-up;
- the decoys (a game explosion without reactions, a sub train) are not
  picked;
- the facecam overlay is found where it is, and the gameplay crop avoids it;
- every clip's format, duration and captions;
- the cam panel's pixels against the source;
- re-use of intermediate files;
- the avatar and `fit` cases;
- light mode on the same VOD (`--url` with a local file): the same moments,
  far less data, and clips aligned frame for frame with the full-VOD ones.

### 11.10 Clipping other people's streams

Clips are the streamer's content. Before posting, and especially before
monetising, check the streamer's rules for clippers (Discord, channel
descriptions) or ask. YouTube can also limit monetisation of "reused
content". Captions, a good choice of moments and your own edits help, but
they do not replace permission. Music playing on stream can trigger Content ID.
