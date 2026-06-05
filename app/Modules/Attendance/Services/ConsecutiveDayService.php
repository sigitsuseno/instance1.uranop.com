<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Attendance\Models\ConsecutiveDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsecutiveDayService
{
    /**
     * Minimum hari berturut-turut yang dianggap signifikan.
     */
    public const MIN_STREAK_DAYS = 3;

    /**
     * Deteksi & simpan streak hadir/absen dalam rentang tanggal.
     *
     * @param  string    $startDate   Y-m-d
     * @param  string    $endDate     Y-m-d
     * @param  int|null  $employeeId  Opsional: spesifik karyawan
     * @param  array     $groupCodes  Opsional: filter by employee group
     * @return array     ['worked' => int, 'absent' => int, 'total' => int]
     */
    public function detect(string $startDate, string $endDate, ?int $employeeId = null, array $groupCodes = []): array
    {
        // ── 1. Ambil att_prepares ──────────────────────────────────
        $query = AttendancePrepare::with('employee:id,name,employee_code,department_id')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('employee_id')
            ->orderBy('date');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if (! empty($groupCodes)) {
            $query->whereHas('employee.groups', function ($q) use ($groupCodes) {
                $q->whereIn('reference_code', $groupCodes);
            });
        }

        $prepares = $query->get();

        if ($prepares->isEmpty()) {
            return ['worked' => 0, 'absent' => 0, 'total' => 0];
        }

        // ── 2. Group by employee_id ────────────────────────────────
        $grouped = $prepares->groupBy('employee_id');

        $allStreaks = new Collection();

        foreach ($grouped as $empId => $records) {
            $sorted = $records->sortBy('date')->values();

            $workedStreaks = $this->findStreaks($sorted, 'worked');
            $absentStreaks = $this->findStreaks($sorted, 'absent');

            foreach ($workedStreaks as $streak) {
                $allStreaks->push([
                    'employee_id' => $empId,
                    'start_date'  => $streak['start'],
                    'end_date'    => $streak['end'],
                    'total_days'  => $streak['days'],
                    'type'        => ConsecutiveDay::TYPE_WORKED,
                ]);
            }

            foreach ($absentStreaks as $streak) {
                $allStreaks->push([
                    'employee_id' => $empId,
                    'start_date'  => $streak['start'],
                    'end_date'    => $streak['end'],
                    'total_days'  => $streak['days'],
                    'type'        => ConsecutiveDay::TYPE_ABSENT,
                ]);
            }
        }

        // ── 3. Simpan ke database (replace mode) ───────────────────
        $stats = ['worked' => 0, 'absent' => 0, 'total' => 0];

        DB::beginTransaction();
        try {
            // Hapus data lama dalam periode
            ConsecutiveDay::forPeriod($startDate, $endDate)
                ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
                ->when(! empty($groupCodes), function ($q) use ($groupCodes) {
                    $q->whereHas('employee.groups', function ($q2) use ($groupCodes) {
                        $q2->whereIn('reference_code', $groupCodes);
                    });
                })
                ->forceDelete();

            // Insert streaks baru
            foreach ($allStreaks as $streak) {
                ConsecutiveDay::create([
                    'employee_id' => $streak['employee_id'],
                    'start_date'  => $streak['start_date'],
                    'end_date'    => $streak['end_date'],
                    'total_days'  => $streak['total_days'],
                    'type'        => $streak['type'],
                    'status'      => ConsecutiveDay::STATUS_CALCULATED,
                ]);

                $stats[$streak['type']]++;
                $stats['total']++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ConsecutiveDayService detect error: ' . $e->getMessage(), [
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'trace'      => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $stats;
    }

    /**
     * Cari streak (deretan hari berturut-turut) dari data att_prepares.
     *
     * @param  Collection  $records  Sorted by date ASC
     * @param  string      $type     'worked' atau 'absent'
     * @return array       [['start' => 'Y-m-d', 'end' => 'Y-m-d', 'days' => int], ...]
     */
    protected function findStreaks(Collection $records, string $type): array
    {
        $streaks = [];
        $current = null; // ['start', 'end', 'count']

        foreach ($records as $record) {
            $dateStr = Carbon::parse($record->date)->toDateString();
            $matches = $this->matchesType($record, $type);

            if (! $matches) {
                // Akhiri streak yang sedang berjalan
                if ($current && $current['count'] >= self::MIN_STREAK_DAYS) {
                    $streaks[] = [
                        'start' => $current['start'],
                        'end'   => $current['end'],
                        'days'  => $current['count'],
                    ];
                }
                $current = null;
                continue;
            }

            // Cek apakah consecutive (tanggal berurutan)
            if ($current) {
                $expectedNext = Carbon::parse($current['end'])->addDay()->toDateString();

                if ($dateStr === $expectedNext) {
                    // Lanjutkan streak
                    $current['end']   = $dateStr;
                    $current['count']++;
                } else {
                    // Gap — akhiri streak lama, mulai baru
                    if ($current['count'] >= self::MIN_STREAK_DAYS) {
                        $streaks[] = [
                            'start' => $current['start'],
                            'end'   => $current['end'],
                            'days'  => $current['count'],
                        ];
                    }
                    $current = ['start' => $dateStr, 'end' => $dateStr, 'count' => 1];
                }
            } else {
                // Mulai streak baru
                $current = ['start' => $dateStr, 'end' => $dateStr, 'count' => 1];
            }
        }

        // Akhiri streak terakhir
        if ($current && $current['count'] >= self::MIN_STREAK_DAYS) {
            $streaks[] = [
                'start' => $current['start'],
                'end'   => $current['end'],
                'days'  => $current['count'],
            ];
        }

        return $streaks;
    }

    /**
     * Cek apakah record matches tipe streak yang dicari.
     */
    protected function matchesType(AttendancePrepare $record, string $type): bool
    {
        return match ($type) {
            'worked' => $record->status === AttendancePrepare::STATUS_HADIR
                        && $record->check_in !== null
                        && $record->check_out !== null,
            'absent' => $record->status === AttendancePrepare::STATUS_ABSENT,
            default  => false,
        };
    }

    /**
     * List consecutive days dengan filter.
     */
    public function list(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = ConsecutiveDay::with('employee:id,name,employee_code,department_id')
            ->orderBy('start_date', 'desc')
            ->orderBy('employee_id');

        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $query->forPeriod($filters['start_date'], $filters['end_date']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['type'])) {
            $query->byType($filters['type']);
        }

        if (! empty($filters['min_days'])) {
            $query->where('total_days', '>=', (int) $filters['min_days']);
        }

        return $query->paginate($filters['per_page'] ?? 50);
    }

    /**
     * Flag / tandai streak tertentu.
     */
    public function flag(int $id, ?string $notes = null): ConsecutiveDay
    {
        $streak = ConsecutiveDay::findOrFail($id);
        $streak->update([
            'status' => ConsecutiveDay::STATUS_FLAGGED,
            'notes'  => $notes ? ($streak->notes ? $streak->notes . "\n" : '') . $notes : $streak->notes,
        ]);

        return $streak->fresh();
    }
}
