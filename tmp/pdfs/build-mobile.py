from pathlib import Path
import json, math, re
from html import escape
from functools import lru_cache

from reportlab.pdfgen import canvas
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.lib.colors import HexColor, Color, white
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.utils import ImageReader
from reportlab.platypus import Paragraph
from reportlab.lib.enums import TA_LEFT, TA_CENTER
from PIL import Image

ROOT = Path(__file__).resolve().parents[2]
TMP = ROOT / 'tmp/pdfs'
OUT = ROOT / 'output/pdf/misechoku-shop-service-mobile.pdf'
OUT.parent.mkdir(parents=True, exist_ok=True)
DATA = json.loads((TMP/'content.json').read_text(encoding='utf8'))
pdfmetrics.registerFont(TTFont('JP', 'C:/Windows/Fonts/meiryo.ttc', subfontIndex=0))
pdfmetrics.registerFont(TTFont('JPB', 'C:/Windows/Fonts/meiryob.ttc', subfontIndex=0))
pdfmetrics.registerFontFamily('JP', normal='JP', bold='JPB', italic='JP', boldItalic='JPB')

MM = 72/25.4
W, H = 105*MM, 220*MM
M = 19
CW = W-2*M
INK = '#241f33'
MUTED = '#625b70'
PURPLE = '#6d28d9'
LINE = '#e2dce8'
LIGHT = '#f6f3f9'
GOLD = '#d4af37'
TITLE_BREAKS = {
    '直接採用と本人への還元の仕組み': '直接採用と\n本人への還元の仕組み',
    '入店ボーナスの条件設定・支払い管理': '入店ボーナスの\n条件設定・支払い管理',
    'プロフィールの確認・候補者の保存': 'プロフィールの確認・\n候補者の保存',
    'メッセージ・日程調整・採用状況の管理': 'メッセージ・日程調整・\n採用状況の管理',
    'スマートフォン・タブレットでの利用': 'スマートフォン・\nタブレットでの利用',
    '接客タイプ診断による候補者の理解': '接客タイプ診断による\n候補者の理解',
}

def para(text, size=12, leading=19.5, color=INK, bold=False, center=False, markup=False):
    st = ParagraphStyle('p', fontName='JPB' if bold else 'JP', fontSize=size,
        leading=leading, textColor=HexColor(color), wordWrap='CJK',
        alignment=TA_CENTER if center else TA_LEFT, splitLongWords=True,
        spaceBefore=0, spaceAfter=0, allowWidows=0, allowOrphans=0)
    return Paragraph(text if markup else escape(text).replace('\n','<br/>'), st)

def ph(p,w=CW): return p.wrap(w,H)[1]

class Book:
    def __init__(self, file, total=0, destinations=None):
        self.c = canvas.Canvas(str(file), pagesize=(W,H), pageCompression=1)
        self.c.setTitle('ミセチョク｜店舗向けサービス説明（スマホ版）')
        self.c.setAuthor('ミセチョク')
        self.c.setSubject('サービスコンセプト・基本機能・キラーコンテンツ')
        self.total = total
        self.dest = destinations or {}
        self.map = {}
        self.pages = []
        self.page = 0
        self.y = H-42

    def start(self, section, key=None, outline=None):
        if self.page: self.c.showPage()
        self.page += 1
        self.y = H-43
        self.c.setFillColor(white); self.c.rect(0,0,W,H,fill=1,stroke=0)
        self.c.setFillColor(HexColor(PURPLE)); self.c.rect(M,H-22,24,3,fill=1,stroke=0)
        self.text('ミセチョク  /  店舗向けサービス説明', M+31,H-15,8,11,MUTED)
        self.c.setStrokeColor(HexColor(LINE)); self.c.setLineWidth(.5)
        self.c.line(M,31,W-M,31)
        self.c.setFont('JP',8); self.c.setFillColor(HexColor(MUTED))
        self.c.drawString(M,16,section)
        self.c.drawRightString(W-M,16,f'{self.page:02d}' + (f' / {self.total:02d}' if self.total else ''))
        if self.page>2:
            self.c.setFillColor(HexColor(PURPLE)); self.c.drawRightString(W-M,H-24,'目次')
            self.c.linkRect('', 'contents', (W-M-29,H-29,W-M,H-10), relative=0, thickness=0)
        if key:
            self.map[key]=self.page
            self.c.bookmarkPage(key)
            if outline: self.c.addOutlineEntry(outline,key,0,False)
        self.pages.append({'page':self.page,'section':section,'key':key})

    def text(self,text,x,y,size=12,leading=19.5,color=INK,bold=False,width=None,center=False):
        p=para(text,size,leading,color,bold,center)
        width=width or CW
        h=ph(p,width)
        if y-h < 31 and size>=10:
            raise ValueError(f'Page {self.page}: text below content area: {text[:30]}, {y-h}')
        p.drawOn(self.c,x,y-h)
        return h

    def p(self,text,size=12,leading=19.5,color=INK,bold=False,gap=10):
        self.y-=self.text(text,M,self.y,size,leading,color,bold)
        self.y-=gap

    def rect(self,x,y,w,h,fill,stroke=None,radius=9):
        self.c.setFillColor(HexColor(fill)); self.c.setStrokeColor(HexColor(stroke or fill))
        self.c.setLineWidth(.6)
        self.c.roundRect(x,y-h,w,h,radius,fill=1,stroke=1)

    def title(self,category,title,continued=False):
        self.p(category+('  /  続き' if continued else ''),9.5,14,PURPLE,True,8)
        self.p(TITLE_BREAKS.get(title,title),18,27,INK,True,17)

    def finish(self):
        self.c.save()

class Block:
    def __init__(self,kind,text='',label='',sub=''):
        self.kind,self.text,self.label,self.sub=kind,text,label,sub

    def pieces(self):
        k=self.kind
        if k=='benefit':
            return [(para('店舗にとってのメリット',10,15,PURPLE,True),7), (para(self.text),0)]
        if k=='fact':
            a=[]
            if self.label: a.append((para(self.label,10.5,16,MUTED,True),8))
            if self.sub: a.append((para(self.sub,12,18,INK,True),4))
            a.append((para(self.text),0))
            return a
        if k=='purpose':
            return [(para('この仕組みの目的',10.5,16,'#785606',True),7),(para(self.text),0)]
        if k=='path':
            a=[]
            if self.sub:a.append((para('画面Path',10.5,16,MUTED,True),8))
            a.extend([(para(self.label,10.5,16,MUTED),4), (para(self.text,10.5,16.5,PURPLE),0)])
            return a
        return [(para(self.text,10.5,17,MUTED),0)]

    def height(self):
        inset=12 if self.kind in ['benefit','purpose'] else 0
        return sum(ph(p,CW-2*inset)+gap for p,gap in self.pieces()) + 2*inset+12

    def draw(self,b):
        h=self.height()
        inset=12 if self.kind in ['benefit','purpose'] else 0
        if self.kind in ['benefit','purpose']:
            b.rect(M,b.y,CW,h-12,LIGHT if self.kind=='benefit' else '#fbf7e9',LINE if self.kind=='benefit' else '#ecddb2')
        if self.kind=='path' and self.sub:
            b.c.setStrokeColor(HexColor(LINE)); b.c.line(M,b.y+3,W-M,b.y+3)
        y=b.y-inset
        for p,gap in self.pieces():
            hh=ph(p,CW-2*inset)
            p.drawOn(b.c,M+inset,y-hh)
            y-=hh+gap
        b.y-=h

def split_blocks(blocks,cap):
    heights=[x.height() for x in blocks]
    @lru_cache(None)
    def solve(i):
        if i==len(blocks):return (0,[])
        best=(float('inf'),[]); used=0
        for j in range(i,len(blocks)):
            used+=heights[j]
            if used>cap:break
            score,pages=solve(j+1)
            # Penalize sparse pages, while always preferring fewer pages.
            cost=1_000_000+(cap-used)**2+score
            if cost<best[0]:best=(cost,[(i,j+1)]+pages)
        return best
    res=solve(0)[1]
    if not res:raise ValueError('Block exceeds page capacity')
    return res

def scene_text(b,s):
    blocks=[Block('benefit',s['benefit'])]
    facts=s['details'] or [{'title':'','text':t} for t in s['facts']]
    for i,f in enumerate(facts):
        blocks.append(Block('fact',f['text'],'機能・仕組み' if i==0 else '',f['title']))
    if s['purpose']: blocks.append(Block('purpose',s['purpose']))
    for t in s['conditions']:blocks.append(Block('note',t))
    for i,p in enumerate(s['paths']):blocks.append(Block('path',p['code'],p['label'],'first' if i==0 else ''))
    for t in s['pathNotes']:blocks.append(Block('note',t))
    if s['id']=='s1':blocks.append(Block('note',s['diagramContext']))
    title_h=ph(para(TITLE_BREAKS.get(s['title'],s['title']),18,27,bold=True))
    cap=H-43-(14+8+title_h+17)-43
    spans=split_blocks(blocks,cap)
    for n,(a,z) in enumerate(spans):
        b.start(s['category'],s['id'] if n==0 else None,s['title'] if n==0 else None)
        b.title(s['category'],s['title'],n>0)
        for block in blocks[a:z]:block.draw(b)
        if b.y<40:raise ValueError(f'Overflow {s["id"]}: {b.y}')

def figure_page(b,s):
    b.start(s['category']+'  /  画面イメージ')
    b.p(TITLE_BREAKS.get(s['title'],s['title']),15,22,INK,True,10)
    b.p('画面イメージ・実画面に差し替え予定',9.5,15,PURPLE,True,10)
    caption_h=ph(para(s['caption'],10.5,17,MUTED))
    ref=f'機能の説明・画面Path：{b.map[s["id"]]}ページから'
    reserved=caption_h+16+17+43
    avail=b.y-reserved
    im=Image.open(TMP/s['figure'])
    iw,ih=im.size
    scale=min(CW/iw,avail/ih)
    dw,dh=iw*scale,ih*scale
    x=(W-dw)/2
    b.c.drawImage(ImageReader(im),x,b.y-dh,dw,dh,mask='auto')
    b.y-=dh+12
    b.p(s['caption'],10.5,17,MUTED,gap=8)
    y0=b.y
    b.p(ref,9.5,15,PURPLE,gap=0)
    b.c.linkRect('',s['id'],(M,y0-17,W-M,y0+2),relative=0,thickness=0)

def arrow_down(b,x,y,color):
    c=b.c;c.setStrokeColor(HexColor(color));c.setFillColor(HexColor(color));c.setLineWidth(1.4)
    c.line(x,y,x,y-15)
    p=c.beginPath();p.moveTo(x-3,y-11);p.lineTo(x,y-15);p.lineTo(x+3,y-11);c.drawPath(p,stroke=1,fill=0)

def arrow_h(b,x1,x2,y,color):
    c=b.c;c.setStrokeColor(HexColor(color));c.setLineWidth(1.3);c.line(x1,y,x2,y)
    for x,dx in [(x1,3),(x2,-3)]:
        p=c.beginPath();p.moveTo(x+dx,y+3);p.lineTo(x,y);p.lineTo(x+dx,y-3);c.drawPath(p,stroke=1,fill=0)

def diagram(b,after):
    title='ミセチョクが目指す世界' if after else 'これまでの構造'
    color='#70449a' if after else '#9c505d'
    bg='#f5effc' if after else '#fcf1f1'
    b.start('コンセプトと特徴  /  仕組みの比較')
    b.p(title,19,28,INK,True,8)
    b.p('図解イメージ・差し替え予定',9.5,15,color,gap=10)
    b.p('店舗とキャストがサービス内で連絡' if after else '紹介料や継続的な支払いがある場合の例',10.5,17,MUTED,gap=13)
    y=b.y
    b.rect(M,y,CW,145,bg)
    b.text('連絡・条件確認の経路',M+12,y-12,10.5,16,color,True)
    cy=y-39
    if after:
        nw=72
        for x,name,sub in [(M+12,'店舗','候補者を検索'),(W-M-12-nw,'キャスト','求人を確認')]:
            b.rect(x,cy,nw,51,'#ffffff','#d5c2ea',8)
            b.text(name,x,cy-7,12,18,color,True,nw,True)
            b.text(sub,x,cy-29,9,14,MUTED,width=nw,center=True)
        arrow_h(b,M+89,W-M-89,cy-22,color)
        b.text('直接トーク',M+86,cy-2,9.5,14,color,True,CW-172,True)
        b.text('条件確認',M+86,cy-30,9,14,MUTED,width=CW-172,center=True)
        b.text('ミセチョク',M+12,cy-62,11,17,color,True,CW-24,True)
        b.text('検索・プロフィール・トークを利用',M+12,cy-81,10,16,MUTED,width=CW-24,center=True)
    else:
        nw=65; gap=(CW-24-3*nw)/2
        xs=[M+12+i*(nw+gap) for i in range(3)]
        for x,name in zip(xs,['店舗','紹介・\nスカウト','キャスト']):
            b.rect(x,cy,nw,50,'#ffffff','#e8cbd0',8)
            b.text(name,x,cy-(7 if '\n' in name else 16),11,17,color,True,nw,True)
        for i in range(2):arrow_h(b,xs[i]+nw+2,xs[i+1]-2,cy-25,color)
        b.text('紹介・条件調整を介してつながる',M+12,cy-63,10.5,17,color,width=CW-24,center=True)
        b.text('紹介された相手と採用を検討',M+12,cy-84,10,16,MUTED,width=CW-24,center=True)
    b.y-=158
    y=b.y
    b.rect(M,y,CW,232,'#fcfaf6','#e9e0ce')
    b.text('採用に関する支払い先',M+12,y-12,10.5,16,'#785606',True)
    b.text('店舗の採用費',M+12,y-40,13,20,INK,True,CW-24,True)
    b.text('ミセチョクで請求・入金を管理' if after else '紹介料・スカウトバック',M+12,y-65,10.5,17,MUTED,width=CW-24,center=True)
    arrow_down(b,W/2,y-87,'#ad8841')
    ry=y-111
    b.rect(M+13,ry,CW-26,56,'#fff1cc' if after else '#f7e5e6','#e7d09b' if after else '#dfb9bd',8)
    b.text('条件を満たしたキャスト本人' if after else '紹介側への支払い',M+19,ry-9,12,19,'#785606' if after else color,True,CW-38,True)
    b.text('入店ボーナスを支払う' if after else '紹介料や継続的な報酬を支払う',M+19,ry-32,10.5,17,MUTED,width=CW-38,center=True)
    b.text('サービス運営：本人への報酬とは別に運営手数料' if after else '継続的な支払いがある場合は、就業中も紹介側への支払いが発生',M+14,y-181,10.5,17,MUTED,width=CW-28)
    b.y-=244
    b.p('ボーナスの支給条件と、申請から受領までの状況を管理します。' if after else 'この紹介料・報酬の支払い先は、働く本人ではなく紹介側です。',11,18,color,True,8)
    b.p('給与の支払いは省略しています。' if not after else '入店ボーナスの支給には条件があります。',9.5,15,MUTED,gap=0)

def trail_page(b,s):
    b.start('コンセプトと特徴  /  入店ボーナスの手続き')
    b.title('コンセプトと特徴','入店ボーナスの手続き')
    for i,step in enumerate(s['trail']):
        y=b.y
        b.rect(M,y,CW,61,LIGHT if i<4 else '#fbf7e9',LINE if i<4 else '#e5d18c')
        b.c.setFillColor(HexColor(PURPLE if i<4 else '#ad8423'));b.c.circle(M+20,y-23,11,fill=1,stroke=0)
        b.text(str(i+1),M+9,y-14,10,15,'#ffffff',True,22,True)
        b.text(step['title'],M+40,y-9,12,19,INK,True,CW-51)
        b.text(step['text'],M+40,y-33,10.5,17,MUTED,width=CW-51)
        b.y-=73
    b.p('具体的な手続きは、次の「入店ボーナスの条件設定・支払い管理」に記載しています。',10.5,17,MUTED,gap=0)

def cover(b):
    b.start('サービスコンセプト・機能紹介','cover')
    b.y-=9
    b.p('営業担当者向け',10,15,PURPLE,True,15)
    b.p('ミセチョク\n店舗向けサービス説明',22,33,INK,True,19)
    b.p(DATA['intro'],12,20,INK,gap=18)
    for title,sub,key in [
        ('1．サービスコンセプトと特徴','直接採用・本人への還元・入店ボーナス','s1'),
        ('2．アプリの機能紹介','① 基本機能  /  ② キラーコンテンツ','s4')]:
        y=b.y
        b.rect(M,y,CW,62,LIGHT,LINE)
        b.text(title,M+12,y-10,12,18,INK,True,CW-24)
        b.text(sub,M+12,y-35,9.5,15,MUTED,width=CW-24)
        b.c.linkRect('',key,(M,y-62,W-M,y),relative=0,thickness=0)
        b.y-=73
    b.p('画面イメージとPathについて',10.5,16,PURPLE,True,6)
    for p in DATA['disclosure']:b.p(p,10,16,MUTED,gap=8)

def contents(b):
    b.start('目次','contents','目次')
    b.p('目次',21,30,INK,True,8)
    b.p('項目名をタップすると移動できます。',10,16,MUTED,gap=18)
    groups=[('1．サービスコンセプトと特徴',DATA['scenes'][:3]),
            ('2．アプリの機能紹介  /  ① 基本機能',DATA['scenes'][3:10]),
            ('2．アプリの機能紹介  /  ② キラーコンテンツ',DATA['scenes'][10:])]
    aliases={
        's1':'直接採用と本人への還元', 's7':'入店ボーナスの条件設定・支払い管理',
        's2':'サービス全体で管理できること', 's4':'キャスト検索',
        's5':'プロフィールの確認・候補者保存','s6':'メッセージ・日程調整・採用管理',
        'quick-replies':'メッセージ定型文・マイ定型文','s3':'店舗情報・求人条件の掲載',
        'input-support':'求人入力のテンプレート','s8':'スマートフォン・タブレットでの利用',
        'ai-search':'AI店舗検索からの求人紹介','type-test':'接客タイプ診断',
        'photo-gallery':'写真投稿・ギャラリー','sns-share':'SNS共有・求人URLの案内'}
    for title,scenes in groups:
        b.p(title,10,16,PURPLE,True,7)
        for s in scenes:
            y=b.y
            num=b.dest.get(s['id'],'--')
            h=b.text(aliases[s['id']],M,y,10.5,17,INK,width=CW-25)
            b.text(str(num),W-M-19,y,10.5,17,PURPLE,True,19,True)
            b.c.linkRect('',s['id'],(M,y-h-4,W-M,y+2),relative=0,thickness=0)
            b.y-=h+8
        b.y-=12

def build(file,total=0,dest=None):
    b=Book(file,total,dest)
    cover(b);contents(b)
    for s in DATA['scenes']:
        scene_text(b,s)
        if s['id']=='s1':
            diagram(b,False);diagram(b,True);trail_page(b,s)
        else:figure_page(b,s)
    b.finish()
    return b

first=build(TMP/'first-pass.pdf')
final=build(OUT,first.page,first.map)
(TMP/'manifest.json').write_text(json.dumps({'pages':final.pages,'destinations':final.map,'pageCount':final.page,'sizeMm':[105,220],'fontPt':12},ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps({'output':str(OUT),'pages':final.page,'bytes':OUT.stat().st_size,'destinations':final.map},ensure_ascii=False))
