from pathlib import Path
import fitz, json
from PIL import Image, ImageDraw

root = Path(__file__).resolve().parents[1]
review = root / 'tmp/pdfs/handover-review'
review.mkdir(parents=True, exist_ok=True)
results = []
for path in sorted((root / 'output/pdf').glob('*.pdf')):
    doc = fitz.open(path)
    pages = []
    warnings = []
    for i, page in enumerate(doc):
        text = page.get_text()
        if len(text.strip()) < 70:
            warnings.append(f'Sparse page {i+1}')
        if '\ufffd' in text:
            warnings.append(f'Replacement glyph on page {i+1}')
        for block in page.get_text('blocks'):
            if block[0] < 30 or block[2] > page.rect.width - 25 or block[3] > page.rect.height - 15:
                warnings.append(f'Bounds on page {i+1}: {block[:4]}')
        pix = page.get_pixmap(matrix=fitz.Matrix(0.65, 0.65), alpha=False)
        pages.append(Image.frombytes('RGB', [pix.width, pix.height], pix.samples))
    for start in range(0, len(pages), 12):
        batch = pages[start:start+12]
        w, h = batch[0].size
        sheet = Image.new('RGB', (3*w, ((len(batch)+2)//3)*(h+22)), '#d8dfe7')
        draw = ImageDraw.Draw(sheet)
        for j, img in enumerate(batch):
            x, y = (j%3)*w, (j//3)*(h+22)
            sheet.paste(img, (x, y+22))
            draw.text((x+8, y+4), f'{path.stem} | page {start+j+1}', fill='black')
        sheet.save(review / f'{path.stem}-{start//12+1}.png')
    results.append({'file':str(path), 'pages':len(doc), 'warnings':warnings})
(review / 'validation.json').write_text(json.dumps(results, indent=2))
print(json.dumps(results, indent=2))
