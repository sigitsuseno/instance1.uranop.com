## UI Theme (PEMBUATAN UI WAJIB MENGGUNAKAN INI)

THEME UI "CLEAN ELEGANT ENTERPRISE APLICATION"

- Gunakan CSS variables di `app.css` (--bg-main, --text-main, --primary, dll).
- Cara penggunanaan CSS Variable seperti Contoh : <aside class="bg-(--bg-sidebar) border-r border-(--border-soft)">
- DILARANG hardcode warna Tailwind (gray-800, dll).
- Layout sudah dibuat default di app.js jadi tidak usah ditambahkan lagi.
- light & dark mode didukung via localStorage.
- sudut jangan terlalu curvy tapi tidak terlalu tajam juga (recommended: rounded-md).

## 🎨 UI THEME TOKENS

```css
:root {
    --bg-main: #f8fafc;
    --bg-sidebar: #ffffff;
    --bg-card: #ffffff;
    --bg-elevated: #f1f5f9;
    --text-main: #0f172a;
    --text-muted: #64748b;
    --text-soft: #94a3b8;
    --primary: #2563eb;
    --primary-hover: #1d4ed8;
    --primary-glow: rgba(37, 99, 235, 0.25);
    --border-soft: #e2e8f0;
    --border-strong: #cbd5f5;
}
.dark {
    --bg-main: #0b1220;
    --bg-sidebar: #0f172a;
    --bg-card: #111c2d;
    --bg-elevated: #162235;
    --text-main: #e5e7eb;
    --text-muted: #94a3b8;
    --text-soft: #64748b;
    --primary: #3b82f6;
    --primary-hover: #60a5fa;
    --primary-glow: rgba(59, 130, 246, 0.35);
    --border-soft: #1f2a44;
    --border-strong: #2b3a5c;
}
```
