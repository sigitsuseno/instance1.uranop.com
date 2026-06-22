<?php

namespace App\Modules\Supervisor\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeBpjs;
use App\Modules\Employee\Services\BpjsCalculator;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Organization\Models\BpjsConfig;
use App\Modules\Employee\Exports\BpjsIuranExport;
use App\Modules\Employee\Exports\BpjsReportExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Standalone BPJS endpoints: list keanggotaan, generate iuran, reports.
 * Bukan nested di bawah /employees/{employee}.
 */
class SupervisorBpjsMembershipController extends Controller
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
    public function show(Request $request, Employee $employee): JsonResponse
    {
        // Cari spesifik by bpjs_id kalau dikirim, atau by pay_period_id
        $bpjs = null;
        if ($bpjsId = $request->integer('bpjs_id')) {
            $bpjs = EmployeeBpjs::where('employee_id', $employee->id)->where('id', $bpjsId)->first();
        } elseif ($payPeriodId = $request->integer('pay_period_id')) {
            $bpjs = EmployeeBpjs::where('employee_id', $employee->id)->where('pay_period_id', $payPeriodId)->first();
        }
        $bpjs ??= $employee->bpjs;

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
     * Lookup by bpjs_id from request (per-period), fallback ke latest.
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $bpjs = null;
        if ($bpjsId = $request->integer('bpjs_id')) {
            $bpjs = EmployeeBpjs::where('employee_id', $employee->id)->where('id', $bpjsId)->first();
        } elseif ($payPeriodId = $request->integer('pay_period_id')) {
            $bpjs = EmployeeBpjs::where('employee_id', $employee->id)->where('pay_period_id', $payPeriodId)->first();
        }
        $bpjs ??= $employee->bpjs;

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
     * Delete BPJS data. Lookup by bpjs_id (query param), fallback ke latest.
     */
    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $bpjs = null;
        if ($bpjsId = $request->integer('bpjs_id')) {
            $bpjs = EmployeeBpjs::where('employee_id', $employee->id)->where('id', $bpjsId)->first();
        } elseif ($payPeriodId = $request->integer('pay_period_id')) {
            $bpjs = EmployeeBpjs::where('employee_id', $employee->id)->where('pay_period_id', $payPeriodId)->first();
        }
        $bpjs ??= $employee->bpjs;

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
                // AMBIL DARI EMPLOYEE MODEL (dinamis by join_date & employee_salaries)
                $periodStr = $payPeriod->end_date->format('Y-m');
                $gajiPokok  = (float) $employee->gaji_pokok($periodStr);
                $tjMk       = (float) $employee->tjMasaKerja($periodStr);
                $tunjangan  = (float) $employee->tunjangan($periodStr);
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
                    'tj_masa_kerja'         => $tjMk,
                    'tunjangan'             => $tunjangan,
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

        // Enrich dengan nilai dinamis dari Employee model
        $payPeriod = $request->pay_period_id ? PayPeriod::find($request->pay_period_id) : null;
        $periodStr = $payPeriod?->end_date?->format('Y-m');

        if ($periodStr) {
            $records->getCollection()->transform(function ($bpjs) use ($periodStr) {
                $emp = $bpjs->employee;
                if ($emp) {
                    $gajiPokok = (float) $emp->gaji_pokok($periodStr);
                    // Selalu ambil dari Employee model (dinamis) — source of truth
                    $tjMk      = (float) $emp->tjMasaKerja($periodStr);
                    $tunjangan = (float) $emp->tunjangan($periodStr);

                    $bpjs->gaji_pokok       = $gajiPokok;
                    $bpjs->tj_masa_kerja    = $tjMk;
                    $bpjs->tunjangan        = $tunjangan;
                    // Recalculate base salary (gaji pokok + tj mk + tunjangan)
                    $bpjs->bpjs_base_salary = $gajiPokok + $tjMk + $tunjangan;
                }
                return $bpjs;
            });
        }

        return response()->json($records);
    }

    /**
     * Export iuran BPJS ke Excel.
     */
    public function exportIuran(Request $request)
    {
        $request->validate([
            'pay_period_id' => 'required|exists:pay_periods,id',
        ]);

        $payPeriod = PayPeriod::findOrFail($request->pay_period_id);
        $periodStr = $payPeriod->end_date->format('Y-m');

        // Query sama persis seperti iuranIndex — tanpa pagination
        $records = EmployeeBpjs::with('employee')
            ->where(function ($q) {
                $q->where('employer_jht', '>', 0)
                  ->orWhere('employer_jkk', '>', 0)
                  ->orWhere('employer_jkm', '>', 0)
                  ->orWhere('employer_kesehatan', '>', 0)
                  ->orWhere('employer_jp', '>', 0)
                  ->orWhere('employee_jht', '>', 0)
                  ->orWhere('employee_kesehatan', '>', 0)
                  ->orWhere('employee_jp', '>', 0);
            })
            ->where('pay_period_id', $payPeriod->id)
            ->orderBy(
                EmployeeBpjs::select('name')
                    ->from('employees')
                    ->whereColumn('employees.id', 'employee_bpjs.employee_id')
                    ->limit(1)
            )
            ->get();

        // Enrich dengan nilai dinamis dari Employee model
        $rows = $records->map(function ($bpjs) use ($periodStr) {
            $emp = $bpjs->employee;
            if (! $emp) return null;

            $gajiPokok = (float) $emp->gaji_pokok($periodStr);
            $tjMk      = (float) $emp->tjMasaKerja($periodStr);
            $tunjangan  = (float) $emp->tunjangan($periodStr);

            return [
                'employee' => [
                    'name'          => $emp->name,
                    'employee_code' => $emp->employee_code,
                ],
                'gaji_pokok'        => $gajiPokok,
                'tj_masa_kerja'     => $tjMk,
                'tunjangan'         => $tunjangan,
                'bpjs_base_salary'  => $gajiPokok + $tjMk + $tunjangan,
                'employer_jht'      => (float) $bpjs->employer_jht,
                'employer_jkk'      => (float) $bpjs->employer_jkk,
                'employer_jkm'      => (float) $bpjs->employer_jkm,
                'employer_kesehatan'=> (float) $bpjs->employer_kesehatan,
                'employer_jp'       => (float) $bpjs->employer_jp,
                'employee_jht'      => (float) $bpjs->employee_jht,
                'employee_kesehatan'=> (float) $bpjs->employee_kesehatan,
                'employee_jp'       => (float) $bpjs->employee_jp,
            ];
        })->filter()->values();

        $fileName = 'Iuran_BPJS_' . str_replace(' ', '_', $payPeriod->name) . '.xlsx';

        return Excel::download(new BpjsIuranExport($rows, $payPeriod->name), $fileName);
    }

    /**
     * Reports — laporan BPJS per periode.
     *
     * Data source: karyawan dari employee_shift_roster untuk periode terpilih,
     * difilter berdasarkan group_label = 'BPJS GROUP' (codes: BPJS-PROD, BPJS-2, BPJS-1).
     * Join ke employee_bpjs untuk data iuran.
     * Kalkulasi dinamis: gaji_pokok(period), tjMasaKerja(period), tunjangan(period).
     */
    public function reports(Request $request): JsonResponse
    {
        $request->validate([
            'pay_period_id' => 'required|exists:pay_periods,id',
            'groups'        => 'nullable|string', // comma-separated BPJS group codes
        ]);

        $payPeriod = PayPeriod::findOrFail($request->pay_period_id);

        // 1. Ambil karyawan dari employee_shift_roster periode ini
        if (! $payPeriod->start_date || ! $payPeriod->end_date) {
            return response()->json(['message' => 'Periode tidak memiliki tanggal mulai/selesai.'], 400);
        }

        $rosteredIds = EmployeeShiftRoster::whereBetween('date', [
            $payPeriod->start_date, $payPeriod->end_date,
        ])->distinct()->pluck('employee_id');

        if ($rosteredIds->isEmpty()) {
            return response()->json([
                'data' => [
                    'period_name'     => $payPeriod->name,
                    'total_karyawan'  => 0,
                    'total_employer'  => 0,
                    'total_employee'  => 0,
                    'total_all'       => 0,
                    'details'         => [],
                ],
            ]);
        }

        // 2. Filter by BPJS GROUP
        $groupCodes = $request->groups
            ? array_filter(array_map('trim', explode(',', $request->groups)))
            : [];

        $employees = Employee::whereIn('id', $rosteredIds)
            ->whereHas('groups', function ($q) {
                // Hanya karyawan dengan group_label = 'BPJS GROUP'
                $q->whereHas('master', function ($mq) {
                    $mq->where('group_label', 'BPJS GROUP');
                });
            })
            ->when(! empty($groupCodes), function ($q) use ($groupCodes) {
                $q->whereHas('groups', function ($gq) use ($groupCodes) {
                    $gq->whereIn('reference_code', $groupCodes);
                });
            })
            ->orderBy('name')
            ->get();

        // 3. Load BPJS data untuk periode ini
        $bpjsByEmployee = EmployeeBpjs::whereIn('employee_id', $employees->pluck('id'))
            ->where('pay_period_id', $payPeriod->id)
            ->get()
            ->keyBy('employee_id');

        // 4. Build detail response
        $periodStr = $payPeriod->end_date->format('Y-m');

        $details = $employees->map(function ($emp) use ($bpjsByEmployee, $periodStr, $payPeriod) {
            $bpjs = $bpjsByEmployee->get($emp->id);

            // Salary dinamis dari Employee model
            $gajiPokok = (float) $emp->gaji_pokok($periodStr);
            $tjMk      = (float) $emp->tjMasaKerja($periodStr);
            $tunjangan = (float) $emp->tunjangan($periodStr);
            $dasar     = $gajiPokok + $tjMk + $tunjangan;

            // Masa kerja (bulan)
            $masaKerja = $emp->join_date
                ? $emp->join_date->diffInMonths($payPeriod->end_date)
                : 0;

            // Group BPJS name
            $bpjsGroup = $emp->groups()
                ->whereHas('master', fn ($q) => $q->where('group_label', 'BPJS GROUP'))
                ->with('master')
                ->first();

            // Iuran (0 jika belum di-generate)
            $empJHT = (float) ($bpjs->employer_jht ?? 0);
            $empJKM = (float) ($bpjs->employer_jkm ?? 0);
            $empJKK = (float) ($bpjs->employer_jkk ?? 0);
            $empTK  = round($empJHT + $empJKM + $empJKK, 2);
            $empJP  = (float) ($bpjs->employer_jp ?? 0);
            $empKS  = (float) ($bpjs->employer_kesehatan ?? 0);

            $eeJHT = (float) ($bpjs->employee_jht ?? 0);
            $eeJP  = (float) ($bpjs->employee_jp ?? 0);
            $eeKS  = (float) ($bpjs->employee_kesehatan ?? 0);
            $eeTotal = round($eeJHT + $eeJP + $eeKS, 2);

            $grandTotal = round($empTK + $empJP + $empKS + $eeTotal, 2);

            return [
                'id'                   => $emp->id,
                'employee'             => [
                    'name'          => $emp->name,
                    'nip'           => $emp->nip,
                    'employee_code' => $emp->employee_code,
                ],
                'group'                => $bpjsGroup?->master?->name ?? '-',
                'group_code'           => $bpjsGroup?->reference_code ?? null,
                'join_year'            => $emp->join_date?->format('Y'),
                'masa_kerja'           => $masaKerja,
                'gaji_pokok'           => $gajiPokok,
                'tj_masa_kerja'        => $tjMk,
                'tunjangan'            => $tunjangan,
                'bpjs_base_salary'     => $dasar,
                'kpj_tk'               => $emp->bpjs_ketenagakerjaan,
                'kpj_ks'               => $emp->bpjs_kesehatan,
                // Employer (Perusahaan)
                'employer_jht'         => $empJHT,
                'employer_jkm'         => $empJKM,
                'employer_jkk'         => $empJKK,
                'employer_tk_total'    => $empTK,
                'employer_jp'          => $empJP,
                'employer_kesehatan'   => $empKS,
                // Employee (Karyawan)
                'employee_jht'         => $eeJHT,
                'employee_jp'          => $eeJP,
                'employee_kesehatan'   => $eeKS,
                'employee_total'       => $eeTotal,
                // Grand
                'grand_total'          => $grandTotal,
            ];
        })->sort(function ($a, $b) {
            $groupCompare = strcmp($a['group_code'] ?? '~', $b['group_code'] ?? '~');
            if ($groupCompare !== 0) return $groupCompare;
            return strcmp($a['employee']['name'], $b['employee']['name']);
        })->values();

        // 5. Summary totals
        $totalEmployer = $details->sum(fn ($d) => $d['employer_tk_total'] + $d['employer_jp'] + $d['employer_kesehatan']);
        $totalEmployee = $details->sum(fn ($d) => $d['employee_total']);

        return response()->json([
            'data' => [
                'period_name'     => $payPeriod->name,
                'total_karyawan'  => $details->count(),
                'total_employer'  => round($totalEmployer, 2),
                'total_employee'  => round($totalEmployee, 2),
                'total_all'       => round($totalEmployer + $totalEmployee, 2),
                'details'         => $details,
            ],
        ]);
    }

    /**
     * Export Laporan BPJS ke Excel — format sesuai sample_laporan_bpjs.xlsx
     * Grouped by BPJS group, satu sheet dengan subtotal per group dan grand total.
     */
    public function exportReports(Request $request)
    {
        $request->validate([
            'pay_period_id' => 'required|exists:pay_periods,id',
            'groups'        => 'nullable|string',
        ]);

        $payPeriod = PayPeriod::findOrFail($request->pay_period_id);

        if (! $payPeriod->start_date || ! $payPeriod->end_date) {
            return response()->json(['message' => 'Periode tidak valid.'], 400);
        }

        $rosteredIds = EmployeeShiftRoster::whereBetween('date', [
            $payPeriod->start_date, $payPeriod->end_date,
        ])->distinct()->pluck('employee_id');

        $groupCodes = $request->groups
            ? array_filter(array_map('trim', explode(',', $request->groups)))
            : [];

        $employees = Employee::whereIn('id', $rosteredIds)
            ->whereHas('groups', function ($q) {
                $q->whereHas('master', function ($mq) {
                    $mq->where('group_label', 'BPJS GROUP');
                });
            })
            ->when(! empty($groupCodes), function ($q) use ($groupCodes) {
                $q->whereHas('groups', function ($gq) use ($groupCodes) {
                    $gq->whereIn('reference_code', $groupCodes);
                });
            })
            ->orderBy('name')
            ->get();

        $bpjsByEmployee = EmployeeBpjs::whereIn('employee_id', $employees->pluck('id'))
            ->where('pay_period_id', $payPeriod->id)
            ->get()
            ->keyBy('employee_id');

        $periodStr = $payPeriod->end_date->format('Y-m');

        $details = $employees->map(function ($emp) use ($bpjsByEmployee, $periodStr, $payPeriod) {
            $bpjs = $bpjsByEmployee->get($emp->id);

            $gajiPokok = (float) $emp->gaji_pokok($periodStr);
            $tjMk      = (float) $emp->tjMasaKerja($periodStr);
            $tunjangan = (float) $emp->tunjangan($periodStr);
            $dasar     = $gajiPokok + $tjMk + $tunjangan;

            $masaKerja = $emp->join_date
                ? $emp->join_date->diffInMonths($payPeriod->end_date)
                : 0;

            $bpjsGroup = $emp->groups()
                ->whereHas('master', fn ($q) => $q->where('group_label', 'BPJS GROUP'))
                ->with('master')
                ->first();

            $empJHT = (float) ($bpjs?->employer_jht ?? 0);
            $empJKM = (float) ($bpjs?->employer_jkm ?? 0);
            $empJKK = (float) ($bpjs?->employer_jkk ?? 0);
            $empTK  = round($empJHT + $empJKM + $empJKK, 2);
            $empJP  = (float) ($bpjs?->employer_jp ?? 0);
            $empKS  = (float) ($bpjs?->employer_kesehatan ?? 0);

            $eeJHT = (float) ($bpjs?->employee_jht ?? 0);
            $eeJP  = (float) ($bpjs?->employee_jp ?? 0);
            $eeKS  = (float) ($bpjs?->employee_kesehatan ?? 0);

            return [
                'id'                => $emp->id,
                'employee'          => ['name' => $emp->name, 'nip' => $emp->nip, 'employee_code' => $emp->employee_code],
                'group'             => $bpjsGroup?->master?->name ?? '-',
                'group_code'        => $bpjsGroup?->reference_code ?? null,
                'join_year'         => $emp->join_date?->format('Y'),
                'masa_kerja'        => $masaKerja,
                'gaji_pokok'        => $gajiPokok,
                'tj_masa_kerja'     => $tjMk,
                'tunjangan'         => $tunjangan,
                'bpjs_base_salary'  => $dasar,
                'kpj_tk'            => $emp->bpjs_ketenagakerjaan,
                'kpj_ks'            => $emp->bpjs_kesehatan,
                'employer_jht'      => $empJHT,
                'employer_jkm'      => $empJKM,
                'employer_jkk'      => $empJKK,
                'employer_tk_total' => $empTK,
                'employer_jp'       => $empJP,
                'employer_kesehatan'=> $empKS,
                'employee_jht'      => $eeJHT,
                'employee_jp'       => $eeJP,
                'employee_kesehatan'=> $eeKS,
                'employee_total'    => round($eeJHT + $eeJP + $eeKS, 2),
                'grand_total'       => round($empTK + $empJP + $empKS + $eeJHT + $eeJP + $eeKS, 2),
            ];
        })->sortBy(fn ($r) => ($r['group_code'] ?? '~') . $r['employee']['name'])->values();

        // Group records by group_code for the export
        $groups = [];
        foreach ($details as $record) {
            $key = $record['group_code'] ?? '_other';
            if (! isset($groups[$key])) {
                $groups[$key] = ['name' => $record['group'] ?? $key, 'records' => []];
            }
            $groups[$key]['records'][] = $record;
        }

        $fileName = 'Laporan_BPJS_' . str_replace(' ', '_', $payPeriod->name) . '.xlsx';

        return Excel::download(
            new BpjsReportExport(array_values($groups), $payPeriod->name),
            $fileName
        );
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
