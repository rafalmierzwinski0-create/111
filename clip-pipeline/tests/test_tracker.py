from clipper.detect.tracker import Tracker, center_distance, iou


def test_iou_basics():
    assert iou([0, 0, 1, 1], [0, 0, 1, 1]) == 1.0
    assert iou([0, 0, 1, 1], [2, 2, 1, 1]) == 0.0
    assert abs(iou([0, 0, 2, 2], [1, 1, 2, 2]) - 1 / 7) < 1e-9


def test_two_static_faces_keep_their_ids():
    tr = Tracker()
    left, right = [0.1, 0.2, 0.1, 0.2], [0.7, 0.2, 0.1, 0.2]
    first = tr.update(0.0, [left, right])
    for k in range(1, 50):
        # Detection order flips every frame; ids must follow position, not order.
        boxes = [right, left] if k % 2 else [left, right]
        ids = tr.update(k / 30, boxes)
        assert dict(zip(map(tuple, boxes), ids)) == {tuple(left): first[0], tuple(right): first[1]}


def test_moving_face_follows_by_center_distance_when_iou_drops():
    tr = Tracker(iou_threshold=0.2, max_center_dist=0.6)
    (tid,) = tr.update(0.0, [[0.10, 0.2, 0.1, 0.2]])
    # Moves half a face width per frame: IoU ~0.33 then the centre fallback.
    assert tr.update(0.1, [[0.15, 0.2, 0.1, 0.2]]) == [tid]
    assert tr.update(0.2, [[0.205, 0.2, 0.1, 0.2]]) == [tid]


def test_track_ends_after_gap_and_at_shot_boundary():
    tr = Tracker(max_gap_s=1.0)
    box = [0.4, 0.2, 0.1, 0.2]
    (a,) = tr.update(0.0, [box])
    (b,) = tr.update(0.9, [box])
    assert a == b
    (c,) = tr.update(2.5, [box])
    assert c != a
    tr.end_all()
    (d,) = tr.update(2.6, [box])
    assert d != c


def test_center_distance_units_are_face_widths():
    assert abs(center_distance([0, 0, 0.1, 0.1], [0.1, 0, 0.1, 0.1]) - 1.0) < 1e-9
