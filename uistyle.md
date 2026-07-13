## UI Theme (PEMBUATAN UI WAJIB MENGGUNAKAN INI)

**PENTING :**

- jangan pakai axoos

THEME UI "CLEAN ELEGANT ENTERPRISE APLICATION"

- Gunakan CSS variables di `app.css` (--bg-main, --text-main, --primary, dll).
- Cara penggunanaan CSS Variable seperti Contoh : <aside class="bg-(--bg-sidebar) border-r border-(--border-soft)">
- DILARANG hardcode warna Tailwind (gray-800, dll).
- Layout sudah dibuat default di app.js jadi tidak usah ditambahkan lagi.
- light & dark mode didukung via localStorage.
- sudut jangan terlalu curvy tapi tidak terlalu tajam juga (recommended: rounded-md).

## ATURAN SPESIFIK WHITESPACE/SPACING, TINGGI ELEMENT SLL

- SPACING : gunakan tailwind standar 4 atau 16px contoh : p-4, px-4, dst (maksimal 6)
- TINGGI ELEMENT seperti tombol, input, select,
    1. untuk kelas sm = h-8, px-2
    2. untuk kelas md = h-10, px-3
    3. untuk kelas lg dan xl = sama dengan md.
- SEMUA ELEMENT TEXT, BUTTON, INPUT, SELECT HARUS MENGIKUTI TINGGI ELEMENT YANG SUDAH DITENTUKAN
- Jangan multiple padding, contoh, di parennt element sudah ada p-4 atau p-6, child element tidan usah gunakan padding lagi, atau sebaliknya jika child berupa card harus menggunakan padding maka parent element gunakan space-y, space-x, atau gap- saja.

## 🎨 UI THEME TOKENS

```css
:root {
    /* Background System */
    --bg-main: #e4e8ec;
    --bg-sidebar: #ffffff;
    --bg-card: #ffffff;
    --bg-elevated: #f1f5f9;

    /* Text */
    --text-main: #0f172a;
    --text-muted: #64748b;
    --text-soft: #94a3b8;

    /* Primary (Brand) */
    --primary: #2563eb;
    --primary-hover: #1d4ed8;
    --primary-glow: rgba(37, 99, 235, 0.25);

    /* Border & UI */
    --border-soft: #e2e8f0;
    --border-strong: #cbd5f5;

    /* Semantic Status */
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
}

.dark {
    /* Background (navy dark, bukan hitam) */
    --bg-main: #090f1b;
    --bg-sidebar: #0f172a;
    --bg-card: #111c2d;
    --bg-elevated: #162235;

    /* Text */
    --text-main: #e5e7eb;
    --text-muted: #94a3b8;
    --text-soft: #64748b;

    /* Primary (blue glow enterprise) */
    --primary: #3b82f6;
    --primary-hover: #60a5fa;
    --primary-glow: rgba(59, 130, 246, 0.35);

    /* Border */
    --border-soft: #1f2a44;
    --border-strong: #2b3a5c;

    /* Semantic Status Dark */
    --success: #059669;
    --warning: #d97706;
    --danger: #dc2626;
}
```
