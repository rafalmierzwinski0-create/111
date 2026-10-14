import numpy as np

from clipper.plan.crop import clamp_speed, crop_size, eased_path, gaussian_smooth, plan_axis


def test_crop_size_is_9_16_full_height():
    w, h = crop_size(1920, 1080, 9, 16, 1.0)
    assert h == 1080 and abs(w - 607.5) < 1e-9
    w, h = crop_size(1920, 1080, 9, 16, 1.5)
    assert abs(h - 720) < 1e-9 and abs(w / h - 9 / 16) < 1e-9


def test_crop_size_never_exceeds_a_narrow_source():
    w, h = crop_size(500, 1080, 9, 16, 1.0)
    assert w == 500 and abs(h - 500 * 16 / 9) < 1e-9


def test_eased_transition_glides_and_respects_speed():
    fps = 30
    path = eased_path([(30, 1000.0)], 120, fps, transition_s=0.6, max_speed_px=1.5 * 1920, start_value=200.0)
    assert path[29] == 200.0 and abs(path[-1] - 1000.0) < 1e-9
    v = np.diff(path) * fps
    assert np.abs(v).max() <= 1.5 * 1920 + 1e-6
    # ease-in: the first step is small compared to the peak step
    steps = np.abs(np.diff(path[29:60]))
    assert steps[0] < 0.2 * steps.max()


def test_slow_speed_limit_stretches_the_transition():
    fps = 30
    path = eased_path([(0, 1000.0)], 300, fps, transition_s=0.6, max_speed_px=200.0, start_value=0.0)
    assert np.abs(np.diff(path)).max() * fps <= 200.0 + 1e-6


def test_dead_zone_ignores_small_target_motion():
    n = 200
    target = 500 + 20 * np.sin(np.arange(n) / 5)  # jitter well inside a 70 px dead zone
    path = plan_axis(target, np.zeros(n, int), fps=30, dead_zone_px=70, transition_s=0.6, max_speed_px=2880, smooth_s=0.08)
    assert np.ptp(path) < 1e-6


def test_speaker_change_moves_the_camera_smoothly():
    n = 120
    target = np.where(np.arange(n) < 60, 300.0, 1300.0)
    seg = np.where(np.arange(n) < 60, 0, 1)
    path = plan_axis(target, seg, fps=30, dead_zone_px=70, transition_s=0.6, max_speed_px=2880, smooth_s=0.08)
    assert abs(path[0] - 300) < 1 and abs(path[-1] - 1300) < 1
    v = np.diff(path) * 30
    assert v.max() <= 2880 + 1e-6 and v.min() >= -1e-6  # monotone glide, no overshoot
    acc = np.abs(np.diff(v)) * 30
    assert acc.max() / 1920 < 8.0


def test_smoothing_and_clamp_helpers():
    x = np.r_[np.zeros(10), np.full(10, 100.0)]
    assert np.abs(np.diff(clamp_speed(x, 10.0))).max() <= 10.0
    y = gaussian_smooth(x, 2.0)
    assert abs(y[0]) < 1e-9 and abs(y[-1] - 100.0) < 1e-9 and np.all(np.diff(y) >= -1e-9)
