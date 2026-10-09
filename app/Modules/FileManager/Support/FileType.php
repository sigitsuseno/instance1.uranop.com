<?php

namespace App\Modules\FileManager\Support;

/**
 * Klasifikasi jenis berkas dan aturan keamanan terkait tipe.
 *
 * Dipakai bersama oleh service (validasi unggahan, pratinjau) dan controller
 * (filter daftar berkas).
 */
class FileType
{
    /** Kategori yang dipakai filter di frontend. */
    public const CATEGORIES = ['image', 'pdf', 'spreadsheet', 'document', 'text', 'archive', 'other'];

    private const MAP = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tif', 'tiff', 'ico', 'heic', 'heif'],
        'pdf' => ['pdf'],
        'spreadsheet' => ['xlsx', 'xls', 'xlsm', 'xlsb', 'csv', 'ods'],
        'document' => ['doc', 'docx', 'rtf', 'odt', 'ppt', 'pptx', 'odp', 'pages', 'numbers'],
        'text' => ['txt', 'log', 'md', 'json', 'xml', 'yml', 'yaml', 'ini', 'sql', 'conf'],
        'archive' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'],
    ];

    /**
     * Mime yang boleh disajikan inline (dipratinjau di browser).
     *
     * Hanya gambar raster. image/svg+xml sengaja TIDAK termasuk karena SVG dapat
     * memuat skrip dan akan dieksekusi di origin aplikasi (XSS).
     */
    private const INLINE_RASTER = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
    ];

    /** Mime tambahan yang dikenali sebagai teks walau bukan berawalan "text/". */
    private const INLINE_TEXT_EXTRA = [
        'application/json',
        'application/xml',
        'application/x-empty',
        'inode/x-empty',
    ];

    /**
     * Ekstensi yang ditolak saat unggah.
     *
     * Pertahanan berlapis: berkas berada di disk privat dan tidak pernah
     * dieksekusi, namun berkas yang bisa dijalankan server/sistem tetap
     * ditolak agar tidak pernah ada di dalam aplikasi.
     */
    private const BLOCKED_EXT = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phtml', 'phar',
        'htaccess', 'htpasswd', 'ini',
        'exe', 'com', 'bat', 'cmd', 'msi', 'scr', 'dll', 'so', 'jar', 'vbs', 'ps1',
        'sh', 'bash', 'py', 'rb', 'pl', 'cgi',
        'jsp', 'jspx', 'asp', 'aspx', 'ashx', 'asmx',
    ];

    /** Ekstensi yang bisa dibaca sebagai spreadsheet oleh PhpSpreadsheet. */
    private const EXCEL_EXT = ['xlsx', 'xls', 'xlsm', 'xlsb', 'ods'];

    public static function extension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /** Kategori berkas untuk keperluan filter; 'other' bila tidak dikenal. */
    public static function categoryOf(string $filename): string
    {
        $ext = self::extension($filename);

        if ($ext === '') {
            return 'other';
        }

        foreach (self::MAP as $category => $extensions) {
            if (in_array($ext, $extensions, true)) {
                return $category;
            }
        }

        return 'other';
    }

    public static function isBlockedExtension(string $filename): bool
    {
        return in_array(self::extension($filename), self::BLOCKED_EXT, true);
    }

    public static function isSpreadsheet(string $filename): bool
    {
        return in_array(self::extension($filename), self::EXCEL_EXT, true);
    }

    /** Apakah berkas ini berupa CSV (dipratinjau sebagai teks, bukan lewat PhpSpreadsheet). */
    public static function isCsv(string $filename): bool
    {
        return self::extension($filename) === 'csv';
    }

    /**
     * Mime yang dipakai untuk menyajikan berkas secara inline, atau null bila
     * berkas ini tidak boleh dipratinjau (harus diunduh).
     *
     * Mime selalu dideteksi dari ISI berkas, tidak pernah dari input pengguna,
     * sehingga tipe palsu tidak bisa dipakai menembus whitelist.
     *
     * Semua tipe teks dipaksa menjadi "text/plain; charset=utf-8" — jadi berkas
     * yang isinya HTML tetap tampil sebagai teks biasa dan tidak pernah
     * dieksekusi sebagai halaman.
     */
    public static function inlineMime(string $absolutePath): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath) ?: null;

        if ($mime === null) {
            return null;
        }

        if (in_array($mime, self::INLINE_RASTER, true)) {
            return $mime;
        }

        if ($mime === 'application/pdf') {
            return $mime;
        }

        if (str_starts_with($mime, 'text/') || in_array($mime, self::INLINE_TEXT_EXTRA, true)) {
            return 'text/plain; charset=utf-8';
        }

        return null;
    }

    /**
     * Cara frontend harus mempratinjau berkas ini.
     *
     * 'image' | 'pdf' | 'text' | 'excel', atau null bila tidak ada pratinjau
     * (frontend hanya menawarkan unduh).
     */
    public static function previewKind(string $filename): ?string
    {
        $ext = self::extension($filename);

        if ($ext === '') {
            return null;
        }

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
            return 'image';
        }

        if ($ext === 'pdf') {
            return 'pdf';
        }

        if ($ext === 'csv' || in_array($ext, self::MAP['text'], true)) {
            return 'text';
        }

        if (in_array($ext, self::EXCEL_EXT, true)) {
            return 'excel';
        }

        return null;
    }

    /** Ikon Boxicons untuk sebuah berkas. */
    public static function icon(string $filename): string
    {
        return match (self::categoryOf($filename)) {
            'image' => 'bx bx-image',
            'pdf' => 'bx bxs-file-pdf',
            'spreadsheet' => 'bx bxs-spreadsheet',
            'document' => 'bx bxs-file-doc',
            'text' => 'bx bx-file-blank',
            'archive' => 'bx bx-archive',
            default => 'bx bx-file',
        };
    }
}
