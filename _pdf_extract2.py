import pdfplumber, json

pdf_path = r"H:\laragon\www\instance1.uranop.com\sample_data\keluaran_pdf_detail.pdf"

with pdfplumber.open(pdf_path) as pdf:
    pages_data = []
    for i, page in enumerate(pdf.pages):
        text = page.extract_text() or ""
        tables = page.extract_tables()
        pages_data.append({
            "page": i+1,
            "text": text,
            "tables": [[[str(c) if c else "" for c in row] for row in table] for table in tables] if tables else []
        })
    # Write to a file
    with open(r"C:\Users\Sigit\_pdf_output.json", "w", encoding="utf-8") as f:
        json.dump(pages_data, f, ensure_ascii=False, indent=2)
    print(f"Total pages: {len(pdf.pages)}")
    for p in pages_data:
        print(f"\n=== PAGE {p['page']} ===")
        print(p['text'][:3000])
