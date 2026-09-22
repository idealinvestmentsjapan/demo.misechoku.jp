from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import mm
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import KeepTogether, PageBreak, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle


ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "output" / "pdf" / "misechoku-service-video-script-review.pdf"
OUT.parent.mkdir(parents=True, exist_ok=True)

FONT_REGULAR = Path(r"C:\Windows\Fonts\YuGothM.ttc")
FONT_BOLD = Path(r"C:\Windows\Fonts\YuGothB.ttc")
if not FONT_REGULAR.exists():
    FONT_REGULAR = Path(r"C:\Windows\Fonts\msgothic.ttc")
if not FONT_BOLD.exists():
    FONT_BOLD = FONT_REGULAR

pdfmetrics.registerFont(TTFont("JP", str(FONT_REGULAR)))
pdfmetrics.registerFont(TTFont("JPBold", str(FONT_BOLD)))

PAGE = (108 * mm, 192 * mm)
INK = colors.HexColor("#211B16")
MUTED = colors.HexColor("#6F655B")
PAPER = colors.HexColor("#FFFDFA")
CREAM = colors.HexColor("#F6F0E7")
GOLD = colors.HexColor("#B58A48")
GOLD_DARK = colors.HexColor("#765426")
NIGHT = colors.HexColor("#17130F")
LINE = colors.HexColor("#E6DBCA")
OK = colors.HexColor("#356C55")

styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name="JPTitle", fontName="JPBold", fontSize=24, leading=32, textColor=colors.white, alignment=TA_LEFT, spaceAfter=6))
styles.add(ParagraphStyle(name="JPKicker", fontName="JPBold", fontSize=9, leading=12, textColor=colors.HexColor("#EFD9AD"), spaceAfter=14))
styles.add(ParagraphStyle(name="JPLeadWhite", fontName="JP", fontSize=11, leading=18, textColor=colors.HexColor("#E7DED4")))
styles.add(ParagraphStyle(name="JPH1", fontName="JPBold", fontSize=18, leading=25, textColor=INK, spaceAfter=12))
styles.add(ParagraphStyle(name="JPH2", fontName="JPBold", fontSize=15, leading=21, textColor=INK, spaceAfter=7))
styles.add(ParagraphStyle(name="JPBody", fontName="JP", fontSize=11.5, leading=19, textColor=INK, spaceAfter=7))
styles.add(ParagraphStyle(name="JPMuted", fontName="JP", fontSize=9.5, leading=16, textColor=MUTED))
styles.add(ParagraphStyle(name="JPLabel", fontName="JPBold", fontSize=8.5, leading=11, textColor=GOLD_DARK, spaceBefore=7, spaceAfter=4))
styles.add(ParagraphStyle(name="JPScreen", fontName="JPBold", fontSize=12.5, leading=19, textColor=GOLD_DARK))
styles.add(ParagraphStyle(name="JPNarr", fontName="JP", fontSize=13, leading=22, textColor=INK))
styles.add(ParagraphStyle(name="JPWhite", fontName="JP", fontSize=12, leading=20, textColor=colors.white))
styles.add(ParagraphStyle(name="JPHandoff", fontName="JPBold", fontSize=15, leading=24, textColor=colors.white, alignment=TA_LEFT))
styles.add(ParagraphStyle(name="JPFooter", fontName="JP", fontSize=7.5, leading=9, textColor=MUTED, alignment=TA_CENTER))


scenes = [
    ("0:00〜0:06", "1. サービス紹介", "夜職の採用を、もっと直接、わかりやすく。", "夜職の採用を、もっと直接、わかりやすく。ミセチョクをご紹介します。", "ロゴが現れ、店舗とキャストの写真を一本の線でつなぐ。"),
    ("0:06〜0:16", "2. ミセチョクとは", "出会いから採用後まで、ひとつに", "ミセチョクは、店舗とキャストが直接つながり、出会いから採用後の入店ボーナスまでを一つにつなぐ、夜職特化のマッチングサービスです。", "『探す→話す→採用→ボーナス』の4アイコンを順に表示する。"),
    ("0:16〜0:29", "3. 条件で探す", "条件で探す／プロフィールで比べる", "店舗情報や求人条件を掲載し、働き方や経験でキャストを検索。プロフィールを見ながら、気になる方を比較できます。", "店舗ページから検索へ移動。条件を2つ選び、候補者カードを3人ほど見せる。"),
    ("0:29〜0:42", "4. 保存して直接話す", "KEEPで保存／トークで直接連絡", "候補者はKEEPで保存。トークから直接メッセージを送り、体験入店や面談の候補日もやり取りできます。", "プロフィールでKEEPを押し、トークと面談候補日の画面へ移る。"),
    ("0:42〜0:54", "5. 採用後も確認", "入店ボーナスの進み具合も確認", "採用が決まった後は、入店ボーナスの申請や承認、入金から受け取りまでの進み具合を、画面で確認できます。", "申請、店舗確認、入金、受領までを一本の流れとして見せる。"),
    ("0:54〜1:06", "6. 操作はひとつながり", "探す・話す・管理する", "キャストを探す、話す、採用後を管理する。操作が一つにつながり、スマートフォンやタブレットから画面に沿って進められます。", "検索、プロフィール、トーク、管理画面をスマホとタブレット上で再表示する。"),
    ("1:06〜1:16", "7. 営業担当へつなぐ", "店舗とキャストが、直接つながる。", "お店とキャストが直接つながり、採用にかけるお金を、働く本人への後押しに。ミセチョクの詳しい活用方法は、担当者がご案内します。", "店舗とキャストを並べ、最後の3秒はロゴとメッセージだけを残す。"),
]


def header_footer(canvas, doc):
    canvas.saveState()
    w, h = PAGE
    if doc.page > 1:
        canvas.setStrokeColor(LINE)
        canvas.line(12 * mm, 10 * mm, w - 12 * mm, 10 * mm)
        canvas.setFont("JP", 7.5)
        canvas.setFillColor(MUTED)
        canvas.drawString(12 * mm, 6.3 * mm, "ミセチョク｜サービス紹介動画 台本")
        canvas.drawRightString(w - 12 * mm, 6.3 * mm, str(doc.page))
    canvas.restoreState()


doc = SimpleDocTemplate(
    str(OUT),
    pagesize=PAGE,
    leftMargin=12 * mm,
    rightMargin=12 * mm,
    topMargin=13 * mm,
    bottomMargin=14 * mm,
    title="ミセチョク 店舗向けサービス紹介動画 台本",
    author="ミセチョク",
)

story = []

# Cover
cover = Table([
    [Paragraph("社長確認用・初稿", styles["JPKicker"])],
    [Paragraph("ミセチョク<br/>サービス紹介動画 台本", styles["JPTitle"])],
    [Paragraph("サービス内容、店舗メリット、操作性に絞った短編動画です。", styles["JPLeadWhite"])],
    [Spacer(1, 8 * mm)],
    [Table([
        [Paragraph("<b>約76秒</b><br/><font size='7'>想定尺</font>", styles["JPWhite"]), Paragraph("<b>7場面</b><br/><font size='7'>構成</font>", styles["JPWhite"]), Paragraph("<b>縦読み</b><br/><font size='7'>スマホ向け</font>", styles["JPWhite"])],
    ], colWidths=[25 * mm] * 3, style=TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor("#302820")),
        ("BOX", (0, 0), (-1, -1), .5, colors.HexColor("#625345")),
        ("INNERGRID", (0, 0), (-1, -1), .5, colors.HexColor("#625345")),
        ("ALIGN", (0, 0), (-1, -1), "CENTER"),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 10),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 10),
    ]))],
], colWidths=[84 * mm], style=TableStyle([
    ("BACKGROUND", (0, 0), (-1, -1), NIGHT),
    ("BOX", (0, 0), (-1, -1), 0, NIGHT),
    ("LEFTPADDING", (0, 0), (-1, -1), 10 * mm),
    ("RIGHTPADDING", (0, 0), (-1, -1), 10 * mm),
    ("TOPPADDING", (0, 0), (-1, 0), 9 * mm),
    ("BOTTOMPADDING", (0, -1), (-1, -1), 12 * mm),
]))
story += [cover, PageBreak(), Paragraph("動画で伝えること", styles["JPH1"])]
for n, text in enumerate([
    "ミセチョクが何のサービスか",
    "店舗にどんなメリットがあるか",
    "どのような順番で操作するか",
], 1):
    story.append(Paragraph(f"<b>{n}</b>　{text}", styles["JPBody"]))
story += [Spacer(1, 3 * mm), Paragraph("料金、登録手順、店舗ごとの活用提案は営業担当から説明します。", styles["JPMuted"])]

for time, title, screen, narration, visual in scenes:
    story.append(PageBreak())
    chip = Table([[Paragraph(time, ParagraphStyle("chip", parent=styles["JPLabel"], textColor=colors.white, alignment=TA_CENTER, spaceBefore=0, spaceAfter=0))]], colWidths=[25 * mm], style=TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), GOLD_DARK),
        ("BOX", (0, 0), (-1, -1), 0, GOLD_DARK),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
    ]))
    story += [chip, Spacer(1, 5 * mm), Paragraph(title, styles["JPH1"])]
    story.append(Paragraph("画面に出す文字", styles["JPLabel"]))
    story.append(Table([[Paragraph(screen, styles["JPScreen"])]], colWidths=[84 * mm], style=TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor("#F3EADC")),
        ("BOX", (0, 0), (-1, -1), .5, colors.HexColor("#DFCBA9")),
        ("LEFTPADDING", (0, 0), (-1, -1), 10),
        ("RIGHTPADDING", (0, 0), (-1, -1), 10),
        ("TOPPADDING", (0, 0), (-1, -1), 10),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 10),
    ])))
    story.append(Paragraph("ナレーション", styles["JPLabel"]))
    story.append(Table([[Paragraph(narration, styles["JPNarr"])]], colWidths=[84 * mm], style=TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor("#FAF7F1")),
        ("LINEBEFORE", (0, 0), (0, -1), 3, GOLD),
        ("LEFTPADDING", (0, 0), (-1, -1), 11),
        ("RIGHTPADDING", (0, 0), (-1, -1), 10),
        ("TOPPADDING", (0, 0), (-1, -1), 12),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 12),
    ])))
    story.append(Paragraph("映像", styles["JPLabel"]))
    story.append(Paragraph(visual, styles["JPMuted"]))

story.append(PageBreak())
story += [Paragraph("動画終了後の営業トーク", styles["JPH1"]), Paragraph("ロゴ画面で止め、相手の関心に合わせて説明を続けます。", styles["JPBody"])]
story.append(Table([[Paragraph("「今の中で、いちばん気になったところはどこでしたか？」", styles["JPHandoff"])]], colWidths=[84 * mm], style=TableStyle([
    ("BACKGROUND", (0, 0), (-1, -1), NIGHT),
    ("LEFTPADDING", (0, 0), (-1, -1), 12),
    ("RIGHTPADDING", (0, 0), (-1, -1), 12),
    ("TOPPADDING", (0, 0), (-1, -1), 15),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 15),
])))
story += [Spacer(1, 8 * mm), Paragraph("品質チェック済み", styles["JPH1"])]
checks = [
    "サービス内容、店舗メリット、操作の流れを説明",
    "料金や登録のお願いは動画に入れていない",
    "他社批判や効果を保証する表現は使っていない",
    "音声なしでも要点が伝わる短い字幕を設定",
    "営業担当が会話を広げられる終わり方",
]
for item in checks:
    story.append(Paragraph(f"<font color='#356C55'><b>✓</b></font>　{item}", styles["JPBody"]))
story += [Spacer(1, 6 * mm), Paragraph("ご確認いただきたい点", styles["JPH1"])]
for title, detail in [
    ("① サービスの説明", "初めて見る店舗にも内容が伝わるか"),
    ("② 一番伝えたい価値", "『店舗とキャストが直接つながる』が中心になっているか"),
    ("③ 最後のメッセージ", "採用費を働く本人への後押しにする考え方が適切か"),
]:
    story.append(KeepTogether([Paragraph(title, styles["JPH2"]), Paragraph(detail, styles["JPMuted"]), Spacer(1, 4 * mm)]))

doc.build(story, onFirstPage=header_footer, onLaterPages=header_footer)
print(OUT)
