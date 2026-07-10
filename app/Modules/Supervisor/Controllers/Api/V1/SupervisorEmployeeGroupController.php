<?php

namespace App\Modules\Supervisor\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Supervisor\Models\SupervisorEmployeeGroup;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupervisorEmployeeGroupController extends Controller
{
    /**
     * GET kanban data: available employees (with roster) + existing groups.
     */
    public function index(Request $request): JsonResponse
    {
        $year  = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', date('n'));

        // Periode: tanggal 25 bulan sebelumnya s/d 24 bulan sekarang (cut-off roster)
        $periodDate = Carbon::createFromDate($year, $month, 1);
        $startDate  = $periodDate->copy()->subMonth()->format('Y-m-25');
        $endDate    = $periodDate->copy()->format('Y-m-24');

        // ── Ambil employee_id yang punya roster di periode terpilih ──
        $rosterEmployeeIds = EmployeeShiftRoster::whereBetween('date', [$startDate, $endDate])
            ->distinct()
            ->pluck('employee_id');

        $employees = Employee::select('id', 'name', 'employee_code', 'nip', 'photo', 'no_urut', 'department_id', 'position_id', 'employment_status')
            ->whereIn('id', $rosterEmployeeIds)
            ->with(['department:id,name', 'position:id,name'])
            ->orderByRaw('no_urut IS NULL, no_urut ASC')
            ->orderBy('nip')
            ->get();

        // ── Ambil data group yang sudah tersimpan untuk periode ini ──
        $groupedRecords = SupervisorEmployeeGroup::where('period_start', $startDate)
            ->where('period_end', $endDate)
            ->with('employee:id,name,employee_code,nip,photo')
            ->get();

        // Group by group_code untuk kanban columns
        $groupedByCode = [];
        foreach ($groupedRecords as $record) {
            $groupedByCode[$record->group_code][] = [
                'id'              => $record->id,
                'uuid'            => $record->uuid,
                'employee_id'     => $record->employee_id,
                'employee'        => $record->employee ? [
                    'id'            => $record->employee->id,
                    'name'          => $record->employee->name,
                    'employee_code' => $record->employee->employee_code,
                    'nip'           => $record->employee->nip,
                    'photo'         => $record->employee->photo_url ?? null,
                ] : null,
                'group_name'      => $record->group_name,
                'group_code'      => $record->group_code,
                'group_component' => $record->group_component,
                'notes'           => $record->notes,
            ];
        }

        // Daftar grup unik (untuk column headers)
        $groups = $groupedRecords->unique('group_code')->map(fn($r) => [
            'group_name' => $r->group_name,
            'group_code' => $r->group_code,
        ])->values();

        // Employee IDs yang sudah di-group (supaya ga muncul di kolom available)
        $groupedEmployeeIds = $groupedRecords->pluck('employee_id')->unique()->toArray();

        return response()->json([
            'employees'           => $employees,           // semua karyawan roster
            'grouped_employee_ids' => $groupedEmployeeIds, // ID yang sudah di-group
            'groups'              => $groups,               // daftar grup
            'grouped_by_code'     => $groupedByCode,        // karyawan per group
            'period'              => [
                'start' => $startDate,
                'end'   => $endDate,
                'year'  => $year,
                'month' => $month,
            ],
        ]);
    }

    /**
     * POST assign karyawan ke group (single, dari drag & drop).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id'     => 'required|integer|exists:employees,id',
            'group_name'      => 'required|string|max:100',
            'group_code'      => 'required|string|max:50',
            'group_component' => 'nullable|array',
            'period_start'    => 'required|date',
            'period_end'      => 'required|date',
            'notes'           => 'nullable|string|max:500',
        ]);

        // Cek duplikat
        $exists = SupervisorEmployeeGroup::where('employee_id', $data['employee_id'])
            ->where('group_code', $data['group_code'])
            ->where('period_start', $data['period_start'])
            ->where('period_end', $data['period_end'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Karyawan sudah ada di grup ini.'], 409);
        }

        $group = SupervisorEmployeeGroup::create($data);

        $group->load('employee:id,name,employee_code,nip,photo');

        return response()->json([
            'message' => 'Karyawan berhasil ditambahkan ke grup.',
            'data'    => [
                'id'              => $group->id,
                'uuid'            => $group->uuid,
                'employee_id'     => $group->employee_id,
                'employee'        => $group->employee ? [
                    'id'            => $group->employee->id,
                    'name'          => $group->employee->name,
                    'employee_code' => $group->employee->employee_code,
                    'nip'           => $group->employee->nip,
                    'photo'         => $group->employee->photo_url ?? null,
                ] : null,
                'group_name'      => $group->group_name,
                'group_code'      => $group->group_code,
                'group_component' => $group->group_component,
                'notes'           => $group->notes,
            ],
        ], 201);
    }

    /**
     * DELETE hapus karyawan dari group.
     */
    public function destroy($id): JsonResponse
    {
        $group = SupervisorEmployeeGroup::findOrFail($id);
        $group->delete();

        return response()->json(['message' => 'Karyawan berhasil dihapus dari grup.']);
    }

    /**
     * POST bulk-update dari kanban (drag & drop batch).
     * Menerima array assignments dan removals.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date',
            'assignments'  => 'nullable|array',
            'assignments.*.employee_id'     => 'required|integer|exists:employees,id',
            'assignments.*.group_name'      => 'required|string|max:100',
            'assignments.*.group_code'      => 'required|string|max:50',
            'assignments.*.group_component' => 'nullable|array',
            'assignments.*.notes'           => 'nullable|string|max:500',
            'removals'     => 'nullable|array',
            'removals.*'   => 'integer|exists:supervisor_employee_groups,id',
        ]);

        DB::beginTransaction();
        try {
            // Process removals
            if (!empty($data['removals'])) {
                SupervisorEmployeeGroup::whereIn('id', $data['removals'])->delete();
            }

            // Process assignments
            if (!empty($data['assignments'])) {
                foreach ($data['assignments'] as $assignment) {
                    SupervisorEmployeeGroup::updateOrCreate(
                        [
                            'employee_id'  => $assignment['employee_id'],
                            'group_code'   => $assignment['group_code'],
                            'period_start' => $data['period_start'],
                            'period_end'   => $data['period_end'],
                        ],
                        [
                            'group_name'      => $assignment['group_name'],
                            'group_component' => $assignment['group_component'] ?? null,
                            'notes'           => $assignment['notes'] ?? null,
                        ]
                    );
                }
            }

            DB::commit();

            return response()->json(['message' => 'Perubahan berhasil disimpan.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET daftar group yang tersedia (untuk dropdown / modal).
     */
    public function groupOptions(Request $request): JsonResponse
    {
        $periodStart = $request->query('period_start');
        $periodEnd   = $request->query('period_end');

        $query = SupervisorEmployeeGroup::select('group_name', 'group_code')
            ->distinct()
            ->orderBy('group_name');

        if ($periodStart && $periodEnd) {
            $query->where('period_start', $periodStart)
                  ->where('period_end', $periodEnd);
        }

        return response()->json(['data' => $query->get()]);
    }
}
