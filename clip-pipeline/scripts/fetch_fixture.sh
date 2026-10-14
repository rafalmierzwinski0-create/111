#!/usr/bin/env sh
# Downloads the Phase 1 test fixture into fixtures/ and verifies its SHA-256.
#
# head-pose-face-detection-female-and-male.mp4 from intel-iot-devkit/sample-videos,
# licensed CC BY 4.0 (https://github.com/intel-iot-devkit/sample-videos):
# two people, static camera, 768x432, 12 fps, 143 s, silent audio track.
set -eu
cd "$(dirname "$0")/.."
mkdir -p fixtures
name=head-pose-face-detection-female-and-male.mp4
url="https://raw.githubusercontent.com/intel-iot-devkit/sample-videos/master/$name"
expected=650166430c4bf9ddc470ac17a86d1fcbd6d76c64e60ed73675fdc6b3e3d3af38
if [ ! -f "fixtures/$name" ]; then
  curl -fsSL -o "fixtures/$name.part" "$url"
  mv "fixtures/$name.part" "fixtures/$name"
fi
actual=$(sha256sum "fixtures/$name" | cut -d' ' -f1)
if [ "$actual" != "$expected" ]; then
  echo "fixtures/$name: SHA-256 $actual, expected $expected" >&2
  exit 1
fi
echo "fixtures/$name"
