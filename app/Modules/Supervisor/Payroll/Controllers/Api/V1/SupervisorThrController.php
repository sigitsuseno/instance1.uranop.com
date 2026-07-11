<?php

namespace App\Modules\Supervisor\Payroll\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeThr;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Settings\Models\ThrConfig;
use App\Modules\Supervisor\Models\SupervisorEmployeeGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SupervisorThrController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->query('thr_year', date('Y'));
        $periodId = $request->query('period_id');
        $search = $request->query('search', '');

        // Ambil employee IDs dari supervisor_employee_groups untuk periode yang dipilih
        $groupEmployeeIds = [];
        if ($periodId) {
            $period = PayPeriod::find($periodId);
            if ($period) {
                $groupEmployeeIds = SupervisorEmployeeGroup::where('period_start', $period->start_date)
                    ->where('period_end', $period->end_date)
                    ->pluck('employee_id')
                    ->unique()
                    ->values()
                    ->toArray();
            }
        }

        $query = EmployeeThr::with('employee.position')
            ->where('thr_year', $year);

        // Filter by supervisor employee groups
        if (!empty($groupEmployeeIds)) {
            $query->whereIn('employee_id', $groupEmployeeIds);
        }

        if (!empty($search)) {
            $query->whereHas('employee', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        $stats = [
            'total_karyawan' => (clone $query)->count(),
            'total_thr' => (clone $query)->sum('total_thr'),
            'sudah_approved' => (clone $query)->where('status', 'approved')->count(),
            'sudah_paid' => (clone $query)->where('status', 'paid')->count(),
        ];

        if ($request->has('all')) {
            $rows = $query->get();
        } else {
            $rows = $query->paginate(20);
        }

        $company = \App\Modules\Organization\Models\Company::first();
        $branch = \App\Modules\Organization\Models\Branch::first();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'stats' => $stats,
            'company' => $company,
            'branch' => $branch,
        ]);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'thr_year' => 'required|integer',
            'reference_date' => 'required|date',
            'period_id' => 'required|integer',
        ]);

        $year = $request->thr_year;
        $periodId = $request->period_id;
        $refDate = Carbon::parse($request->reference_date);
        $thrConfigs = ThrConfig::where('is_active', true)->orderBy('min_months', 'desc')->get();

        if ($thrConfigs->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi THR belum diatur di menu Pengaturan.'
            ], 400);
        }

        // Ambil employee IDs dari supervisor_employee_groups
        $period = PayPeriod::findOrFail($periodId);
        $groupEmployeeIds = SupervisorEmployeeGroup::where('period_start', $period->start_date)
            ->where('period_end', $period->end_date)
            ->pluck('employee_id')
            ->unique()
            ->values()
            ->toArray();

        if (empty($groupEmployeeIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada karyawan di group untuk periode ini. Silakan assign karyawan di Karyawan Group terlebih dahulu.'
            ], 400);
        }

        // Ambil karyawan aktif atau resign, filter by group
        $employees = Employee::whereNotNull('join_date')
            ->whereIn('id', $groupEmployeeIds)
            ->where(function($q) {
                $q->where('is_active', true)
                  ->orWhere('employment_status', 'resigned');
            })
            ->get();

        $generatedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($employees as $emp) {
                $joinDate = Carbon::parse($emp->join_date);
                
                // Filter: join_date < reference_date
                if ($joinDate->gte($refDate)) {
                    continue; 
                }

                $diffInDaysTotal = $joinDate->diffInDays($refDate);
                
                // Minimal kerja 1 bulan (anggap 30 hari)
                if ($diffInDaysTotal < 30) {
                    continue;
                }

                // Karyawan resign: resign_date tidak lebih dari 1 bulan dari reference_date
                if ($emp->employment_status === 'resigned' && $emp->resign_date) {
                    $resignDate = Carbon::parse($emp->resign_date);
                    if ($resignDate->lt($refDate->copy()->subMonth())) {
                        continue;
                    }
                }

                $diffInMonthsExact = (int) $joinDate->diffInMonths($refDate);
                $sisaHari = $joinDate->copy()->addMonths($diffInMonthsExact)->diffInDays($refDate);
                
                if ($diffInMonthsExact >= 12) {
                    $lamaBekerja = "> 1 Tahun";
                } else {
                    $lamaBekerja = "{$diffInMonthsExact} bulan {$sisaHari} hari";
                }

                $gajiPokok = $emp->baseSalary();
                $tunjangan = $emp->tunjangan ?? 0;
                $tjMasaKerja = $emp->tjMasaKerja() ?? 0;
                $premi = $emp->premi ?? 0;

                $basisThr = $gajiPokok + $tunjangan + $tjMasaKerja;
                $rawTotal = 0;

                if ($diffInMonthsExact >= 12) {
                    $rawTotal = $basisThr;
                } else {
                    $tarifPerHari = ($basisThr / 12) / 30;
                    $rawTotal = $tarifPerHari * $diffInDaysTotal;
                }

                $roundedTotal = ceil($rawTotal / 100) * 100;
                $pembulatan = $roundedTotal - $rawTotal;

                EmployeeThr::updateOrCreate(
                    [
                        'employee_id' => $emp->id,
                        'thr_year' => $year,
                    ],
                    [
                        'thr_month' => $refDate->translatedFormat('F'),
                        'gaji_pokok' => $gajiPokok,
                        'premi' => $premi,
                        'tunjangan_masa_kerja' => $tjMasaKerja,
                        'join_date' => $emp->join_date,
                        'reference_date' => $refDate,
                        'total_bulan' => $diffInMonthsExact,
                        'sisa_hari' => $sisaHari,
                        'lama_bekerja' => $lamaBekerja,
                        'thr_amount' => $rawTotal,
                        'pembulatan' => $pembulatan,
                        'total_thr' => $roundedTotal,
                        'total_terima' => $roundedTotal,
                        'no_account' => $emp->bank_account_number,
                        'status' => 'draft',
                        'updated_by' => auth()->id() ?? 1,
                    ]
                );

                $generatedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil generate THR untuk {$generatedCount} karyawan."
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate THR: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $thr = EmployeeThr::findOrFail($id);
        
        $request->validate([
            'tunjangan' => 'numeric|min:0',
            'tunjangan_masa_kerja' => 'numeric|min:0',
        ]);

        $tunjangan = $request->input('tunjangan', 0);
        $tjMasaKerja = $request->input('tunjangan_masa_kerja', 0);
        $gajiPokok = $thr->gaji_pokok;
        $basisThr = $gajiPokok + $tunjangan + $tjMasaKerja;
        
        $rawTotal = 0;
        $diffInMonthsExact = $thr->total_bulan;
        if ($diffInMonthsExact >= 12) {
            $rawTotal = $basisThr;
        } else {
            $diffInDaysTotal = ($thr->total_bulan * 30) + $thr->sisa_hari;
            $tarifPerHari = ($basisThr / 12) / 30;
            $rawTotal = $tarifPerHari * $diffInDaysTotal;
        }

        $roundedTotal = ceil($rawTotal / 100) * 100;
        $pembulatan = $roundedTotal - $rawTotal;

        $thr->update([
            'premi' => $tunjangan, 
            'tunjangan_masa_kerja' => $tjMasaKerja,
            'thr_amount' => $rawTotal,
            'pembulatan' => $pembulatan,
            'total_thr' => $roundedTotal,
            'total_terima' => $roundedTotal,
            'catatan' => $request->input('catatan', $thr->catatan),
            'updated_by' => auth()->id() ?? 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Komponen THR berhasil diupdate.'
        ]);
    }

    public function destroy($id)
    {
        $thr = EmployeeThr::findOrFail($id);
        $thr->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data THR berhasil dihapus.'
        ]);
    }
}
