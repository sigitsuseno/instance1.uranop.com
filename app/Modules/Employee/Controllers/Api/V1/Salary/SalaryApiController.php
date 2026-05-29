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

        return response()->json([
            'message' => 'Riwayat gaji berhasil ditambahkan.',
        ], 201);
    }
}
