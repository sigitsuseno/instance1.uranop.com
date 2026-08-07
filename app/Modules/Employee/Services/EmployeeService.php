<?php

namespace App\Modules\Employee\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeSalaryComponent;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmployeeService
{
    /**
     * Get paginated employees with filters.
     */
    public function getPaginated(array $filters = []): LengthAwarePaginator
    {
        $query = Employee::with(['department', 'position', 'latestContract', 'groups']);

        // Default sorting if not provided
        $sortBy = $filters['sort_by'] ?? 'nip';
        $sortDir = strtolower($filters['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (in_array($sortBy, ['name', 'nik', 'nip', 'employee_code'])) {
            $query->orderBy($sortBy, $sortDir);
        } elseif ($sortBy === 'contract_end_date') {
            $endSub = \App\Modules\Employee\Models\EmployeeContract::select('end_date')
                ->whereColumn('employee_id', 'employees.id')
                ->where('is_latest', true)
                ->limit(1);

            $statusSub = \App\Modules\Employee\Models\EmployeeContract::select('status')
                ->whereColumn('employee_id', 'employees.id')
                ->where('is_latest', true)
                ->limit(1);

            if ($sortDir === 'desc') {
                // Default tampilan halaman kontrak:
                // 1) Kontrak berstatus active di atas,
                // 2) lalu diurutkan berdasarkan end_date terlama (paling jauh) di atas.
                $query->orderByRaw("CASE WHEN ({$statusSub->toSql()}) = 'active' THEN 0 ELSE 1 END", $statusSub->getBindings())
                      ->orderByRaw("({$endSub->toSql()}) IS NULL ASC", $endSub->getBindings())
                      ->orderByRaw("({$endSub->toSql()}) DESC", $endSub->getBindings());
            } else {
                $query->orderByRaw("({$endSub->toSql()}) IS NULL ASC", $endSub->getBindings())
                      ->orderByRaw("({$endSub->toSql()}) ASC", $endSub->getBindings());
            }
        } else {
            $query->orderBy('nip', 'asc');
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['position_id'])) {
            $query->where('position_id', $filters['position_id']);
        }

        if (! empty($filters['employment_status'])) {
            $query->where('employment_status', $filters['employment_status']);
        }

        if (! empty($filters['period_start']) && ! empty($filters['period_end'])) {
            // Filter: hanya karyawan yang punya roster/shift di periode tersebut
            $query->whereHas('shiftRosters', function ($q) use ($filters) {
                $q->whereBetween('date', [$filters['period_start'], $filters['period_end']]);
            });
        } elseif (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        } else {
            // Safety net: kalau ga ada filter periode & is_active, default ke karyawan aktif aja
            // Mencegah semua karyawan (termasuk yg belum join / udah resign) muncul tanpa filter
            $query->where('is_active', true);
        }

        if (! empty($filters['contract_type'])) {
            $query->whereHas('latestContract', function ($q) use ($filters) {
                $q->where('contract_type', $filters['contract_type']);
            });
        }

        if (! empty($filters['contract_status'])) {
            $status = $filters['contract_status'];

            if ($status === 'no_contract') {
                // Karyawan yang belum punya kontrak sama sekali
                $query->whereDoesntHave('latestContract');
            } else {
                // Status Aktif / Segera Berakhir / Expired dihitung dari end_date
                // (sama dengan logika badge di halaman kontrak), bukan dari kolom status DB
                // yang sering tidak sinkron dengan tanggal berakhirnya kontrak.
                $today       = now()->startOfDay();
                $activeStart = now()->addDays(15)->startOfDay();

                $query->whereHas('latestContract', function ($q) use ($status, $today, $activeStart) {
                    match ($status) {
                        'active' => $q->where(function ($sub) use ($activeStart) {
                            $sub->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $activeStart);
                        }),
                        'expiring_soon' => $q->whereNotNull('end_date')
                            ->whereDate('end_date', '>=', $today)
                            ->whereDate('end_date', '<', $activeStart),
                        'expired' => $q->whereNotNull('end_date')
                            ->whereDate('end_date', '<', $today),
                        'terminated' => $q->whereIn('status', ['terminated', 'resign', 'phk', 'mangkir']),
                        default => $q->where('status', $status),
                    };
                });
            }
        }

        if (! empty($filters['exclude_expired_contracts'])) {
            // Sembunyikan karyawan yang kontrak terakhirnya sudah expired (end_date sudah lewat).
            // View default halaman kontrak hanya menampilkan kontrak yang masih berlaku
            // (aktif + segera berakhir). Kontrak yang sudah expired dilihat via filter status "Expired".
            $query->whereDoesntHave('latestContract', function ($q) {
                $q->where('end_date', '<', now()->toDateString());
            });
            // Sembunyikan juga karyawan yang sudah keluar (punya end_date atau resign_date)
            $query->whereNull('end_date')->whereNull('resign_date');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Create new employee with initial position history.
     */
    public function create(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();

            if (empty($data['employee_code'])) {
                $data['employee_code'] = $this->generateEmployeeCode();
            }

            $data['created_by'] = $user->id;

            // Handle photo upload
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $data['photo'] = $data['photo']->store('employees/photos', 'public');
            }

            $employee = Employee::create($data);

            if (isset($data['employee_group_codes']) && is_array($data['employee_group_codes'])) {
                $groupsData = array_map(fn($code) => ['reference_code' => $code], $data['employee_group_codes']);
                $employee->groups()->createMany($groupsData);
            }

            // Create initial position history
            if ($employee->department_id || $employee->position_id) {
                $employee->positionHistories()->create([
                    'old_department_id' => null,
                    'old_position_id'   => null,
                    'new_department_id' => $employee->department_id,
                    'new_position_id'   => $employee->position_id,
                    'effective_date'    => $employee->join_date,
                    'change_reason'     => 'initial',
                    'created_by'        => $user->id,
                ]);
            }

            return $employee;
        });
    }

    /**
     * Update employee, auto-create position history if jabatan berubah.
     */
    public function update(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $user = Auth::user();

            $positionChanged   = isset($data['position_id'])   && $data['position_id']   != $employee->position_id;
            $departmentChanged = isset($data['department_id']) && $data['department_id'] != $employee->department_id;

            $data['updated_by'] = $user->id;

            // Handle photo upload
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                if ($employee->photo) {
                    Storage::disk('public')->delete($employee->photo);
                }
                $data['photo'] = $data['photo']->store('employees/photos', 'public');
            }

            $oldDeptId     = $employee->department_id;
            $oldPositionId = $employee->position_id;

            $employee->update($data);

            if (isset($data['employee_group_codes']) && is_array($data['employee_group_codes'])) {
                $employee->groups()->delete();
                $groupsData = array_map(fn($code) => ['reference_code' => $code], $data['employee_group_codes']);
                $employee->groups()->createMany($groupsData);
            }

            // Create position history if position or department changed
            if ($positionChanged || $departmentChanged) {
                $employee->positionHistories()->create([
                    'old_department_id' => $oldDeptId,
                    'old_position_id'   => $oldPositionId,
                    'new_department_id' => $employee->department_id,
                    'new_position_id'   => $employee->position_id,
                    'effective_date'    => $data['position_change_date'] ?? now()->toDateString(),
                    'change_reason'     => $data['change_reason'] ?? 'transfer',
                    'created_by'        => $user->id,
                ]);
            }

            return $employee->fresh();
        });
    }

    /**
     * Soft delete employee.
     */
    public function delete(Employee $employee): bool
    {
        return DB::transaction(function () use ($employee) {
            if ($employee->contracts()->where('status', 'active')->exists()) {
                throw new \Exception('Karyawan masih memiliki kontrak aktif.');
            }

            return $employee->delete();
        });
    }

    /**
     * Toggle employee active status.
     */
    public function toggleStatus(Employee $employee): Employee
    {
        $employee->is_active  = ! $employee->is_active;
        $employee->updated_by = Auth::id();
        $employee->save();

        return $employee->fresh();
    }

    /**
     * Deactivate employee.
     */
    public function deactivate(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $reason = $data['reason'];
            $date   = $data['date'];

            // 1. Update status kontrak menjadi reason (resign/phk/mangkir)
            $employee->contracts()->update(['status' => $reason]);

            // 2. Tentukan employment_status
            $employmentStatus = match ($reason) {
                'resign'  => 'resigned',
                'phk', 'mangkir' => 'terminated',
                default   => 'resigned',
            };

            // 3. Update employee
            $updateData = [
                'is_active'         => false,
                'end_date'          => $date,
                'employment_status' => $employmentStatus,
                'updated_by'        => Auth::id(),
            ];

            if ($reason === 'resign') {
                $updateData['resign_date'] = $date;
            }

            $employee->update($updateData);

            return $employee->fresh();
        });
    }

    /**
     * Sync denormalized salary columns (base_salary, premi, tunjangan) di tabel employees
     * dari employee_salary_components (komponen aktif terbaru).
     * Dipanggil setiap kali salary component berubah.
     */
    public function syncDenormalized(Employee $employee): void
    {
        $comp = $employee->salaryComponents()
            ->where('is_active', true)
            ->latest('effective_date')
            ->first();

        if ($comp) {
            $employee->update([
                'base_salary' => $comp->gaji_pokok,
                'premi'       => $comp->premi,
                'tunjangan'   => $comp->tunjangan,
            ]);
        }
    }

    /**
     * Get statistics.
     */
    public function getStats(): array
    {
        $total = Employee::count();

        return [
            'total'     => $total,
            'active'    => Employee::active()->count(),
            'permanent' => Employee::where('employment_status', 'permanent')->count(),
            'contract'  => Employee::where('employment_status', 'contract')->count(),
            'probation' => Employee::where('employment_status', 'probation')->count(),
            'male'      => Employee::where('gender', 'L')->count(),
            'female'    => Employee::where('gender', 'P')->count(),
        ];
    }

    /**
     * Get employees as dropdown options.
     */
    public function getOptions(bool $activeOnly = true): array
    {
        $query = Employee::with(['department:id,name', 'position:id,name'])->orderBy('name');

        if ($activeOnly) {
            $query->active();
        }

        return $query->get(['id', 'name', 'nip', 'employee_code', 'join_date', 'department_id', 'position_id'])
            ->map(fn ($e) => [
                'id'              => $e->id,
                'name'            => $e->name,
                'nip'             => $e->nip,
                'code'            => $e->employee_code,
                'label'           => "{$e->employee_code} - {$e->name}",
                'join_date'       => $e->join_date,
                'department_name' => $e->department?->name,
                'position_name'   => $e->position?->name,
            ])
            ->toArray();
    }

    /**
     * Generate unique employee code: EMP{YY}{0001}.
     */
    protected function generateEmployeeCode(): string
    {
        $prefix = 'EMP';
        $year   = date('y');

        $last = Employee::whereYear('created_at', date('Y'))
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $last
            ? str_pad((int) substr($last->employee_code, -4) + 1, 4, '0', STR_PAD_LEFT)
            : '0001';

        $code = $prefix.$year.$nextNumber;

        // Ensure unique
        while (Employee::where('employee_code', $code)->exists()) {
            $nextNumber = str_pad((int) $nextNumber + 1, 4, '0', STR_PAD_LEFT);
            $code       = $prefix.$year.$nextNumber;
        }

        return $code;
    }
}
