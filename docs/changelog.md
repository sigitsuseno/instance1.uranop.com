# Changelog

## 2026-10-09
- [New] File Manager: modul `app/Modules/FileManager` untuk mengelola berkas aplikasi — folder bertingkat (buat/ganti nama/hapus/pindah dengan breadcrumb), unggah multi-berkas, unduh, ganti nama, hapus, pratinjau gambar/PDF/teks/isi sheet Excel, serta pencarian dan filter jenis berkas
- [New] File Manager: berkas disimpan di disk privat `file_manager` (`storage/app/file-manager`) yang tidak dapat diakses lewat URL publik; seluruh akses lewat endpoint `api/v1/file-manager/*` dan terbatas untuk `superadmin`
- [New] File Manager: halaman `resources/js/Pages/Admin/FileManager/Index.vue` (rute `/admin/file-manager`, `meta.requiresSuperadmin`) dengan komponen `Breadcrumbs`, `FileGrid`, `UploadModal`, `PreviewModal`, `ExcelPreview`, dan `MoveModal`; unduhan memakai fetch + Blob karena endpoint butuh header Bearer

## 2026-07-20
- [New] Consecutive Day: Search karyawan di filter bar — `filterEmployeeId` + `SearchableSelect` + `watch` auto-fetch
