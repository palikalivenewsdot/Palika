"""Build a 1200x600 duo card: MP Krantishikha Dhital + Home Minister Sudan Gurung."""
import sys, os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from PIL import Image, ImageDraw, ImageFilter, ImageEnhance
from deva_text import ShapedFont, draw_text, text_width

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
IMG = os.path.join(ROOT, "image-search")
FONTS = os.path.join(ROOT, "assets", "fonts")
OUT = os.path.join(ROOT, "output")
os.makedirs(OUT, exist_ok=True)

W, H = 1200, 600
HALF = W // 2

BOLD = os.path.join(FONTS, "Mukta-Bold.ttf")
SEMI = os.path.join(FONTS, "Mukta-SemiBold.ttf")
MED = os.path.join(FONTS, "Mukta-Medium.ttf")

NAME_SIZE = 46
ROLE_SIZE = 22
ACCENT = (37, 99, 235)          # accent bar (matches the bottom rule)
ROLE_COLOR = (196, 219, 255)


def square_crop(im, zoom=1.0, bias_x=0.5, bias_y=0.5):
    w, h = im.size
    side = min(w, h) / zoom
    cx, cy = w * bias_x, h * bias_y
    left = max(0, min(w - side, cx - side / 2))
    top = max(0, min(h - side, cy - side / 2))
    return im.crop((int(left), int(top), int(left + side), int(top + side)))


def graded(im, size, saturation=1.0, contrast=1.03, brightness=1.0, sharp=1.0):
    im = im.convert("RGB")
    im = ImageEnhance.Color(im).enhance(saturation)
    im = ImageEnhance.Contrast(im).enhance(contrast)
    im = ImageEnhance.Brightness(im).enhance(brightness)
    im = im.resize(size, Image.LANCZOS)
    if sharp != 1.0:
        blurred = im.filter(ImageFilter.GaussianBlur(1.2))
        im = Image.blend(blurred, im, sharp)
    return im


def vertical_gradient(size, top_rgba, bottom_rgba, curve=1.0):
    w, h = size
    grad = Image.new("L", (1, h))
    for y in range(h):
        t = (y / max(h - 1, 1)) ** curve
        grad.putpixel((0, y), int(round(255 * t)))
    grad = grad.resize((w, h))
    top = Image.new("RGB", (w, h), top_rgba[:3])
    bot = Image.new("RGB", (w, h), bottom_rgba[:3])
    a_top = Image.new("L", (w, h), top_rgba[3])
    a_bot = Image.new("L", (w, h), bottom_rgba[3])
    out = Image.composite(bot, top, grad).convert("RGBA")
    out.putalpha(Image.composite(a_bot, a_top, grad))
    return out


def soft_shadow(mask, offset=(0, 3), blur=8, opacity=205):
    sh = Image.new("L", mask.size, 0)
    sh.paste(mask, offset)
    sh = sh.filter(ImageFilter.GaussianBlur(blur))
    sh = sh.point(lambda v: min(255, int(v * opacity / 255)))
    return sh


def build():
    ks = Image.open(os.path.join(IMG, "krantishikha-dhital-mp-rsp-5.png"))
    sg = Image.open(os.path.join(IMG, "sudan-gurung-home-minister-nepal-4.jpg"))

    ks = square_crop(ks, zoom=1.0, bias_x=0.5, bias_y=0.52)
    sg = square_crop(sg, zoom=1.06, bias_x=0.5, bias_y=0.40)

    left = graded(ks, (HALF, H), saturation=0.90, brightness=0.99, sharp=1.15)
    right = graded(sg, (HALF, H), saturation=0.94, brightness=1.0, sharp=1.30)

    # harmonise the two source grades: cool the warm half, warm the neutral half
    left = Image.blend(left, Image.new("RGB", left.size, (60, 90, 160)), 0.05)
    right = Image.blend(right, Image.new("RGB", right.size, (255, 214, 170)), 0.06)

    canvas = Image.new("RGB", (W, H), (10, 16, 28))
    canvas.paste(left, (0, 0))
    canvas.paste(right, (HALF, 0))

    shade = vertical_gradient((W, H), (0, 0, 0, 0), (4, 9, 20, 252), curve=2.0)
    canvas = Image.alpha_composite(canvas.convert("RGBA"), shade).convert("RGB")

    # gentle vignette so the two halves read as one design
    vig = Image.new("L", (W, H), 0)
    vd = ImageDraw.Draw(vig)
    vd.ellipse([-W * 0.35, -H * 0.55, W * 1.35, H * 1.55], fill=255)
    vig = vig.filter(ImageFilter.GaussianBlur(140)).point(lambda v: 255 - int(v * 0.30))
    canvas = Image.composite(canvas, Image.new("RGB", (W, H), (0, 0, 0)), vig)

    d = ImageDraw.Draw(canvas, "RGBA")
    d.rectangle([HALF - 1, 0, HALF + 2, H], fill=(255, 255, 255, 105))
    d.rectangle([0, H - 5, W, H], fill=ACCENT + (255,))

    name_f = ShapedFont(BOLD, NAME_SIZE)
    role_f = ShapedFont(SEMI, ROLE_SIZE)

    people = [
        dict(side="left", name="क्रान्तिशिखा धिताल",
             role="सांसद, राष्ट्रिय स्वतन्त्र पार्टी"),
        dict(side="right", name="सुदन गुरुङ",
             role="गृहमन्त्री, नेपाल सरकार"),
    ]

    for p in people:
        pad_x = 40
        name_y = H - 122
        role_y = name_y + NAME_SIZE + 16
        bar_h = NAME_SIZE + 6

        if p["side"] == "left":
            anchor_x = pad_x
            d.rectangle([anchor_x, name_y + 4, anchor_x + 6, name_y + bar_h],
                        fill=ACCENT + (255,))
            lines = [
                (p["name"], name_f, (255, 255, 255), name_y, anchor_x + 24, "left"),
                (p["role"], role_f, ROLE_COLOR, role_y, anchor_x + 24, "left"),
            ]
        else:
            anchor_x = W - pad_x
            name_w = text_width(name_f, p["name"])
            bar_x = anchor_x - name_w - 28
            d.rectangle([bar_x, name_y + 4, bar_x + 6, name_y + bar_h],
                        fill=ACCENT + (255,))
            lines = [
                (p["name"], name_f, (255, 255, 255), name_y, anchor_x, "right"),
                (p["role"], role_f, ROLE_COLOR, role_y, anchor_x, "right"),
            ]

        for text, font, color, y, ax, side in lines:
            mask, adv = font.render(text)
            x = ax if side == "left" else ax - adv
            shadow = soft_shadow(mask, offset=(0, 3), blur=7, opacity=215)
            canvas.paste((0, 0, 0), (int(round(x)), int(round(y))), shadow)
            canvas.paste(Image.new("RGB", mask.size, color),
                         (int(round(x)), int(round(y))), mask)

    clean = canvas.copy()

    canvas.save(os.path.join(OUT, "krantishikha-dhital-sudan-gurung-1200x600.png"))
    canvas.save(os.path.join(OUT, "krantishikha-dhital-sudan-gurung-1200x600.jpg"),
                quality=93, subsampling=0)
    e = ImageDraw.Draw(clean, "RGBA")
    e.rectangle([HALF - 1, 0, HALF + 2, H], fill=(255, 255, 255, 105))
    e.rectangle([0, H - 5, W, H], fill=ACCENT + (255,))
    clean.save(os.path.join(OUT, "krantishikha-dhital-sudan-gurung-1200x600-clean.jpg"),
               quality=93, subsampling=0)
    print("done", canvas.size)


if __name__ == "__main__":
    build()
