"""Render Devanagari (and other complex scripts) with HarfBuzz shaping + FreeType rasterising.

Pillow's ImageFont cannot shape Indic scripts without libraqm, which is not
available in this environment. uharfbuzz (shaping) + freetype-py (hinting and
rasterising) does the full job, including ligatures and matra reordering.
"""
from PIL import Image
import freetype as ft
import uharfbuzz as hb

LOAD = ft.FT_LOAD_RENDER | ft.FT_LOAD_TARGET_NORMAL


class ShapedFont:
    def __init__(self, path, size_px):
        self.path = path
        self.size_px = size_px
        self.face = ft.Face(path)
        self.face.set_pixel_sizes(0, size_px)
        self.upem = self.face.units_per_EM
        with open(path, "rb") as fh:
            self.hbface = hb.Face(fh.read())
        self.hbfont = hb.Font(self.hbface)
        self.hbfont.scale = (self.upem, self.upem)
        self.ascender = self.face.size.ascender >> 6
        self.descender = self.face.size.descender >> 6

    # -- helpers -----------------------------------------------------------
    def _shape(self, text):
        buf = hb.Buffer()
        buf.add_str(text)
        buf.guess_segment_properties()
        hb.shape(self.hbfont, buf)
        return buf.glyph_infos, buf.glyph_positions

    def _scale(self, v):
        return v * self.size_px / self.upem

    def _runs(self, text):
        """Return [(PIL 'L' image, gx, gy)] plus pen advance, in pixels.

        gx/gy are the position of the glyph bitmap's top-left corner relative
        to the pen origin / baseline.
        """
        infos, poss = self._shape(text)
        pen = 0.0
        out = []
        for info, pos in zip(infos, poss):
            self.face.load_glyph(info.codepoint, LOAD)
            g = self.face.glyph
            b = g.bitmap
            if b.width and b.rows:
                img = Image.frombytes("L", (b.width, b.rows), bytes(b.buffer))
                gx = pen + self._scale(pos.x_offset) + g.bitmap_left
                gy = -self._scale(pos.y_offset) - g.bitmap_top
                out.append((img, gx, gy))
            pen += self._scale(pos.x_advance)
        return out, pen

    # -- public ------------------------------------------------------------
    def advance(self, text):
        return self._runs(text)[1]

    def measure(self, text):
        """Return (width, height) of the rendered run in pixels."""
        runs, adv = self._runs(text)
        if not runs:
            return 0, 0
        xmin = min(gx for _, gx, _ in runs)
        xmax = max(gx + im.width for im, gx, _ in runs)
        ymin = min(gy for _, _, gy in runs)
        ymax = max(gy + im.height for im, _, gy in runs)
        return max(xmax - xmin, adv), ymax - ymin

    def render(self, text, pad=1):
        """Render text into a new 'L' mask (white = ink, 0 = transparent)."""
        runs, adv = self._runs(text)
        if not runs:
            return Image.new("L", (1, 1), 0)
        xmin = min(gx for _, gx, _ in runs)
        xmax = max([gx + im.width for im, gx, _ in runs] + [adv])
        ymin = min(gy for _, _, gy in runs)
        ymax = max(gy + im.height for im, _, gy in runs)
        w = int(round(xmax - xmin)) + 2 * pad
        h = int(round(ymax - ymin)) + 2 * pad
        img = Image.new("L", (max(w, 1), max(h, 1)), 0)
        for im, gx, gy in runs:
            img.paste(im, (int(round(gx - xmin)) + pad, int(round(gy - ymin)) + pad))
        return img, adv - xmin


def draw_text(base, text, font, xy, color, anchor="lt"):
    """Draw shaped text onto a PIL image, returns the new RGB(A) image.

    xy = (x, y) position of the anchor; anchor is two chars: [l|c|r][t|m|b].
    """
    mask, _ = font.render(text)
    x, y = xy
    if anchor[0] == "c":
        x -= mask.width / 2
    elif anchor[0] == "r":
        x -= mask.width
    if anchor[1] == "m":
        y -= mask.height / 2
    elif anchor[1] == "b":
        y -= mask.height
    rgb = color if len(color) == 3 else color[:3]
    alpha = 255 if len(color) == 3 else color[3]
    layer = Image.new("RGB", base.size, rgb)
    amask = Image.new("L", base.size, 0)
    amask.paste(Image.new("L", mask.size, alpha), (int(round(x)), int(round(y))), mask)
    out = Image.composite(layer, base.convert("RGB"), amask)
    return out


def text_width(font, text):
    return font.render(text)[1]


def wrap(font, text, max_width):
    """Greedy word wrap for shaped text, returns a list of lines."""
    words = text.split()
    lines, cur = [], ""
    for w in words:
        trial = (cur + " " + w).strip()
        if text_width(font, trial) <= max_width or not cur:
            cur = trial
        else:
            lines.append(cur)
            cur = w
    if cur:
        lines.append(cur)
    return lines


def wrap_css(font, text, max_width):
    return wrap(font, text, max_width)
