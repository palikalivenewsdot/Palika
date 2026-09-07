#!/usr/bin/env python3
"""Generate Palika Live Media advertisement proposal DOCX (full custom design)."""
from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_CELL_VERTICAL_ALIGNMENT
from docx.oxml.ns import qn, nsdecls
from docx.oxml import parse_xml

# ---- Brand palette ----
NAVY = RGBColor(0x0F, 0x3B, 0x5F)
NAVY_HEX = "0F3B5F"
ACCENT_RED = RGBColor(0xC1, 0x27, 0x2D)
ACCENT_HEX = "C1272D"
LIGHT_BG_HEX = "EFF3F8"
DARK_TEXT = RGBColor(0x26, 0x26, 0x26)
GREY_TEXT = RGBColor(0x5A, 0x5A, 0x5A)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
LINK_BLUE = RGBColor(0x0E, 0x5A, 0xC7)

doc = Document()

# ---- Page setup: A4 ----
section = doc.sections[0]
section.page_width = Cm(21.0)
section.page_height = Cm(29.7)
section.top_margin = Cm(1.8)
section.bottom_margin = Cm(1.8)
section.left_margin = Cm(2.0)
section.right_margin = Cm(2.0)
section.header_distance = Cm(1.0)
section.footer_distance = Cm(1.0)
section.different_first_page_header_footer = True  # clean cover page

# ---- Default styles ----
style = doc.styles["Normal"]
style.font.name = "Calibri"
style.font.size = Pt(10.5)
style.font.color.rgb = DARK_TEXT
style.paragraph_format.space_after = Pt(6)
style.paragraph_format.line_spacing = 1.15
style.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY

for i in range(1, 3):
    hs = doc.styles[f"Heading {i}"]
    hs.font.name = "Calibri"
    hs.font.color.rgb = NAVY
    hs.font.bold = True

# ============ Helpers ============

def shade_cell(cell, hex_color):
    tcPr = cell._tc.get_or_add_tcPr()
    tcPr.append(parse_xml(f'<w:shd {nsdecls("w")} w:fill="{hex_color}"/>'))


def set_cell_borders(cell, color="B0BEC5", size="4"):
    tcPr = cell._tc.get_or_add_tcPr()
    tcPr.append(parse_xml(
        f'<w:tcBorders {nsdecls("w")}>'
        f'<w:top w:val="single" w:sz="{size}" w:color="{color}"/>'
        f'<w:left w:val="single" w:sz="{size}" w:color="{color}"/>'
        f'<w:bottom w:val="single" w:sz="{size}" w:color="{color}"/>'
        f'<w:right w:val="single" w:sz="{size}" w:color="{color}"/>'
        f'</w:tcBorders>'))


def add_horizontal_line(paragraph, color=NAVY_HEX, size="12"):
    pPr = paragraph._p.get_or_add_pPr()
    pPr.append(parse_xml(
        f'<w:pBdr {nsdecls("w")}>'
        f'<w:bottom w:val="single" w:sz="{size}" w:space="4" w:color="{color}"/>'
        f'</w:pBdr>'))


def shade_para(paragraph, hex_color):
    pPr = paragraph._p.get_or_add_pPr()
    pPr.append(parse_xml(f'<w:shd {nsdecls("w")} w:fill="{hex_color}" w:val="clear"/>'))


def add_para(text, bold=False, italic=False, size=10.5, color=DARK_TEXT,
             align=WD_ALIGN_PARAGRAPH.JUSTIFY, space_after=6, space_before=0):
    p = doc.add_paragraph()
    p.alignment = align
    p.paragraph_format.space_after = Pt(space_after)
    p.paragraph_format.space_before = Pt(space_before)
    run = p.add_run(text)
    run.bold = bold
    run.italic = italic
    run.font.size = Pt(size)
    run.font.color.rgb = color
    run.font.name = "Calibri"
    return p


def add_mixed_para(segments, align=WD_ALIGN_PARAGRAPH.JUSTIFY, space_after=6, space_before=0):
    p = doc.add_paragraph()
    p.alignment = align
    p.paragraph_format.space_after = Pt(space_after)
    p.paragraph_format.space_before = Pt(space_before)
    for text, fmt in segments:
        run = p.add_run(text)
        run.bold = fmt.get("bold", False)
        run.italic = fmt.get("italic", False)
        run.font.size = Pt(fmt.get("size", 10.5))
        run.font.color.rgb = fmt.get("color", DARK_TEXT)
        run.font.name = "Calibri"
        if fmt.get("underline"):
            run.underline = True
    return p


def add_hyperlink(paragraph, url, text, size=10.5):
    part = paragraph.part
    r_id = part.relate_to(url, "http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink",
                           is_external=True)
    hyperlink = parse_xml(
        f'<w:hyperlink {nsdecls("w")} xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:id="{r_id}">'
        f'<w:r><w:rPr>'
        f'<w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/>'
        f'<w:sz w:val="{int(size * 2)}"/>'
        f'<w:color w:val="0E5AC7"/>'
        f'<w:u w:val="single"/>'
        f'</w:rPr><w:t>{text}</w:t></w:r>'
        f'</w:hyperlink>')
    paragraph._p.append(hyperlink)


def section_heading(number, title, page_break_before=False):
    if page_break_before:
        doc.add_page_break()
    p = doc.add_paragraph(style="Heading 1")
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    p.paragraph_format.space_before = Pt(14)
    p.paragraph_format.space_after = Pt(6)
    p.paragraph_format.keep_with_next = True
    shade_para(p, LIGHT_BG_HEX)
    r = p.add_run(f"  {number}   ")
    r.bold = True
    r.font.size = Pt(11)
    r.font.color.rgb = WHITE
    r.font.name = "Calibri"
    r._r.get_or_add_rPr().append(parse_xml(f'<w:shd {nsdecls("w")} w:fill="{NAVY_HEX}" w:val="clear"/>'))
    r2 = p.add_run(f"  {title.upper()}")
    r2.bold = True
    r2.font.size = Pt(12)
    r2.font.color.rgb = NAVY
    r2.font.name = "Calibri"
    return p


def sub_heading(text):
    p = doc.add_paragraph(style="Heading 2")
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    p.paragraph_format.space_before = Pt(8)
    p.paragraph_format.space_after = Pt(4)
    p.paragraph_format.keep_with_next = True
    r = p.add_run(text)
    r.bold = True
    r.font.size = Pt(11)
    r.font.color.rgb = NAVY
    r.font.name = "Calibri"
    add_horizontal_line(p, color="C9D4E0", size="6")
    return p


def add_bullet(text, size=10.5):
    p = doc.add_paragraph(style="List Bullet")
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    p.paragraph_format.space_after = Pt(2)
    p.paragraph_format.left_indent = Cm(1.0)
    r = p.add_run(text)
    r.font.size = Pt(size)
    r.font.name = "Calibri"
    r.font.color.rgb = DARK_TEXT
    return p


def styled_table(headers, rows, col_widths=None, font_size=9.5):
    table = doc.add_table(rows=1 + len(rows), cols=len(headers))
    table.style = "Table Grid"
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.autofit = True
    for j, h in enumerate(headers):
        cell = table.cell(0, j)
        cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
        shade_cell(cell, NAVY_HEX)
        cell.text = ""
        pa = cell.paragraphs[0]
        pa.alignment = WD_ALIGN_PARAGRAPH.CENTER
        r = pa.add_run(h)
        r.bold = True
        r.font.size = Pt(font_size)
        r.font.color.rgb = WHITE
        r.font.name = "Calibri"
    for i, row in enumerate(rows):
        for j, val in enumerate(row):
            cell = table.cell(i + 1, j)
            cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
            if i % 2 == 1:
                shade_cell(cell, LIGHT_BG_HEX)
            cell.text = ""
            pa = cell.paragraphs[0]
            pa.alignment = WD_ALIGN_PARAGRAPH.CENTER if j > 0 else WD_ALIGN_PARAGRAPH.LEFT
            if j == 0:
                pa.paragraph_format.left_indent = Pt(4)
            r = pa.add_run(str(val))
            r.font.size = Pt(font_size)
            r.font.name = "Calibri"
            r.font.color.rgb = DARK_TEXT
            if j == 0:
                r.bold = True
    if col_widths:
        for j, w in enumerate(col_widths):
            for i in range(1 + len(rows)):
                table.cell(i, j).width = Cm(w)
    doc.add_paragraph().paragraph_format.space_after = Pt(2)
    return table


def spacer(pts=12):
    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(pts)
    p.paragraph_format.space_before = Pt(0)
    return p


# ============ HEADER / FOOTER (not on cover) ============
header = section.header
hp = header.paragraphs[0]
hp.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = hp.add_run("PALIKA LIVE MEDIA PVT. LTD.")
r.bold = True
r.font.size = Pt(13)
r.font.color.rgb = NAVY
r.font.name = "Calibri"
hp2 = header.add_paragraph()
hp2.alignment = WD_ALIGN_PARAGRAPH.CENTER
hp2.paragraph_format.space_after = Pt(1)
r2 = hp2.add_run("Independent  \u2022  Development-Oriented  \u2022  Trusted Journalism  \u2014  www.palikalive.com")
r2.font.size = Pt(8)
r2.font.color.rgb = GREY_TEXT
r2.font.name = "Calibri"
add_horizontal_line(hp2, color=ACCENT_HEX, size="12")

footer = section.footer
fp = footer.paragraphs[0]
fp.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = fp.add_run("Palika Live Media Pvt. Ltd.  \u2022  Anamnagar, Kathmandu  \u2022  +977-9851096624  \u2022  palikalivenews@gmail.com  \u2022  www.palikalive.com")
r.font.size = Pt(7.5)
r.font.color.rgb = GREY_TEXT
r.font.name = "Calibri"
fp2 = footer.add_paragraph()
fp2.alignment = WD_ALIGN_PARAGRAPH.CENTER
fp2.paragraph_format.space_before = Pt(0)
run = fp2.add_run()
run._r.append(parse_xml(f'<w:fldSimple {nsdecls("w")} w:instr="PAGE"/>'))
run2 = fp2.add_run("  |  Advertisement Proposal \u2014 Everest Bank Ltd.")
run2.font.size = Pt(7.5)
run2.font.color.rgb = GREY_TEXT
run2.font.name = "Calibri"

# ============================================================
# COVER PAGE
# ============================================================
spacer(48)
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_after = Pt(2)
r = p.add_run("P A L I K A   L I V E   M E D I A   P V T .   L T D .")
r.bold = True
r.font.size = Pt(10)
r.font.color.rgb = NAVY
r.font.name = "Calibri"

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_before = Pt(2)
p.paragraph_format.space_after = Pt(2)
r = p.add_run("ADVERTISEMENT\nPROPOSAL")
r.bold = True
r.font.size = Pt(34)
r.font.color.rgb = NAVY
r.font.name = "Calibri"
add_horizontal_line(p, color=ACCENT_HEX, size="18")

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_before = Pt(6)
p.paragraph_format.space_after = Pt(2)
r = p.add_run("Digital Advertising  \u2022  Sponsored Content  \u2022  Institutional Collaboration")
r.bold = True
r.font.size = Pt(10.5)
r.font.color.rgb = ACCENT_RED
r.font.name = "Calibri"

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_after = Pt(10)
r = p.add_run("PalikaLive.com  +  \u2018Local Government\u2019 Print Journal  \u2014  Partnership through 2026")
r.italic = True
r.font.size = Pt(10.5)
r.font.color.rgb = GREY_TEXT
r.font.name = "Calibri"

box = doc.add_table(rows=1, cols=2)
box.alignment = WD_TABLE_ALIGNMENT.CENTER
box.autofit = False
box.columns[0].width = Cm(8.0)
box.columns[1].width = Cm(8.0)
c0 = box.cell(0, 0)
shade_cell(c0, LIGHT_BG_HEX)
set_cell_borders(c0, color=NAVY_HEX, size="6")
c0.text = ""
pa = c0.paragraphs[0]
pa.alignment = WD_ALIGN_PARAGRAPH.LEFT
ra = pa.add_run("PREPARED FOR\n")
ra.bold = True
ra.font.size = Pt(8)
ra.font.color.rgb = NAVY
ra.font.name = "Calibri"
ra2 = pa.add_run("Everest Bank Ltd.\nHead Office, Kathmandu")
ra2.font.size = Pt(11)
ra2.font.color.rgb = DARK_TEXT
ra2.font.name = "Calibri"
c1 = box.cell(0, 1)
shade_cell(c1, LIGHT_BG_HEX)
set_cell_borders(c1, color=NAVY_HEX, size="6")
c1.text = ""
pb = c1.paragraphs[0]
pb.alignment = WD_ALIGN_PARAGRAPH.LEFT
rb = pb.add_run("PREPARED BY\n")
rb.bold = True
rb.font.size = Pt(8)
rb.font.color.rgb = NAVY
rb.font.name = "Calibri"
rb2 = pb.add_run("Palika Live Media Pvt. Ltd.\nAnamnagar, Kathmandu")
rb2.font.size = Pt(11)
rb2.font.color.rgb = DARK_TEXT
rb2.font.name = "Calibri"

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_before = Pt(10)
r = p.add_run("Date: 2083/05/10  (B.S.)")
r.bold = True
r.font.size = Pt(11)
r.font.color.rgb = NAVY
r.font.name = "Calibri"

spacer(36)
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run("Confidential \u2014 Prepared exclusively for Everest Bank Ltd.  \u2022  www.palikalive.com")
r.italic = True
r.font.size = Pt(9)
r.font.color.rgb = GREY_TEXT
r.font.name = "Calibri"

doc.add_page_break()

# ============================================================
# CONTENTS PAGE
# ============================================================
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.LEFT
p.paragraph_format.space_after = Pt(8)
r = p.add_run("INSIDE THIS PROPOSAL")
r.bold = True
r.font.size = Pt(16)
r.font.color.rgb = NAVY
r.font.name = "Calibri"
add_horizontal_line(p, color=ACCENT_HEX, size="12")

contents = [
    ("1", "Cover Letter"),
    ("2", "About Palika Live Media"),
    ("3", "Our Reach, By The Numbers"),
    ("4", "Digital Advertising Rate Card"),
    ("5", "\u2018Local Government\u2019 Print Journal & Rate Card"),
    ("6", "Verified Legal & Regulatory Standing"),
    ("7", "Analytics Snapshot \u2014 PalikaLive.com"),
    ("8", "Terms & Partnership Consideration"),
]
ct = doc.add_table(rows=len(contents), cols=2)
ct.alignment = WD_TABLE_ALIGNMENT.CENTER
ct.autofit = False
ct.columns[0].width = Cm(2.0)
ct.columns[1].width = Cm(14.0)
for i, (num, title) in enumerate(contents):
    ncell = ct.cell(i, 0)
    tcell = ct.cell(i, 1)
    for cell in (ncell, tcell):
        tcPr = cell._tc.get_or_add_tcPr()
        tcPr.append(parse_xml(
            f'<w:tcBorders {nsdecls("w")}>'
            f'<w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="single" w:sz="4" w:color="D5DEE8"/><w:right w:val="nil"/>'
            f'</w:tcBorders>'))
        if i % 2 == 1:
            shade_cell(cell, "F5F8FC")
    ncell.text = ""
    pn = ncell.paragraphs[0]
    pn.alignment = WD_ALIGN_PARAGRAPH.CENTER
    rn = pn.add_run(num)
    rn.bold = True
    rn.font.size = Pt(12)
    rn.font.color.rgb = ACCENT_RED
    rn.font.name = "Calibri"
    tcell.text = ""
    pt = tcell.paragraphs[0]
    pt.alignment = WD_ALIGN_PARAGRAPH.LEFT
    rt = pt.add_run(title)
    rt.bold = True
    rt.font.size = Pt(11)
    rt.font.color.rgb = NAVY
    rt.font.name = "Calibri"

spacer(12)
add_para("Contact: +977-9851096624  \u2022  palikalivenews@gmail.com  \u2022  marketing@palikalive.com  \u2022  www.palikalive.com",
         italic=True, size=9, color=GREY_TEXT, align=WD_ALIGN_PARAGRAPH.CENTER)

doc.add_page_break()

# ============================================================
# 1. COVER LETTER
# ============================================================
section_heading("1", "Cover Letter")

t = doc.add_table(rows=1, cols=2)
t.alignment = WD_TABLE_ALIGNMENT.CENTER
t.autofit = False
t.columns[0].width = Cm(11)
t.columns[1].width = Cm(5)
t.cell(0, 0).text = ""
pa = t.cell(0, 0).paragraphs[0]
pa.alignment = WD_ALIGN_PARAGRAPH.LEFT
ra = pa.add_run("To,\nEverest Bank Ltd.\nHead Office, Kathmandu.")
ra.font.size = Pt(10.5)
ra.font.name = "Calibri"
ra.font.color.rgb = DARK_TEXT
t.cell(0, 1).text = ""
pb2 = t.cell(0, 1).paragraphs[0]
pb2.alignment = WD_ALIGN_PARAGRAPH.RIGHT
rb = pb2.add_run("Date: 2083/05/10")
rb.bold = True
rb.font.size = Pt(10.5)
rb.font.name = "Calibri"
rb.font.color.rgb = DARK_TEXT
for row in t.rows:
    for cell in row.cells:
        cell._tc.get_or_add_tcPr().append(parse_xml(
            f'<w:tcBorders {nsdecls("w")}><w:top w:val="nil"/><w:left w:val="nil"/>'
            f'<w:bottom w:val="nil"/><w:right w:val="nil"/></w:tcBorders>'))

add_mixed_para([
    ("Subject: ", {"bold": True, "size": 11, "color": NAVY}),
    ("Advertisement Proposal", {"bold": True, "size": 11, "color": NAVY, "underline": True}),
], space_before=4, space_after=6)

add_para("Respected Sir/Madam,", space_after=4)

add_para(
    "Palika Live Media Pvt. Ltd. (formerly Gorkha Sandesh Media Pvt. Ltd.) is a mission-driven media "
    "organization dedicated to the progress of Nepali society, civilization, and culture. Our purpose is to "
    "build hope, trust, and positivity among our readers through investigative, constructive, and "
    "development-oriented journalism, and to act as a catalyst for the values that unite and uplift Nepal\u2019s communities.")

add_para(
    "As Nepal continues to mature as a federal democratic republic, governance now operates across three tiers \u2014 "
    "federal, provincial, and local. Constitutional guarantees of free speech and press freedom have expanded the "
    "media\u2019s role in holding every level of government accountable, exposing irregularities, and championing the "
    "public interest. Meeting that responsibility calls for close cooperation between the press and the institutions it covers.")

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
p.paragraph_format.space_after = Pt(6)
r = p.add_run("Through ")
r.font.size = Pt(10.5)
r.font.name = "Calibri"
r.font.color.rgb = DARK_TEXT
add_hyperlink(p, "http://www.palikalive.com", "www.palikalive.com")
r = p.add_run(
    ", we have built a digital platform designed for this moment: unbiased, development-oriented reporting that "
    "holds local and provincial governments accountable, while reaching further than traditional print, radio, and "
    "television as a primary source of civic information.")
r.font.size = Pt(10.5)
r.font.name = "Calibri"
r.font.color.rgb = DARK_TEXT

add_para(
    "We would welcome your organization\u2019s partnership through digital advertising, sponsored content, or institutional "
    "collaboration as we grow this platform, and our companion print journal \u2018Local Government\u2019, together through 2026.")

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.LEFT
p.paragraph_format.space_before = Pt(6)
r = p.add_run("Sincerely,")
r.italic = True
r.font.size = Pt(10.5)
r.font.name = "Calibri"
r.font.color.rgb = DARK_TEXT

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.LEFT
p.paragraph_format.space_before = Pt(14)
r = p.add_run("Liladhar Koirala\n")
r.bold = True
r.font.size = Pt(11)
r.font.name = "Calibri"
r.font.color.rgb = NAVY
r = p.add_run("Palika Live Media Pvt. Ltd.")
r.font.size = Pt(10.5)
r.font.name = "Calibri"
r.font.color.rgb = DARK_TEXT

# ============================================================
# 2. ABOUT
# ============================================================
section_heading("2", "About Palika Live Media")

add_para(
    "Palika Live Media Pvt. Ltd. is an independent digital media organization committed to advancing transparent, "
    "accountable, and development-oriented journalism in Nepal. Through PalikaLive.com and its associated platforms, "
    "we provide credible, fact-based reporting with a strong focus on local governance, public policy, social and "
    "economic development, and civic affairs.")
add_para(
    "Our mission is to contribute to the positive transformation of Nepali society by promoting ethical journalism, "
    "strengthening democratic institutions, encouraging informed public participation, and fostering transparency and "
    "accountability at every level of government. We produce investigative, constructive, and solutions-oriented "
    "journalism that informs citizens, empowers communities, and supports evidence-based public discourse.")
add_para(
    "Guided by editorial independence, integrity, and public service, Palika Live also works to preserve and promote "
    "Nepal\u2019s culture, heritage, and diversity while supporting the country\u2019s federal democratic system and the "
    "continued strengthening of local governance.")

sub_heading("Registered & Recognized")
for item in [
    "Office of the Company Registrar",
    "Press Council Nepal",
    "Department of Information and Broadcasting",
    "Advertisement Board of Nepal",
    "Social Security Fund (SSF)",
    "Ajirkot Rural Municipality",
]:
    add_bullet(item)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
p.paragraph_format.space_before = Pt(4)
r = p.add_run("Registered Office: ")
r.bold = True
r.font.size = Pt(10.5)
r.font.name = "Calibri"
r.font.color.rgb = DARK_TEXT
r = p.add_run(
    "Ajirkot Rural Municipality, Gorkha District, Gandaki Province, Nepal. Palika Live is also distributed via "
    "Hamro Patro and Nepali Patro, extending our reach across Nepal and the Nepali diaspora worldwide.")
r.font.size = Pt(10.5)
r.font.name = "Calibri"
r.font.color.rgb = DARK_TEXT

# ============================================================
# 3. REACH
# ============================================================
section_heading("3", "Our Reach, By The Numbers")

stats = [
    ("50,000+", "DAILY VISITORS"),
    ("100,000+", "TOTAL READERSHIP"),
    ("10+", "EDITORIAL VERTICALS"),
    ("6", "SOCIAL PLATFORMS + APP"),
]
st = doc.add_table(rows=1, cols=4)
st.alignment = WD_TABLE_ALIGNMENT.CENTER
for j, (num, label) in enumerate(stats):
    cell = st.cell(0, j)
    shade_cell(cell, NAVY_HEX if j % 2 == 0 else ACCENT_HEX)
    set_cell_borders(cell, color="FFFFFF", size="8")
    cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
    cell.text = ""
    pa = cell.paragraphs[0]
    pa.alignment = WD_ALIGN_PARAGRAPH.CENTER
    rn = pa.add_run(num + "\n")
    rn.bold = True
    rn.font.size = Pt(16)
    rn.font.color.rgb = WHITE
    rn.font.name = "Calibri"
    rl = pa.add_run(label)
    rl.bold = True
    rl.font.size = Pt(8)
    rl.font.color.rgb = WHITE
    rl.font.name = "Calibri"

sub_heading("Why Partner with Us")
for item in [
    "Reach a highly relevant, civic-minded audience across all seven provinces",
    "Strong digital presence across web, Hamro Patro, Nepali Patro, and six social platforms",
    "A trusted platform built on ethical, development-oriented journalism",
    "Customized visibility solutions tailored to your organization\u2019s goals",
    "A companion print journal, \u2018Local Government\u2019, reaching institutional and policy audiences",
    "Backed by a team with more than a decade of newsroom experience",
]:
    add_bullet(item)

# ============================================================
# 4. DIGITAL RATE CARD
# ============================================================
section_heading("4", "Digital Advertising Rate Card", page_break_before=True)
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.LEFT
p.paragraph_format.space_after = Pt(4)
r = p.add_run("PalikaLive.com placements, priced in NPR and exclusive of 13% VAT.")
r.italic = True
r.font.size = Pt(9.5)
r.font.name = "Calibri"
r.font.color.rgb = GREY_TEXT

styled_table(
    ["Advertising Space", "Size (px)", "Single Page", "Multi Page", "Front Page"],
    [
        ["Road Block @ Contact Block", "800 \u00d7 500", "50,000", "50,000", "50,000"],
        ["Banner Ad", "1200 \u00d7 100", "50,000", "65,000", "75,000"],
        ["Mid Banner (Inside)", "900 \u00d7 150", "50,000", "60,000", "\u2014"],
        ["Wide Banner (Below Menu)", "1200 \u00d7 100", "50,000", "90,000", "90,000"],
        ["Sponsored Content", "1,000 words", "30,000", "30,000", "30,000"],
    ],
    col_widths=[5.5, 3.0, 2.5, 2.5, 2.5],
)
add_para("All formats, placements, and durations can be tailored to your campaign objectives and budget.",
         italic=True, size=9.5, color=GREY_TEXT, align=WD_ALIGN_PARAGRAPH.LEFT)

# ============================================================
# 5. PRINT JOURNAL
# ============================================================
section_heading("5", "\u2018Local Government\u2019 Print Journal")
add_para(
    "Following the declaration of local levels in 2073 B.S. and Nepal\u2019s transition to a federal democratic republic, "
    "Palika Live Media publishes \u2018Local Government\u2019 \u2014 a special, research-driven journal documenting the practice, "
    "achievements, and future direction of local governance in Nepal.")
sub_heading("Key Themes Covered")
for item in [
    "Federal restructuring of Nepal: background and transformation since 2073 B.S.",
    "Roles, responsibilities, and achievements of local, provincial, and federal governments",
    "Policy implementation, service delivery, and institutional performance at the local level",
    "Development efforts, infrastructure projects, and community-based innovations",
    "Challenges in inter-governmental coordination and governance reform",
    "Future prospects for strengthening Nepal\u2019s federal system",
]:
    add_bullet(item)

sub_heading("Print Advertisement Rate Card")
styled_table(
    ["Advertisement", "Size", "Rate (NPR)"],
    [
        ["Inside Front Cover", "210mm \u00d7 299mm", "1,00,000"],
        ["Back Cover", "210mm \u00d7 299mm", "1,20,000"],
        ["Inside Back Cover", "210mm \u00d7 299mm", "50,000"],
        ["Page 3 (Full Page)", "210mm \u00d7 299mm", "60,000"],
        ["Inside Page (Full)", "210mm \u00d7 299mm", "35,000"],
        ["Inside Page (Half)", "210mm \u00d7 147mm", "25,000"],
    ],
    col_widths=[6.0, 5.0, 5.0],
)
add_para("Rates are exclusive of 13% VAT. Sizes and placements may be customized by mutual agreement.",
         italic=True, size=9.5, color=GREY_TEXT, align=WD_ALIGN_PARAGRAPH.LEFT)

# ============================================================
# 6. LEGAL STANDING
# ============================================================
section_heading("6", "Verified Legal & Regulatory Standing", page_break_before=True)
add_para(
    "PalikaLive.com, operated by Palika Live Media Pvt. Ltd., was previously known as Gorkha Sandesh Media Pvt. Ltd. "
    "The company has since been rebranded as Palika Live Media Pvt. Ltd., reflecting its expanded national focus on "
    "local governance, public policy, and development journalism across Nepal.")

# ============================================================
# 7. ANALYTICS
# ============================================================
section_heading("7", "Analytics Snapshot \u2014 PalikaLive.com")
add_para(
    "To provide transparency and demonstrate the reach of our digital platform, a live Google Analytics snapshot of "
    "PalikaLive.com is included below. Updated analytics and supporting reports can be made available for independent "
    "verification upon request.")

abox = doc.add_table(rows=1, cols=1)
abox.alignment = WD_TABLE_ALIGNMENT.CENTER
acell = abox.cell(0, 0)
shade_cell(acell, "F7F9FC")
acell._tc.get_or_add_tcPr().append(parse_xml(
    f'<w:tcBorders {nsdecls("w")}>'
    f'<w:top w:val="dashed" w:sz="8" w:color="{NAVY_HEX}"/>'
    f'<w:left w:val="dashed" w:sz="8" w:color="{NAVY_HEX}"/>'
    f'<w:bottom w:val="dashed" w:sz="8" w:color="{NAVY_HEX}"/>'
    f'<w:right w:val="dashed" w:sz="8" w:color="{NAVY_HEX}"/>'
    f'</w:tcBorders>'))
acell.text = ""
pa = acell.paragraphs[0]
pa.alignment = WD_ALIGN_PARAGRAPH.CENTER
pa.paragraph_format.space_before = Pt(30)
pa.paragraph_format.space_after = Pt(30)
r = pa.add_run("[  Google Analytics snapshot of PalikaLive.com \u2014 to be inserted here  ]")
r.italic = True
r.font.size = Pt(11)
r.font.name = "Calibri"
r.font.color.rgb = NAVY
add_para("Figures reflect Google Analytics data as of the date this proposal was prepared.",
         italic=True, size=9, color=GREY_TEXT, align=WD_ALIGN_PARAGRAPH.CENTER)

# ============================================================
# 8. TERMS
# ============================================================
section_heading("8", "Terms & Partnership Consideration")
sub_heading("Terms & Conditions")
for item in [
    "All advertising rates, digital and print, are exclusive of 13% VAT.",
    "Advertisement sizes, formats, and placements can be customized to your requirements.",
    "Additional terms and conditions apply and will be confirmed upon agreement.",
]:
    add_bullet(item)

sub_heading("Request for Partnership Consideration")
add_para(
    "We respectfully submit this proposal for your kind consideration and request that it be reviewed by the "
    "appropriate department or decision-making authority within your organization.")
add_para(
    "We would be pleased to present this proposal in person to your marketing, communications, or corporate affairs "
    "team at a time convenient to you, and to tailor the scope of partnership \u2014 advertising formats, content "
    "collaborations, and investment options across both PalikaLive.com and the \u2018Local Government\u2019 journal \u2014 to your "
    "organization\u2019s objectives, audience, and budget.")
add_para(
    "Thank you for your time and consideration. We look forward to a long-term, mutually beneficial partnership in "
    "promoting informed public discourse, good governance, and sustainable development across Nepal.")

# ============ CTA + CONTACT ============
cta = doc.add_paragraph()
cta.alignment = WD_ALIGN_PARAGRAPH.CENTER
cta.paragraph_format.space_before = Pt(12)
cta.paragraph_format.space_after = Pt(8)
shade_para(cta, ACCENT_HEX)
r = cta.add_run("  LET\u2019S BUILD THIS PARTNERSHIP  ")
r.bold = True
r.font.size = Pt(14)
r.font.color.rgb = WHITE
r.font.name = "Calibri"

contact = doc.add_table(rows=1, cols=1)
contact.alignment = WD_TABLE_ALIGNMENT.CENTER
cc = contact.cell(0, 0)
shade_cell(cc, NAVY_HEX)
set_cell_borders(cc, color=NAVY_HEX, size="6")
cc.text = ""
lines = [
    ("Palika Live Media Pvt. Ltd.", True, 12),
    ("Previously registered as Gorkha Sandesh Media Pvt. Ltd.", False, 9),
    ("Registrar\u2019s Office: Ajirkot Rural Municipality\u20133, Gorkha  |  Contact Office: Anamnagar, Kathmandu", False, 10),
    ("Phone: +977-9851096624", False, 10),
    ("Email: palikalivenews@gmail.com  \u00b7  marketing@palikalive.com", False, 10),
    ("Website: www.palikalive.com", False, 10),
    ("We look forward to the possibility of a long and mutually beneficial association with your organization.", False, 9),
]
for idx, (txt, bold, size) in enumerate(lines):
    cp = cc.paragraphs[0] if idx == 0 else cc.add_paragraph()
    cp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    cp.paragraph_format.space_after = Pt(1)
    cp.paragraph_format.space_before = Pt(1)
    cr = cp.add_run(txt)
    cr.bold = bold
    cr.font.size = Pt(size)
    cr.font.color.rgb = WHITE
    cr.font.name = "Calibri"

out = "/home/user/Palika/PalikaLive_Advertisement_Proposal_Everest_Bank.docx"
doc.save(out)
print(f"Saved: {out}")
