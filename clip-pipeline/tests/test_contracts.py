import copy

import pytest

from clipper import contracts
from clipper.config import load_config
from clipper.detect.landmarks import GROUPS, LIPS_INNER, LIPS_OUTER, stored_indices
from clipper.detect.run import frame_step, summarize_tracks


def minimal_detections():
    indices = stored_indices("subset")
    face = {"track_id": 0, "bbox": [0.1, 0.2, 0.1, 0.2], "landmarks": [[0.15, 0.3, 0.0]] * len(indices),
            "blendshapes": {"jawOpen": 0.1}, "pose": {"yaw": 1.0, "pitch": 0.0, "roll": 0.0}}
    frames = [{"i": k, "t": k / 30, "shot": 0, "faces": [dict(face)]} for k in range(30)]
    return {
        "schema_version": "1.0", "kind": "detections", "fingerprint": "0" * 64,
        "source": {"path": "/x.mp4", "size_bytes": 1, "mtime": 0.0, "width": 1920, "height": 1080, "fps": 30.0,
                   "duration": 1.0, "frame_count": 30, "has_audio": True},
        "range": {"start": 0.0, "end": 1.0},
        "analysis": {"fps": 30.0, "frame_step": 1, "detector": "mediapipe.face_landmarker", "mediapipe_version": "x",
                     "model": {"name": "m", "sha256": "a" * 64}, "num_faces": 4},
        "landmark_spec": {"topology": "mediapipe_face_mesh_478", "coords": "normalized_xyz", "indices": indices,
                          "groups": GROUPS, "blendshapes": ["jawOpen"]},
        "shots": [{"index": 0, "start": 0.0, "end": 1.0}],
        "frames": frames,
        "tracks": summarize_tracks(frames, 30.0, 0.5),
    }


def test_lip_groups_have_40_unique_points():
    assert len(LIPS_OUTER) == len(set(LIPS_OUTER)) == 20
    assert len(LIPS_INNER) == len(set(LIPS_INNER)) == 20
    assert not set(LIPS_OUTER) & set(LIPS_INNER)


def test_subset_contains_every_group_and_full_is_478():
    sub = stored_indices("subset")
    assert all(i in sub for g in GROUPS.values() for i in g)
    assert stored_indices("full") == list(range(478))
    with pytest.raises(ValueError):
        stored_indices("bogus")


def test_landmark_group_maps_to_rows():
    doc = minimal_detections()
    rows = contracts.landmark_group(doc, "lips_inner")
    assert [doc["landmark_spec"]["indices"][r] for r in rows] == LIPS_INNER


def test_schema_accepts_valid_and_rejects_missing_lips(tmp_path):
    doc = minimal_detections()
    path = contracts.write(copy.deepcopy(doc), tmp_path / "d.json", "detections")
    assert contracts.read(path, "detections")["frames"][0]["faces"][0]["track_id"] == 0
    broken = copy.deepcopy(doc)
    broken["frames"][3]["faces"][0]["landmarks"] = [[0.1, 0.1, 0.0]] * 10
    with pytest.raises(contracts.ContractError):
        contracts.validate(broken, "detections")
    wrong_kind = copy.deepcopy(doc)
    wrong_kind["kind"] = "speakers"
    with pytest.raises(contracts.ContractError):
        contracts.validate(wrong_kind, "detections")


def test_summarize_tracks_marks_short_tracks_spurious():
    frames = [{"i": k, "t": k / 10, "shot": 0, "faces": [{"track_id": 0, "bbox": [0, 0, 0.1, 0.1]}]} for k in range(20)]
    frames[5]["faces"].append({"track_id": 1, "bbox": [0.5, 0, 0.1, 0.1]})
    tracks = {t["track_id"]: t for t in summarize_tracks(frames, 10.0, 0.5)}
    assert not tracks[0]["spurious"] and tracks[0]["coverage"] == 1.0
    assert tracks[1]["spurious"] and tracks[1]["num_frames"] == 1


def test_frame_step_caps_analysis_rate():
    assert frame_step(29.97, 30) == 1
    assert frame_step(30.0, 30) == 1
    assert frame_step(60.0, 30) == 2
    assert frame_step(50.0, 30) == 2
    assert frame_step(12.0, 30) == 1


def test_fingerprint_changes_with_settings():
    cfg = load_config()
    a = contracts.fingerprint("detections", cfg["detect"])
    cfg["detect"]["num_faces"] = 2
    assert a != contracts.fingerprint("detections", cfg["detect"])


def test_unknown_config_key_is_rejected(tmp_path):
    p = tmp_path / "c.yaml"
    p.write_text("detect:\n  num_face: 2\n")
    with pytest.raises(ValueError, match="detect.num_face"):
        load_config(p)


def test_speaker_segment_kind_and_speaker_must_agree():
    seg = {"index": 0, "start": 0.0, "end": 1.0, "speaker": None, "kind": "speaker"}
    doc = {"schema_version": "1.0", "kind": "speakers", "fingerprint": "0" * 64,
           "source": minimal_detections()["source"], "range": {"start": 0.0, "end": 1.0},
           "inputs": {"detections": {"path": "d.json", "fingerprint": "0" * 64}},
           "backend": {"name": "heuristic", "version": "1", "params": {}},
           "frames": [{"i": 0, "t": 0.0, "scores": {"0": 0.9}, "raw_active": 0}],
           "segments": [seg], "tracks": []}
    with pytest.raises(contracts.ContractError):
        contracts.validate(doc, "speakers")
    seg["speaker"] = 0
    contracts.validate(doc, "speakers")
    seg["kind"] = "hold"
    with pytest.raises(contracts.ContractError):
        contracts.validate(doc, "speakers")
