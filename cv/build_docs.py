#!/usr/bin/env python3
"""
Build the EMBL JR4434 application documents (Word + PDF) from the Markdown sources.

    .venv/bin/python cv/build_docs.py

Inputs  : cv/Liladhar_Koirala_CV_EMBL_JR4434.md, cv/Cover_Letter_EMBL_JR4434.md
Outputs : matching .docx and .pdf files in cv/

Requires: python-docx, fpdf2   (pip install python-docx fpdf2)

The Markdown is deliberately simple so that the generated Word file stays ATS-safe:
single column, standard headings, real bullet lists, no tables, no text boxes, no images.
"""

from __future__ import annotations

import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))

CV_MD = os.path.join(HERE, "Liladhar_Koirala_CV_EMBL_JR4434.md")
CL_MD = os.path.join(HERE, "Cover_Letter_EMBL_JR4434.md")

BODY_FONT = "Calibri"
BODY_SIZE = 10.5
MARGIN_CM = 1.8

INLINE = re.compile(r"(\*\*.+?\*\*|\*[^*\n]+?\*|\[[^\]\n]+\]\([^)\n]+\))")
LINK = re.compile(r"\[([^\]]+)\]\(([^)]+)\)")


# --------------------------------------------------------------------------- #
# shared markdown helpers
# --------------------------------------------------------------------------- #
def tokenize(text):
    """Split a line into (text, bold, italic) tuples."""
    out = []
    for piece in INLINE.split(text):
        if not piece:
            continue
        if piece.startswith("**") and piece.endswith("**") and len(piece) > 4:
            out.append((piece[2:-2], True, False))
        elif piece.startswith("*") and piece.endswith("*") and len(piece) > 2:
            out.append((piece[1:-1], False, True))
        elif piece.startswith("[") and "](" in piece:
            m = LINK.fullmatch(piece)
            out.append((m.group(1) if m else piece, False, False))
        else:
            out.append((piece, False, False))
    return out or [(text, False, False)]


def classify(line, state):
    """Return (kind, text) for one markdown line."""
    stripped = line.rstrip()
    if not stripped.strip():
        return ("blank", "")
    if re.fullmatch(r"-{3,}", stripped.strip()):
        return ("rule", "")
    if stripped.startswith("### "):
        return ("h3", stripped[4:])
    if stripped.startswith("## "):
        return ("h2", stripped[3:])
    if stripped.startswith("# "):
        return ("h1", stripped[2:])
    if stripped.startswith("- "):
        return ("bullet", stripped[2:])
    if stripped.startswith("> "):
        return ("quote", stripped[2:])
    return ("para", stripped)


def read_lines(path):
    with open(path, encoding="utf-8") as fh:
        return fh.read().splitlines()


# --------------------------------------------------------------------------- #
# DOCX
# --------------------------------------------------------------------------- #
def _bottom_border(paragraph, color="000000", sz="6"):
    from docx.oxml import OxmlElement
    from docx.oxml.ns import qn

    pPr = paragraph._p.get_or_add_pPr()
    pBdr = OxmlElement("w:pBdr")
    bottom = OxmlElement("w:bottom")
    bottom.set(qn("w:val"), "single")
    bottom.set(qn("w:sz"), sz)
    bottom.set(qn("w:space"), "2")
    bottom.set(qn("w:color"), color)
    pBdr.append(bottom)
    pPr.append(pBdr)


def _page_number_footer(section, left_text):
    from docx.enum.text import WD_ALIGN_PARAGRAPH
    from docx.oxml import OxmlElement
    from docx.oxml.ns import qn
    from docx.shared import Pt, RGBColor

    footer = section.footer
    para = footer.paragraphs[0]
    para.alignment = WD_ALIGN_PARAGRAPH.CENTER
    para.text = ""

    run = para.add_run(left_text + "  |  Page ")
    field = para.add_run()
    f1 = OxmlElement("w:fldChar")
    f1.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText")
    instr.set(qn("xml:space"), "preserve")
    instr.text = "PAGE"
    f2 = OxmlElement("w:fldChar")
    f2.set(qn("w:fldCharType"), "end")
    field._r.append(f1)
    field._r.append(instr)
    field._r.append(f2)

    for r in para.runs:
        r.font.size = Pt(8)
        r.font.name = BODY_FONT
        r.font.color.rgb = RGBColor(0x60, 0x60, 0x60)


def _emit_runs(paragraph, text, size, base_bold=False, base_italic=False):
    from docx.shared import Pt

    for chunk, bold, italic in tokenize(text):
        run = paragraph.add_run(chunk)
        run.bold = bold or base_bold
        run.italic = italic or base_italic
        run.font.size = Pt(size)
        run.font.name = BODY_FONT


def build_docx(md_path, out_path, doc_kind):
    from docx import Document
    from docx.enum.section import WD_SECTION
    from docx.enum.text import WD_ALIGN_PARAGRAPH
    from docx.shared import Cm, Pt, RGBColor

    doc = Document()

    section = doc.sections[0]
    section.page_width = Cm(21.0)
    section.page_height = Cm(29.7)
    for attr in ("top_margin", "bottom_margin", "left_margin", "right_margin"):
        setattr(section, attr, Cm(MARGIN_CM))

    normal = doc.styles["Normal"]
    normal.font.name = BODY_FONT
    normal.font.size = Pt(BODY_SIZE)
    normal.paragraph_format.space_after = Pt(4)
    normal.paragraph_format.space_before = Pt(0)
    normal.paragraph_format.line_spacing = 1.04

    lines = read_lines(md_path)
    state = {}
    header_lines_done = 0
    skip_first_h1 = doc_kind == "letter"

    for raw in lines:
        kind, text = classify(raw, state)

        if kind == "blank":
            continue

        if kind == "h1" and skip_first_h1:
            continue  # file label, not part of the letter

        if kind == "rule":
            continue  # section headings already carry a rule

        if kind == "h1":
            p = doc.add_paragraph()
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            p.paragraph_format.space_after = Pt(2)
            _emit_runs(p, text.upper(), 20, base_bold=True)
            header_lines_done += 1
            continue

        if kind == "h2":
            p = doc.add_paragraph()
            _bottom_border(p)
            p.paragraph_format.space_before = Pt(9)
            p.paragraph_format.space_after = Pt(4)
            p.paragraph_format.keep_with_next = True
            _emit_runs(p, text.upper(), 11, base_bold=True)
            continue

        if kind == "h3":
            p = doc.add_paragraph()
            p.paragraph_format.space_before = Pt(6.5)
            p.paragraph_format.space_after = Pt(1)
            p.paragraph_format.keep_with_next = True
            _emit_runs(p, text, BODY_SIZE, base_bold=True)
            continue

        if kind == "bullet":
            p = doc.add_paragraph(style="List Bullet")
            p.paragraph_format.left_indent = Cm(0.55)
            p.paragraph_format.first_line_indent = Cm(-0.25)
            p.paragraph_format.space_after = Pt(2)
            p.paragraph_format.line_spacing = 1.05
            _emit_runs(p, text, BODY_SIZE - 0.5)
            continue

        if kind == "quote":
            p = doc.add_paragraph()
            p.paragraph_format.left_indent = Cm(0.6)
            p.paragraph_format.space_after = Pt(4)
            _emit_runs(p, text, BODY_SIZE - 0.5, base_italic=True)
            continue

        # ordinary paragraph
        p = doc.add_paragraph()
        if doc_kind == "cv" and header_lines_done < 3:
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            header_lines_done += 1
            if header_lines_done == 2:
                p.paragraph_format.space_after = Pt(1)
                _emit_runs(p, text, 11.5, base_bold=True)
                continue
            if header_lines_done == 3:
                p.paragraph_format.space_after = Pt(8)
                _emit_runs(p, text, 9.5)
                continue
        if doc_kind == "letter":
            # UK business-letter convention: everything ragged right
            p.alignment = WD_ALIGN_PARAGRAPH.LEFT
            p.paragraph_format.space_after = Pt(7)
            # sender block and recipient block: tight leading, no justification
            if text.startswith("[DD"):
                p.alignment = WD_ALIGN_PARAGRAPH.LEFT
                p.paragraph_format.space_before = Pt(12)
                p.paragraph_format.space_after = Pt(12)
            elif text.startswith("[") or text.isupper() or re.match(r"^\+?\d", text):
                p.alignment = WD_ALIGN_PARAGRAPH.LEFT
                p.paragraph_format.space_after = Pt(0)
        _emit_runs(p, text, BODY_SIZE)

    _page_number_footer(
        section,
        "Liladhar Koirala" if doc_kind == "cv" else "Liladhar Koirala - Cover letter JR4434",
    )

    doc.save(out_path)
    return out_path


# --------------------------------------------------------------------------- #
# PDF
# --------------------------------------------------------------------------- #
def build_pdf(md_path, out_path, doc_kind):
    from fpdf import FPDF
    from fpdf.enums import XPos, YPos

    fontdir = "/usr/share/fonts/truetype/dejavu"
    if not os.path.isdir(fontdir):
        print(f"warning: {fontdir} missing - PDF may not support unicode", file=sys.stderr)
        fontdir = None

    class PDF(FPDF):
        def footer(self):
            self.set_y(-12)
            self.set_font(family, "", 7.5)
            self.set_text_color(110, 110, 110)
            label = "Liladhar Koirala" if doc_kind == "cv" else "Liladhar Koirala - Cover letter JR4434"
            self.cell(0, 5, f"{label}  |  Page {self.page_no()}/{{nb}}", align="C")
            self.set_text_color(0, 0, 0)

    family = "DejaVu" if fontdir else "helvetica"
    pdf = PDF(format="A4")
    pdf.set_margins(MARGIN_CM, MARGIN_CM, MARGIN_CM)
    pdf.set_auto_page_break(True, margin=13)
    if fontdir:
        # DejaVu ships without oblique faces here, so italic falls back to the
        # upright / bold outlines. The .docx is the submission artefact and keeps
        # true italics.
        regular = os.path.join(fontdir, "DejaVuSans.ttf")
        bold = os.path.join(fontdir, "DejaVuSans-Bold.ttf")
        pdf.add_font(family, "", regular)
        pdf.add_font(family, "B", bold)
        pdf.add_font(family, "I", os.path.join(fontdir, "DejaVuSans-Oblique.ttf")
                     if os.path.exists(os.path.join(fontdir, "DejaVuSans-Oblique.ttf")) else regular)
        pdf.add_font(family, "BI", os.path.join(fontdir, "DejaVuSans-BoldOblique.ttf")
                     if os.path.exists(os.path.join(fontdir, "DejaVuSans-BoldOblique.ttf")) else bold)
    pdf.add_page()
    pdf.alias_nb_pages()

    width = pdf.w - 2 * MARGIN_CM
    header_done = 0
    skip_first_h1 = doc_kind == "letter"

    def strip_md(text):
        text = re.sub(r"\*\*(.+?)\*\*", r"\1", text)
        text = re.sub(r"\*(.+?)\*", r"\1", text)
        text = LINK.sub(r"\1", text)
        return text

    def rule():
        y = pdf.get_y() + 1
        pdf.set_draw_color(0, 0, 0)
        pdf.set_line_width(0.25)
        pdf.line(MARGIN_CM, y, MARGIN_CM + width, y)
        pdf.set_y(y + 2.5)

    for raw in read_lines(md_path):
        kind, text = classify(raw, {})
        if kind == "blank":
            continue
        if kind == "h1" and skip_first_h1:
            continue
        if kind == "rule":
            continue

        if kind == "h1":
            pdf.set_font(family, "B", 16)
            pdf.multi_cell(width, 6.4, strip_md(text).upper(), align="C",
                           new_x=XPos.LMARGIN, new_y=YPos.NEXT)
            header_done += 1
            continue

        if kind == "h2":
            pdf.ln(2)
            pdf.set_font(family, "B", 10)
            pdf.multi_cell(width, 4.6, strip_md(text).upper(), align="L",
                           new_x=XPos.LMARGIN, new_y=YPos.NEXT)
            rule()
            continue

        if kind == "h3":
            pdf.ln(1.4)
            pdf.set_font(family, "B", 9.5)
            pdf.multi_cell(width, 4.5, strip_md(text), align="L",
                           new_x=XPos.LMARGIN, new_y=YPos.NEXT)
            continue

        if kind == "bullet":
            pdf.set_font(family, "", 9)
            x0 = MARGIN_CM + 1.5
            pdf.set_x(x0)
            pdf.multi_cell(width - 1.5, 4.2, "\u2022  " + strip_md(text), align="L",
                           new_x=XPos.LMARGIN, new_y=YPos.NEXT)
            pdf.ln(0.2)
            continue

        if kind == "quote":
            pdf.set_font(family, "I", 9)
            pdf.set_x(MARGIN_CM + 4)
            pdf.multi_cell(width - 4, 4.4, strip_md(text), align="L",
                           new_x=XPos.LMARGIN, new_y=YPos.NEXT)
            continue

        # paragraph
        if doc_kind == "cv" and header_done < 3:
            header_done += 1
            if header_done == 2:
                pdf.set_font(family, "B", 10.5)
                pdf.multi_cell(width, 4.8, strip_md(text), align="C",
                               new_x=XPos.LMARGIN, new_y=YPos.NEXT)
            else:
                pdf.set_font(family, "", 8.5)
                pdf.multi_cell(width, 4.2, strip_md(text), align="C",
                               new_x=XPos.LMARGIN, new_y=YPos.NEXT)
                pdf.ln(2)
            continue

        pdf.set_font(family, "", 9.5)
        align = "L"
        if doc_kind == "letter" and (text.startswith("[") or text.isupper()):
            align = "L"
        if doc_kind == "letter" and text.startswith("[DD"):
            pdf.ln(4)
        pdf.multi_cell(width, 4.6, strip_md(text), align=align,
                       new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        pdf.ln(4 if (doc_kind == "letter" and text.startswith("[DD")) else 1.1)

    pdf.output(out_path)
    return out_path


# --------------------------------------------------------------------------- #
# LEGACY .DOC (RTF container — opens natively in every Word version)
# --------------------------------------------------------------------------- #
def _rtf_esc(text):
    out = []
    for ch in text:
        if ch in "\\{}":
            out.append("\\" + ch)
            continue
        o = ord(ch)
        if o < 128:
            out.append(ch)
        else:
            try:
                out.append("\\'%02x" % ch.encode("cp1252")[0])
            except UnicodeEncodeError:
                out.append("\\u%d?" % o)
    return "".join(out)


def _rtf_runs(text, size_halfpoints):
    parts = []
    for chunk, bold, italic in tokenize(text):
        body = _rtf_esc(chunk)
        pre = ("\\b " if bold else "") + ("\\i " if italic else "")
        post = ("\\b0 " if bold else "") + ("\\i0 " if italic else "")
        parts.append(f"{pre}{body}{post}")
    return f"\\fs{size_halfpoints} " + "".join(parts)


def build_doc(md_path, out_path, doc_kind):
    """Emit a legacy Word .doc (RTF payload). A4, same layout rules as the .docx."""
    paras = []
    header_done = 0
    skip_first_h1 = doc_kind == "letter"

    for raw in read_lines(md_path):
        kind, text = classify(raw, {})
        if kind == "blank" or kind == "rule":
            continue
        if kind == "h1" and skip_first_h1:
            continue

        if kind == "h1":
            paras.append("\\pard\\qc\\sb0\\sa40 " + _rtf_runs(text.upper(), 40) + "\\b0\\par")
            header_done += 1
            continue
        if kind == "h2":
            paras.append(
                "\\pard\\sb180\\sa80\\brdrb\\brdrs\\brdrw10\\brsp40\\keepn "
                + _rtf_runs(text.upper(), 22) + "\\b0\\par"
            )
            continue
        if kind == "h3":
            paras.append("\\pard\\sb130\\sa20\\keepn " + _rtf_runs(text, 21) + "\\b0\\par")
            continue
        if kind == "bullet":
            paras.append(
                "\\pard\\li284\\fi-142\\sa40\\fs20 \\'95\\tab " + _rtf_runs(text, 20) + "\\par"
            )
            continue
        if kind == "quote":
            paras.append("\\pard\\li340\\sa80 " + _rtf_runs(text, 20) + "\\i0\\par")
            continue

        # ordinary paragraph
        if doc_kind == "cv" and header_done < 3:
            header_done += 1
            if header_done == 2:
                paras.append("\\pard\\qc\\sa20 " + _rtf_runs(text, 23) + "\\b0\\par")
            else:
                paras.append("\\pard\\qc\\sa160 " + _rtf_runs(text, 19) + "\\par")
            continue
        if doc_kind == "letter":
            if text.startswith("[DD"):
                paras.append("\\pard\\sb240\\sa240 " + _rtf_runs(text, 21) + "\\par")
            elif text.startswith("[") or text.isupper() or re.match(r"^\+?\d", text):
                paras.append("\\pard\\sb0\\sa0 " + _rtf_runs(text, 21) + "\\par")
            else:
                paras.append("\\pard\\sa140 " + _rtf_runs(text, 21) + "\\par")
            continue
        paras.append("\\pard\\sa80 " + _rtf_runs(text, 21) + "\\par")

    label = "Liladhar Koirala" if doc_kind == "cv" else "Liladhar Koirala - Cover letter JR4434"
    footer = (
        "{\\footer\\pard\\qc\\fs16 " + _rtf_esc(label)
        + "  |  Page {\\field{\\*\\fldinst PAGE}}\\par}"
    )
    rtf = (
        "{\\rtf1\\ansi\\ansicpg1252\\uc1\\deff0"
        "{\\fonttbl{\\f0\\fswiss\\fcharset0 Calibri;}}"
        "{\\colortbl;\\red0\\green0\\black;}"
        "\\paperw11906\\paperh16838"
        "\\margl1021\\margr1021\\margt1021\\margb1021"
        + footer
        + "\\f0\\fs21 "
        + "\n".join(paras)
        + "}"
    )
    with open(out_path, "w", encoding="ascii") as fh:
        fh.write(rtf)
    return out_path


# --------------------------------------------------------------------------- #
def main():
    jobs = [
        (CV_MD, os.path.join(HERE, "Liladhar_Koirala_CV_EMBL_JR4434"), "cv"),
        (CL_MD, os.path.join(HERE, "Cover_Letter_EMBL_JR4434"), "letter"),
    ]
    for md, stem, kind in jobs:
        if not os.path.exists(md):
            print(f"missing source: {md}", file=sys.stderr)
            continue
        build_docx(md, stem + ".docx", kind)
        print("wrote", stem + ".docx")
        try:
            build_doc(md, stem + ".doc", kind)
            print("wrote", stem + ".doc")
        except Exception as exc:
            print(f"doc skipped ({exc})", file=sys.stderr)
        try:
            build_pdf(md, stem + ".pdf", kind)
            print("wrote", stem + ".pdf")
        except Exception as exc:  # PDF is a convenience output, never block the docx
            print(f"pdf skipped ({exc})", file=sys.stderr)

    # report any placeholder still left in the sources
    left = 0
    for md, _, _ in jobs:
        for i, line in enumerate(read_lines(md), 1):
            for m in re.finditer(r"\[[^\]\[]+\]", line):
                left += 1
                print(f"  placeholder  {os.path.basename(md)}:{i}  {m.group(0)[:70]}")
    print(f"\n{left} bracketed placeholder(s) still to fill - see cv/placeholders.md")


if __name__ == "__main__":
    main()
