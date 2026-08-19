# -*- coding: utf-8 -*-
"""Generate assets/img/og-card.png — the 1200x630 landscape social-share card.

The hero cutout (assets/img/hero-student.png, 613x830 portrait) is too small
and the wrong shape for twitter:card=summary_large_image (~2:1 crop), so this
composes a proper landscape card from the site's design tokens (css/style.css):
cream page background, peach blob behind the student, green wave — all drawn
at 2x and downsampled for clean edges. Language-neutral (no text), so EN and
FR pages share it. Rerun after changing the hero image, then rerun
launch_hygiene.py to refresh the meta tags.
"""
import math
import os

from PIL import Image, ImageDraw

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
HERO = os.path.join(ROOT, "assets", "img", "hero-student.png")
OUT = os.path.join(ROOT, "assets", "img", "og-card.png")

W, H = 1200, 630
S = 2  # supersample factor

CREAM = (0xFB, 0xF6, 0xED)   # --primary-light-1
PEACH = (0xFB, 0xBC, 0x7E)   # --primary
GREEN = (0xB9, 0xD9, 0x89)   # --secondary

img = Image.new("RGB", (W * S, H * S), CREAM)
d = ImageDraw.Draw(img)

# Green wave along the bottom
wave = []
for x in range(0, W * S + 8, 8):
    y = (H - 78) * S + 26 * S * math.sin(2 * math.pi * 1.25 * x / (W * S))
    wave.append((x, y))
wave += [(W * S, H * S), (0, H * S)]
d.polygon(wave, fill=GREEN)

# Peach blob behind the student (right of centre, clipped by the bottom)
cx, cy, r = 840 * S, 330 * S, 285 * S
d.ellipse([cx - r, cy - r, cx + r, cy + r], fill=PEACH)

# Small accent dots on the empty left side
d.ellipse([150 * S, 140 * S, 202 * S, 192 * S], fill=PEACH)
d.ellipse([258 * S, 108 * S, 286 * S, 136 * S], fill=GREEN)
d.ellipse([190 * S, 260 * S, 214 * S, 284 * S], fill=GREEN)

# Student cutout, bottom-anchored over the blob
hero = Image.open(HERO).convert("RGBA")
hh = 585 * S
hw = round(hero.width * hh / hero.height)
hero = hero.resize((hw, hh), Image.LANCZOS)
img.paste(hero, (cx - hw // 2, H * S - hh), hero)

img = img.resize((W, H), Image.LANCZOS)
img.save(OUT, optimize=True)
print("Wrote %s (%dx%d, %.0f KB)" % (OUT, W, H, os.path.getsize(OUT) / 1024.0))
