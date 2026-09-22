from pathlib import Path
from PIL import Image, ImageDraw
from pypdf import PdfReader
import json
import sys
import re
import unicodedata
sys.stdout.reconfigure(encoding='utf-8')

root = Path(__file__).resolve().parent
reader = PdfReader(root / 'print-check.pdf')
pages = []
for i, page in enumerate(reader.pages, 1):
    text = page.extract_text() or ''
    lines = [line.strip() for line in text.splitlines() if line.strip()]
    pages.append({'page': i, 'characters': len(text), 'first': lines[:3], 'last': lines[-3:]})
full_text = '\n'.join(page.extract_text() or '' for page in reader.pages)
expected = ['働く本人', '標準8分の台本', '3分の台本', '出発前のチェック', '登録のお手伝い', 'メール認証', '営業記録', '言葉の早見表', '営業責任者', '会社名・紹介文']
normalize = lambda text: re.sub(r'\W', '', unicodedata.normalize('NFKC', text).replace('\u2ed1', '長'))
content_path = root / 'print-text-blocks.json'
if content_path.exists():
    blocks = json.loads(content_path.read_text(encoding='utf-8'))
    missing = [block for block in blocks if normalize(block) not in normalize(full_text)]
    content_report = {'checked_blocks': len(blocks), 'missing_blocks': missing, 'normalization': 'NFKC and CJK long-radical glyph mapping'}
    (root / 'print-content-check.json').write_text(json.dumps(content_report, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps(content_report, ensure_ascii=False))
report = {'page_count': len(pages), 'missing_content': [x for x in expected if normalize(x) not in normalize(full_text)], 'short_pages': [x for x in pages if x['characters'] < 80], 'pages': pages}
(root / 'print-review.json').write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
images = sorted(root.glob('print-page-*.png'))[:len(pages)]
for start in range(0, len(images), 12):
    subset = images[start:start+12]
    sheet = Image.new('RGB', (1200, 1320), '#e9e6ef')
    draw = ImageDraw.Draw(sheet)
    for pos, image_path in enumerate(subset):
        im = Image.open(image_path).convert('RGB')
        im.thumbnail((280, 400))
        x, y = (pos % 4) * 300 + 10, (pos // 4) * 440 + 25
        draw.text((x, y-19), f'Page {start+pos+1}', fill='#241f33')
        sheet.paste(im, (x, y))
    sheet.save(root / f'print-contact-{start//12+1}.png')
print(json.dumps({k: v for k, v in report.items() if k != 'pages'}, ensure_ascii=False))
for p in pages:
    print(f"{p['page']:02}: {p['characters']} chars | {' / '.join(p['first'][:2])}")
