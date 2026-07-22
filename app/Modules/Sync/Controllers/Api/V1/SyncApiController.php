<?php

namespace App\Modules\Sync\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Sync\Models\DesktopLicense;
use App\Modules\Sync\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SyncApiController extends Controller
{
    /**
     * Aktivasi lisensi — dipanggil dari desktop KonfigurasiScreen.
     * Tidak perlu auth.
     *
     * Request: { license_key: "hsk_..." }
     * Response: { valid, instance_name, licensed_until, token }
     */
    public function activate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => 'required|string|min:4',
        ]);

        $key = $validated['license_key'];

        // Validasi format: hsk_ + 48 karakter (total 52)
        if (!str_starts_with($key, 'hsk_') || strlen($key) !== 52) {
            return response()->json([
                'valid'    => false,
                'message'  => 'License key tidak valid. Format: hsk_ diikuti 48 karakter (total 52).',
            ], 422);
        }

        // Cek apakah license key sudah dipakai
        $existing = DesktopLicense::where('license_key', $key)->first();
        if ($existing && $existing->status === 'active') {
            // Sudah aktif, kembalikan token yang ada
            return response()->json([
                'valid'           => true,
                'instance_name'   => $existing->instance_name,
                'licensed_until'  => $existing->licensed_until?->toDateString(),
                'token'           => $existing->token,
                'message'         => 'Lisensi sudah aktif.',
            ]);
        }

        // Generate token untuk desktop
        $token = Str::random(80);

        // Simpan atau update license
        $license = DesktopLicense::updateOrCreate(
            ['license_key' => $key],
            [
                'instance_name'  => 'HRIS Desktop',
                'activated_at'   => now(),
                'licensed_until' => now()->addYear()->toDateString(),
                'status'         => 'active',
                'last_sync_ip'   => $request->ip(),
                'token'          => $token,
            ]
        );

        return response()->json([
            'valid'           => true,
            'instance_name'   => $license->instance_name,
            'licensed_until'  => $license->licensed_until->toDateString(),
            'token'           => $token,
        ]);
    }

    /**
     * Cek status lisensi — dipanggil saat startup dan sebelum sync.
     * Tidak perlu auth.
     */
    public function licenseStatus(Request $request): JsonResponse
    {
        // Cari license aktif
        $license = DesktopLicense::active()->first();
        // Fallback: cari license dengan status apapun
        if (!$license) {
            $license = DesktopLicense::first();
        }

        if (!$license) {
            return response()->json([
                'status'          => 'inactive',
                'instance_name'   => 'Belum diaktivasi',
                'licensed_until'  => null,
            ]);
        }

        // Auto-expire jika sudah lewat
        if ($license->licensed_until && $license->licensed_until < now()->toDateString()) {
            $license->update(['status' => 'expired']);
            return response()->json([
                'status'          => 'expired',
                'instance_name'   => $license->instance_name,
                'licensed_until'  => $license->licensed_until->toDateString(),
            ]);
        }

        // Update last sync time
        $license->update([
            'last_sync_at' => now(),
            'last_sync_ip' => $request->ip(),
        ]);

        return response()->json([
            'status'          => $license->status,
            'instance_name'   => $license->instance_name,
            'licensed_until'  => $license->licensed_until?->toDateString(),
            'activated_at'    => $license->activated_at?->toDateTimeString(),
            'last_sync_at'    => $license->last_sync_at?->toDateTimeString(),
        ]);
    }

    /**
     * Daftar perubahan per modul sejak timestamp tertentu.
     * Auth: desktop.token
     *
     * GET /api/sync/changes?since=2026-07-01T00:00:00
     */
    public function changes(Request $request): JsonResponse
    {
        $since = $request->query('since');

        $changes = SyncService::detectChanges($since);

        return response()->json([
            'changes' => $changes,
            'total'   => count($changes),
            'since'   => $since ?? 'all',
        ]);
    }

    /**
     * PULL data untuk modul tertentu.
     * Auth: desktop.token
     *
     * GET /api/sync/{module}?since=2026-07-01T00:00:00
     */
    public function pull(Request $request, string $module): JsonResponse
    {
        $since = $request->query('since');

        $result = SyncService::pullModule($module, $since);

        if (isset($result['error'])) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    /**
     * PUSH data dari desktop ke server.
     * Auth: desktop.token
     *
     * POST /api/sync/{module}/batch
     * Body: [{ uuid, server_id, data, action }, ...]
     */
    public function push(Request $request, string $module): JsonResponse
    {
        $validated = $request->validate([
            'batch' => 'required|array',
            'batch.*.uuid'       => 'required|string',
            'batch.*.server_id'  => 'nullable|integer',
            'batch.*.action'     => 'required|string|in:created,updated,deleted',
            'batch.*.data'       => 'required|array',
        ]);

        $result = SyncService::pushModule($module, $validated['batch']);

        if (isset($result['error'])) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    /**
     * Sync data organisasi — flat arrays.
     * Auth: desktop.token
     */
    public function organization(): JsonResponse
    {
        return response()->json(SyncService::pullModule('organization', null));
    }

    /**
     * Sync data setting.
     * Auth: desktop.token
     */
    public function settings(): JsonResponse
    {
        return response()->json(SyncService::pullModule('settings', null));
    }

    /**
     * Sync data users.
     * Auth: desktop.token
     */
    public function users(): JsonResponse
    {
        return response()->json(SyncService::pullModule('users', null));
    }

    /**
     * Sync data permissions.
     * Auth: desktop.token
     */
    public function permissions(): JsonResponse
    {
        return response()->json(SyncService::pullModule('permissions', null));
    }
}
