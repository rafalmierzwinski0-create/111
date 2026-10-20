"""Two phone screenshots side by side on one image, for screenshot 3.

Usage: python3 -I tools/wporg-shots/phones.py <left.png> <right.png> <out.png>
"""
import sys

from PIL import Image, ImageDraw, ImageFilter

left, right, out = sys.argv[1:4]
W, H = 2560, 1800
canvas = Image.new('RGB', (W, H))
top, bottom = (232, 243, 237), (205, 228, 216)
draw = ImageDraw.Draw(canvas)
for y in range(H):
    t = y / (H - 1)
    draw.line([(0, y), (W, y)], fill=tuple(round(a + (b - a) * t) for a, b in zip(top, bottom)))

phone_h = 1600
for i, path in enumerate((left, right)):
    shot = Image.open(path).convert('RGB')
    scale = phone_h / shot.height
    shot = shot.resize((round(shot.width * scale), phone_h), Image.LANCZOS)
    w = shot.width
    x = W // 2 - w - 70 if i == 0 else W // 2 + 70
    y = (H - phone_h) // 2

    shadow = Image.new('L', (w + 160, phone_h + 160), 0)
    ImageDraw.Draw(shadow).rounded_rectangle([80, 100, 80 + w, 100 + phone_h], 64, fill=90)
    canvas.paste((40, 70, 55), (x - 80, y - 80), shadow.filter(ImageFilter.GaussianBlur(36)))

    frame = Image.new('L', (w + 28, phone_h + 28), 0)
    ImageDraw.Draw(frame).rounded_rectangle([0, 0, w + 27, phone_h + 27], 70, fill=255)
    canvas.paste((17, 24, 22), (x - 14, y - 14), frame)

    mask = Image.new('L', (w, phone_h), 0)
    ImageDraw.Draw(mask).rounded_rectangle([0, 0, w - 1, phone_h - 1], 58, fill=255)
    canvas.paste(shot, (x, y), mask)

canvas.save(out, optimize=True)
