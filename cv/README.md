# Application pack — Liladhar Koirala · EMBL-EBI Digital Transformation Specialist (JR4434)

Everything in this folder is tailored to the live vacancy **JR4434** (Hinxton, Cambridgeshire · Grade 4 · full time ·
three-year project contract · reports to the Head of the Operations Management Team). The advert **closes at 23:59 CET
on 20/09/2026** and requires **both a cover letter and a CV**, submitted through EMBL's Workday careers portal.

## Files

| File | Purpose |
|---|---|
| `Liladhar_Koirala_CV_EMBL_JR4434.md` | CV source — edit this one; the Word (.docx/.doc) and PDF files are generated from it |
| `Liladhar_Koirala_CV_EMBL_JR4434.docx` | ATS-safe Word CV (single column, standard headings, no tables, no text boxes) — **submit this** |
| `Liladhar_Koirala_CV_EMBL_JR4434.doc` | Legacy Word 97–2003 format (RTF container) for portals that only accept `.doc`; opens natively in every Word version |
| `Liladhar_Koirala_CV_EMBL_JR4434.pdf` | PDF copy for portals that prefer PDF |
| `Cover_Letter_EMBL_JR4434.md` / `.docx` / `.doc` / `.pdf` | One-page cover letter mapped to the JD's essential criteria |
| `jd-keyword-mapping.md` | Requirement-by-requirement crosswalk + keyword checklist; read this before you submit |
| `build_docs.py` | Regenerates the `.docx` and `.pdf` from the Markdown sources |
| `placeholders.md` | Single list of every `[bracketed]` item you must replace |

## Step 1 — fill in the placeholders

Every `[square bracket]` is a fact I could not know. See `placeholders.md` for the full list. The most important ones:

1. **Contact details** — city/country, phone with international dialling code, email, LinkedIn URL.
2. **Employment history** — for each role: exact job title, organisation, city, month/year start and end. Reverse
   chronological, most recent first.
3. **Real numbers** — team size, content volume, audience size, number of documents, number of training sessions,
   adoption %, cycle-time reduction. Numbers are what make this CV competitive; never invent one.
4. **Education** — degree title exactly as printed on the certificate, institution, year.
5. **Certifications** — list only what you hold. Nothing there yet? See "Step 4".
6. **Workday HCM line** — adjust to your true level (see Step 3).

## Step 2 — what was deliberately left out

Per your instruction, warehouse, restaurant, cashier and other unrelated employment periods are **not** listed as
separate jobs. Because you confirmed they contain HR/finance-adjacent systems work, the CV now carries **one
consolidated entry** at the end of *Professional Experience*:

> **Earlier operational roles (consolidated)** — two bullets: daily use of rostering / point-of-sale / stock systems
> (the HR and finance processes an ERP transformation digitises), and onboarding/training new starters on them.

That single entry does three jobs: it surfaces HR- and finance-process exposure (the role starts in HR, then moves to
Finance and Procurement), it shows training-and-adoption experience outside media, and it closes timeline gaps so a
screener sees no unexplained hole. The closing line under *Additional Information* then offers the complete itemised
history on request. If your media roles are in fact continuous with no gaps, you may delete both the consolidated
entry and that closing line.

## Step 3 — the Workday HCM gap (read this)

"Knowledge of Workday HCM" is listed under **You have**, so it is an essential criterion, and it is the weakest point of
this profile. Three things mitigate it, in order of value:

- **Any HRIS/ERP exposure at all counts.** If you have used a staff self-service portal, HR system, payroll system,
  rostering tool, applicant-tracking system or ERP module — even as an end user — name it in the CV and describe what you
  did with it. End-user familiarity plus documentation skill is a credible combination for a Change & Engagement role.
- **Self-study before you apply.** Free/low-cost: Workday's own learning content and Community overview pages,
  "Workday Essential Training" (LinkedIn Learning), and Workday HCM fundamentals on Coursera/Pluralsight. Two or three
  weeks is enough to speak accurately about business processes, tenants, configuration vs. end-user experience, and how
  Workday Help is structured. Then upgrade the CV line from *"familiar with core concepts"* to what is true.
- **Honesty in the cover letter.** Paragraph 5 does this already. Do not overclaim Workday administration experience —
  EMBL will test it at interview, and an exaggerated essential criterion ends the application.

## Step 4 — optional but high-impact additions

- A **change management** foundation certificate (Prosci Change Practitioner, APMG Change Management Foundation) —
  directly named skills in the JD: impact assessment, stakeholder mapping, readiness, resistance, adoption.
- A **project management** foundation certificate (AgilePM, PRINCE2 Foundation, CAPM, Google PM Certificate).
- **Digital accessibility (WCAG 2.2)** and plain-language training — the JD asks for Help content that is *accessible*.
- **Google Analytics** or equivalent, if your analytics claims need backing.

## Step 5 — regenerate the Word and PDF files

```bash
python3 -m venv .venv && .venv/bin/pip install python-docx fpdf2   # once
.venv/bin/python cv/build_docs.py
```

Edit the `.md` files, re-run the script, and the `.docx`, `.doc` and `.pdf` are rebuilt. Do not edit the `.docx` by
hand unless you keep the layout rules below.

## ATS and formatting rules used here

- Single column, no tables, no text boxes, no columns, no images, no headers carrying your name.
- Standard section headings in capital letters: PROFESSIONAL SUMMARY, CORE COMPETENCIES, PROFESSIONAL EXPERIENCE,
  SELECTED PROJECTS & IMPACT HIGHLIGHTS, EDUCATION, CERTIFICATIONS & PROFESSIONAL DEVELOPMENT, LANGUAGES,
  ADDITIONAL INFORMATION.
- Simple bullets, plain hyphens and middle dots — no custom glyphs, icons or emoji.
- Calibri 10.5 pt body / 20 pt name, black text, 1.8 cm margins; CV 2 pages, cover letter 1 page (verified by the
  build script's PDF render, which uses a wider font than Calibri so Word has headroom).
- The PDF is a convenience copy: its sizes are calibrated down (DejaVu Sans) so that its pagination matches what
  Calibri produces in Word. **Submit the `.docx`** — or the PDF if the portal asks for PDF.
- Cover letter set ragged right (left-aligned), UK business-letter convention; CV paragraphs left-aligned.
- Dates in a consistent `Month YYYY – Month YYYY` pattern; job title and employer on their own lines.
- UK/international English spelling throughout, matching EMBL's own text: *organisation, prioritisation, programme,
  analyse, centre*.
- **No photo, date of birth, marital status, nationality or religion** — EMBL is a DORA signatory and applies DORA
  principles to recruitment; personal details can only work against you.
- File name matches the reference: `Liladhar_Koirala_CV_EMBL_JR4434.docx`.

## Before you hit submit

- [ ] Every `[bracket]` replaced or deleted — `placeholders.md` lists all 115 of them; the build script re-lists any
      that survive, and that list must be empty
- [ ] Instructional sentences deleted (certifications note, projects guidance, letter bracketed clauses)
- [ ] No invented numbers, employers, dates or qualifications
- [ ] CV ≤ 2 pages; cover letter ≤ 1 page (re-run `build_docs.py` and check the printed page counts)
- [ ] Spell-check; UK English consistent
- [ ] Both files uploaded to Workday, and the online form fields (they duplicate the CV) completed fully — some ATS
      read the form rather than the attachment
- [ ] `jd-keyword-mapping.md` keyword checklist ticked
- [ ] Submitted before 23:59 CET on 20/09/2026 — do not leave it to the last day
