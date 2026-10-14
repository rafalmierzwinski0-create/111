import numpy as np

from clipper.detect.shots import ShotDetector


def frame(color):
    img = np.zeros((90, 160, 3), np.uint8)
    img[:] = color
    return img


def test_detects_hard_cut_and_ignores_static_frames():
    det = ShotDetector(threshold=27.0, min_shot_s=0.5)
    cuts = []
    for k in range(60):
        t = k / 30
        img = frame((40, 120, 200)) if k < 30 else frame((200, 60, 30))
        if det.update(t, img):
            cuts.append(t)
    assert cuts == [1.0]


def test_min_shot_length_suppresses_flashes():
    det = ShotDetector(threshold=27.0, min_shot_s=0.5)
    colors = [(40, 120, 200)] * 30 + [(200, 60, 30)] + [(40, 120, 200)] * 5
    cuts = [k for k, c in enumerate(colors) if det.update(k / 30, frame(c))]
    assert cuts == [30]  # the flash back 1 frame later is within min_shot_s


def test_noise_is_not_a_cut():
    rng = np.random.default_rng(0)
    det = ShotDetector(threshold=27.0, min_shot_s=0.5)
    base = rng.integers(0, 255, (90, 160, 3), dtype=np.uint8)
    for k in range(30):
        noisy = np.clip(base.astype(int) + rng.integers(-6, 7, base.shape), 0, 255).astype(np.uint8)
        assert not det.update(k / 30, noisy)
