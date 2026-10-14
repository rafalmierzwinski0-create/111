import numpy as np

from clipper.render.renderer import crop_frame


def test_crop_frame_identity_and_subpixel_shift():
    img = np.tile(np.arange(200, dtype=np.uint8), (100, 1))[:, :, None].repeat(3, axis=2)
    same = crop_frame(img, 50.0, 10.0, 60.0, 40.0, 60, 40)
    assert np.array_equal(same, img[10:50, 50:110])
    half = crop_frame(img, 50.5, 10.0, 60.0, 40.0, 60, 40)
    assert np.allclose(half[:, 1:-1, 0].astype(float), img[10:50, 51:109, 0] + 0.5, atol=1.0)


def test_crop_frame_upscale_and_downscale_shapes():
    img = np.random.default_rng(0).integers(0, 255, (1080, 1920, 3), dtype=np.uint8)
    assert crop_frame(img, 600.25, 0.0, 607.5, 1080.0, 1080, 1920).shape == (1920, 1080, 3)
    assert crop_frame(img, 100.0, 0.0, 1215.0, 1080.0, 540, 480).shape == (480, 540, 3)
