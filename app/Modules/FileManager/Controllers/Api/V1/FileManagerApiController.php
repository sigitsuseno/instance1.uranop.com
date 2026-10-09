<?php

namespace App\Modules\FileManager\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\FileManager\Services\FileManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * API File Manager.
 *
 * Hanya untuk superadmin. Gerbang utamanya adalah middleware route
 * ('role:superadmin'); pemeriksaan di konstruktor adalah lapis kedua agar
 * controller ini tetap aman bila kelak dipakai dari route lain.
 */
class FileManagerApiController extends Controller
{
    public function __construct(private readonly FileManagerService $service)
    {
        abort_unless(
            Auth::user()?->hasRole('superadmin'),
            403,
            'Hanya superadmin yang bisa mengakses File Manager.'
        );
    }

    /** Daftar isi sebuah folder. */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'nullable|string|max:1000',
            'search' => 'nullable|string|max:200',
            'type' => 'nullable|string|max:20',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        return response()->json($this->service->browse(
            $validated['path'] ?? '',
            $validated['search'] ?? null,
            $validated['type'] ?? null,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? FileManagerService::DEFAULT_PER_PAGE),
        ));
    }

    /** Pohon folder, untuk pemilih folder tujuan saat memindahkan berkas. */
    public function tree(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exclude' => 'nullable|string|max:1000',
        ]);

        return response()->json([
            'data' => $this->service->tree($validated['exclude'] ?? null),
        ]);
    }

    public function storeFolder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'nullable|string|max:1000',
            'name' => 'required|string|max:180',
        ]);

        $folder = $this->service->createFolder($validated['path'] ?? '', $validated['name']);
        $this->logMutation('create_folder', $folder['path']);

        return response()->json([
            'message' => 'Folder berhasil dibuat.',
            'data' => $folder,
        ], 201);
    }

    public function rename(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string|max:1000',
            'name' => 'required|string|max:180',
        ]);

        $item = $this->service->rename($validated['path'], $validated['name']);
        $this->logMutation('rename', $item['path']);

        return response()->json([
            'message' => 'Nama berhasil diubah.',
            'data' => $item,
        ]);
    }

    public function move(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paths' => 'required|array|min:1|max:100',
            'paths.*' => 'required|string|max:1000',
            'target' => 'nullable|string|max:1000',
        ]);

        $moved = $this->service->move($validated['paths'], $validated['target'] ?? '');

        foreach ($moved as $item) {
            $this->logMutation('move', $item['path']);
        }

        return response()->json([
            'message' => count($moved) === 0
                ? 'Tidak ada yang perlu dipindahkan.'
                : count($moved).' item berhasil dipindahkan.',
            'data' => $moved,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paths' => 'required|array|min:1|max:100',
            'paths.*' => 'required|string|max:1000',
        ]);

        $deleted = $this->service->deletePaths($validated['paths']);

        foreach ($deleted as $item) {
            $this->logMutation('delete', $item['path']);
        }

        return response()->json([
            'message' => count($deleted).' item berhasil dihapus.',
            'data' => $deleted,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'nullable|string|max:1000',
            'files' => 'required|array|min:1|max:'.FileManagerService::MAX_UPLOAD_FILES,
            'files.*' => 'file|max:'.FileManagerService::MAX_UPLOAD_KB,
        ]);

        $result = $this->service->upload(
            $request->input('path', ''),
            $request->file('files') ?? []
        );

        $storedCount = count($result['stored']);
        $failedCount = count($result['errors']);

        foreach ($result['stored'] as $item) {
            $this->logMutation('upload', $item['path']);
        }

        if ($storedCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada berkas yang berhasil diunggah.',
                'errors' => $result['errors'],
            ], 422);
        }

        return response()->json([
            'success' => $failedCount === 0,
            'message' => $failedCount === 0
                ? $storedCount.' berkas berhasil diunggah.'
                : $storedCount.' berkas berhasil diunggah, '.$failedCount.' gagal.',
            'data' => $result,
        ], 201);
    }

    /** Unduh berkas sebagai lampiran. */
    public function download(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'path' => 'required|string|max:1000',
        ]);

        $info = $this->service->downloadInfo($validated['path']);

        // Tipe sengaja dibuat octet-stream: berkas tidak pernah dirender browser,
        // hanya diunduh, apa pun isinya.
        return response()->download($info['absolute'], $info['name'], [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Sajikan berkas secara inline untuk pratinjau.
     *
     * Hanya tipe pada whitelist FileType yang dilayani; sisanya 415 dan
     * frontend menawarkan unduhan.
     */
    public function preview(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'path' => 'required|string|max:1000',
        ]);

        $info = $this->service->previewInfo($validated['path']);

        abort_unless(
            $info['inline'],
            415,
            'Pratinjau tidak tersedia untuk jenis berkas ini. Silakan unduh berkasnya.'
        );

        return response()->file($info['absolute'], [
            'Content-Type' => $info['mime'],
            'Content-Disposition' => (new ResponseHeaderBag)->makeDisposition(
                ResponseHeaderBag::DISPOSITION_INLINE,
                $info['name'],
                $this->asciiFallback($info['name']),
            ),
            'X-Content-Type-Options' => 'nosniff',
            // Sandbox menetralkan skrip seandainya ada tipe yang lolos.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /** Isi sheet spreadsheet untuk pratinjau. */
    public function excel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string|max:1000',
            'sheet' => 'nullable|string|max:180',
        ]);

        return response()->json([
            'data' => $this->service->excelPreview(
                $validated['path'],
                $validated['sheet'] ?? null
            ),
        ]);
    }

    /** Nama berkas versi ASCII, dipakai sebagai fallback Content-Disposition. */
    private function asciiFallback(string $name): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $ascii = $ascii === false ? '' : $ascii;
        $ascii = preg_replace('/[^\x20-\x7E]/', '', $ascii) ?? '';
        $ascii = str_replace(['"', '\\'], '', $ascii);

        return trim($ascii) === '' ? 'berkas' : trim($ascii);
    }

    private function logMutation(string $action, string $path): void
    {
        // Modul ini tidak punya tabel sendiri, jadi jejak perubahan dicatat ke log.
        Log::info('FileManager: '.$action, [
            'path' => $path,
            'user_id' => Auth::id(),
        ]);
    }
}
