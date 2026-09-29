#!/usr/bin/env python3
"""SVG rasmlarni PNG ga o'giradi (Speaking rasmlari uchun).

    python3 content/tools/render_images.py content/mocks/<slug> [--width 1200]

`<slug>/images/*.svg` -> `<slug>/images/*.png`. SVG manba sifatida saqlanadi, PNG seeder tomonidan yuklanadi.
Tavsiya: viewBox="0 0 1200 800" (3:2). Shrift — faqat "DejaVu Sans" (matn kerak bo'lsa).
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

import cairosvg


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("mock_dir")
    parser.add_argument("--width", type=int, default=1200)
    args = parser.parse_args()

    images = Path(args.mock_dir) / "images"
    svgs = sorted(images.glob("*.svg"))
    if not svgs:
        sys.exit(f"{images} ichida .svg yo'q")
    for svg in svgs:
        png = svg.with_suffix(".png")
        cairosvg.svg2png(url=str(svg), write_to=str(png), output_width=args.width)
        size = png.stat().st_size
        flag = "  (DIQQAT: 8 MB dan katta!)" if size > 8 * 1024 * 1024 else ""
        print(f"{svg.name} -> {png.name} ({size // 1024} KB){flag}")


if __name__ == "__main__":
    main()
