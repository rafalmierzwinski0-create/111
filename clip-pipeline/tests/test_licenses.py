from clipper.licenses import classify, forbidden_imports


def test_classify():
    assert classify("MIT") == "ok"
    assert classify("Apache Software License; Apache 2.0") == "ok"
    assert classify("BSD-3-Clause AND 0BSD AND MIT AND Zlib AND CC0-1.0") == "ok"
    assert classify("Mozilla Public License 2.0 (MPL 2.0)") == "ok"
    assert classify("GNU Lesser General Public License v3 (LGPLv3)") == "ok"
    assert classify("AGPL-3.0") == "forbidden"
    assert classify("GNU Affero General Public License v3") == "forbidden"
    assert classify("GPL-3.0-only") == "forbidden"
    assert classify("GNU General Public License v2 (GPLv2)") == "forbidden"
    assert classify("CC-BY-NC-4.0") == "forbidden"
    assert classify("Proprietary") == "unknown"


def test_no_forbidden_imports_in_package():
    assert forbidden_imports() == []


def test_forbidden_import_is_found(tmp_path):
    (tmp_path / "bad.py").write_text("from ultralytics import YOLO\n")
    assert forbidden_imports(tmp_path) == [str(tmp_path / "bad.py")]
