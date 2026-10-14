"""Stream mode: clips from long game/IRL stream VODs.

Unlike podcast mode, the best moments of a stream are reactions, not
sentences: the streamer gets loud, chat explodes, someone says "no way".
Stream mode finds those peaks in cheap whole-VOD signals (audio loudness,
chat activity, optional transcript), cuts several short clips around them,
and lays each out for a vertical short: facecam on top and gameplay below,
gameplay only, or following the speaker for camera-only streams.
"""
