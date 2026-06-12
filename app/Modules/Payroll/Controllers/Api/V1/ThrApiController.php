<?php

namespace App\Modules\Payroll\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeThr;
use App\Modules\Settings\Models\ThrConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ThrApiController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->query('thr_year', date('Y'));
        $search = $request->query('search', '');

        $query = EmployeeThr::with('employee.position')
            ->where('thr_year', $year);

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
        ]);

        $year = $request->thr_year;
        $refDate = Carbon::parse($request->reference_date);
        $thrConfigs = ThrConfig::where('is_active', true)->orderBy('min_months', 'desc')->get();

        if ($thrConfigs->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi THR belum diatur di menu Pengaturan.'
            ], 400);
        }

        // 1. Ambil karyawan aktif atau resign
        $employees = Employee::whereNotNull('join_date')
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
                
                // 2. Filter: join_date < reference_date
                if ($joinDate->gte($refDate)) {
                    continue; 
                }

                $diffInDaysTotal = $joinDate->diffInDays($refDate);
                
                // 3. Minimal kerja 1 bulan (anggap 30 hari)
                if ($diffInDaysTotal < 30) {
                    continue;
                }

                // 4. Karyawan resign: resign_date tidak lebih dari 1 bulan dari reference_date
                if ($emp->employment_status === 'resigned' && $emp->resign_date) {
                    $resignDate = Carbon::parse($emp->resign_date);
                    if ($resignDate->lt($refDate->copy()->subMonth())) {
                        continue; // resign lebih dari 1 bulan yang lalu
                    }
                }

                $diffInMonthsExact = (int) $joinDate->diffInMonths($refDate);
                $sisaHari = $joinDate->copy()->addMonths($diffInMonthsExact)->diffInDays($refDate);
                
                if ($diffInMonthsExact >= 12) {
                    $lamaBekerja = "> 1 Tahun";
                } else {
                    $lamaBekerja = "{$diffInMonthsExact} bulan {$sisaHari} hari";
                }

                // Ambil komponen
                $gajiPokok = $emp->baseSalary();
                $tunjangan = $emp->tunjangan ?? 0;
                $tjMasaKerja = $emp->tjMasaKerja() ?? 0;
                $premi = $emp->premi ?? 0; // Tetap disimpan tapi jika tidak masuk rumus, tidak apa-apa.

                $basisThr = $gajiPokok + $tunjangan + $tjMasaKerja;
                $rawTotal = 0;

                // 5. Rumus hitungan
                if ($diffInMonthsExact >= 12) {
                    // Masa kerja > 1 tahun
                    $rawTotal = $basisThr;
                } else {
                    // Masa kerja < 1 tahun = (((gaji_pokok + tunjangan + tj_masa_kerja) / 12) / 30) x total_hari
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
            $diffInDaysTotal = ($thr->total_bulan * 30) + $thr->sisa_hari; // Approximation to match generation
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
