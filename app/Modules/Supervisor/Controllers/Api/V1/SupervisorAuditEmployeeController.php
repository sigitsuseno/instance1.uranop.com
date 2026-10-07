<?php

namespace App\Modules\Supervisor\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Resources\EmployeeListResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupervisorAuditEmployeeController extends Controller
{
    /**
     * GET /api/v1/supervisor/employee-data/karyawan-audit
     *
     * Daftar karyawan untuk papan kanban pemilahan karyawan supervisor.
     * Mengembalikan kedua kelompok sekaligus (masuk / belum masuk) karena
     * kanban menampilkannya berdampingan.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search'        => 'nullable|string|max:100',
            'department_id' => 'nullable|integer|exists:departments,id',
            'status'        => 'nullable|in:aktif,nonaktif,semua',
        ]);

        $status = $filters['status'] ?? 'aktif';

        $employees = Employee::with(['department', 'position'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->search($search))
            ->when($filters['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->when($status === 'aktif', fn ($query) => $query->where('is_active', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('is_active', false))
            ->orderBy('no_urut')
            ->orderBy('nip')
            ->get();

        // Ringkasan dihitung di luar filter, supaya angkanya menunjukkan
        // cakupan keseluruhan dan bukan hasil pencarian yang sedang aktif.
        $counts = Employee::query()
            ->where('is_active', true)
            ->selectRaw('is_audit, COUNT(*) as total')
            ->groupBy('is_audit')
            ->pluck('total', 'is_audit');

        return response()->json([
            'data'  => EmployeeListResource::collection($employees)->resolve(),
            'stats' => [
                'audit'     => (int) ($counts[1] ?? 0),
                'non_audit' => (int) ($counts[0] ?? 0),
            ],
        ]);
    }

    /**
     * POST /api/v1/supervisor/employee-data/karyawan-audit/bulk-update
     *
     * Memindahkan karyawan antar kolom kanban.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'changes'               => 'required|array|min:1',
            'changes.*.employee_id' => 'required|integer|exists:employees,id',
            'changes.*.is_audit'    => 'required|boolean',
        ]);

        $userId = Auth::id();
        $updated = 0;
        $skipped = 0;

        DB::beginTransaction();

        try {
            foreach ($data['changes'] as $change) {
                $employee = Employee::find($change['employee_id']);

                if (! $employee) {
                    $skipped++;

                    continue;
                }

                $target = (bool) $change['is_audit'];

                if ((bool) $employee->is_audit === $target) {
                    $skipped++;

                    continue;
                }

                // Disimpan per baris, bukan lewat whereIn()->update(), karena
                // mass update tidak memicu event model — HasAuditLog tidak akan
                // mencatat perubahannya dan updated_by tidak terisi.
                $employee->is_audit = $target;
                $employee->updated_by = $userId;
                $employee->save();

                $updated++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menyimpan perubahan: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => "Berhasil menyimpan perubahan pada {$updated} karyawan.",
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }
}
