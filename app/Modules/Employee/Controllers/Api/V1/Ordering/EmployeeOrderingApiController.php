<?php

namespace App\Modules\Employee\Controllers\Api\V1\Ordering;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Settings\Models\EmployeeGroupMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeOrderingApiController extends Controller
{
    public function index(Request $request)
    {
        $groupId = $request->query('group_id');

        $query = Employee::select('id', 'name', 'employee_code', 'nik', 'photo', 'no_urut', 'employment_status')
            ->orderByRaw('no_urut IS NULL, no_urut ASC')
            ->orderBy('nip');

        // Filter by group if requested
        if ($groupId) {
            $master = EmployeeGroupMaster::find($groupId);
            if ($master) {
                $validCodes = EmployeeGroupMaster::where('group_label', $master->group_label)
                    ->pluck('code')
                    ->toArray();

                $query->whereHas('groups', function ($q) use ($validCodes) {
                    $q->whereIn('reference_code', $validCodes);
                });
            }
        }

        $employees = $query->get();

        // Ambil daftar group master untuk dropdown filter
        $groups = EmployeeGroupMaster::select('id', 'name', 'group_label', 'code')
            ->whereIn('group_label', function ($q) {
                $q->select('group_label')
                    ->from('employee_group_settings')
                    ->where('is_active', true);
            })
            ->orderBy('group_label')
            ->orderBy('name')
            ->get();

        return response()->json([
            'employees' => $employees,
            'groups' => $groups,
        ]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'ordered_ids' => 'required|array|min:1',
            'ordered_ids.*' => 'integer|exists:employees,id',
        ]);

        $orderedIds = $request->input('ordered_ids');

        DB::beginTransaction();
        try {
            foreach ($orderedIds as $index => $id) {
                Employee::where('id', $id)->update(['no_urut' => $index + 1]);
            }
            DB::commit();

            return response()->json([
                'message' => 'Urutan berhasil disimpan.',
                'count' => count($orderedIds),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan urutan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function autoNumber(Request $request)
    {
        $groupId = $request->input('group_id');
        $scope = $request->input('scope', 'all'); // 'all' atau 'group'

        $query = Employee::select('id', 'name', 'no_urut')
            ->orderBy('nip');

        if ($scope === 'group' && $groupId) {
            $master = EmployeeGroupMaster::find($groupId);
            if ($master) {
                $validCodes = EmployeeGroupMaster::where('group_label', $master->group_label)
                    ->pluck('code')
                    ->toArray();

                $query->whereHas('groups', function ($q) use ($validCodes) {
                    $q->whereIn('reference_code', $validCodes);
                });
            }
        }

        $employees = $query->get();

        DB::beginTransaction();
        try {
            $count = 0;
            foreach ($employees as $index => $emp) {
                Employee::where('id', $emp->id)->update(['no_urut' => $index + 1]);
                $count++;
            }
            DB::commit();

            return response()->json([
                'message' => "Berhasil mengisi nomor urut untuk {$count} karyawan.",
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal auto-number: ' . $e->getMessage(),
            ], 500);
        }
    }
}
