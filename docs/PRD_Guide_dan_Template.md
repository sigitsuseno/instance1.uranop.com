# 📋 Panduan Lengkap Membuat PRD (Product Requirements Document)

> *Buat Sigit — biar gak pusing lagi pas mau launch fitur baru*

---

## 🧐 Apa Itu PRD?

**Product Requirements Document (PRD)** adalah dokumen yang menjelaskan secara detail *apa* yang akan dibangun, *kenapa* dibangun, dan *seperti apa* hasil akhirnya. Bukan dokumen teknis (bukan tempatnya ngomongin API atau database), tapi jembatan antara visi produk dengan tim developer.

### Kenapa PRD Penting?
- ✅ Semua tim satu pemahaman — gak ada "tapi aku kira..."
- ✅ Fitur gak melebar kemana-mana (scope creep mati!)
- ✅ Prioritasi jadi jelas — mana yang penting, mana yang nanti
- ✅ Developer tinggal eksekusi, gak perlu nebak-nebak

---

## 📐 Struktur PRD yang Lengkap

### 1️⃣ **Judul & Identitas Dokumen**

| Field | Contoh |
|-------|--------|
| Nama Proyek/Fitur | Modul Absensi Karyawan |
| Versi Dokumen | v1.0 |
| Status | Draft / Review / Final |
| Author | Sigit |
| Tanggal | 2026-06-02 |
| Stakeholders | Sigit (PM), Tim Developer, Client |

---

### 2️⃣ **Executive Summary** (1 Paragraf)

Ringkasan mini buat stakeholder yang males baca semua. Jawab 3 pertanyaan:
- **Masalah apa?**
- **Solusi apa?**
- **Kenapa sekarang?**

> *Contoh:*
> "Saat ini pencatatan absensi dilakukan manual via spreadsheet, rawan manipulasi dan sulit direkap. Modul absensi digital akan menggantikannya dengan fitur clock-in/out via QR code, generate laporan otomatis, dan integrasi payroll. Fitur ini penting karena client demand #1 di Q3 ini."

---

### 3️⃣ **Problem Statement** (Latar Belakang)

Jelasin masalah yang mau dipecahin, lengkap dengan data kalo bisa.

**Pertanyaan pemandu:**
- Masalah konkret apa yang user alami sekarang?
- Gimana cara mereka mengakalinya saat ini (workaround)?
- Ada data pendukung? (keluhan customer, survey, data usage)

> *Contoh:*
> "Karyawan sering 'lupa' absen pulang karena harus login ke web lewat browser. 40% data absensi diisi manual oleh HR tiap akhir bulan, memakan 2 hari kerja."

---

### 4️⃣ **Goals & Success Metrics** (Tujuan & Ukuran Sukses)

Bikin SMART: **S**pecific, **M**easurable, **A**chievable, **R**elevant, **T**ime-bound.

| Goal | Metric | Current Baseline | Target | Timeline |
|------|--------|-----------------|--------|----------|
| Efisiensi rekap absen | Waktu rekap bulanan | 2 hari | 10 menit | 1 bulan |
| Akurasi data | Error rate data absen | 15% | <1% | 1 bulan |
| Adopsi user | % karyawan pake fitur | 0% | 90% | 2 bulan |

---

### 5️⃣ **Target Persona / User Stories**

Siapa aja yang bakal pake fitur ini?

**Contoh Persona:**
| Role | Nama Persona | Pain Point | Goal |
|------|-------------|------------|------|
| Karyawan | Budi (Staff) | Males buka laptop cuma buat absen | Absen cepat dari HP |
| HR | Sari (HR Admin) | Recap absen manual tiap bulan | Generate laporan 1 klik |
| Manajemen | Pak Adi (Direktur) | Gak bisa lihat kehadiran real-time | Dashboard kehadiran real-time |

---

### 6️⃣ **Functional Requirements** (Fitur — yang *HARUS* jalan)

Ini inti PRD-nya! Tulis per fitur lengkap dengan:
- **User Story** — "Sebagai [role], saya ingin [aksi], agar [benefit]"
- **Acceptance Criteria** — detail biar developer & QA punya acuan yang sama
- **Priority** — pake **MoSCoW** (lihat bagian 10)

#### Template per Fitur:

```
### FR-001: Clock-in via QR Code
- **User Story:** Sebagai karyawan, saya ingin scan QR code untuk clock-in, agar absensi cepat dan akurat.
- **Priority:** Must Have
- **Acceptance Criteria:**
  - [ ] Scan QR code langsung mencatat clock-in dengan timestamp real-time
  - [ ] Karyawan bisa clock-in dalam radius 50m dari kantor (geofence)
  - [ ] Tampilkan sukses/error dalam 2 detik
  - [ ] Gak bisa clock-in ulang sebelum 12 jam sejak clock-in terakhir
  - [ ] QR code expired tiap 30 detik (security)
- **Notes:** QR code statis per kantor cabang, bukan per karyawan
```

---

### 7️⃣ **Non-Functional Requirements** (Kualitas Sistem)

Bukan fitur, tapi standar kualitas sistem secara keseluruhan.

| Kategori | Requirement |
|----------|------------|
| **Performance** | Halaman dashboard load < 3 detik |
| **Security** | Semua data absensi dienkripsi AES-256 |
| **Reliability** | Uptime 99.9%, support offline mode sementara |
| **Scalability** | Support 10.000 user concurrent |
| **Compatibility** | Works on Chrome, Firefox, Safari, Mobile Android/iOS |
| **Compliance** | Sesuai UU Ketenagakerjaan No. 13/2003 |

---

### 8️⃣ **Scope — In Scope vs Out of Scope**

Ini paliiiing penting! Batasin biar gak melebar.

| ✅ In Scope (Dikerjakan) | ❌ Out of Scope (Dikerjakan Nanti / Gak Dikerjain) |
|--------------------------|---------------------------------------------------|
| Clock-in/out via QR code | Integrasi payroll otomatis (v2.0) |
| Riwayat absensi pribadi | Management shift & jadwal (v2.0) |
| Laporan absensi per bulan | Face recognition (v3.0 — butuh hardware) |
| Notifikasi jika lupa absen | Pengajuan lembur via mobile (v2.0) |

---

### 9️⃣ **UX / Design References**

- Link Figma / mockup
- User flow diagram
- Atau minimal: sketsa kasar di kertas

> *Kalo belum ada desain, tulis aja:* "Mengikuti pattern dashboard Laravel yang sudah ada, dengan tambahan QR scanner button di navbar."

---

### 🔟 **Prioritas MoSCoW Method**

| Label | Arti | Konsekuensi |
|-------|------|-------------|
| 🟢 **Must Have** | Wajib! Gak release tanpa ini | Release ditunda kalo gak selesai |
| 🔵 **Should Have** | Penting tapi ada workaround | Masuk release kalo waktu cukup |
| 🟡 **Could Have** | Nice-to-have, gak urgent | Dilempar ke sprint berikutnya |
| 🔴 **Won't Have** | Sadar diri — gak dikerjain sekarang | Buat PRD versi 2.0 |

---

### 1️⃣1️⃣ **Risiko & Dependensi**

Apa aja yang bisa bikin project melambat atau gagal?

| Risiko | Dampak | Mitigasi |
|--------|--------|----------|
| QR code library gak support semua browser | Medium | Uji coba library di minggu 1, siapkan fallback manual code |
| API pihak ketiga (jika ada) down saat demo | High | Endpoint /health check tiap request, fallback cache |
| Tim fokus di project lain | Medium | Alokasi dedicated developer 2 minggu |

---

### 1️⃣2️⃣ **Timeline & Milestones**

Gak perlu sampe detail sprint, cukup milestone besar.

```
Minggu 1: Setup & finalisasi API endpoint absensi
Minggu 2: QR code scanner + geofence
Minggu 3: Dashboard HR + laporan
Minggu 4: Testing, bug fix, UAT
Minggu 5: Release 🚀
```

---

## ⭐ Best Practices PRD yang Patut Ditiru

### 1. **PRD is a Living Document**
Gak perlu nunggu sempurna baru dishare. Share draft awal, iterasi bareng tim.

### 2. **Hindari Kata-kata Ambigu**
| ❌ Hindari | ✅ Ganti dengan |
|-----------|----------------|
| "User-friendly" | "Menyelesaikan absen dalam 3 klik, < 10 detik" |
| "Cepat" | "Response time < 2 detik (P95)" |
| "Mudah digunakan" | "Zero training — karyawan langsung paham dalam 1 kali coba" |
| "Sebisa mungkin" | Hapus! Pake Must/Should/Could |

### 3. **Tulis Acceptance Criteria sebelum Coding**
AC yang baik = developer gak bolak-balik nanya. Format **Given/When/Then** sangat membantu:

> **Given** karyawan sudah login
> **When** karyawan tap QR code valid
> **Then** sistem catat clock-in dengan timestamp real-time
> **And** tampilkan notifikasi sukses

### 4. **Libatkan Developer dari Awal**
Developer yang dibawa diskusi PRD dari awal akan punya *ownership* yang lebih tinggi dan bisa kasih masukan teknis lebih awal.

### 5. **Gunakan Visual**
User flow diagram, wireframe, atau screenshot referensi > 10 paragraf teks.

---

## 📄 Template PRD Siap Pakai

```
# PRD: [Nama Fitur / Proyek]

| Metadata | |
|----------|---------|
| Author | [Nama] |
| Versi | v1.0 |
| Status | Draft |
| Tanggal | [DD/MM/YYYY] |
| Stakeholders | [Daftar] |

---

## 1. Executive Summary

[1 paragraf: masalah → solusi → kenapa sekarang]

## 2. Problem Statement

- Masalah: ...
- Dampak: ...
- Data pendukung: ...

## 3. Goals & Success Metrics

| Goal | Metric | Baseline | Target | Timeline |
|------|--------|----------|--------|----------|
| | | | | |

## 4. Target Persona

| Role | Persona | Pain Point | Goal |
|------|---------|------------|------|
| | | | |

## 5. Functional Requirements

### FR-001: [Judul Fitur]
- **User Story:** Sebagai [role], saya ingin [aksi], agar [benefit].
- **Priority:** Must / Should / Could / Won't
- **Acceptance Criteria:**
  - [ ] ...
  - [ ] ...
  - [ ] ...
- **Notes:** ...

### FR-002: [Judul Fitur]
...
*(ulangi untuk setiap fitur)*

## 6. Non-Functional Requirements

- Performance: ...
- Security: ...
- Reliability: ...
- Scalability: ...
- Compatibility: ...

## 7. Scope

### ✅ In Scope
- ...

### ❌ Out of Scope
- ...

## 8. UX / Design References

[Link Figma / screenshot / user flow]

## 9. Risks & Dependencies

| Risk | Impact | Mitigation |
|------|--------|------------|
| | | |

## 10. Timeline & Milestones

- Milestone 1: ...
- Milestone 2: ...
- Milestone 3: ...

---

## Approval

| Role | Name | Signature | Date |
|------|------|-----------|------|
| Product Owner | | | |
| Tech Lead | | | |
| Designer | | | |
```

---

## 🔗 Referensi & Sumber

- [Atlassian: How to write a PRD](https://www.atlassian.com/agile/product-management/product-requirements-document)
- [ProductPlan: PRD Guide](https://www.productplan.com/learn/product-requirements-document/)
- [Roman Pichler: PRD Template](https://www.romanpichler.com/blog/product-requirements-document-template/)
- [Intercom: What is a PRD?](https://www.intercom.com/blog/product-requirements-documents/)
- [SVPG: Inspired — Marty Cagan](https://www.svpg.com/product-strategy/)

---

> *Dibuat dengan ❤️ oleh Paijo untuk Sigit — 2 Juni 2026*
