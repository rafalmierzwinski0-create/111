import json
from pathlib import Path

import numpy as np
import pytest

from clipper.config import load_config
from clipper.stream import captions, layout, moments, signals
from clipper.stream.chat import load_chat
from clipper.transcript import Word


def test_chat_formats(tmp_path):
    td = tmp_path / "td.json"
    td.write_text(json.dumps({"comments": [{"content_offset_seconds": 12.5, "message": {"body": "KEKW"}},
                                           {"content_offset_seconds": 3.0, "message": {"body": "hi"}}]}))
    assert load_chat(td) == [(3.0, "hi"), (12.5, "KEKW")]
    cd = tmp_path / "cd.json"
    cd.write_text(json.dumps([{"time_in_seconds": 7, "message": "W"}]))
    assert load_chat(cd) == [(7.0, "W")]
    txt = tmp_path / "chat.txt"
    txt.write_text("[0:01:05] someone: NO WAY\n00:00:10 other: hello there\ngarbage line\n")
    assert load_chat(txt) == [(10.0, "hello there"), (65.0, "NO WAY")]
    bad = tmp_path / "bad.txt"
    bad.write_text("nothing here")
    with pytest.raises(ValueError):
        load_chat(bad)


def test_chat_signal_finds_burst_and_shifts_by_delay():
    p = load_config()["stream"]["signals"]
    rng = np.random.default_rng(0)
    msgs = [(float(t), "hello") for t in np.sort(rng.uniform(0, 600, 600))]
    msgs += [(304.0 + k * 0.1, "KEKW") for k in range(80)]
    score, rate, tops = signals.chat_signal(sorted(msgs), 600, p)
    peak = int(np.argmax(score))
    assert 298 <= peak <= 308 and score[peak] == 1.0
    assert score[100] < 0.3 and tops[300]["kekw"] > 0


def test_rolling_median_and_combine():
    x = np.r_[np.zeros(50), np.full(5, 10.0), np.zeros(50)]
    med = signals.rolling_median(x, 21)
    assert med.max() == 0.0
    c = signals.combine({"audio": np.ones(10), "chat": None, "speech": np.zeros(10)}, {"audio": 0.45, "chat": 0.4, "speech": 0.15}, 1)
    assert np.allclose(c, 0.45 / 0.6)
    with pytest.raises(ValueError):
        signals.combine({"audio": None, "chat": None}, {"audio": 1, "chat": 1}, 1)


def test_peaks_windows_and_snapping():
    p = dict(load_config()["stream"]["moments"], count=2)
    hype = np.zeros(400)
    for c, v in ((100, 0.9), (104, 0.8), (250, 0.7), (330, 0.3)):
        hype[c - 3:c + 4] = np.maximum(hype[c - 3:c + 4], v * np.exp(-np.abs(np.arange(-3, 4)) / 2))
    loud = np.full(4000, -20.0)
    loud[880:885] = -60.0  # a quiet spot near 100 - 12 s
    sig = {"combined": hype, "audio": hype, "audio_excess": hype * 20, "loudness_db": loud}
    words = [Word(87.0, 89.5, "word")]
    clips = moments.pick(sig, p, 400.0, words)
    assert [c["rank"] for c in clips] == [1, 2]
    first = clips[0]
    assert first["start"] < 100 < first["end"] and first["peak"] == 100.5
    assert not (87.0 < first["start"] < 89.5)  # never inside a word
    assert clips[1]["start"] < 250 < clips[1]["end"]
    assert all(c["end"] - c["start"] >= p["min_s"] - 2.5 for c in clips)


def test_find_facecam_needs_a_fixed_position():
    fixed = [[[0.8, 0.6, 0.07, 0.14]] for _ in range(10)]
    box, info = layout.find_facecam(fixed)
    assert box is not None and info["presence"] == 1.0
    moving = [[[0.1 * k, 0.3, 0.07, 0.14]] for k in range(10)]
    assert layout.find_facecam(moving)[0] is None


def test_overlay_box_from_persistent_edges():
    edges = np.full((540, 960), 2.0, np.float32)
    x0, y0, x1, y1 = 700, 280, 940, 520
    edges[y0:y1, x0] = edges[y0:y1, x1] = 200
    edges[y0, x0:x1] = edges[y1, x0:x1] = 200
    face = [780 / 960, 340 / 540, 70 / 960, 90 / 540]
    box, info = layout.overlay_box(face, edges)
    assert info["overlay"] == "border found"
    assert abs(box[0] - x0 / 960) < 0.01 and abs(box[2] - (x1 - x0) / 960) < 0.015


@pytest.mark.parametrize("name", ["fit", "stacked", "gameplay"])
def test_layout_regions_stay_inside_the_frame(name):
    cfg = load_config()
    src = {"width": 1920, "height": 1080}
    plan = layout.plan_layout(src, cfg["stream"], name, [0.8, 0.6, 0.07, 0.14], None, [0.73, 0.52, 0.25, 0.44])
    for r in plan["regions"]:
        x, y, w, h = r["src"]
        assert x >= 0 and y >= 0 and x + w <= 1920.5 and y + h <= 1080.5
        dx, dy, dw, dh = r["dst"]
        assert dx + dw <= 1080 and dy + dh <= 1920
        if name != "fit":
            assert abs(w / h - dw / dh) < 0.01
    if name == "stacked":
        cam, game = plan["regions"]
        assert cam["src"][0] >= 0.73 * 1920 - 1 and cam["src"][0] + cam["src"][2] <= (0.73 + 0.25) * 1920 + 1
        assert game["src"][0] + game["src"][2] <= 0.73 * 1920 + 1  # gameplay does not show the cam overlay


def test_choose_layout():
    assert layout.choose_layout("auto", None, None)[0] == "fit"
    assert layout.choose_layout("auto", [0.8, 0.6, 0.07, 0.14], None)[0] == "stacked"
    assert layout.choose_layout("auto", [0.3, 0.1, 0.3, 0.5], None)[0] == "follow"
    assert layout.choose_layout("auto", None, [0.7, 0.5, 0.2, 0.3])[0] == "stacked"
    assert layout.choose_layout("gameplay", [0.8, 0.6, 0.07, 0.14], None)[0] == "gameplay"


def test_captions_highlight_and_timing():
    c = load_config()["stream"]["captions"]
    words = [Word(10.0, 10.3, "no"), Word(10.4, 10.7, "way!"), Word(10.8, 11.2, "oh"), Word(11.3, 11.6, "my"), Word(20, 20.4, "late")]
    ass = captions.build_ass(words, 9.0, 15.0, 1080, 1920, c, 0.35)
    events = [ln for ln in ass.splitlines() if ln.startswith("Dialogue")]
    assert len(events) == 4                       # one event per word, the late word is outside the clip
    assert "0:00:01.00" in events[0]              # relative to the clip start
    assert "NO" in events[0] and "OH" in events[0] and "MY" not in events[0]  # 3 words per line, then a new line
    assert "MY" in events[3] and "NO" not in events[3]
    assert "\\cHFFE135" not in events[0] and "\\c&H0035E1FF}NO" in events[0]  # the spoken word is highlighted
    assert captions.build_ass(words, 30, 40, 1080, 1920, c, 0.35) is None


def test_tall_avatar_uses_contain_mode():
    cfg = load_config()
    src = {"width": 1920, "height": 1080}
    plan = layout.plan_layout(src, cfg["stream"], "stacked", [0.78, 0.60, 0.15, 0.31], None, [0.73, 0.52, 0.245, 0.46])
    cam = plan["regions"][0]
    assert cam["mode"] == "contain" and abs(cam["src"][3] - 0.46 * 1080) < 2  # the whole avatar box, not a wide slice
    small = layout.plan_layout(src, cfg["stream"], "stacked", [0.83, 0.60, 0.07, 0.15], None, [0.73, 0.52, 0.25, 0.44])
    assert "mode" not in small["regions"][0]


def test_ytdlp_fetcher_commands(monkeypatch, tmp_path):
    from clipper.stream import fetch
    calls = []

    def fake_run(cmd, capture_output, text):
        calls.append(cmd)
        if "-J" in cmd:
            out = json.dumps({"id": "v1", "title": "t", "duration": 14400.0, "extractor_key": "TwitchVod"})
        else:
            target = cmd[cmd.index("-o") + 1].replace(".%(ext)s", ".m4a")
            Path(target).write_bytes(b"x")
            out = ""
        return type("P", (), {"returncode": 0, "stdout": out, "stderr": ""})()

    monkeypatch.setattr(fetch.subprocess, "run", fake_run)
    monkeypatch.setattr(fetch, "_ytdlp", lambda: ["yt-dlp"])
    f = fetch.YtDlpFetcher("https://www.twitch.tv/videos/1", max_height=720)
    assert f.info()["duration"] == 14400.0
    f.audio(tmp_path / "audio.m4a")
    assert "-x" in calls[-1] and "ba/b" in calls[-1]
    f.section(3600.5, 3650.25, tmp_path / "seg.mp4")
    cmd = calls[-1]
    assert cmd[cmd.index("--download-sections") + 1] == "*3600.50-3650.25"
    assert "--force-keyframes-at-cuts" in cmd and "bv*[height<=720]+ba/b[height<=720]" in cmd
    assert cmd[-1] == "https://www.twitch.tv/videos/1"


def test_make_fetcher_picks_local_for_files(tmp_path):
    from clipper.stream.fetch import LocalFetcher, YtDlpFetcher, make_fetcher
    f = tmp_path / "vod.mp4"
    f.write_bytes(b"")
    assert isinstance(make_fetcher(str(f)), LocalFetcher)
    assert isinstance(make_fetcher("https://youtube.com/watch?v=x"), YtDlpFetcher)


def test_twitch_chat_offset_paging(monkeypatch):
    from clipper.stream import twitch_chat
    # 3 messages per second for 100 s; pages return 10 comments starting at the requested second
    data = [(f"id{s}_{k}", s + k / 3) for s in range(100) for k in range(3)]

    def fake_page(video, offset, attempts=5):
        rows = [d for d in data if d[1] >= offset][:10]
        return {"edges": [{"node": {"id": i, "contentOffsetSeconds": int(t), "message": {"fragments": [{"text": "KEKW"}]}}}
                          for i, t in rows]}

    monkeypatch.setattr(twitch_chat, "_page", fake_page)
    stats = {}
    got = twitch_chat._chunk("1", 20, 50, stats)
    assert len(got) == 90 and all(20 <= t < 50 for t, _ in got.values()) and not stats
    assert twitch_chat.vod_id("https://www.twitch.tv/videos/2900094401") == "2900094401"
    assert twitch_chat.vod_id("https://www.youtube.com/watch?v=x") is None
