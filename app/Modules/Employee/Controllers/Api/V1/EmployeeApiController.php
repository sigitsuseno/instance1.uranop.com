<?php

namespace App\Modules\Employee\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Resources\EmployeeListResource;
use App\Modules\Employee\Resources\EmployeeResource;
use App\Modules\Employee\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EmployeeApiController extends Controller
{
    public function __construct(
        protected EmployeeService $employeeService
    ) {}

    /**
     * GET /api/employees
     * List karyawan dengan pagination dan filter.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search'            => 'nullable|string|max:100',
            'department_id'     => 'nullable|integer|exists:departments,id',
            'position_id'       => 'nullable|integer|exists:positions,id',
            'employment_status' => 'nullable|string',
            'is_active'         => 'nullable|boolean',
            'contract_type'     => 'nullable|string',
            'contract_status'   => 'nullable|string',
            'period_start'      => 'nullable|date',
            'period_end'        => 'nullable|date|after_or_equal:period_start',
            'per_page'          => 'nullable|integer|min:5|max:100',
            'exclude_expired_contracts' => 'nullable|boolean',
        ]);

        $employees = $this->employeeService->getPaginated($filters);

        return EmployeeListResource::collection($employees);
    }

    /**
     * POST /api/employees
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_code'          => 'nullable|string|max:50|unique:employees',
            'nik'                    => 'nullable|string|max:50|unique:employees',
            'name'                   => 'required|string|max:200',
            'photo'                  => 'nullable|image|max:2048',
            'gender'                 => 'required|in:L,P',
            'place_of_birth'         => 'nullable|string|max:100',
            'date_of_birth'          => 'nullable|date',
            'religion'               => 'nullable|string|max:50',
            'blood_type'             => 'nullable|string|max:5',
            'email'                  => 'nullable|email|max:200',
            'phone'                  => 'nullable|string|max:50',
            'address'                => 'nullable|string',
            'postal_code'            => 'nullable|string|max:20',
            'npwp'                   => 'nullable|string|max:50',
            'bpjs_ketenagakerjaan'   => 'nullable|string|max:50',
            'bpjs_kesehatan'         => 'nullable|string|max:50',
            'has_npwp'               => 'boolean',
            'ptkp'                   => 'nullable|string|max:10',
            'employment_status'      => 'required|in:probation,contract,permanent,outsource,freelance',
            'payroll_cycle'          => 'nullable|in:monthly,weekly,daily',
            'join_date'              => 'required|date',
            'end_date'               => 'nullable|date|after:join_date',
            'permanent_date'         => 'nullable|date',
            'bank_name'              => 'nullable|string|max:100',
            'bank_account_number'    => 'nullable|string|max:100',
            'bank_account_name'      => 'nullable|string|max:200',
            'department_id'          => 'nullable|integer|exists:departments,id',
            'position_id'            => 'nullable|integer|exists:positions,id',
            'employee_group_codes'   => 'nullable|array',
            'employee_group_codes.*' => 'string',
            'user_id'                => 'nullable|integer|exists:users,id',
        ]);

        $employee = $this->employeeService->create($data);

        return response()->json([
            'message' => "Karyawan {$employee->name} berhasil ditambahkan.",
            'data'    => new EmployeeResource($employee->load(['department', 'position', 'latestContract', 'groups.master'])),
        ], 201);
    }

    /**
     * GET /api/employees/{id}
     */
    public function show(Employee $employee): JsonResponse
    {
        $employee->load([
            'department',
            'position',
            'groups.master',
            'latestContract',
            'contracts' => fn ($q) => $q->latest('start_date'),
            'positionHistories' => fn ($q) => $q->with(['oldPosition', 'newPosition', 'oldDepartment', 'newDepartment'])->latest('effective_date'),
            'families',
            'documents',
            'bpjs',
        ]);

        return response()->json([
            'data' => new EmployeeResource($employee),
        ]);
    }

    /**
     * PUT /api/employees/{id}
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'nik'                    => "nullable|string|max:50|unique:employees,nik,{$employee->id}",
            'name'                   => 'required|string|max:200',
            'photo'                  => 'nullable|image|max:2048',
            'gender'                 => 'required|in:L,P',
            'place_of_birth'         => 'nullable|string|max:100',
            'date_of_birth'          => 'nullable|date',
            'religion'               => 'nullable|string|max:50',
            'blood_type'             => 'nullable|string|max:5',
            'email'                  => 'nullable|email|max:200',
            'phone'                  => 'nullable|string|max:50',
            'address'                => 'nullable|string',
            'postal_code'            => 'nullable|string|max:20',
            'npwp'                   => 'nullable|string|max:50',
            'bpjs_ketenagakerjaan'   => 'nullable|string|max:50',
            'bpjs_kesehatan'         => 'nullable|string|max:50',
            'has_npwp'               => 'boolean',
            'ptkp'                   => 'nullable|string|max:10',
            'employment_status'      => 'required|in:probation,contract,permanent,outsource,freelance,resigned,terminated',
            'payroll_cycle'          => 'nullable|in:monthly,weekly,daily',
            'join_date'              => 'required|date',
            'end_date'               => 'nullable|date',
            'permanent_date'         => 'nullable|date',
            'resign_date'            => 'nullable|date',
            'bank_name'              => 'nullable|string|max:100',
            'bank_account_number'    => 'nullable|string|max:100',
            'bank_account_name'      => 'nullable|string|max:200',
            'department_id'          => 'nullable|integer|exists:departments,id',
            'position_id'            => 'nullable|integer|exists:positions,id',
            'employee_group_codes'   => 'nullable|array',
            'employee_group_codes.*' => 'string',
            'change_reason'          => 'nullable|string',
            'position_change_date'   => 'nullable|date',
        ]);

        $employee = $this->employeeService->update($employee, $data);

        return response()->json([
            'message' => "Data karyawan {$employee->name} berhasil diperbarui.",
            'data'    => new EmployeeResource($employee->load(['department', 'position', 'latestContract', 'groups.master'])),
        ]);
    }

    /**
     * DELETE /api/employees/{id}
     */
    public function destroy(Employee $employee): JsonResponse
    {
        $this->employeeService->delete($employee);

        return response()->json([
            'message' => "Karyawan {$employee->name} berhasil dihapus.",
        ]);
    }

    /**
     * PATCH /api/employees/{id}/toggle-status
     */
    public function toggleStatus(Employee $employee): JsonResponse
    {
        $employee = $this->employeeService->toggleStatus($employee);

        $status = $employee->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'message' => "Karyawan {$employee->name} berhasil {$status}.",
            'data'    => ['id' => $employee->id, 'is_active' => $employee->is_active],
        ]);
    }

    /**
     * PATCH /api/employees/{id}/deactivate
     */
    public function deactivate(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'date'   => 'required|date',
            'reason' => 'required|string|in:resign,phk,mangkir',
        ]);

        $employee = $this->employeeService->deactivate($employee, $data);

        return response()->json([
            'message' => "Karyawan {$employee->name} berhasil dinonaktifkan dengan status {$data['reason']}.",
            'data'    => ['id' => $employee->id, 'is_active' => $employee->is_active],
        ]);
    }

    /**
     * GET /api/employees/stats
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => $this->employeeService->getStats(),
        ]);
    }

    /**
     * GET /api/employees/options
     * Dropdown list (ringan, tanpa pagination).
     */
    public function options(Request $request): JsonResponse
    {
        $activeOnly = $request->boolean('active_only', true);

        return response()->json([
            'data' => $this->employeeService->getOptions($activeOnly),
        ]);
    }

    /**
     * GET /api/employees/export
     * Export data karyawan ke format Excel.
     */
    public function export(Request $request)
    {
        $filters = $request->only([
            'search',
            'department_id',
            'position_id',
            'employment_status',
            'is_active',
            'period_start',
            'period_end',
        ]);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Employee\Exports\EmployeeExport($filters), 
            'data_karyawan_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
