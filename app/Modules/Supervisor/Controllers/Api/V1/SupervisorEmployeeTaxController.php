<?php

namespace App\Modules\Supervisor\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use Illuminate\Http\Request;

class SupervisorEmployeeTaxController extends Controller
{
    /**
     * Display a listing of employees with their tax configurations.
     */
    public function index(Request $request)
    {
        $periodId = $request->input('period_id');
        $search = $request->input('search');

        $query = Employee::query();

        if ($periodId) {
            $period = PayPeriod::find($periodId);
            if ($period) {
                $query->activeInPeriod($period->start_date, $period->end_date)
                      ->whereHas('shiftRosters', function ($q) use ($period) {
                          $q->whereBetween('date', [$period->start_date, $period->end_date]);
                      });
            }
        }

        if ($search) {
            $query->search($search);
        }

        // Fetch paginated results
        $employees = $query->orderBy('name')
            ->select(['id', 'name', 'employee_code', 'nik', 'npwp', 'has_npwp', 'ptkp'])
            ->paginate($request->integer('per_page', 15));

        return response()->json($employees);
    }

    /**
     * Update the specified employee's tax config in storage.
     */
    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $validated = $request->validate([
            'npwp' => 'nullable|string|max:50',
            'has_npwp' => 'boolean',
            'ptkp' => 'nullable|string|max:10',
        ]);

        $employee->update($validated);

        return response()->json([
            'message' => 'Konfigurasi pajak karyawan berhasil diperbarui',
            'data' => $employee->only(['id', 'name', 'employee_code', 'nik', 'npwp', 'has_npwp', 'ptkp'])
        ]);
    }
}
