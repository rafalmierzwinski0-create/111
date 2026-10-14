"""Burned-in captions for shorts: ASS subtitles from transcript word timings.

Words are shown a few at a time (stream.captions.words_per_line), big and
bold with a thick outline, the word being spoken highlighted, placed where
the layout leaves room (between the cam and gameplay panels for `stacked`).
"""

from __future__ import annotations


def _ts(t: float) -> str:
    t = max(0.0, t)
    h, rem = divmod(t, 3600)
    m, s = divmod(rem, 60)
    return f"{int(h)}:{int(m):02d}:{s:05.2f}"


def _color(hex_rgb: str) -> str:
    r, g, b = hex_rgb[0:2], hex_rgb[2:4], hex_rgb[4:6]
    return f"&H00{b}{g}{r}".upper()


def _escape(text: str) -> str:
    return text.replace("\\", "").replace("{", "(").replace("}", ")")


def build_ass(words: list, clip_start: float, clip_end: float, width: int, height: int, c: dict, y_frac: float) -> str | None:
    """ASS document for the words inside [clip_start, clip_end], times relative to the clip; None without words."""
    inside = [w for w in words if w.end > clip_start and w.start < clip_end]
    if not inside:
        return None
    upper = c["uppercase"]
    lines = []
    group: list = []
    for w in inside:
        if group and (len(group) >= c["words_per_line"] or w.start - group[-1].end > 0.6):
            lines.append(group)
            group = []
        group.append(w)
    if group:
        lines.append(group)

    y = int(height * y_frac)
    head = f"""[Script Info]
ScriptType: v4.00+
PlayResX: {width}
PlayResY: {height}
WrapStyle: 0
ScaledBorderAndShadow: yes

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Cap,{c['font']},{c['size']},{_color(c['color'])},{_color(c['color'])},&H00000000,&H64000000,-1,0,0,0,100,100,0,0,1,{c['outline']},2,5,60,60,0,1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
"""
    events = []
    for k, line in enumerate(lines):
        end_line = lines[k + 1][0].start if k + 1 < len(lines) else line[-1].end + 0.3
        end_line = min(end_line, line[-1].end + 1.0)
        for j, w in enumerate(line):
            a = w.start - clip_start
            b = (line[j + 1].start if j + 1 < len(line) else end_line) - clip_start
            if b <= 0 or a >= clip_end - clip_start:
                continue
            parts = []
            for i, x in enumerate(line):
                text = _escape(x.text.upper() if upper else x.text)
                parts.append(f"{{\\c{_color(c['highlight'])}}}{text}{{\\c{_color(c['color'])}}}" if i == j else text)
            events.append(f"Dialogue: 0,{_ts(a)},{_ts(b)},Cap,,0,0,0,,{{\\pos({width // 2},{y})}}" + " ".join(parts))
    return head + "\n".join(events) + "\n"
