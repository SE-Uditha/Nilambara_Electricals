"""Render every page; verify page geometry, text bounds, links, Unicode and content coverage."""
from pathlib import Path
from collections import Counter
import hashlib, html.parser, json, re
import fitz
from PIL import Image, ImageDraw

ROOT=Path(__file__).resolve().parents[1]
REVIEW=ROOT/'documentation/evidence/pdf-review'
REVIEW.mkdir(parents=True,exist_ok=True)
class Links(html.parser.HTMLParser):
    def __init__(self):super().__init__();self.ids=[];self.links=[]
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if 'id'in a:self.ids.append(a['id'])
        if a.get('href','').startswith('#'):self.links.append(a['href'][1:])

results=[]
for path in sorted((ROOT/'documentation/final-pdfs').glob('*.pdf')):
    document=fitz.open(path);texts=[];warnings=[];images=[];all_fonts=set();table_pages=[]
    for stale in REVIEW.glob(path.stem+'-*.png'):
        match=re.fullmatch(re.escape(path.stem)+r'-(\d+)\.png',stale.name)
        sheet=re.fullmatch(re.escape(path.stem)+r'-sheet-(\d+)\.png',stale.name)
        if (match and int(match[1])>len(document)) or (sheet and int(sheet[1])>(len(document)+15)//16):
            stale.unlink()  # only stale render outputs inside this verified review directory
    parser=Links();parser.feed((ROOT/'documentation/sources'/(path.stem+'.html')).read_text(encoding='utf-8'))
    duplicate=[k for k,n in Counter(parser.ids).items() if n>1]
    missing=[k for k in parser.links if k not in parser.ids]
    if duplicate or missing:warnings.append({'duplicate_ids':duplicate,'missing_anchors':missing})
    links=sum(len(page.get_links()) for page in document)
    for i,page in enumerate(document):
        text=page.get_text();texts.append(text)
        if len(text.strip())<85:warnings.append(f'Sparse/blank page {i+1}')
        if '\ufffd' in text:warnings.append(f'Replacement glyph on page {i+1}')
        if abs(min(page.rect.width,page.rect.height)-595.28)>2 or abs(max(page.rect.width,page.rect.height)-841.89)>2:warnings.append(f'Non-A4 geometry page {i+1}')
        for block in page.get_text('dict')['blocks']:
            if block.get('type')!=0:continue
            for line in block['lines']:
                for span in line['spans']:
                    x0,y0,x1,y1=span['bbox']
                    all_fonts.add(span['font'])
                    if x0<12 or x1>page.rect.width-12 or y0<7 or y1>page.rect.height-7:warnings.append(f'Text outside safe page bounds: {i+1} {span["text"][:40]}')
        pix=page.get_pixmap(matrix=fitz.Matrix(.9,.9),alpha=False)
        target=REVIEW/f'{path.stem}-{i+1:03}.png';pix.save(target)
        image=Image.frombytes('RGB',[pix.width,pix.height],pix.samples)
        image.thumbnail((290,410))
        images.append(image)
    for start in range(0,len(images),16):
        batch=images[start:start+16]
        sheet=Image.new('RGB',(1200,((len(batch)+3)//4)*440),'#cdd8e3');draw=ImageDraw.Draw(sheet)
        for j,image in enumerate(batch):
            x=(j%4)*300;y=(j//4)*440
            sheet.paste(image,(x+(300-image.width)//2,y+23))
            draw.text((x+8,y+5),str(start+j+1),fill='black')
        sheet.save(REVIEW/f'{path.stem}-sheet-{start//16+1:02}.png')
    combined='\n'.join(texts)
    if path.stem.endswith('User_Manual'):
        for n in range(1,21):
            if f'{n}.' not in combined:warnings.append(f'Missing chapter {n}')
        if not any('Figure 45.' in t for t in texts):warnings.append('Screenshot atlas incomplete')
    if path.stem.endswith('Feature_Guidance'):
        for n in range(1,104):
            if f'F-{n:03}' not in combined:warnings.append(f'Missing feature F-{n:03}')
    if path.stem.endswith('Results'):
        for n in range(1,219):
            if f'T13-{n:03}' not in combined:warnings.append(f'Missing test case T13-{n:03}')
        if not re.search('[\u0d80-\u0dff]',combined):warnings.append('Sinhala text missing')
        for word in ['218','3566','327']:
            if word not in combined:warnings.append('Missing evidence total '+word)
    result={'file':str(path),'pages':len(document),'portrait':sum(p.rect.width<p.rect.height for p in document),'landscape':sum(p.rect.width>p.rect.height for p in document),'bytes':path.stat().st_size,'sha256':hashlib.sha256(path.read_bytes()).hexdigest(),'internal_or_external_links':links,'outline_entries':len(document.get_toc()),'fonts':sorted(all_fonts),'warnings':warnings,'rendered_pages':len(images)}
    results.append(result)
(ROOT/'documentation/evidence/document-verification.json').write_text(json.dumps(results,indent=2),encoding='utf-8')
print(json.dumps(results,indent=2))
if any(r['warnings'] for r in results):raise SystemExit(1)
