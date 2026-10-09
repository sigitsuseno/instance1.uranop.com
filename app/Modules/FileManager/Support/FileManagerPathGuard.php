<?php

namespace App\Modules\FileManager\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Penjaga path: memastikan setiap path yang diproses tetap berada di dalam
 * root disk File Manager.
 *
 * Semua operasi berkas pada modul ini HARUS melewati kelas ini. Ada dua tingkat
 * pemeriksaan yang sengaja dipisah:
 *
 *  - normalize()  — keamanan struktural, dipakai untuk path apa pun (termasuk
 *                   membaca folder yang namanya dibuat di luar aplikasi).
 *  - assertName() — aturan penamaan ketat, hanya untuk nama yang BARU dibuat
 *                   (folder baru, rename, unggahan) agar tidak melahirkan nama
 *                   yang bermasalah di Windows.
 *
 * Lapis terakhir adalah pemeriksaan containment memakai realpath(), sehingga
 * walau ada segmen yang lolos, path tetap tidak bisa keluar dari root.
 */
class FileManagerPathGuard
{
    public const DISK = 'file_manager';

    private const MAX_DEPTH = 20;

    private const MAX_NAME_LENGTH = 180;

    /** Nama perangkat yang dicadangkan Windows; tidak bisa dipakai sebagai nama berkas. */
    private const RESERVED_NAMES = [
        'CON', 'PRN', 'AUX', 'NUL',
        'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
        'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9',
    ];

    /**
     * Normalisasi path relatif terhadap root disk. Path kosong berarti root.
     *
     * Menolak (422) path absolut, null byte, segmen "..", dan struktur yang
     * terlalu dalam. Segmen "." dan kosong dibuang.
     */
    public function normalize(?string $path): string
    {
        $path = (string) $path;

        if (str_contains($path, "\0")) {
            $this->reject();
        }

        // Backslash juga pemisah direktori di Windows.
        $path = str_replace('\\', '/', $path);
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        // Path absolut tidak pernah punya arti di sini — tolak, jangan ditafsirkan ulang.
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:#', $path) === 1) {
            $this->reject();
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                $this->reject();
            }

            $segments[] = $segment;
        }

        if (count($segments) > self::MAX_DEPTH) {
            throw new HttpException(422, 'Struktur folder terlalu dalam.');
        }

        return implode('/', $segments);
    }

    /**
     * Validasi nama berkas/folder yang baru dibuat. Mengembalikan nama dalam
     * bentuk NFC (bentuk yang dipakai NTFS) agar tidak lahir duplikat semu.
     */
    public function assertName(string $name): string
    {
        $name = $this->toValidUtf8(trim($name));

        if ($name === '' || $name === '.' || $name === '..') {
            throw new HttpException(422, 'Nama tidak boleh kosong.');
        }

        // NTFS menyimpan nama sebagai NFC; nama dari macOS bisa datang sebagai NFD
        // dan tampak identik padahal berbeda byte.
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($name, \Normalizer::FORM_C);
            if ($normalized !== false) {
                $name = $normalized;
            }
        }

        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new HttpException(422, 'Nama terlalu panjang (maksimal '.self::MAX_NAME_LENGTH.' karakter).');
        }

        if (preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) {
            throw new HttpException(422, 'Nama mengandung karakter kontrol.');
        }

        if (preg_match('#[<>:"|?*/\\\\]#u', $name) === 1) {
            throw new HttpException(422, 'Nama tidak boleh mengandung karakter < > : " | ? * / \\');
        }

        if (str_starts_with($name, '.')) {
            throw new HttpException(422, 'Nama tidak boleh diawali titik.');
        }

        // Windows membuang titik/spasi di ujung nama, sehingga bisa menabrak nama lain.
        if (preg_match('/[ .]$/u', $name) === 1) {
            throw new HttpException(422, 'Nama tidak boleh diakhiri spasi atau titik.');
        }

        if (in_array(strtoupper(strtok($name, '.')), self::RESERVED_NAMES, true)) {
            throw new HttpException(422, 'Nama tersebut dicadangkan oleh sistem.');
        }

        return $name;
    }

    /** Absolut path root disk, dibuat bila belum ada. */
    public function root(): string
    {
        $root = Storage::disk(self::DISK)->path('');

        if (! is_dir($root)) {
            mkdir($root, 0775, true);
        }

        return rtrim(realpath($root) ?: $root, DIRECTORY_SEPARATOR);
    }

    /**
     * Absolut path dari path relatif, dijamin berada di dalam root.
     *
     * @param  bool  $mustExist  true untuk berkas/folder yang harus sudah ada (404 bila tidak),
     *                           false untuk target yang akan dibuat.
     */
    public function absolute(string $relative, bool $mustExist = true): string
    {
        $relative = $this->normalize($relative);
        $this->assertNoSymlink($relative);

        $root = $this->root();

        $absolute = $relative === ''
            ? $root
            : $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        // Backstop: kanonikalisasi. Untuk target baru, yang sudah pasti ada adalah induknya.
        $canonical = realpath($mustExist ? $absolute : dirname($absolute));

        if ($canonical === false) {
            throw new HttpException(404, 'Berkas atau folder tidak ditemukan.');
        }

        $canonical = rtrim($canonical, DIRECTORY_SEPARATOR);

        if ($canonical !== $root && ! str_starts_with($canonical.DIRECTORY_SEPARATOR, $root.DIRECTORY_SEPARATOR)) {
            $this->reject();
        }

        if (is_link($absolute)) {
            $this->reject();
        }

        return $absolute;
    }

    /**
     * Tolak bila ada komponen path yang berupa symlink.
     *
     * Symlink yang ditanam lebih dulu bisa menunjuk keluar root, sehingga
     * lapis containment realpath() di atas tidak cukup bila tidak dicek.
     */
    private function assertNoSymlink(string $relative): void
    {
        if ($relative === '') {
            return;
        }

        $current = $this->root();

        foreach (explode('/', $relative) as $segment) {
            $current .= DIRECTORY_SEPARATOR.$segment;

            if (is_link($current)) {
                $this->reject();
            }

            if (! file_exists($current)) {
                break;
            }
        }
    }

    /** Apakah $child berada di dalam (atau sama dengan) $parent. */
    public function isInside(string $child, string $parent): bool
    {
        $child = trim($this->normalize($child), '/');
        $parent = trim($this->normalize($parent), '/');

        if ($parent === '') {
            return $child !== '';
        }

        return $child === $parent || str_starts_with($child, $parent.'/');
    }

    /**
     * Ubah nama menjadi UTF-8 valid.
     *
     * Nama berkas di disk bisa bukan UTF-8 (mis. hasil salinan via SMB), dan
     * json_encode() mengembalikan false untuk string seperti itu sehingga
     * seluruh respons jadi kosong.
     */
    public function toValidUtf8(string $name): string
    {
        if (mb_check_encoding($name, 'UTF-8')) {
            return $name;
        }

        $converted = @mb_convert_encoding($name, 'UTF-8', 'Windows-1252');

        return $converted === false ? '' : $converted;
    }

    private function reject(): never
    {
        throw new HttpException(422, 'Path tidak valid.');
    }
}
