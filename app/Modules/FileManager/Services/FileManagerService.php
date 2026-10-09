<?php

namespace App\Modules\FileManager\Services;

use App\Modules\FileManager\Support\ExcelRangeLimitFilter;
use App\Modules\FileManager\Support\FileManagerPathGuard;
use App\Modules\FileManager\Support\FileType;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Logika pengelolaan berkas pada disk privat "file_manager".
 *
 * Folder dan berkas di disk adalah sumber kebenaran tunggal — tidak ada tabel
 * registri, sehingga tidak ada risiko data baris dan isi disk saling menyimpang.
 *
 * Seluruh path yang berasal dari pengguna WAJIB lewat FileManagerPathGuard.
 * Pengecualiannya adalah hasil penelusuran disk sendiri (Flysystem sudah
 * terkurung di dalam root), yang path absolutnya cukup disusun dari root.
 */
class FileManagerService
{
    public const DEFAULT_PER_PAGE = 25;

    /** Batas jumlah berkas per satu kali unggah. */
    public const MAX_UPLOAD_FILES = 50;

    /** Batas ukuran per berkas unggahan (KB, mengikuti satuan aturan validasi Laravel). */
    public const MAX_UPLOAD_KB = 20480;

    /** Batas ukuran berkas yang boleh dipratinjau di browser. */
    public const MAX_PREVIEW_BYTES = 10 * 1024 * 1024;

    /** Batas ukuran berkas spreadsheet yang boleh dibaca untuk pratinjau isi. */
    public const MAX_EXCEL_BYTES = 10 * 1024 * 1024;

    public const EXCEL_MAX_ROWS = 200;

    public const EXCEL_MAX_COLUMNS = 30;

    public const EXCEL_MAX_SHEETS = 15;

    /** Batas jumlah entri yang ditelusuri saat pencarian rekursif. */
    public const SCAN_LIMIT = 5000;

    /** Batas jumlah node pada pohon folder (untuk pemilih folder tujuan). */
    private const TREE_NODE_LIMIT = 2000;

    private const MAX_TREE_DEPTH = 20;

    public function __construct(private readonly FileManagerPathGuard $guard) {}

    protected function disk(): Filesystem
    {
        return Storage::disk(FileManagerPathGuard::DISK);
    }

    /**
     * Daftar isi sebuah folder.
     *
     * Folder selalu ditampilkan penuh; paginasi hanya berlaku untuk berkas.
     */
    public function browse(string $path, ?string $search, ?string $type, int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $path = $this->guard->normalize($path);
        $root = $this->guard->root();
        $absoluteDir = $this->guard->absolute($path);

        if (! is_dir($absoluteDir)) {
            throw new HttpException(422, 'Bukan sebuah folder.');
        }

        $perPage = max(1, min($perPage, 200));
        $page = max(1, $page);
        $search = trim((string) $search);
        $type = $this->normalizeType($type);

        $folderPaths = [];
        $filePaths = [];
        $truncated = false;

        if ($search !== '') {
            [$folderPaths, $filePaths, $truncated] = $this->searchRecursive($absoluteDir, $root, $search, $type);
        } else {
            foreach ($this->disk()->directories($path) as $directory) {
                $folderPaths[] = $directory;
            }

            foreach ($this->disk()->files($path) as $file) {
                $filePaths[] = $file;
            }
        }

        $folders = [];
        foreach ($folderPaths as $relative) {
            $absolute = $this->absoluteFrom($root, $relative);

            // Symlink dilewati agar tidak menjadi jalan keluar dari root.
            if (is_link($absolute) || ! is_dir($absolute)) {
                continue;
            }

            $folders[] = $this->describeFolder($relative, $absolute);
        }

        $files = [];
        foreach ($filePaths as $relative) {
            $absolute = $this->absoluteFrom($root, $relative);

            if (is_link($absolute) || ! is_file($absolute)) {
                continue;
            }

            $files[] = $this->describeFile($relative, $absolute);
        }

        usort($folders, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
        usort($files, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        $total = count($files);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        return [
            'data' => array_slice($files, ($page - 1) * $perPage, $perPage),
            'folders' => $folders,
            'breadcrumbs' => $this->breadcrumbs($path),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'truncated' => $truncated,
        ];
    }

    /**
     * Pohon folder sebagai daftar datar ber-indentasi, untuk pemilih folder tujuan.
     *
     * @param  string|null  $exclude  Path yang dikeluarkan beserta seluruh turunannya.
     */
    public function tree(?string $exclude = null): array
    {
        $exclude = ($exclude === null || $exclude === '') ? null : $this->guard->normalize($exclude);
        $root = $this->guard->root();

        $nodes = [['name' => 'Beranda', 'path' => '', 'depth' => 0, 'disabled' => false]];

        $this->walkTree($root, '', 0, $exclude, $nodes);

        return $nodes;
    }

    public function createFolder(string $parentPath, string $name): array
    {
        $parentPath = $this->guard->normalize($parentPath);
        $name = $this->guard->assertName($name);

        $parentAbsolute = $this->guard->absolute($parentPath);

        if (! is_dir($parentAbsolute)) {
            throw new HttpException(422, 'Folder tujuan tidak ditemukan.');
        }

        $target = $parentAbsolute.DIRECTORY_SEPARATOR.$name;

        // file_exists bersifat case-insensitive di Windows, jadi ini juga
        // mencegah lahirnya folder yang hanya beda huruf besar/kecil.
        if (file_exists($target)) {
            throw new HttpException(422, "Sudah ada berkas atau folder bernama \"{$name}\" di sini.");
        }

        if (! @mkdir($target, 0775)) {
            throw new HttpException(500, 'Gagal membuat folder.');
        }

        return $this->describeFolder($this->join($parentPath, $name), $target);
    }

    /** Ganti nama berkas atau folder; jenisnya dideteksi dari disk. */
    public function rename(string $path, string $newName): array
    {
        $normalized = $this->requireNotRoot($path, 'Path tidak valid.');
        $absolute = $this->guard->absolute($normalized);

        if (is_dir($absolute)) {
            return $this->renameFolder($normalized, $newName);
        }

        if (is_file($absolute)) {
            return $this->renameFile($normalized, $newName);
        }

        throw new HttpException(404, 'Berkas atau folder tidak ditemukan.');
    }

    public function renameFolder(string $path, string $newName): array
    {
        $path = $this->requireNotRoot($path, 'Folder utama tidak bisa diganti nama.');
        $newName = $this->guard->assertName($newName);

        $absolute = $this->guard->absolute($path);

        if (! is_dir($absolute)) {
            throw new HttpException(422, 'Bukan sebuah folder.');
        }

        $parentRelative = $this->parentOf($path);
        $parentAbsolute = $this->guard->absolute($parentRelative);
        $target = $parentAbsolute.DIRECTORY_SEPARATOR.$newName;

        $this->moveOnDisk($absolute, $target, $newName);

        return $this->describeFolder($this->join($parentRelative, $newName), $target);
    }

    public function deleteFolder(string $path): array
    {
        $path = $this->requireNotRoot($path, 'Folder utama tidak bisa dihapus.');

        $absolute = $this->guard->absolute($path);

        if (! is_dir($absolute)) {
            throw new HttpException(422, 'Bukan sebuah folder.');
        }

        $this->removeDirectory($absolute);

        if (is_dir($absolute)) {
            throw new HttpException(500, 'Sebagian isi folder gagal dihapus.');
        }

        return ['path' => $path, 'name' => $this->guard->toValidUtf8(basename($path))];
    }

    public function renameFile(string $path, string $newName): array
    {
        $path = $this->requireNotRoot($path, 'Path tidak valid.');
        $newName = $this->guard->assertName($newName);

        if (FileType::isBlockedExtension($newName)) {
            throw new HttpException(422, 'Jenis berkas ini tidak diizinkan.');
        }

        $absolute = $this->guard->absolute($path);

        if (! is_file($absolute)) {
            throw new HttpException(404, 'Berkas tidak ditemukan.');
        }

        $parentRelative = $this->parentOf($path);
        $parentAbsolute = $this->guard->absolute($parentRelative);
        $target = $parentAbsolute.DIRECTORY_SEPARATOR.$newName;

        $this->moveOnDisk($absolute, $target, $newName);

        return $this->describeFile($this->join($parentRelative, $newName), $target);
    }

    public function deleteFile(string $path): array
    {
        $path = $this->requireNotRoot($path, 'Path tidak valid.');
        $absolute = $this->guard->absolute($path);

        if (! is_file($absolute)) {
            throw new HttpException(404, 'Berkas tidak ditemukan.');
        }

        if (! @unlink($absolute)) {
            throw new HttpException(500, 'Gagal menghapus berkas.');
        }

        return ['path' => $path, 'name' => $this->guard->toValidUtf8(basename($path))];
    }

    /**
     * Hapus beberapa path sekaligus; tiap path boleh berupa berkas atau folder.
     *
     * @param  array<int, string>  $paths
     */
    public function deletePaths(array $paths): array
    {
        $deleted = [];

        foreach ($paths as $path) {
            $normalized = $this->guard->normalize($path);

            if ($normalized === '') {
                throw new HttpException(422, 'Folder utama tidak bisa dihapus.');
            }

            $absolute = $this->guard->absolute($normalized);

            if (is_dir($absolute)) {
                $deleted[] = $this->deleteFolder($normalized);

                continue;
            }

            $deleted[] = $this->deleteFile($normalized);
        }

        return $deleted;
    }

    /**
     * Pindahkan berkas dan/atau folder ke folder tujuan.
     *
     * @param  array<int, string>  $paths
     */
    public function move(array $paths, string $targetPath): array
    {
        $targetPath = $this->guard->normalize($targetPath);
        $targetAbsolute = $this->guard->absolute($targetPath);

        if (! is_dir($targetAbsolute)) {
            throw new HttpException(422, 'Folder tujuan tidak ditemukan.');
        }

        $moved = [];

        foreach ($paths as $path) {
            $source = $this->requireNotRoot($path, 'Folder utama tidak bisa dipindahkan.');

            // Memindahkan folder ke dalam dirinya sendiri akan menghilangkan isinya.
            if ($this->guard->isInside($targetPath, $source)) {
                throw new HttpException(422, 'Folder tidak bisa dipindahkan ke dalam dirinya sendiri.');
            }

            $sourceAbsolute = $this->guard->absolute($source);

            if (! file_exists($sourceAbsolute)) {
                throw new HttpException(404, 'Berkas atau folder tidak ditemukan.');
            }

            // Sudah berada di folder tujuan — tidak ada yang perlu dilakukan.
            if ($this->sameDirectory(dirname($sourceAbsolute), $targetAbsolute)) {
                continue;
            }

            $name = basename($sourceAbsolute);

            if (is_dir($sourceAbsolute)) {
                $destination = $targetAbsolute.DIRECTORY_SEPARATOR.$name;

                // Folder tidak pernah digabung diam-diam; minta pengguna memutuskan.
                if (file_exists($destination)) {
                    throw new HttpException(422, "Sudah ada folder bernama \"{$name}\" di folder tujuan.");
                }

                if (! @rename($sourceAbsolute, $destination)) {
                    throw new HttpException(500, 'Gagal memindahkan folder.');
                }

                $moved[] = $this->describeFolder($this->join($targetPath, $name), $destination);

                continue;
            }

            $unique = $this->uniqueName($targetAbsolute, $name);
            $destination = $targetAbsolute.DIRECTORY_SEPARATOR.$unique;

            if (! @rename($sourceAbsolute, $destination)) {
                throw new HttpException(500, 'Gagal memindahkan berkas.');
            }

            $moved[] = $this->describeFile($this->join($targetPath, $unique), $destination);
        }

        return $moved;
    }

    /**
     * Simpan berkas yang diunggah ke folder tujuan.
     *
     * Satu berkas yang gagal tidak membatalkan sisanya; kegagalan dikumpulkan
     * agar pengguna tahu persis mana yang bermasalah.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function upload(string $dirPath, array $files): array
    {
        $dirPath = $this->guard->normalize($dirPath);
        $dirAbsolute = $this->guard->absolute($dirPath);

        if (! is_dir($dirAbsolute)) {
            throw new HttpException(422, 'Folder tujuan tidak ditemukan.');
        }

        $stored = [];
        $errors = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $original = $this->guard->toValidUtf8((string) $file->getClientOriginalName());
            $original = basename(str_replace('\\', '/', $original));

            try {
                if (! $file->isValid()) {
                    throw new HttpException(422, 'Berkas gagal diunggah.');
                }

                $name = $this->guard->assertName($original);

                if (FileType::isBlockedExtension($name)) {
                    throw new HttpException(422, 'Jenis berkas ini tidak diizinkan.');
                }

                $unique = $this->uniqueName($dirAbsolute, $name);

                if ($file->storeAs($dirPath, $unique, FileManagerPathGuard::DISK) === false) {
                    throw new HttpException(500, 'Gagal menyimpan berkas.');
                }

                $relative = $this->join($dirPath, $unique);
                $stored[] = $this->describeFile($relative, $this->absoluteFrom($this->guard->root(), $relative));
            } catch (HttpException $e) {
                $errors[] = ['name' => $original, 'error' => $e->getMessage()];
            } catch (\Throwable $e) {
                Log::error('FileManager: gagal menyimpan unggahan.', [
                    'name' => $original,
                    'path' => $dirPath,
                    'message' => $e->getMessage(),
                ]);

                $errors[] = ['name' => $original, 'error' => 'Gagal menyimpan berkas.'];
            }
        }

        return ['stored' => $stored, 'errors' => $errors];
    }

    /** Informasi berkas untuk unduhan. */
    public function downloadInfo(string $path): array
    {
        $path = $this->requireNotRoot($path, 'Path tidak valid.');
        $absolute = $this->guard->absolute($path);

        if (! is_file($absolute)) {
            throw new HttpException(404, 'Berkas tidak ditemukan.');
        }

        return [
            'absolute' => $absolute,
            'name' => basename($absolute),
            'size' => (int) (filesize($absolute) ?: 0),
        ];
    }

    /**
     * Informasi berkas untuk pratinjau inline.
     *
     * 'mime' berisi null bila berkas tidak boleh disajikan inline — pemanggil
     * wajib membalas 415 dan menawarkan unduhan saja.
     */
    public function previewInfo(string $path): array
    {
        $path = $this->requireNotRoot($path, 'Path tidak valid.');
        $absolute = $this->guard->absolute($path);

        if (! is_file($absolute)) {
            throw new HttpException(404, 'Berkas tidak ditemukan.');
        }

        $size = (int) (filesize($absolute) ?: 0);

        if ($size > self::MAX_PREVIEW_BYTES) {
            throw new HttpException(413, 'Berkas terlalu besar untuk dipratinjau. Silakan unduh berkasnya.');
        }

        $mime = FileType::inlineMime($absolute);

        return [
            'absolute' => $absolute,
            'name' => basename($absolute),
            'size' => $size,
            'mime' => $mime,
            'inline' => $mime !== null,
        ];
    }

    /**
     * Baca isi spreadsheet untuk pratinjau (nama sheet dan beberapa baris awal).
     *
     * Pembacaan dibatasi oleh ExcelRangeLimitFilter sehingga sel di luar batas
     * tidak pernah dibuat di memori.
     */
    public function excelPreview(string $path, ?string $sheet = null): array
    {
        $path = $this->requireNotRoot($path, 'Path tidak valid.');
        $absolute = $this->guard->absolute($path);

        if (! is_file($absolute)) {
            throw new HttpException(404, 'Berkas tidak ditemukan.');
        }

        if (! FileType::isSpreadsheet(basename($absolute))) {
            throw new HttpException(422, 'Pratinjau isi hanya tersedia untuk berkas Excel (.xlsx/.xls).');
        }

        if ((int) (filesize($absolute) ?: 0) > self::MAX_EXCEL_BYTES) {
            throw new HttpException(422, 'Berkas terlalu besar untuk dibaca isinya.');
        }

        try {
            $reader = IOFactory::createReaderForFile($absolute);
            $reader->setReadDataOnly(true);

            // Info per sheet dibaca dari metadata workbook (murah), dan dipakai
            // untuk mengetahui jumlah baris sebenarnya sebelum pemotongan.
            $info = method_exists($reader, 'listWorksheetInfo') ? $reader->listWorksheetInfo($absolute) : [];
            $rowsByName = [];
            $names = [];

            foreach ($info as $entry) {
                $name = (string) ($entry['worksheetName'] ?? '');
                if ($name === '') {
                    continue;
                }
                $names[] = $name;
                $rowsByName[$name] = (int) ($entry['totalRows'] ?? 0);
            }

            if ($names === []) {
                $names = method_exists($reader, 'listWorksheetNames') ? $reader->listWorksheetNames($absolute) : [];
            }

            if ($names === []) {
                throw new HttpException(422, 'Berkas tidak memiliki sheet yang bisa dibaca.');
            }

            if ($sheet !== null && $sheet !== '') {
                if (! in_array($sheet, $names, true)) {
                    throw new HttpException(422, 'Sheet tersebut tidak ditemukan di dalam berkas.');
                }
                $selected = [$sheet];
            } else {
                $selected = array_slice($names, 0, self::EXCEL_MAX_SHEETS);
            }

            $reader->setReadFilter(new ExcelRangeLimitFilter(self::EXCEL_MAX_ROWS, self::EXCEL_MAX_COLUMNS));

            if (method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly($selected);
            }

            $spreadsheet = $reader->load($absolute);

            $sheets = [];
            foreach ($spreadsheet->getAllSheets() as $worksheet) {
                $title = $worksheet->getTitle();
                $totalRows = $rowsByName[$title] ?? null;

                $rows = [];
                foreach ($worksheet->toArray(null, true, false, false) as $row) {
                    $rows[] = array_map(fn ($value) => $this->stringifyCell($value), $row);
                }

                $sheets[] = [
                    'name' => $title,
                    'rows' => $rows,
                    'total_rows' => $totalRows,
                    'truncated' => $totalRows !== null && $totalRows > self::EXCEL_MAX_ROWS,
                ];
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return [
                'sheets' => $sheets,
                'sheet_names' => $names,
                'loaded_sheets' => count($sheets),
                'total_sheets' => count($names),
                'max_rows' => self::EXCEL_MAX_ROWS,
                'max_columns' => self::EXCEL_MAX_COLUMNS,
            ];
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('FileManager: gagal membaca berkas Excel.', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            throw new HttpException(422, 'Isi berkas tidak bisa dibaca. Pastikan berkas Excel tidak rusak.');
        }
    }

    // -----------------------------------------------------------------------
    // Penelusuran
    // -----------------------------------------------------------------------

    /**
     * Pencarian rekursif dari folder aktif, dibatasi SCAN_LIMIT entri.
     *
     * @return array{0: array<int, string>, 1: array<int, string>, 2: bool}
     */
    private function searchRecursive(string $base, string $root, string $search, ?string $type): array
    {
        $folders = [];
        $files = [];
        $visited = 0;
        $truncated = false;
        $needle = mb_strtolower($search);

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if (++$visited > self::SCAN_LIMIT) {
                    $truncated = true;
                    break;
                }

                /** @var \SplFileInfo $item */
                // Symlink tidak ditelusuri maupun ditampilkan.
                if ($item->isLink()) {
                    continue;
                }

                $absolute = $item->getPathname();
                $relative = $this->relativeFrom($root, $absolute);

                if ($relative === null) {
                    continue;
                }

                $name = $item->getFilename();
                $matches = str_contains(mb_strtolower($this->guard->toValidUtf8($name)), $needle);

                if ($item->isDir()) {
                    // Saat memfilter jenis berkas, folder disembunyikan agar
                    // hasil pencarian fokus pada berkas.
                    if ($type === null && $matches) {
                        $folders[] = $relative;
                    }

                    continue;
                }

                if (! $item->isFile() || ! $matches) {
                    continue;
                }

                if ($type !== null && FileType::categoryOf($name) !== $type) {
                    continue;
                }

                $files[] = $relative;
            }
        } catch (\Throwable $e) {
            Log::warning('FileManager: penelusuran terhenti.', [
                'base' => $base,
                'message' => $e->getMessage(),
            ]);
        }

        return [$folders, $files, $truncated];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function walkTree(string $absoluteDir, string $relative, int $depth, ?string $exclude, array &$nodes): void
    {
        if ($depth >= self::MAX_TREE_DEPTH || count($nodes) >= self::TREE_NODE_LIMIT) {
            return;
        }

        $entries = @scandir($absoluteDir) ?: [];
        $directories = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $absolute = $absoluteDir.DIRECTORY_SEPARATOR.$entry;

            if (is_link($absolute) || ! is_dir($absolute)) {
                continue;
            }

            $directories[] = $entry;
        }

        usort($directories, 'strnatcasecmp');

        foreach ($directories as $entry) {
            $childRelative = $this->join($relative, $entry);
            $disabled = $exclude !== null && $this->guard->isInside($childRelative, $exclude);

            $nodes[] = [
                'name' => $this->guard->toValidUtf8($entry),
                'path' => $childRelative,
                'depth' => $depth + 1,
                'disabled' => $disabled,
            ];

            // Turunan dari path yang dikecualikan pasti ikut dikecualikan.
            if (! $disabled) {
                $this->walkTree($absolute, $childRelative, $depth + 1, $exclude, $nodes);
            }
        }
    }

    // -----------------------------------------------------------------------
    // Deskripsi entri
    // -----------------------------------------------------------------------

    private function describeFile(string $relative, string $absolute): array
    {
        $name = basename($relative);
        $size = (int) (@filesize($absolute) ?: 0);
        $modified = @filemtime($absolute);

        return [
            'type' => 'file',
            'name' => $this->guard->toValidUtf8($name),
            'path' => $relative,
            'size' => $size,
            'size_human' => $this->humanSize($size),
            'ext' => FileType::extension($name),
            'category' => FileType::categoryOf($name),
            'icon' => FileType::icon($name),
            'preview_kind' => FileType::previewKind($name),
            'modified_at' => $modified ? date(DATE_ATOM, $modified) : null,
        ];
    }

    private function describeFolder(string $relative, string $absolute): array
    {
        $modified = @filemtime($absolute);

        return [
            'type' => 'folder',
            'name' => $this->guard->toValidUtf8(basename($relative)),
            'path' => $relative,
            'item_count' => $this->countChildren($absolute),
            'modified_at' => $modified ? date(DATE_ATOM, $modified) : null,
        ];
    }

    private function countChildren(string $absolute): int
    {
        $count = 0;

        try {
            foreach (new \DirectoryIterator($absolute) as $entry) {
                if (! $entry->isDot()) {
                    $count++;
                }
            }
        } catch (\Throwable) {
            return 0;
        }

        return $count;
    }

    /** @return array<int, array{name: string, path: string}> */
    private function breadcrumbs(string $path): array
    {
        $crumbs = [['name' => 'Beranda', 'path' => '']];

        if ($path === '') {
            return $crumbs;
        }

        $accumulated = '';

        foreach (explode('/', $path) as $segment) {
            $accumulated = $this->join($accumulated, $segment);
            $crumbs[] = ['name' => $this->guard->toValidUtf8($segment), 'path' => $accumulated];
        }

        return $crumbs;
    }

    // -----------------------------------------------------------------------
    // Operasi berkas
    // -----------------------------------------------------------------------

    /**
     * Ganti nama berkas/folder di disk, termasuk perubahan besar-kecil huruf saja.
     *
     * Di Windows, rename "a.txt" menjadi "A.txt" gagal karena nama tujuan
     * dianggap sudah ada. Karena itu perubahan yang hanya menyangkut
     * besar-kecil huruf dilakukan lewat nama sementara.
     */
    private function moveOnDisk(string $source, string $target, string $newName): void
    {
        $sameName = strcasecmp(basename($source), $newName) === 0;

        if (file_exists($target) && ! $sameName) {
            throw new HttpException(422, "Sudah ada berkas atau folder bernama \"{$newName}\" di sini.");
        }

        if (basename($source) === $newName) {
            return;
        }

        if ($sameName && file_exists($target)) {
            $temporary = $source.'.fm-rename-'.bin2hex(random_bytes(4));

            if (! @rename($source, $temporary)) {
                throw new HttpException(500, 'Gagal mengganti nama.');
            }

            if (! @rename($temporary, $target)) {
                @rename($temporary, $source);

                throw new HttpException(500, 'Gagal mengganti nama.');
            }

            return;
        }

        if (! @rename($source, $target)) {
            throw new HttpException(500, 'Gagal mengganti nama.');
        }
    }

    /**
     * Hapus folder beserta isinya.
     *
     * Ditulis manual (tidak memakai deleteDirectory milik Flysystem) supaya
     * symlink di dalam folder dihapus sebagai tautan, bukan isi targetnya.
     */
    private function removeDirectory(string $absolute): void
    {
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                /** @var \SplFileInfo $item */
                if ($item->isLink() || $item->isFile()) {
                    @unlink($item->getPathname());

                    continue;
                }

                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('FileManager: penghapusan folder tidak selesai.', [
                'path' => $absolute,
                'message' => $e->getMessage(),
            ]);
        }

        @rmdir($absolute);
    }

    /** Nama unik di dalam folder tujuan, dengan penomoran "nama (1).ext". */
    private function uniqueName(string $directoryAbsolute, string $name): string
    {
        if (! file_exists($directoryAbsolute.DIRECTORY_SEPARATOR.$name)) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = $extension === '' ? $name : substr($name, 0, -(strlen($extension) + 1));
        $suffix = $extension === '' ? '' : '.'.$extension;

        for ($i = 1; $i <= 999; $i++) {
            $candidate = $base.' ('.$i.')'.$suffix;

            if (! file_exists($directoryAbsolute.DIRECTORY_SEPARATOR.$candidate)) {
                return $candidate;
            }
        }

        throw new HttpException(422, 'Terlalu banyak berkas dengan nama yang sama di folder ini.');
    }

    // -----------------------------------------------------------------------
    // Utilitas
    // -----------------------------------------------------------------------

    private function requireNotRoot(string $path, string $message): string
    {
        $normalized = $this->guard->normalize($path);

        if ($normalized === '') {
            throw new HttpException(422, $message);
        }

        return $normalized;
    }

    private function absoluteFrom(string $root, string $relative): string
    {
        return $relative === ''
            ? $root
            : $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function relativeFrom(string $root, string $absolute): ?string
    {
        $prefix = rtrim(str_replace('\\', '/', $root), '/').'/';
        $normalized = str_replace('\\', '/', $absolute);

        if (! str_starts_with($normalized, $prefix)) {
            return null;
        }

        return substr($normalized, strlen($prefix));
    }

    private function parentOf(string $path): string
    {
        $position = strrpos($path, '/');

        return $position === false ? '' : substr($path, 0, $position);
    }

    private function join(string $base, string $name): string
    {
        return $base === '' ? $name : $base.'/'.$name;
    }

    private function sameDirectory(string $left, string $right): bool
    {
        $leftReal = realpath($left);
        $rightReal = realpath($right);

        if ($leftReal === false || $rightReal === false) {
            return false;
        }

        return strcasecmp(rtrim($leftReal, DIRECTORY_SEPARATOR), rtrim($rightReal, DIRECTORY_SEPARATOR)) === 0;
    }

    private function normalizeType(?string $type): ?string
    {
        $type = $type === null ? '' : strtolower(trim($type));

        if ($type === '' || $type === 'all') {
            return null;
        }

        return in_array($type, FileType::CATEGORIES, true) ? $type : null;
    }

    private function stringifyCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof RichText) {
            return $value->getPlainText();
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string) (int) $size : number_format($size, 1, ',', '.')).' '.$units[$unit];
    }
}
