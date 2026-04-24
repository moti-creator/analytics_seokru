"""Generate favicon.ico + PNG variants from logo."""
from PIL import Image, ImageDraw, ImageFont
import os

OUT = os.path.join(os.path.dirname(__file__), '..', 'public')
BG = (26, 115, 232)
FG = (255, 255, 255)

def draw_logo(size):
    ss = 4
    w = size * ss
    img = Image.new('RGBA', (w, w), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    radius = max(2, int(size * 0.18)) * ss
    d.rounded_rectangle([(0, 0), (w - 1, w - 1)], radius=radius, fill=BG)
    font = None
    for p in ['C:/Windows/Fonts/arialbd.ttf','C:/Windows/Fonts/segoeuib.ttf','/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf']:
        if os.path.exists(p):
            font = ImageFont.truetype(p, int(size * 0.5) * ss); break
    if font is None: font = ImageFont.load_default()
    text = 'SA'
    bbox = d.textbbox((0, 0), text, font=font)
    tw = bbox[2] - bbox[0]; th = bbox[3] - bbox[1]
    x = (w - tw) // 2 - bbox[0]
    y = (w - th) // 2 - bbox[1] - int(size * 0.04 * ss)
    d.text((x, y), text, font=font, fill=FG)
    return img.resize((size, size), Image.LANCZOS)

# PNGs
for sz in (16, 32, 48, 180, 192, 512):
    img = draw_logo(sz)
    bg = Image.new('RGB', (sz, sz), BG); bg.paste(img, (0, 0), img)
    path = os.path.join(OUT, f'favicon-{sz}.png')
    bg.save(path, 'PNG', optimize=True)
    print(f'wrote {path}')

# ico (multi-size)
ico_sizes = [(16,16),(32,32),(48,48)]
ico_imgs = [draw_logo(s[0]).convert('RGBA') for s in ico_sizes]
ico_imgs[0].save(os.path.join(OUT, 'favicon.ico'), format='ICO', sizes=ico_sizes)
print(f'wrote favicon.ico')
