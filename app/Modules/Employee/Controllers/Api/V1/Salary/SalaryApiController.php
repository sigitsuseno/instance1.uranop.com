<?php

namespace App\Modules\Employee\Controllers\Api\V1\Salary;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeSalary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalaryApiController extends Controller
{
    /**
     * GET /api/employees/{employee}/salaries
     */
    public function index(Employee $employee): JsonResponse
    {
        $salaries = $employee->salaries()
            ->orderBy('effective_date', 'desc')
            ->get();

        return response()->json(['data' => $salaries]);
    }

    /**
     * POST /api/employees/{employee}/salaries
     * Tambah riwayat gaji baru (nonaktifkan sebelumnya).
     */
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'base_salary'           => 'required|numeric|min:0',
            'premi'                 => 'nullable|numeric|min:0',
            'tunjangan'             => 'nullable|numeric|min:0',
            'allowance_transport'   => 'nullable|numeric|min:0',
            'allowance_meal'        => 'nullable|numeric|min:0',
            'allowance_position'    => 'nullable|numeric|min:0',
            'effective_date'        => 'nullable|date',
            'change_type'           => 'nullable|in:initial,increase,decrease,promotion,adjustment',
            'letter_number'         => 'nullable|string|max:100',
            'reason'                => 'nullable|string',
        ]);

        \DB::transaction(function () use ($data, $employee) {
            // Get current base salary before update
            $current = $employee->salaries()->where('is_active', true)->first();
            $data['previous_basic_salary'] = $current?->base_salary ?? 0;

            // Deactivate old records
            $employee->salaries()->where('is_active', true)->update(['is_active' => false]);

            $data['is_active']  = true;
            $data['created_by'] = Auth::id();

            $employee->salaries()->create($data);
        });

        return response()->json(['message' => 'Berhasil menambahkan data gaji'], 201);
    }

    public function globalIndex(Request $request): JsonResponse
    {
        $query = EmployeeSalary::with('employee.department', 'employee.position', 'createdBy');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('change_type')) {
            $query->where('change_type', $request->change_type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->input('per_page', 15);
        $salaries = $query->orderBy('id', 'desc')->paginate($perPage);

        return \App\Modules\Employee\Resources\EmployeeSalaryResource::collection($salaries)->response();
    }

    public function globalStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id'           => 'required|exists:employees,id',
            'base_salary'           => 'required|numeric|min:0',
            'premi'                 => 'nullable|numeric|min:0',
            'tunjangan'             => 'nullable|numeric|min:0',
            'allowance_transport'   => 'nullable|numeric|min:0',
            'allowance_meal'        => 'nullable|numeric|min:0',
            'allowance_position'    => 'nullable|numeric|min:0',
            'effective_date'        => 'nullable|date',
            'change_type'           => 'nullable|in:initial,increase,decrease,promotion,adjustment',
            'letter_number'         => 'nullable|string|max:100',
            'reason'                => 'nullable|string',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);

        \DB::transaction(function () use ($data, $employee) {
            $current = $employee->salaries()->where('is_active', true)->first();
            $data['previous_basic_salary'] = $current?->base_salary ?? 0;
            $employee->salaries()->where('is_active', true)->update(['is_active' => false]);

            $data['is_active']  = true;
            $data['created_by'] = Auth::id();

            $employee->salaries()->create($data);
        });

        return response()->json([
            'message' => 'Gaji karyawan berhasil ditambahkan.',
        ], 201);
    }

    public function globalShow(EmployeeSalary $salary): JsonResponse
    {
        $salary->load('employee.department', 'employee.position', 'createdBy');
        return response()->json(['data' => new \App\Modules\Employee\Resources\EmployeeSalaryResource($salary)]);
    }

    public function globalUpdate(Request $request, EmployeeSalary $salary): JsonResponse
    {
        $data = $request->validate([
            'base_salary'           => 'required|numeric|min:0',
            'premi'                 => 'nullable|numeric|min:0',
            'tunjangan'             => 'nullable|numeric|min:0',
            'allowance_transport'   => 'nullable|numeric|min:0',
            'allowance_meal'        => 'nullable|numeric|min:0',
            'allowance_position'    => 'nullable|numeric|min:0',
            'effective_date'        => 'nullable|date',
            'change_type'           => 'nullable|in:initial,increase,decrease,promotion,adjustment',
            'letter_number'         => 'nullable|string|max:100',
            'reason'                => 'nullable|string',
        ]);

        $salary->update($data);

        return response()->json([
            'message' => 'Data gaji berhasil diperbarui.',
        ]);
    }

    public function globalDestroy(EmployeeSalary $salary): JsonResponse
    {
        $salary->delete();

        return response()->json([
            'message' => 'Data gaji berhasil dihapus.',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120'
        ]);

        $import = new \App\Modules\Employee\Imports\EmployeeSalaryImport();
        \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));

        $result = $import->getResult();

        if (count($result['failed']) > 0) {
            return response()->json([
                'message' => 'Import selesai dengan beberapa error.',
                'stats' => $result['stats'],
                'errors' => $result['failed']
            ], 422);
        }

        return response()->json([
            'message' => 'Import gaji berhasil.',
            'stats' => $result['stats']
        ]);
    }
}
