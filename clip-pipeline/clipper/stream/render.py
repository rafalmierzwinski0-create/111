"""Rendering stream clips with one ffmpeg pass each (static layouts need no per-frame work)."""

from __future__ import annotations

import subprocess
from pathlib import Path

from clipper.video import require_tool


def _crop(r: list[float]) -> str:
    x, y, w, h = (int(round(v)) // 2 * 2 for v in r)
    return f"crop={max(w, 2)}:{max(h, 2)}:{x}:{y}"


def filter_graph(plan: dict, ass_path: Path | None) -> str:
    regs = {r["name"]: r for r in plan["regions"]}
    ow, oh = plan["output"]["width"], plan["output"]["height"]
    layout = plan["layout"]
    if layout == "stacked":
        cam, game = regs["cam"], regs["gameplay"]
        th = cam["dst"][3]
        if cam.get("mode") == "contain":
            # whole cam/avatar at panel height, centred, on a blurred stretch of itself
            top = (f"[a]{_crop(cam['src'])},split=2[c1][c2];[c1]scale={ow // 4}:{th // 4},gblur=sigma=6,scale={ow}:{th},"
                   f"eq=brightness=-0.15,setsar=1[cbg];[c2]scale=-2:{th}:flags=lanczos,setsar=1[cfg];"
                   f"[cbg][cfg]overlay=(W-w)/2:0[top]")
        else:
            top = f"[a]{_crop(cam['src'])},scale={ow}:{th}:flags=lanczos,setsar=1[top]"
        g = (f"[0:v]split=2[a][b];{top};"
             f"[b]{_crop(game['src'])},scale={ow}:{game['dst'][3]}:flags=lanczos,setsar=1[bot];[top][bot]vstack=2[v0]")
    elif layout == "gameplay":
        g = f"[0:v]{_crop(regs['gameplay']['src'])},scale={ow}:{oh}:flags=lanczos,setsar=1[v0]"
    elif layout == "fit":
        bg, fg = regs["background"], regs["stream"]
        g = (f"[0:v]split=2[a][b];[a]{_crop(bg['src'])},scale={ow // 4}:{oh // 4},gblur=sigma={bg['blur'] / 4},"
             f"scale={ow}:{oh},eq=brightness=-0.12,setsar=1[bg];[b]scale={ow}:{fg['dst'][3]}:flags=lanczos,setsar=1[fg];"
             f"[bg][fg]overlay=0:{fg['dst'][1]}[v0]")
    else:
        raise ValueError(f"layout {layout!r} is not rendered by ffmpeg directly")
    if ass_path is not None:
        esc = str(ass_path).replace("\\", "/").replace(":", "\\:").replace("'", "\\'")
        g += f";[v0]subtitles='{esc}'[v]"
    else:
        g += ";[v0]null[v]"
    return g


def render_clip(source: dict, plan: dict, clip: dict, out_path: Path, ass_path: Path | None) -> list[str]:
    o = plan["output"]
    dur = clip["end"] - clip["start"]
    cmd = [require_tool("ffmpeg"), "-v", "error", "-y", "-ss", f"{clip['start']:.3f}", "-t", f"{dur:.3f}", "-i", source["path"],
           "-filter_complex", filter_graph(plan, ass_path), "-map", "[v]"]
    if source.get("has_audio"):
        cmd += ["-map", "0:a:0", "-c:a", o["audio_codec"], "-b:a", o["audio_bitrate"], "-ac", "2"]
    fps = min(source["fps"], o["max_fps"])
    cmd += ["-r", f"{fps:.4f}", "-c:v", o["video_codec"], "-crf", str(o["crf"]), "-preset", o["preset"],
            "-pix_fmt", "yuv420p", "-movflags", "+faststart", str(out_path)]
    out_path.parent.mkdir(parents=True, exist_ok=True)
    proc = subprocess.run(cmd, capture_output=True, text=True)
    if proc.returncode != 0:
        raise RuntimeError(f"ffmpeg failed rendering {out_path.name}: {proc.stderr.strip()[-800:]}")
    return cmd
