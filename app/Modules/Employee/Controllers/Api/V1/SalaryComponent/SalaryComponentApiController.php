<?php

namespace App\Modules\Employee\Controllers\Api\V1\SalaryComponent;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeSalaryComponent;
use App\Modules\Employee\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalaryComponentApiController extends Controller
{
    public function __construct(
        protected EmployeeService $employeeService
    ) {}

    /**
     * GET /api/employees/{employee}/salary-components
     */
    public function index(Employee $employee): JsonResponse
    {
        $components = $employee->salaryComponents()
            ->orderBy('effective_date', 'desc')
            ->get();

        return response()->json(['data' => $components]);
    }

    /**
     * POST /api/employees/{employee}/salary-components
     * Tambah komponen gaji baru. Set komponen lama jadi non-aktif.
     */
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'gaji_pokok'            => 'required|numeric|min:0',
            'premi'                 => 'nullable|numeric|min:0',
            'tunjangan_masa_kerja'  => 'nullable|numeric|min:0',
            'hari_kerja'            => 'nullable|integer|min:0|max:31',
            'lembur_minggu_holiday' => 'nullable|numeric|min:0',
            'lembur'                => 'nullable|numeric|min:0',
            'tunjangan'             => 'nullable|numeric|min:0',
            'tunjangan_lain'        => 'nullable|numeric|min:0',
            'bpjs_tk'               => 'nullable|numeric|min:0',
            'bpjs_ks'               => 'nullable|numeric|min:0',
            'bpjs_pensiun'          => 'nullable|numeric|min:0',
            'pph'                   => 'nullable|numeric|min:0',
            'kompensasi_pph'        => 'nullable|numeric|min:0',
            'kasbon'                => 'nullable|numeric|min:0',
            'effective_date'        => 'nullable|date',
            'end_date'              => 'nullable|date',
        ]);

        \DB::transaction(function () use ($data, $employee) {
            // Nonaktifkan komponen sebelumnya
            $employee->salaryComponents()->where('is_active', true)->update(['is_active' => false]);

            $data['is_active']   = true;
            $data['created_by']  = Auth::id();

            $employee->salaryComponents()->create($data);

            // Sync denormalized columns
            $this->employeeService->syncDenormalized($employee);
        });

        return response()->json([
            'message' => 'Komponen gaji berhasil disimpan.',
            'data'    => [
                'gaji_pokok'          => $employee->gaji_pokok(),
                'premi'               => $employee->premi_component(),
                'tunjangan_masa_kerja'=> $employee->tunjangan_masa_kerja(),
                'total_gaji'          => $employee->totalGaji(),
            ],
        ], 201);
    }

    /**
     * PUT /api/employees/{employee}/salary-components/{component}
     */
    public function update(Request $request, Employee $employee, EmployeeSalaryComponent $component): JsonResponse
    {
        abort_if($component->employee_id !== $employee->id, 404);

        $data = $request->validate([
            'gaji_pokok'            => 'required|numeric|min:0',
            'premi'                 => 'nullable|numeric|min:0',
            'tunjangan_masa_kerja'  => 'nullable|numeric|min:0',
            'hari_kerja'            => 'nullable|integer|min:0|max:31',
            'lembur_minggu_holiday' => 'nullable|numeric|min:0',
            'lembur'                => 'nullable|numeric|min:0',
            'tunjangan'             => 'nullable|numeric|min:0',
            'tunjangan_lain'        => 'nullable|numeric|min:0',
            'bpjs_tk'               => 'nullable|numeric|min:0',
            'bpjs_ks'               => 'nullable|numeric|min:0',
            'bpjs_pensiun'          => 'nullable|numeric|min:0',
            'pph'                   => 'nullable|numeric|min:0',
            'kompensasi_pph'        => 'nullable|numeric|min:0',
            'kasbon'                => 'nullable|numeric|min:0',
            'effective_date'        => 'nullable|date',
            'end_date'              => 'nullable|date',
        ]);

        $data['updated_by'] = Auth::id();
        $component->update($data);

        if ($component->is_active) {
            $this->employeeService->syncDenormalized($employee);
        }

        return response()->json([
            'message' => 'Komponen gaji berhasil diperbarui.',
            'data'    => $component->fresh(),
        ]);
    }
}
