#!/usr/bin/env python3
"""
تبدیل markdown فارسی به فایل ورد.

چرا این فایل هست: روی این ماشین نه pandoc هست نه python-docx، و سند طرح
باید به شکل ورد هم در دسترس باشد. docx در واقع یک zip از چند فایل XML
است، پس با کتابخانه‌ی استاندارد پایتون ساختنی است.

فقط همان چیزهایی را می‌شناسد که در سند طرح به کار رفته‌اند: سرفصل،
پاراگراف، فهرست، جدول، بلوک کد، نقل‌قول و خط جداکننده. عمداً یک مبدل
عمومی markdown نیست؛ چیزی که لازم نداریم را هم پشتیبانی نمی‌کند.

راست‌به‌چپ بودن روی هر پاراگراف و هر بازه‌ی متن جداگانه اعلام می‌شود،
چون ورد جهت را از سند به ارث نمی‌دهد و اگر جا بیفتد، متن فارسی با
نقطه‌گذاری جابه‌جا دیده می‌شود.

روش استفاده:
    python3 md2docx.py ورودی.md خروجی.docx
"""

import re
import sys
import zipfile
from xml.sax.saxutils import escape

FONT = "Tahoma"

NS = (
    'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
    'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
)


def esc(text):
    """متن خام را برای نشستن داخل XML امن می‌کند."""
    return escape(text, {'"': "&quot;"})


# ─────────────────────────────────────────────────────────────────────
# متن درون‌خطی
# ─────────────────────────────────────────────────────────────────────

def runs(text):
    """
    یک خط markdown را به بازه‌های ورد می‌شکند.

    سه نشانه شناخته می‌شود: **پررنگ**، `کد` و [متن](نشانی). پیوند
    به‌صورت «متن (نشانی)» صاف می‌شود — افزودن پیوند واقعی یک فایل rels
    جدا می‌خواهد و ارزشش را ندارد وقتی سند برای خواندن است نه کلیک.
    """
    text = re.sub(r"\[([^\]]+)\]\(([^)]+)\)", r"\1 (\2)", text)

    out = []
    # هر تکه یا **پررنگ** است، یا `کد`، یا متن ساده
    for piece in re.split(r"(\*\*[^*]+\*\*|`[^`]+`)", text):
        if not piece:
            continue

        if piece.startswith("**") and piece.endswith("**"):
            out.append(run(piece[2:-2], bold=True))
        elif piece.startswith("`") and piece.endswith("`"):
            out.append(run(piece[1:-1], mono=True))
        else:
            out.append(run(piece))

    return "".join(out)


def run(text, bold=False, mono=False, size=None):
    """یک بازه‌ی متن، با جهت راست‌به‌چپ صریح."""
    props = ['<w:rFonts w:ascii="%s" w:hAnsi="%s" w:cs="%s"/>' % (
        ("Consolas", "Consolas", "Consolas") if mono else (FONT, FONT, FONT)
    )]

    if bold:
        props.append("<w:b/><w:bCs/>")
    if size:
        props.append('<w:sz w:val="%d"/><w:szCs w:val="%d"/>' % (size, size))

    props.append("<w:rtl/>")

    return (
        "<w:r><w:rPr>%s</w:rPr>"
        '<w:t xml:space="preserve">%s</w:t></w:r>' % ("".join(props), esc(text))
    )


def para(inner, style=None, size=None, before=0, after=120,
         shade=None, indent=0, mono=False):
    """یک پاراگراف راست‌به‌چپ."""
    props = ['<w:bidi/>', '<w:jc w:val="both"/>']

    if style:
        props.insert(0, '<w:pStyle w:val="%s"/>' % style)
    if shade:
        props.append('<w:shd w:val="clear" w:fill="%s"/>' % shade)
    if indent:
        props.append('<w:ind w:right="%d"/>' % indent)

    props.append('<w:spacing w:before="%d" w:after="%d" w:line="288" '
                 'w:lineRule="auto"/>' % (before, after))

    rpr = []
    if size:
        rpr.append('<w:sz w:val="%d"/><w:szCs w:val="%d"/>' % (size, size))
    if rpr:
        props.append("<w:rPr>%s</w:rPr>" % "".join(rpr))

    return "<w:p><w:pPr>%s</w:pPr>%s</w:p>" % ("".join(props), inner)


# ─────────────────────────────────────────────────────────────────────
# جدول
# ─────────────────────────────────────────────────────────────────────

def cell(text, header=False, width=2000):
    """یک خانه‌ی جدول. سرستون پس‌زمینه و متن پررنگ دارد."""
    shade = '<w:shd w:val="clear" w:fill="EFE7DD"/>' if header else ""

    body = para(runs(text) if not header else run(text, bold=True),
                after=40, size=19)

    return (
        "<w:tc><w:tcPr>"
        '<w:tcW w:w="%d" w:type="dxa"/>%s'
        '<w:vAlign w:val="center"/>'
        "</w:tcPr>%s</w:tc>" % (width, shade, body)
    )


def table(rows):
    """
    جدول با ستون‌های هم‌عرض.

    ترتیب خانه‌ها وارونه می‌شود: ورد ستون‌ها را از چپ می‌چیند، پس
    برای اینکه ستون اول سند سمت راست بنشیند باید معکوس نوشته شوند.
    """
    n = max(len(r) for r in rows)
    width = 9000 // n

    borders = "".join(
        '<w:%s w:val="single" w:sz="4" w:space="0" w:color="D4CCC2"/>' % side
        for side in ("top", "left", "bottom", "right", "insideH", "insideV")
    )

    out = [
        "<w:tbl><w:tblPr>"
        '<w:tblStyle w:val="TableGrid"/>'
        '<w:bidiVisual/>'
        '<w:tblW w:w="9000" w:type="dxa"/>'
        "<w:tblBorders>%s</w:tblBorders>"
        "</w:tblPr>" % borders
    ]

    for i, row in enumerate(rows):
        cells = list(row) + [""] * (n - len(row))
        out.append("<w:tr>")
        for c in reversed(cells):
            out.append(cell(c, header=(i == 0), width=width))
        out.append("</w:tr>")

    out.append("</w:tbl>")
    # ورد بعد از جدول یک پاراگراف می‌خواهد وگرنه جدول بعدی به آن می‌چسبد
    out.append(para("", after=120))

    return "".join(out)


# ─────────────────────────────────────────────────────────────────────
# پیمایش markdown
# ─────────────────────────────────────────────────────────────────────

# اندازه‌ی سرفصل‌ها بر حسب نیم‌نقطه (ورد واحدش همین است)
HEADING = {1: (36, 240), 2: (28, 300), 3: (23, 240), 4: (21, 200)}


def convert(md):
    """markdown را به بدنه‌ی document.xml تبدیل می‌کند."""
    body = []
    lines = md.split("\n")
    i = 0

    while i < len(lines):
        line = lines[i]
        stripped = line.strip()

        # ── بلوک کد
        if stripped.startswith("```"):
            i += 1
            code = []
            while i < len(lines) and not lines[i].strip().startswith("```"):
                code.append(lines[i])
                i += 1
            i += 1

            for c in code:
                body.append(para(
                    run(c if c.strip() else " ", mono=True, size=17),
                    after=0, shade="F4F1ED",
                ))
            body.append(para("", after=120))
            continue

        # ── جدول
        if stripped.startswith("|") and stripped.endswith("|"):
            rows = []
            while i < len(lines) and lines[i].strip().startswith("|"):
                raw = lines[i].strip().strip("|")
                # خط جداکننده‌ی سرستون محتوا نیست
                if not re.fullmatch(r"[\s|:-]+", raw):
                    rows.append([c.strip() for c in raw.split("|")])
                i += 1

            if rows:
                body.append(table(rows))
            continue

        # ── خط جداکننده
        if re.fullmatch(r"-{3,}|\*{3,}|_{3,}", stripped):
            body.append(
                "<w:p><w:pPr><w:bidi/><w:pBdr>"
                '<w:bottom w:val="single" w:sz="6" w:space="1" w:color="D4CCC2"/>'
                "</w:pBdr></w:pPr></w:p>"
            )
            i += 1
            continue

        # ── سرفصل
        m = re.match(r"^(#{1,4})\s+(.*)", stripped)
        if m:
            level = len(m.group(1))
            size, before = HEADING[level]
            body.append(para(
                run(m.group(2), bold=True, size=size),
                before=before, after=120,
            ))
            i += 1
            continue

        # ── نقل‌قول
        if stripped.startswith(">"):
            quote = []
            while i < len(lines) and lines[i].strip().startswith(">"):
                quote.append(lines[i].strip().lstrip(">").strip())
                i += 1
            body.append(para(runs(" ".join(quote)), shade="F4F1ED", indent=200))
            continue

        # ── فهرست
        if re.match(r"^[-*]\s+", stripped) or re.match(r"^\d+\.\s+", stripped):
            item = re.sub(r"^([-*]|\d+\.)\s+", "", stripped)
            body.append(para(
                run("•  ") + runs(item), indent=280, after=60,
            ))
            i += 1
            continue

        # ── خط خالی
        if not stripped:
            i += 1
            continue

        # ── پاراگراف: خطهای پیاپی یک پاراگراف‌اند
        chunk = []
        while i < len(lines) and lines[i].strip() and not re.match(
            r"^(#{1,4}\s|[-*]\s|\d+\.\s|>|\||```|-{3,}$)", lines[i].strip()
        ):
            chunk.append(lines[i].strip())
            i += 1

        if chunk:
            body.append(para(runs(" ".join(chunk))))

    return "".join(body)


# ─────────────────────────────────────────────────────────────────────
# بسته‌بندی
# ─────────────────────────────────────────────────────────────────────

CONTENT_TYPES = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>"""

RELS = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>"""

DOC_RELS = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>"""

STYLES = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles %s>
<w:docDefaults><w:rPrDefault><w:rPr>
<w:rFonts w:ascii="%s" w:hAnsi="%s" w:cs="%s"/>
<w:sz w:val="21"/><w:szCs w:val="21"/>
</w:rPr></w:rPrDefault>
<w:pPrDefault><w:pPr><w:bidi/></w:pPr></w:pPrDefault>
</w:docDefaults>
<w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/>
<w:tblPr><w:tblCellMar>
<w:top w:w="80" w:type="dxa"/><w:left w:w="100" w:type="dxa"/>
<w:bottom w:w="80" w:type="dxa"/><w:right w:w="100" w:type="dxa"/>
</w:tblCellMar></w:tblPr></w:style>
</w:styles>""" % (NS, FONT, FONT, FONT)

# A4 با حاشیه‌ی حدود ۲ سانتی‌متر، و بخش راست‌به‌چپ
SECTION = (
    "<w:sectPr>"
    '<w:pgSz w:w="11906" w:h="16838"/>'
    '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" '
    'w:header="709" w:footer="709" w:gutter="0"/>'
    "<w:bidi/>"
    "</w:sectPr>"
)


def build(md, out_path):
    document = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        "<w:document %s><w:body>%s%s</w:body></w:document>"
        % (NS, convert(md), SECTION)
    )

    with zipfile.ZipFile(out_path, "w", zipfile.ZIP_DEFLATED) as z:
        z.writestr("[Content_Types].xml", CONTENT_TYPES)
        z.writestr("_rels/.rels", RELS)
        z.writestr("word/_rels/document.xml.rels", DOC_RELS)
        z.writestr("word/styles.xml", STYLES)
        z.writestr("word/document.xml", document)

    return len(document)


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit("روش استفاده: md2docx.py ورودی.md خروجی.docx")

    with open(sys.argv[1], encoding="utf-8") as f:
        source = f.read()

    size = build(source, sys.argv[2])
    print("ساخته شد: %s (%d بایت XML)" % (sys.argv[2], size))
