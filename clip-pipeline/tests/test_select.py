import socket

import numpy as np
import pytest

from clipper import netguard
from clipper.config import load_config
from clipper.select.base import Candidate, generate, non_max_suppression
from clipper.select.heuristic import HeuristicScorer
from clipper.select.llm import HostedEndpointRefused, LLMScorer, check_endpoint, is_local
from clipper.select.run import clip_range
from clipper.transcript import import_file, is_sentence_end, sentences


def seg(start, end, text, speaker="A"):
    words, t = [], start
    toks = text.split()
    step = (end - start) / len(toks)
    for w in toks:
        words.append({"start": round(t, 3), "end": round(t + step * 0.9, 3), "word": w})
        t += step
    return {"start": start, "end": end, "text": text, "speaker": speaker, "words": words}


def doc(*segments):
    return {"schema_version": "1.0", "kind": "transcript", "language": "en", "segments": list(segments)}


def test_sentence_ends_and_abbreviations():
    assert is_sentence_end("there.") and is_sentence_end("why?") and is_sentence_end('"really!"')
    assert not is_sentence_end("Dr.") and not is_sentence_end("e.g.") and not is_sentence_end("U.S.") and not is_sentence_end("so")


def test_sentences_split_on_punctuation_and_speaker_change():
    d = doc(seg(0, 4, "Dr. Smith said hello. Then he left"), seg(4.5, 6, "Did he?", "B"))
    s = sentences(d)
    assert [x.text for x in s] == ["Dr. Smith said hello.", "Then he left", "Did he?"]
    assert s[1].terminal is False and s[0].terminal and s[2].speaker == "B"


def test_segments_without_words_are_interpolated():
    d = doc({"start": 0.0, "end": 10.0, "text": "One two. Three four five six."})
    s = sentences(d)
    assert len(s) == 2 and s[0].interpolated and 0 < s[0].end < s[1].start < 10


def test_candidates_are_whole_sentences_within_bounds():
    segs = [seg(k * 5.0, k * 5.0 + 4.5, f"Sentence number {k} is here.") for k in range(40)]
    s = sentences(doc(*segs))
    cands, _ = generate(s, 30, 60)
    starts, ends = {x.start for x in s}, {x.end for x in s}
    assert cands and all(c.start in starts and c.end in ends and 30 <= c.duration <= 60 for c in cands)


def test_heuristic_prefers_standalone_hook_over_back_reference():
    cfg = load_config()
    good = [seg(0, 4, "Why do most people fail at this?"), seg(4.5, 30, "Because they measure the wrong thing every single day."),
            seg(30.5, 40, "So the lesson is simple. Measure outcomes.")]
    bad = [seg(0, 4, "And that is why it happened."), seg(4.5, 30, "Because they measure the wrong thing every single day."),
           seg(30.5, 40, "So the lesson is simple. Measure outcomes.")]
    scores = []
    for d in (good, bad):
        s = sentences(doc(*d))
        cands, _ = generate(s, 30, 60)
        ranked = HeuristicScorer(cfg).rank(cands, s, {"language": "en", "sentence_loudness": None})
        full = next(c for c in ranked if c.first == 0 and c.last == len(s) - 1)
        scores.append(full)
    assert scores[0].features["standalone"] > scores[1].features["standalone"]
    assert scores[0].features["hook"] > scores[1].features["hook"]
    assert scores[0].score > scores[1].score


def test_non_max_suppression_keeps_distinct_moments():
    a = Candidate(0, 3, 0, 60, "a", score=0.9)
    b = Candidate(0, 4, 0, 65, "b", score=0.85)
    c = Candidate(10, 14, 100, 160, "c", score=0.5)
    assert [x.text for x in non_max_suppression([a, b, c], 5, 0.5)] == ["a", "c"]


def test_clip_padding_never_reaches_neighbour_sentences():
    s = sentences(doc(seg(0, 4.9, "First one."), seg(5.0, 40, "Middle sentence goes on."), seg(40.1, 45, "Last one.")))
    start, end = clip_range(s, 1, 1, 50.0, 0.5, 0.5)
    assert s[0].end <= start <= s[1].start and s[1].end <= end <= s[2].start


def test_llm_endpoint_guard():
    assert is_local("http://127.0.0.1:11434") and is_local("http://localhost:8080/v1") and is_local("http://[::1]:1234")
    assert not is_local("https://api.openai.com") and not is_local("http://192.168.1.10:11434")
    with pytest.raises(HostedEndpointRefused):
        check_endpoint("https://api.openai.com", allow_hosted=False)
    check_endpoint("https://api.openai.com", allow_hosted=True)  # explicit opt-in
    cfg = load_config()
    cfg["select"]["llm"]["endpoint"] = "https://api.example.com"
    with pytest.raises(HostedEndpointRefused):
        LLMScorer(cfg)


def test_llm_reply_parsing():
    parsed = LLMScorer._parse('sure! {"ratings": [{"id": 0, "score": 7, "reason": "ok"}, {"id": 9, "score": 3}]}', 2)
    assert parsed == {0: (7.0, "ok")}
    assert LLMScorer._parse("no json here", 2) == {}


def test_netguard_blocks_outbound_but_not_loopback():
    netguard.install()
    srv = socket.socket()
    srv.bind(("127.0.0.1", 0))
    srv.listen(1)
    c = socket.socket()
    c.connect(srv.getsockname())  # loopback allowed
    c.close()
    srv.close()
    with pytest.raises(netguard.NetworkBlocked):
        socket.socket().connect(("93.184.216.34", 80))


def test_import_srt_and_vtt(tmp_path):
    srt = tmp_path / "a.srt"
    srt.write_text("1\n00:00:01,000 --> 00:00:03,500\nHOST: Hello there.\n\n2\n00:00:04,000 --> 00:00:06,000\nGUEST: Hi!\n")
    d = import_file(srt, "en")
    assert [(s["speaker"], s["text"], s["start"]) for s in d["segments"]] == [("HOST", "Hello there.", 1.0), ("GUEST", "Hi!", 4.0)]
    vtt = tmp_path / "a.vtt"
    vtt.write_text("WEBVTT\n\n00:01.000 --> 00:02.000\n<v Ann>Good morning.\n")
    assert import_file(vtt)["segments"][0] == {"start": 1.0, "end": 2.0, "text": "Good morning.", "speaker": "Ann"}


def test_import_whisperx_json(tmp_path):
    import json
    p = tmp_path / "w.json"
    p.write_text(json.dumps({"language": "pl", "segments": [{"start": 0.0, "end": 2.0, "text": " Dzień dobry.", "speaker": "SPEAKER_00",
                                                             "words": [{"word": "Dzień", "start": 0.0, "end": 0.5},
                                                                       {"word": "2025", "start": None, "end": None},
                                                                       {"word": "dobry.", "start": 0.6, "end": 1.0}]}]}))
    d = import_file(p)
    assert d["language"] == "pl" and len(d["segments"][0]["words"]) == 2 and d["segments"][0]["speaker"] == "SPEAKER_00"
