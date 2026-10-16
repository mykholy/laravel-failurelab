#!/usr/bin/env python3
"""Render the FailureLab README artwork reproducibly as WebP; dev tooling only."""
from __future__ import annotations

import math
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont, ImageFilter

WIDTH, HEIGHT = 1600, 660
DEST = Path("assets/failurelab-readme-hero.webp")
DEST.parent.mkdir(parents=True, exist_ok=True)
font_path = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
bold_path = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"


def font(size: int, *, bold: bool = False) -> ImageFont.FreeTypeFont:
    return ImageFont.truetype(bold_path if bold else font_path, size)


def rr(draw: ImageDraw.ImageDraw, region: tuple[int, int, int, int], fill: str, radius: int, outline: str | None = None, width: int = 1) -> None:
    draw.rounded_rectangle(region, radius, fill=fill, outline=outline, width=width)


img = Image.new("RGB", (WIDTH, HEIGHT), (11, 14, 31))
pix = img.load()
# Deep indigo to black, with restrained magenta/coral directional light.
for y in range(HEIGHT):
    for x in range(WIDTH):
        nx, ny = x / WIDTH, y / HEIGHT
        glow = max(0, 1 - math.hypot((nx - 0.73) * 1.22, (ny - 0.47) * 1.18))
        left = max(0, 1 - math.hypot((nx + 0.02) * 1.5, (ny - 1.0) * 1.4))
        r = int(10 + 35 * glow + 10 * left)
        g = int(14 + 5 * glow + 12 * left)
        b = int(30 + 52 * glow + 31 * left)
        pix[x, y] = (r, g, b)

d = ImageDraw.Draw(img, "RGBA")
for x in range(32, WIDTH, 40):
    d.line([(x, 0), (x, HEIGHT)], fill=(147, 132, 214, 10), width=1)
for y in range(28, HEIGHT, 40):
    d.line([(0, y), (WIDTH, y)], fill=(147, 132, 214, 10), width=1)

# Nested radar-style tracks: bounded, deterministic failure coordinates.
for r in (142, 245, 356, 467):
    d.arc((1190-r, 330-r, 1190+r, 330+r), 202, 338, fill=(171, 100, 255, 35), width=2)
d.line([(940, 90), (940, 562)], fill=(144, 114, 184, 62), width=2)
d.rounded_rectangle((12, 12, 1588, 648), radius=30, outline=(176, 145, 213, 54), width=2)

# Logomark: tested fault / successful recovery in concentric rings.
d.ellipse((91, 78, 155, 142), outline=(237, 106, 180, 255), width=6)
d.arc((100, 87, 145, 132), 36, 322, fill=(255, 174, 131, 255), width=7)
d.polygon([(123, 72), (113, 100), (129, 100), (119, 127), (150, 93), (132, 93), (142, 72)], fill=(255, 195, 133, 255))

d.text((176, 87), "LARAVEL  /  TESTING TOOLKIT", font=font(22, bold=True), fill=(217, 179, 238, 255))
d.text((82, 175), "FAILURE", font=font(100, bold=True), fill=(251, 248, 255, 255), stroke_width=0)
d.text((611, 175), "LAB", font=font(100, bold=True), fill=(255, 149, 135, 255))
d.text((88, 296), "Break the test. Prove the recovery.", font=font(31, bold=True), fill=(222, 222, 244, 255))
d.text((89, 350), "Deterministic fault injection and replay assertions", font=font(23), fill=(186, 190, 216, 255))
d.text((89, 383), "for Laravel integration tests.", font=font(23), fill=(186, 190, 216, 255))

def chip(x: int, width: int, label: str, dot: tuple[int, int, int, int]) -> None:
    rr(d, (x, 465, x + width, 516), "#20223f", 17, "#51456e", 2)
    d.ellipse((x + 16, 485, x + 26, 495), fill=dot)
    d.text((x + 36, 479), label, font=font(19, bold=True), fill=(233, 221, 243, 255))

chip(91, 195, "Fault injection", (250, 139, 158, 255))
chip(301, 206, "Replay testing", (190, 139, 250, 255))
chip(522, 220, "Safe assertions", (123, 236, 188, 255))
d.text((90, 571), "TEST-ONLY    /    PHP 8.3+    /    LARAVEL 12 & 13", font=font(17, bold=True), fill=(140, 150, 179, 255))

# Code pane: reduced exactness claims; this is a visual example, not runnable code.
rr(d, (960, 105, 1530, 568), "#131828", 26, "#554668", 3)
d.rounded_rectangle((962, 107, 1528, 171), radius=25, fill=(36, 34, 57, 255))
d.rectangle((962, 145, 1528, 171), fill=(36, 34, 57, 255))
for dx, fill in [(987, "#f58c93"), (1011, "#efbd72"), (1035, "#6cd7ba")]:
    d.ellipse((dx, 126, dx+11, 137), fill=fill)
d.text((1064, 122), "recovery.feature.test.php", font=font(16), fill=(178, 176, 202, 255))
lines=[
    ("01", "scenario('provider accepted')", "#c6b2ff"),
    ("02", "  ->interruptOnceAt('accepted')", "#f6bbad"),
    ("03", "  ->attempts(2)", "#98ddd0"),
    ("04", "  ->run($operation);", "#d7def6"),
    ("05", "", "#d7def6"),
    ("06", "assertFaultPlanSatisfied()", "#c7c1fc"),
    ("07", "assertCompleted()", "#9bdcb4"),
]
for i, (number, txt, color) in enumerate(lines):
    y = 210 + i * 37
    d.text((990, y), number, font=font(17), fill=(109, 117, 147, 255))
    d.text((1042, y), txt, font=font(19), fill=color)
rr(d, (995, 496, 1474, 545), "#153a37", 14, "#278f78", 2)
d.ellipse((1015, 515, 1030, 530), fill="#79e8bb")
d.text((1044, 506), "RECOVERY INVARIANT VERIFIED", font=font(18, bold=True), fill=(172, 244, 211, 255))

img.save(DEST, "WEBP", quality=90, method=6)
with Image.open(DEST) as result:
    assert result.format == "WEBP" and result.size == (WIDTH, HEIGHT)
    result.verify()
print(f"{DEST}: {DEST.stat().st_size:,} bytes ({WIDTH}x{HEIGHT})")
