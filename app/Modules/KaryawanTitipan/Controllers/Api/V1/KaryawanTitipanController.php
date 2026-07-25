<?php

namespace App\Modules\KaryawanTitipan\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\KaryawanTitipan\Models\KaryawanTitipan;
use App\Modules\KaryawanTitipan\Models\KaryawanTitipanRoster;
use App\Modules\Payroll\Models\PayPeriod;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class KaryawanTitipanController extends Controller
{
    // =============================================
    //  CRUD Karyawan Titipan
    // =============================================

    public function index(Request $request): JsonResponse
    {
        $query = KaryawanTitipan::query()->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $data = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data'       => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama'          => 'required|string|max:200',
            'employee_code' => 'nullable|string|max:50|unique:karyawan_titipan,employee_code',
            'period_id'     => 'required|exists:pay_periods,id',
            'uang_makan'    => 'nullable|numeric|min:0',
            'status'        => 'required|in:aktif,nonaktif',
        ]);

        // Ambil periode
        $period = PayPeriod::findOrFail($data['period_id']);
        $startDate = Carbon::parse($period->start_date);
        $endDate = Carbon::parse($period->end_date);

        // Batasi sampai hari ini
        $today = Carbon::today();
        if ($endDate->gt($today)) {
            $endDate = $today;
        }

        // Set data karyawan
        $karyawanData = [
            'uuid'          => (string) Str::uuid(),
            'nama'          => $data['nama'],
            'employee_code' => $data['employee_code'] ?? null,
            'start_date'    => $period->start_date,
            'end_date'      => $period->end_date,
            'status'        => $data['status'],
            'component'     => ['uang_makan' => $data['uang_makan'] ?? 0],
            'created_by'    => Auth::id(),
        ];

        $karyawan = KaryawanTitipan::create($karyawanData);

        // Generate roster otomatis: default 'H' per tanggal
        $rosterCount = 0;
        $d = $startDate->copy();
        while ($d->lte($endDate)) {
            KaryawanTitipanRoster::create([
                'karyawan_titipan_id' => $karyawan->id,
                'date'                => $d->format('Y-m-d'),
                'status'              => 'H',
                'created_by'          => Auth::id(),
            ]);
            $rosterCount++;
            $d->addDay();
        }

        return response()->json([
            'message'      => "Karyawan titipan berhasil ditambahkan dengan {$rosterCount} entri roster.",
            'data'         => $karyawan->fresh(),
            'roster_count' => $rosterCount,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $karyawan = KaryawanTitipan::findOrFail($id);
        return response()->json(['data' => $karyawan]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $karyawan = KaryawanTitipan::findOrFail($id);

        $data = $request->validate([
            'nama'          => 'required|string|max:200',
            'employee_code' => 'nullable|string|max:50|unique:karyawan_titipan,employee_code,' . $id,
            'start_date'    => 'nullable|date',
            'end_date'      => 'nullable|date|after_or_equal:start_date',
            'status'        => 'required|in:aktif,nonaktif',
            'component'     => 'nullable|json',
        ]);

        if (!empty($data['component'])) {
            $data['component'] = json_decode($data['component'], true);
        }

        $data['updated_by'] = Auth::id();
        $karyawan->update($data);

        return response()->json([
            'message' => 'Karyawan titipan berhasil diupdate.',
            'data'    => $karyawan->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $karyawan = KaryawanTitipan::findOrFail($id);
        $karyawan->delete();

        return response()->json([
            'message' => 'Karyawan titipan berhasil dihapus.',
        ]);
    }

    // =============================================
    //  ROSTER — Generate & Matrix
    // =============================================

    /**
     * GET /api/v1/karyawan-titipan/periods
     * Dropdown periode payroll
     */
    public function periods(): JsonResponse
    {
        $periods = PayPeriod::orderBy('start_date', 'desc')->get(['id', 'name', 'start_date', 'end_date']);
        return response()->json(['data' => $periods]);
    }

    /**
     * POST /api/v1/karyawan-titipan/roster/generate
     * Generate roster untuk semua karyawan aktif di periode tertentu
     */
    public function generateRoster(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
        ]);

        $period = PayPeriod::findOrFail($data['period_id']);
        $startDate = Carbon::parse($period->start_date);
        $endDate   = Carbon::parse($period->end_date);

        $today = Carbon::today();
        if ($endDate->gt($today)) {
            $endDate = $today;
        }

        $employees = KaryawanTitipan::where('status', 'aktif')->get();

        if ($employees->isEmpty()) {
            return response()->json(['message' => 'Tidak ada karyawan titipan aktif.'], 422);
        }

        $created = 0;

        foreach ($employees as $employee) {
            $d = $startDate->copy();
            while ($d->lte($endDate)) {
                $dateStr = $d->format('Y-m-d');
                // Skip kalo udah ada
                $exists = KaryawanTitipanRoster::where('karyawan_titipan_id', $employee->id)
                    ->where('date', $dateStr)
                    ->exists();

                if (!$exists) {
                    KaryawanTitipanRoster::create([
                        'karyawan_titipan_id' => $employee->id,
                        'date'                => $dateStr,
                        'status'              => 'H',
                        'created_by'          => Auth::id(),
                    ]);
                    $created++;
                }
                $d->addDay();
            }
        }

        return response()->json([
            'message' => "Roster berhasil digenerate: {$created} entri baru.",
            'created' => $created,
        ]);
    }

    /**
     * POST /api/v1/karyawan-titipan/roster/regenerate
     * Hapus semua roster periode lalu generate ulang
     */
    public function regenerateRoster(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
        ]);

        $period = PayPeriod::findOrFail($data['period_id']);

        // Hapus roster di range periode
        KaryawanTitipanRoster::whereBetween('date', [$period->start_date, $period->end_date])->delete();

        // Generate ulang
        return $this->generateRoster($request);
    }

    /**
     * GET /api/v1/karyawan-titipan/roster?period_id=X
     * Ambil data roster matrix
     */
    public function getRoster(Request $request): JsonResponse
    {
        $periodId = $request->integer('period_id');
        $period = $periodId ? PayPeriod::find($periodId) : PayPeriod::orderBy('start_date', 'desc')->first();

        if (!$period) {
            return response()->json([
                'dates'   => [],
                'records' => [],
                'periods' => PayPeriod::orderBy('start_date', 'desc')->get(['id', 'name', 'start_date', 'end_date']),
                'period'  => null,
            ]);
        }

        $startDate = Carbon::parse($period->start_date);
        $endDate   = Carbon::parse($period->end_date);

        $today = Carbon::today();
        if ($endDate->gt($today)) {
            $endDate = $today;
        }

        // Generate date list
        $dates = [];
        $d = $startDate->copy();
        while ($d->lte($endDate)) {
            $dates[] = [
                'date'       => $d->format('Y-m-d'),
                'day'        => $d->format('d'),
                'day_name'   => $d->translatedFormat('D'),
                'is_weekend' => $d->isSunday(),
            ];
            $d->addDay();
        }

        $dateStrings = array_column($dates, 'date');

        // Ambil karyawan + roster
        $employees = KaryawanTitipan::where('status', 'aktif')->orderBy('nama')->get();

        $rosters = KaryawanTitipanRoster::whereIn('karyawan_titipan_id', $employees->pluck('id'))
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->groupBy('karyawan_titipan_id');

        $records = $employees->map(function ($employee) use ($rosters, $dateStrings) {
            $empRosters = $rosters->get($employee->id, collect())
                ->keyBy(fn($r) => $r->date->format('Y-m-d'));

            $attendance = [];
            foreach ($dateStrings as $dateStr) {
                $roster = $empRosters->get($dateStr);
                $attendance[$dateStr] = [
                    'roster_id' => $roster?->id,
                    'status'    => $roster?->status ?? '-',
                ];
            }

            return [
                'id'            => $employee->id,
                'nama'          => $employee->nama,
                'employee_code' => $employee->employee_code,
                'attendance'    => $attendance,
            ];
        });

        return response()->json([
            'dates'   => $dates,
            'records' => $records,
            'periods' => PayPeriod::orderBy('start_date', 'desc')->get(['id', 'name', 'start_date', 'end_date']),
            'period'  => $period ? ['id' => $period->id, 'name' => $period->name] : null,
        ]);
    }

    /**
     * PUT /api/v1/karyawan-titipan/roster/{id}
     * Update status satu cell roster
     */
    public function updateRoster(Request $request, int $id): JsonResponse
    {
        $roster = KaryawanTitipanRoster::findOrFail($id);

        $data = $request->validate([
            'status' => 'required|in:H,A,C,I,S,Off,-',
        ]);

        $roster->update([
            'status'     => $data['status'],
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Status berhasil diupdate.',
            'data'    => $roster->fresh(),
        ]);
    }
}
