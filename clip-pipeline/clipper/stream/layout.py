"""Vertical layouts for stream clips, and facecam detection.

Layouts (output 1080x1920 by default):
  fit       the whole stream frame, full width, centred on a blurred, zoomed
            copy of itself. Shows everything (game, HUD, facecam or VTuber
            avatar wherever it sits), so it needs no detection. Default when
            no facecam is known.
  stacked   facecam / avatar on top (stream.cam_height of the output), a
            gameplay crop below. Needs the facecam region: detected
            automatically for real faces, or set once with stream.facecam
            (VTuber avatars are often not detected as faces).
  gameplay  a 9:16 crop of the gameplay only (centre by default).
  follow    for camera-only streams (IRL / just chatting): the podcast
            pipeline's speaker-following crop.

Facecam detection samples frames across the VOD and looks for a face that
stays in the same place in most of them (a facecam overlay), as opposed to
faces that move around (game characters, IRL camera).
"""

from __future__ import annotations

import logging

import cv2
import numpy as np

from clipper import models
from clipper.video import probe

log = logging.getLogger(__name__)


EDGE_WIDTH = 960  # resolution of the persistent-edge map


def sample_faces(video_path: str | list[str], cfg: dict, samples: int = 40) -> tuple[list[list[list[float]]], np.ndarray | None, np.ndarray | None]:
    """Face boxes (normalised x, y, w, h) in `samples` frames spread over the video, one sample frame,
    and the persistent-edge map (median gradient over the samples, EDGE_WIDTH wide)."""
    from mediapipe.tasks.python import BaseOptions, vision
    import mediapipe as mp

    paths = video_path if isinstance(video_path, list) else [video_path]
    per = max(3, samples // len(paths))
    plan = []  # (path, time): spread over each file (light mode: over each downloaded clip)
    for path in paths:
        d = probe(path)["duration"]
        plan += [(path, float(t)) for t in np.linspace(d * 0.05, d * 0.95, per)]
    model = models.ensure(models.FACE_LANDMARKER, cfg)
    opts = vision.FaceLandmarkerOptions(base_options=BaseOptions(model_asset_path=str(model)),
                                        running_mode=vision.RunningMode.IMAGE, num_faces=4,
                                        min_face_detection_confidence=0.4, min_face_presence_confidence=0.4)
    caps = {path: cv2.VideoCapture(str(path)) for path in paths}
    out, example, grads = [], None, []
    with vision.FaceLandmarker.create_from_options(opts) as lm:
        for path, t in plan:
            cap = caps[path]
            cap.set(cv2.CAP_PROP_POS_MSEC, t * 1000)
            ok, img = cap.read()
            if not ok:
                continue
            if example is None or len(out) == len(plan) // 2:
                example = img
            out.append(_faces_tiled(lm, img))
            small = cv2.cvtColor(cv2.resize(img, (EDGE_WIDTH, round(img.shape[0] * EDGE_WIDTH / img.shape[1]))),
                                 cv2.COLOR_BGR2GRAY).astype(np.float32)
            grads.append(cv2.magnitude(cv2.Sobel(small, cv2.CV_32F, 1, 0), cv2.Sobel(small, cv2.CV_32F, 0, 1)))
    for cap in caps.values():
        cap.release()
    edges = np.median(np.stack(grads), axis=0) if len(grads) >= 5 else None
    return out, example, edges


def overlay_box(face: list[float], edges: np.ndarray | None) -> tuple[list[float] | None, dict]:
    """The facecam overlay's rectangle around a face, from edges that stay put while the game changes.

    Gameplay moves, so its edges average out in the median gradient; an
    overlay's border (or the boundary between cam picture and game) does not.
    From the face box, look outwards on each side for the strongest
    persistent straight line. Returns normalised (x, y, w, h), or None when
    fewer than three sides are found.
    """
    if edges is None:
        return None, {"overlay": "no edge map"}
    h, w = edges.shape
    fx0, fy0 = int(face[0] * w), int(face[1] * h)
    fx1, fy1 = int((face[0] + face[2]) * w), int((face[1] + face[3]) * h)
    fw, fh = fx1 - fx0, fy1 - fy0
    thr = max(8.0, 4.0 * float(np.median(edges)))
    ra, rb = max(0, fy0 - fh // 2), min(h, fy1 + fh // 2)
    ca, cb = max(0, fx0 - fw // 2), min(w, fx1 + fw // 2)

    def strongest(profile: np.ndarray, positions: range):
        best, best_v = None, thr
        for x in positions:
            v = float(profile[x])
            if v > best_v:
                best, best_v = x, v
        return best

    cols = edges[ra:rb].mean(axis=0)
    rows = edges[:, ca:cb].mean(axis=1)
    left = strongest(cols, range(max(0, fx0 - 3 * fw), max(0, fx0 - fw // 4)))
    right = strongest(cols, range(min(w - 1, fx1 + fw // 4), min(w, fx1 + 3 * fw)))
    top = strongest(rows, range(max(0, fy0 - 2 * fh), max(0, fy0 - fh // 4)))
    bottom = strongest(rows, range(min(h - 1, fy1 + fh // 4), min(h, fy1 + 2 * fh)))
    found = {k: v is not None for k, v in (("left", left), ("right", right), ("top", top), ("bottom", bottom))}
    if sum(found.values()) < 3:
        return None, {"overlay": "border not found", "sides": found}
    # A missing side (cam flush with the frame edge, or no border there): use the frame edge or mirror the opposite side.
    left = left if left is not None else max(0, fx0 - ((right - fx1) if right is not None else fw))
    right = right if right is not None else min(w - 1, fx1 + (fx0 - left))
    top = top if top is not None else max(0, fy0 - ((bottom - fy1) if bottom is not None else fh))
    bottom = bottom if bottom is not None else min(h - 1, fy1 + (fy0 - top))
    inset = 3  # stay inside the border line
    box = [(left + inset) / w, (top + inset) / h, (right - left - 2 * inset) / w, (bottom - top - 2 * inset) / h]
    return [round(v, 4) for v in box], {"overlay": "border found", "sides": found}


def _detect(lm, img: np.ndarray) -> list[list[float]]:
    import mediapipe as mp
    rgb = np.ascontiguousarray(cv2.cvtColor(img, cv2.COLOR_BGR2RGB))
    res = lm.detect(mp.Image(image_format=mp.ImageFormat.SRGB, data=rgb))
    boxes = []
    for face in res.face_landmarks:
        xs, ys = [p.x for p in face], [p.y for p in face]
        boxes.append([min(xs), min(ys), max(xs) - min(xs), max(ys) - min(ys)])
    return boxes


def _faces_tiled(lm, img: np.ndarray) -> list[list[float]]:
    """Faces in the whole frame and in overlapping tiles.

    Face Landmarker's detector only finds faces that fill a decent part of its
    input; a facecam face is often ~10 % of the frame width. In a tile half
    the frame's size the same face is twice as large relative to the input.
    Boxes are returned in whole-frame fractions, duplicates merged.
    """
    H, W = img.shape[:2]
    boxes = _detect(lm, img)
    tw, th = W // 2, H * 2 // 3
    for ty in (0, H - th):
        for tx in (0, (W - tw) // 2, W - tw):
            for b in _detect(lm, img[ty:ty + th, tx:tx + tw]):
                boxes.append([(tx + b[0] * tw) / W, (ty + b[1] * th) / H, b[2] * tw / W, b[3] * th / H])
    merged: list[list[float]] = []
    for b in sorted(boxes, key=lambda b: -b[2] * b[3]):
        if all(_iou(b, m) < 0.3 for m in merged):
            merged.append(b)
    return merged


def _iou(a, b) -> float:
    iw = max(0.0, min(a[0] + a[2], b[0] + b[2]) - max(a[0], b[0]))
    ih = max(0.0, min(a[1] + a[3], b[1] + b[3]) - max(a[1], b[1]))
    inter = iw * ih
    return inter / (a[2] * a[3] + b[2] * b[3] - inter + 1e-9)


def find_facecam(frames: list[list[list[float]]], min_presence: float = 0.4) -> tuple[list[float] | None, dict]:
    """The face box that appears in the same place in at least min_presence of the sampled frames."""
    centres = [(k, b) for k, boxes in enumerate(frames) for b in boxes]
    if not frames or not centres:
        return None, {"sampled": len(frames), "faces": 0, "presence": 0.0}
    best, best_frames = None, set()
    for _, b in centres:
        cx, cy = b[0] + b[2] / 2, b[1] + b[3] / 2
        members = [(k, o) for k, o in centres if abs(o[0] + o[2] / 2 - cx) < 0.04 and abs(o[1] + o[3] / 2 - cy) < 0.06]
        fr = {k for k, _ in members}
        if len(fr) > len(best_frames):
            best, best_frames = members, fr
    presence = len(best_frames) / len(frames)
    info = {"sampled": len(frames), "faces": len(centres), "presence": round(presence, 3)}
    if presence < min_presence:
        return None, info
    box = np.median(np.array([o for _, o in best]), axis=0).tolist()
    return [round(v, 4) for v in box], info


def _fit_aspect(x, y, w, h, aspect, W, H):
    """Shrink (x, y, w, h) around its centre to width/height = aspect, inside the frame."""
    cx, cy = x + w / 2, y + h / 2
    w, h = min(w, W), min(h, H)
    if w / h > aspect:
        w = h * aspect
    else:
        h = w / aspect
    x = min(max(cx - w / 2, 0), W - w)
    y = min(max(cy - h / 2, 0), H - h)
    return [round(x, 1), round(y, 1), round(w, 1), round(h, 1)]


def cam_region_from_face(face: list[float], W: int, H: int, aspect: float, scale: float,
                         overlay: list[float] | None = None) -> list[float]:
    """A crop around a detected face at the panel's aspect.

    With the overlay rectangle known, the crop stays inside it (largest crop of
    the panel's aspect, centred on the face as far as the overlay allows), so
    no game pixels leak into the cam panel. Without it: `scale` face heights
    tall around the face.
    """
    fx, fy, fw, fh = face[0] * W, face[1] * H, face[2] * W, face[3] * H
    cx, cy = fx + fw / 2, fy + fh / 2
    if overlay:
        ox, oy, ow_, oh_ = overlay[0] * W, overlay[1] * H, overlay[2] * W, overlay[3] * H
        w, h = (ow_, ow_ / aspect) if ow_ / oh_ <= aspect else (oh_ * aspect, oh_)
        x = min(max(cx - w / 2, ox), ox + ow_ - w)
        y = min(max(cy - h / 2 + 0.08 * h, oy), oy + oh_ - h)
        return [round(x, 1), round(y, 1), round(w, 1), round(h, 1)]
    h = min(H, fh * scale)
    w = min(W, h * aspect)
    h = w / aspect
    return _fit_aspect(cx - w / 2, cy - h / 2 + 0.08 * h, w, h, aspect, W, H)


def plan_layout(src: dict, s: dict, layout: str, facecam_box: list[float] | None, cam_region_norm: list[float] | None,
                overlay: list[float] | None = None) -> dict:
    """Regions (source px -> output px) for the chosen layout."""
    W, H = src["width"], src["height"]
    ow, oh = s["output"]["width"], s["output"]["height"]
    gx = s["gameplay_x"]
    if layout == "stacked":
        top_h = int(round(oh * s["cam_height"] / 2)) * 2
        bot_h = oh - top_h
        if cam_region_norm:
            x, y, w, h = cam_region_norm
            cam = _fit_aspect(x * W, y * H, w * W, h * H, ow / top_h, W, H)
        else:
            cam = cam_region_from_face(facecam_box, W, H, ow / top_h, s["cam_scale"], overlay)
            face_h = facecam_box[3] * H
            if face_h > 0.8 * cam[3]:
                # A tall avatar / big face would lose its head in the wide panel: show the whole
                # overlay (or a box around the face) in the panel, with a blurred fill at the sides.
                box = overlay or [facecam_box[0] - facecam_box[2] * 0.25, facecam_box[1] - facecam_box[3] * 0.15,
                                  facecam_box[2] * 1.5, facecam_box[3] * 1.3]
                bx, by = max(0.0, box[0] * W), max(0.0, box[1] * H)
                cam_full = [round(bx, 1), round(by, 1), round(min(W - bx, box[2] * W), 1), round(min(H - by, box[3] * H), 1)]
                return _stacked(cam_full, top_h, bot_h, ow, W, H, gx, s, overlay or cam_region_norm, contain=True)
        return _stacked(cam, top_h, bot_h, ow, W, H, gx, s, overlay or cam_region_norm, contain=False)
    if layout == "gameplay":
        w = H * ow / oh
        return {"layout": "gameplay", "regions": [{"name": "gameplay", "src": _fit_aspect(gx * W - w / 2, 0, w, H, ow / oh, W, H),
                                                  "dst": [0, 0, ow, oh]}]}
    if layout == "fit":
        fit_h = int(round(ow * H / W / 2)) * 2
        y0 = int(round((oh - fit_h) * s["fit_y"] / 2)) * 2
        bg_w = H * ow / oh
        return {"layout": "fit", "regions": [
            {"name": "background", "src": _fit_aspect((W - bg_w) / 2, 0, bg_w, H, ow / oh, W, H), "dst": [0, 0, ow, oh], "blur": s["fit_blur"]},
            {"name": "stream", "src": [0, 0, W, H], "dst": [0, y0, ow, fit_h]}]}
    if layout == "follow":
        return {"layout": "follow", "regions": []}
    raise ValueError(f"unknown layout {layout!r} (fit, stacked, gameplay, follow)")


def _stacked(cam, top_h, bot_h, ow, W, H, gx, s, avoid_box, contain: bool) -> dict:
    game_h = H / max(1.0, s["gameplay_zoom"])
    game_w = game_h * ow / bot_h
    game = _fit_aspect(gx * W - game_w / 2, (H - game_h) / 2, game_w, game_h, ow / bot_h, W, H)
    game = _avoid(game, avoid_box, W, H)
    cam_region = {"name": "cam", "src": cam, "dst": [0, 0, ow, top_h]}
    if contain:
        cam_region["mode"] = "contain"
    return {"layout": "stacked", "regions": [cam_region, {"name": "gameplay", "src": game, "dst": [0, top_h, ow, bot_h]}]}


def _avoid(game: list[float], box_norm: list[float] | None, W: int, H: int, max_shift: float = 0.1) -> list[float]:
    """Shift the gameplay crop sideways (at most max_shift of the width) so it does not show the cam overlay."""
    if not box_norm:
        return game
    gx, gy, gw, gh = game
    bx0, bx1 = box_norm[0] * W, (box_norm[0] + box_norm[2]) * W
    by0, by1 = box_norm[1] * H, (box_norm[1] + box_norm[3]) * H
    if gy >= by1 or gy + gh <= by0 or gx >= bx1 or gx + gw <= bx0:
        return game
    left_shift = (gx + gw) - bx0      # move left so the crop ends before the box
    right_shift = bx1 - gx            # or move right so it starts after it
    shift = -left_shift if left_shift <= right_shift else right_shift
    if abs(shift) > max_shift * W:
        return game
    return [round(min(max(gx + shift, 0), W - gw), 1), gy, gw, gh]


def choose_layout(requested: str, facecam_box: list[float] | None, cam_region_norm: list[float] | None) -> tuple[str, str]:
    if requested != "auto":
        return requested, "set by stream.layout / --layout"
    if cam_region_norm:
        return "stacked", "facecam region set in stream.facecam"
    if facecam_box is None:
        return "fit", "no facecam detected (a VTuber avatar is usually not detected as a face): whole stream on a blurred background"
    if facecam_box[3] >= 0.35:
        return "follow", "the camera is the main picture (IRL / just chatting): follow the speaker"
    return "stacked", "facecam detected in a fixed position"


def find_avatar(path: str, start: float, end: float, hsv_low: list[int], hsv_high: list[int], samples: int = 10,
                min_presence: float = 0.7, min_area: float = 0.004, max_area: float = 0.3) -> tuple[list[float] | None, dict]:
    """A VTuber avatar by its colour: the largest blob of avatar-coloured pixels that stays
    in the same place in most sampled frames of [start, end] (the game behind it moves).

    Returns the blob's bounding box (normalised x, y, w, h) or None.
    """
    cap = cv2.VideoCapture(str(path))
    masks = []
    for t in np.linspace(start + 0.5, end - 0.5, samples):
        cap.set(cv2.CAP_PROP_POS_MSEC, float(t) * 1000)
        ok, img = cap.read()
        if not ok:
            continue
        small = cv2.resize(img, (480, round(img.shape[0] * 480 / img.shape[1])), interpolation=cv2.INTER_AREA)
        hsv = cv2.cvtColor(small, cv2.COLOR_BGR2HSV)
        masks.append(cv2.inRange(hsv, np.array(hsv_low, np.uint8), np.array(hsv_high, np.uint8)) > 0)
    cap.release()
    if len(masks) < 3:
        return None, {"avatar": "too few frames"}
    presence = np.mean(masks, axis=0) >= min_presence
    m = cv2.morphologyEx(presence.astype(np.uint8), cv2.MORPH_CLOSE, np.ones((5, 5), np.uint8))
    m = cv2.morphologyEx(m, cv2.MORPH_OPEN, np.ones((3, 3), np.uint8))
    n, labels, stats, _ = cv2.connectedComponentsWithStats(m)
    h, w = m.shape
    best = None
    for k in range(1, n):
        x, y, bw, bh, area = stats[k]
        frac = area / (w * h)
        if min_area <= frac <= max_area and (best is None or area > stats[best][4]):
            best = k
    if best is None:
        return None, {"avatar": "not found", "persistent_px": int(presence.sum())}
    x, y, bw, bh, area = stats[best]
    box = [round(x / w, 4), round(y / h, 4), round(bw / w, 4), round(bh / h, 4)]
    return box, {"avatar": "found", "area": round(area / (w * h), 4), "frames": len(masks)}


def pad_box(box: list[float], pad: float, top_extra: float = 0.0) -> list[float]:
    """Grow a normalised box by `pad` of its size on each side (plus `top_extra` of its height on top, for hats)."""
    x, y, w, h = box
    x0, y0 = max(0.0, x - w * pad), max(0.0, y - h * (pad + top_extra))
    x1, y1 = min(1.0, x + w * (1 + pad)), min(1.0, y + h * (1 + pad))
    return [round(x0, 4), round(y0, 4), round(x1 - x0, 4), round(y1 - y0, 4)]


def avatar_in_region(path: str, start: float, end: float, region: list[float], hsv_low: list[int], hsv_high: list[int],
                     samples: int = 10, min_presence: float = 0.7) -> tuple[float, list[float] | None]:
    """Share of `region` covered by persistently avatar-coloured pixels, and their bounding box (normalised)."""
    cap = cv2.VideoCapture(str(path))
    masks = []
    for t in np.linspace(start + 0.5, end - 0.5, samples):
        cap.set(cv2.CAP_PROP_POS_MSEC, float(t) * 1000)
        ok, img = cap.read()
        if not ok:
            continue
        H, W = img.shape[:2]
        x, y, w, h = (int(round(v)) for v in (region[0] * W, region[1] * H, region[2] * W, region[3] * H))
        hsv = cv2.cvtColor(img[y:y + h, x:x + w], cv2.COLOR_BGR2HSV)
        masks.append(cv2.inRange(hsv, np.array(hsv_low, np.uint8), np.array(hsv_high, np.uint8)) > 0)
    cap.release()
    if len(masks) < 3:
        return 0.0, None
    pres = (np.mean(masks, axis=0) >= min_presence).astype(np.uint8)
    pres = cv2.morphologyEx(pres, cv2.MORPH_OPEN, np.ones((5, 5), np.uint8))
    fill = float(pres.mean())
    ys, xs = np.nonzero(pres)
    if not len(xs):
        return fill, None
    rh, rw = pres.shape
    box = [region[0] + xs.min() / rw * region[2], region[1] + ys.min() / rh * region[3],
           (xs.max() - xs.min() + 1) / rw * region[2], (ys.max() - ys.min() + 1) / rh * region[3]]
    return round(fill, 3), [round(v, 4) for v in box]


def clip_layout_for_avatar(src: dict, s: dict, path: str, start: float, end: float) -> tuple[dict | None, str, dict]:
    """Per-clip layout from the profile's avatar description (stream.avatar), or None to use the default."""
    a = s["avatar"]
    lo, hi = a["hsv_low"], a["hsv_high"]
    for region in a["regions"]:
        fill, box = avatar_in_region(path, start, end, region, lo, hi, a["samples"], a["min_presence"])
        if fill >= a["min_fill"] and box:
            cam = pad_box(box, a["pad"], a["hat_extra"])
            plan = plan_layout(src, s, "stacked", box, None, cam)
            return plan, f"avatar at its gameplay spot ({fill:.0%} of the region): avatar on top, gameplay below", \
                {"region": region, "fill": fill, "box": box}
    box, info = find_avatar(path, start, end, lo, hi, a["samples"], a["min_presence"], max_area=a["max_area_center"])
    if box:
        cx = box[0] + box[2] / 2
        if 1 / 3 <= cx <= 2 / 3:
            s2 = dict(s, gameplay_x=cx)
            return plan_layout(src, s2, "gameplay", None, None), "avatar in the middle of the scene: 9:16 crop around it", \
                {"box": box, **info}
    return None, "no avatar found in this clip: default layout", {"box": box, **info}
