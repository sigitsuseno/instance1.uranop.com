<?php

namespace App\Modules\Employee\Controllers\Api\V1\Bpjs;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeBpjs;
use App\Modules\Employee\Services\BpjsCalculator;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Settings\Models\BpjsConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Standalone BPJS endpoints: list keanggotaan, generate iuran, reports.
 * Bukan nested di bawah /employees/{employee}.
 */
class BpjsEmployeeController extends Controller
{
    /**
     * List karyawan dengan data BPJS (submenu Keanggotaan).
     * Tabel: Nama | No BPJS | ☐TK | ☐KES | ☐PEN | Aksi
     */
    public function index(Request $request): JsonResponse
    {
        $payPeriodId = $request->integer('pay_period_id');

        $query = Employee::query()
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                       ->orWhere('employee_code', 'like', "%{$search}%")
                       ->orWhere('nip', 'like', "%{$search}%");
                });
            });

        // Filter karyawan berdasarkan shift roster di periode terpilih
        if ($payPeriodId) {
            $payPeriod = PayPeriod::find($payPeriodId);
            if ($payPeriod && $payPeriod->start_date && $payPeriod->end_date) {
                $rosteredIds = EmployeeShiftRoster::whereBetween('date', [$payPeriod->start_date, $payPeriod->end_date])
                    ->distinct()
                    ->pluck('employee_id');
                $query->whereIn('id', $rosteredIds);
            }
        }

        $query->orderBy('name');

        $employees = $query->paginate($request->integer('per_page', 25));

        // Ambil semua BPJS records untuk periode ini (atau latest kalau nggak ada periode)
        $bpjsQuery = EmployeeBpjs::whereIn('employee_id', $employees->pluck('id'));
        if ($payPeriodId) {
            $bpjsQuery->where('pay_period_id', $payPeriodId);
        }
        $bpjsByEmployee = $bpjsQuery->get()->keyBy('employee_id');

        $employees->through(function ($emp) use ($bpjsByEmployee) {
            $bpjs = $bpjsByEmployee->get($emp->id);
            return [
                'id'             => $emp->id,
                'name'           => $emp->name,
                'employee_code'  => $emp->employee_code,
                'nip'            => $emp->nip,
                'department'     => $emp->department?->name,
                'position'       => $emp->position?->name,
                'bpjs_ketenagakerjaan_no' => $emp->bpjs_ketenagakerjaan,
                'bpjs_kesehatan_no'       => $emp->bpjs_kesehatan,
                'has_bpjs_tk'    => $bpjs ? (bool) $bpjs->has_bpjs_tk : true,
                'has_bpjs_ks'    => $bpjs ? (bool) $bpjs->has_bpjs_ks : true,
                'has_bpjs_pen'   => $bpjs ? (bool) $bpjs->has_bpjs_pen : true,
                'bpjs_id'        => $bpjs->id ?? null,
            ];
        });

        return response()->json($employees);
    }

    /**
     * Show single employee BPJS data (for form edit).
     */
    public function show(Employee $employee): JsonResponse
    {
        $bpjs = $employee->bpjs;

        if (! $bpjs) {
            return response()->json(['data' => null, 'message' => 'Belum ada data BPJS.'], 404);
        }

        return response()->json(['data' => $bpjs]);
    }

    /**
     * Store/create BPJS data for an employee.
     */
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $this->validatedInput($request);
        $data['employee_id'] = $employee->id;
        $data['created_by']  = auth()->id();

        // Jika ada pay_period_id, pakai; kalau nggak, null (master record)
        $data['pay_period_id'] = $request->integer('pay_period_id') ?: null;

        $bpjs = EmployeeBpjs::create($data);

        return response()->json([
            'message' => 'Data BPJS berhasil ditambahkan.',
            'data'    => $bpjs,
        ], 201);
    }

    /**
     * Update BPJS data for an employee.
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $bpjs = $employee->bpjs;

        if (! $bpjs) {
            return response()->json(['message' => 'Data BPJS belum ada. Tambahkan dulu.'], 404);
        }

        $data = $this->validatedInput($request);
        $data['updated_by'] = auth()->id();
        $bpjs->update($data);

        return response()->json([
            'message' => 'Data BPJS berhasil diperbarui.',
            'data'    => $bpjs->fresh(),
        ]);
    }

    /**
     * Delete BPJS data.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        $bpjs = $employee->bpjs;

        if (! $bpjs) {
            return response()->json(['message' => 'Data BPJS tidak ditemukan.'], 404);
        }

        $bpjs->delete();

        return response()->json(['message' => 'Data BPJS berhasil dihapus.']);
    }

    /**
     * Generate Iuran BPJS — hanya karyawan yang ada di shift roster periode ini.
     * Ambil bpjs_config aktif, kalkulasi, simpan ke employee_bpjs & pay_records.
     * Kalau periode is_split → update pay_records segment B.
     */
    public function generateIuran(Request $request): JsonResponse
    {
        $request->validate([
            'pay_period_id' => 'required|exists:pay_periods,id',
            'employee_ids'  => 'nullable|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        $config = BpjsConfig::active()
            ->where('effective_date', '<=', now())
            ->orderBy('effective_date', 'desc')
            ->first();

        if (! $config) {
            return response()->json(['message' => 'Belum ada konfigurasi BPJS yang aktif.'], 400);
        }

        $calculator = (new BpjsCalculator)->setConfig($config);

        // Ambil karyawan murni dari shift roster di periode terpilih
        $payPeriod = PayPeriod::find($request->pay_period_id);
        if ($payPeriod && $payPeriod->start_date && $payPeriod->end_date) {
            $rosteredIds = EmployeeShiftRoster::whereBetween('date', [$payPeriod->start_date, $payPeriod->end_date])
                ->distinct()
                ->pluck('employee_id');
            $employees = Employee::whereIn('id', $rosteredIds)->get();
        } else {
            return response()->json(['message' => 'Periode tidak valid.'], 400);
        }

        $successCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($employees as $employee) {
                // Ambil atau buat BPJS record untuk periode ini
                $bpjsData = EmployeeBpjs::firstOrCreate(
                    [
                        'employee_id'    => $employee->id,
                        'pay_period_id'  => $request->pay_period_id,
                    ],
                    [
                        'has_bpjs_tk'  => true,
                        'has_bpjs_ks'  => true,
                        'has_bpjs_pen' => true,
                        'created_by'   => auth()->id(),
                    ]
                );

                if (! $bpjsData->has_bpjs_tk && ! $bpjsData->has_bpjs_ks && ! $bpjsData->has_bpjs_pen) {
                    continue; // Nggak ikut BPJS sama sekali
                }

                // Dasar perhitungan = gaji_pokok + tj_masa_kerja + tunjangan
                $gajiPokok  = (float) ($employee->activeSalary()?->base_salary ?? $employee->base_salary ?? 0);
                $tjMk       = (float) ($bpjsData->tj_masa_kerja ?? 0);
                $tunjangan  = (float) ($bpjsData->tunjangan ?? 0);
                $baseSalary = $gajiPokok + $tjMk + $tunjangan;

                if ($baseSalary <= 0) {
                    $errors[] = "{$employee->name}: gaji dasar 0";
                    continue;
                }

                $result = $calculator->calculate(
                    $baseSalary,
                    (bool) $bpjsData->has_bpjs_tk,
                    (bool) $bpjsData->has_bpjs_ks,
                    (bool) $bpjsData->has_bpjs_pen,
                    (int) $bpjsData->kesehatan_dependents
                );

                $bpjsData->update([
                    'pay_period_id'        => $request->pay_period_id,
                    'bpjs_base_salary'     => $baseSalary,
                    'employer_jht'         => $result['employer']['jht'],
                    'employer_jkk'         => $result['employer']['jkk'],
                    'employer_jkm'         => $result['employer']['jkm'],
                    'employer_kesehatan'   => $result['employer']['kesehatan'],
                    'employer_jp'          => $result['employer']['jp'],
                    'employee_jht'         => $result['employee']['jht'],
                    'employee_kesehatan'   => $result['employee']['kesehatan'],
                    'employee_jp'          => $result['employee']['jp'],
                    'last_generated_at'    => now(),
                    'updated_by'           => auth()->id(),
                ]);

                // Update pay_records — hanya kalau record sudah ada (dari recap attendance)
                $segment = $payPeriod->is_split ? 'B' : null;
                PayRecord::where('employee_id', $employee->id)
                    ->where('pay_period_id', $request->pay_period_id)
                    ->where('segment', $segment)
                    ->update([
                        'bpjs_tk'   => $result['employee']['jht'],
                        'bpjs_ks'   => $result['employee']['kesehatan'],
                        'bpjs_pen'  => $result['employee']['jp'],
                        'updated_by' => auth()->id(),
                    ]);

                $successCount++;
            }

            DB::commit();

            return response()->json([
                'message' => "Berhasil generate iuran BPJS untuk {$successCount} karyawan.",
                'total'   => $successCount,
                'errors'  => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal generate iuran: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List iuran BPJS (submenu Iuran BPJS).
     * Tabel: Nama | Gaji Pokok | TJ MK | Tunjangan | Dasar BPJS | 5 EM | 3 EE
     */
    public function iuranIndex(Request $request): JsonResponse
    {
        $query = EmployeeBpjs::with('employee')
            ->where(function ($q) {
                $q->where('employer_jht', '>', 0)
                  ->orWhere('employer_jkk', '>', 0)
                  ->orWhere('employer_jkm', '>', 0)
                  ->orWhere('employer_kesehatan', '>', 0)
                  ->orWhere('employer_jp', '>', 0)
                  ->orWhere('employee_jht', '>', 0)
                  ->orWhere('employee_kesehatan', '>', 0)
                  ->orWhere('employee_jp', '>', 0);
            });

        if ($request->pay_period_id) {
            $query->where('pay_period_id', $request->pay_period_id);
        }

        if ($request->search) {
            $search = $request->search;
            $query->whereHas('employee', fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%")
            );
        }

        $records = $query->orderBy(
            EmployeeBpjs::select('name')
                ->from('employees')
                ->whereColumn('employees.id', 'employee_bpjs.employee_id')
                ->limit(1)
        )->paginate($request->integer('per_page', 25));

        return response()->json($records);
    }

    /**
     * Reports — summary total iuran per periode.
     */
    public function reports(Request $request): JsonResponse
    {
        $request->validate(['pay_period_id' => 'required|exists:pay_periods,id']);

        $records = EmployeeBpjs::with('employee:id,name,employee_code')
            ->where('pay_period_id', $request->pay_period_id)
            ->where(function ($q) {
                $q->where('has_bpjs_tk', true)
                  ->orWhere('has_bpjs_ks', true)
                  ->orWhere('has_bpjs_pen', true);
            })
            ->get();

        $totalEmployer = $records->sum(fn ($r) => $r->total_employer);
        $totalEmployee = $records->sum(fn ($r) => $r->total_employee);

        return response()->json([
            'data' => [
                'total_karyawan' => $records->count(),
                'total_employer' => $totalEmployer,
                'total_employee' => $totalEmployee,
                'total_all'      => $totalEmployer + $totalEmployee,
                'details'        => $records,
            ],
        ]);
    }

    /** --- helpers --- */

    private function validatedInput(Request $request): array
    {
        return $request->validate([
            'bpjs_ketenagakerjaan_no'   => 'nullable|string|max:50',
            'bpjs_kesehatan_no'         => 'nullable|string|max:50',
            'has_bpjs_tk'               => 'boolean',
            'has_bpjs_ks'               => 'boolean',
            'has_bpjs_pen'              => 'boolean',
            'bpjs_base_type'            => 'nullable|in:gaji_pokok,umk,custom',
            'tj_masa_kerja'             => 'nullable|numeric|min:0',
            'tunjangan'                 => 'nullable|numeric|min:0',
            'status_ketenagakerjaan'    => 'nullable|in:active,inactive,suspended',
            'status_kesehatan'          => 'nullable|in:active,inactive,suspended',
            'date_joined_ketenagakerjaan' => 'nullable|date',
            'date_joined_kesehatan'     => 'nullable|date',
            'date_left_ketenagakerjaan' => 'nullable|date',
            'date_left_kesehatan'       => 'nullable|date',
            'bpjs_kesehatan_class'      => 'nullable|in:Kelas I,Kelas II,Kelas III',
            'kesehatan_dependents'      => 'nullable|integer|min:0|max:5',
            'faskes_tingkat_1'          => 'nullable|string|max:100',
            'faskes_tingkat_1_code'     => 'nullable|string|max:20',
            'notes'                     => 'nullable|string',
        ]);
    }
}
