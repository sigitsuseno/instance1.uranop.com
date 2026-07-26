<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Attendance\Models\RawLog;
use App\Modules\Attendance\Services\AttendanceCalculatorService;
use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Schedule\Models\Holiday;
use App\Modules\Schedule\Models\WorkingCalendar;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceService
{


    /**
     * Lengkapi attendance — update check_in/check_out manual untuk record yang incomplete.
     *
     * @param int   $prepareId
     * @param array $data  ['check_in' => 'H:i', 'check_out' => 'H:i', 'status' => '...', 'notes' => '...']
     * @return AttendancePrepare
     */
    public function lengkapi(int $prepareId, array $data): AttendancePrepare
    {
        $prepare = AttendancePrepare::findOrFail($prepareId);

        if ($prepare->is_locked) {
            throw new \RuntimeException('Data sudah terkunci.');
        }

        $update = [];

        if (array_key_exists('check_in', $data)) {
            $update['check_in'] = $data['check_in']
                ? Carbon::parse($prepare->date->toDateString() . ' ' . $data['check_in'])
                : null;
        }

        if (array_key_exists('check_out', $data)) {
            $update['check_out'] = $data['check_out']
                ? Carbon::parse($prepare->date->toDateString() . ' ' . $data['check_out'])
                : null;
        }

        if (array_key_exists('status', $data)) {
            $update['status'] = $data['status'];
        }

        if (array_key_exists('notes', $data)) {
            $update['notes'] = $data['notes'];
        }

        if (array_key_exists('overtime', $data)) {
            $update['overtime'] = (int) $data['overtime'];
        }

        $prepare->update($update);

        // Update review_status berdasarkan kelengkapan
        $prepare->refresh();
        $prepare->update([
            'review_status' => $prepare->hasCompleteTimes()
                ? AttendancePrepare::REVIEW_LENGKAP
                : AttendancePrepare::REVIEW_CEK,
        ]);

        return $prepare->fresh();
    }

    /**
     * Bulk lengkapi attendance.
     */
    public function bulkLengkapi(array $records, int $userId): array
    {
        $updated = 0;
        $created = 0;
        $errors  = [];

        DB::beginTransaction();
        try {
            foreach ($records as $item) {
                $id   = $item['id'] ?? null;
                $eId  = $item['employee_id'] ?? null;
                $date = $item['date'] ?? null;

                // Update existing
                $isNew = false;
                if ($id) {
                    $prepare = AttendancePrepare::find($id);
                } else {
                    // Create new — cari dulu apakah sudah ada (biar gak duplicate)
                    $prepare = AttendancePrepare::where('employee_id', $eId)
                        ->where('date', $date)
                        ->first();
                    if (!$prepare) {
                        $prepare = new AttendancePrepare();
                        $prepare->employee_id = $eId;
                        $prepare->date        = $date;
                        $isNew = true;
                    }
                }

                if (!$prepare || $prepare->is_locked) {
                    continue;
                }

                $updateData = [];

                if (isset($item['check_in'])) {
                    $updateData['check_in'] = $item['check_in']
                        ? Carbon::parse($prepare->date->toDateString() . ' ' . $item['check_in'])
                        : null;
                }

                if (isset($item['check_out'])) {
                    $updateData['check_out'] = $item['check_out']
                        ? Carbon::parse($prepare->date->toDateString() . ' ' . $item['check_out'])
                        : null;
                }

                if (isset($item['status'])) {
                    $updateData['status'] = $item['status'];
                }

                if (isset($item['overtime'])) {
                    $updateData['overtime'] = (int) $item['overtime'];
                }

                if (isset($item['lm'])) {
                    $updateData['lm'] = (int) $item['lm'];
                }

                if (isset($item['overtime_count'])) {
                    $updateData['overtime_count'] = (int) $item['overtime_count'];
                }

                if (isset($item['lm_count'])) {
                    $updateData['lm_count'] = (int) $item['lm_count'];
                }

                if (!empty($updateData)) {
                    $updateData['review_status'] = AttendancePrepare::REVIEW_LENGKAP;
                }

                $note = $item['notes'] ?? '';
                if ($note) {
                    $updateData['notes'] = ($prepare->notes ? $prepare->notes . "\n" : '')
                        . "Dilengkapi oleh: #{$userId}";
                }

                if (!empty($updateData)) {
                    $prepare->fill($updateData)->save();
                    $isNew ? $created++ : $updated++;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = $e->getMessage();
        }

        return ['updated' => $updated, 'created' => $created, 'errors' => $errors];
    }

    /**
     * Auto-lengkapi attendance untuk karyawan dalam grup tertentu.
     *
     * Rules:
     * - Holiday (dari sch_holidays): lengkapi dari roster work_hour_start/end → status='libur'
     * - Minggu: lengkapi dari roster → status='libur' (ada scan) / 'off' (tidak ada)
     * - Hari kerja: cek cuti → 'cuti', else lengkapi dari roster → 'hadir', else 'absent'
     *
     * @param  string[]  $groupCodes  e.g. ['GRP-JKT'] atau ['GRP-ALLIN', 'GRP-GD', 'GRP-SS']
     * @param  string    $startDate   Y-m-d
     * @param  string    $endDate     Y-m-d
     * @param  bool      $fillAbsent  Isi check_in/out dari roster meskipun nol scan (weekday only)
     */
    public function autoLengkapi(array $groupCodes, string $startDate, string $endDate, bool $fillAbsent = false): array
    {
        // ── 1. Ambil employee IDs dalam grup ──────────────────────
        $employeeIds = Employee::whereHas('groups', function ($q) use ($groupCodes) {
            $q->whereIn('reference_code', $groupCodes);
        })->pluck('id');

        if ($employeeIds->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Tidak ada karyawan dalam grup yang dipilih.',
                'stats'   => null,
            ];
        }

        // ── 2. Ambil att_prepares yang perlu dilengkapi ──────────
        //     a) incomplete (check_in/out kosong) — existing
        //     b) holiday tapi status != 'libur' — status correction
        $prepares = AttendancePrepare::whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('is_locked', false)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereNull('check_in')
                  ->orWhereNull('check_out');
                // Holiday status correction: absent di holiday → off
                $holidayDates = Holiday::whereBetween('date', [$startDate, $endDate])
                    ->pluck('date')
                    ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : $d)
                    ->toArray();
                if (!empty($holidayDates)) {
                    $q->orWhere(function ($q2) use ($holidayDates) {
                        $q2->whereIn('date', $holidayDates)
                           ->where('status', AttendancePrepare::STATUS_ABSENT);
                    });
                }
            })
            ->get();

        if ($prepares->isEmpty()) {
            return [
                'success' => true,
                'message' => 'Semua data sudah lengkap.',
                'stats'   => ['total' => 0, 'filled' => 0, 'holiday' => 0, 'off' => 0, 'absent' => 0, 'cuti' => 0, 'hadir' => 0],
            ];
        }

        // ── 3. Preload holidays ──────────────────────────────────
        $holidayDates = Holiday::whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->flip();

        // ── 4. Preload approved leaves ───────────────────────────
        $allIds = $prepares->pluck('employee_id')->unique();

        $leaves = LeaveRequest::whereIn('employee_id', $allIds)
            ->where('status', 'approved')
            ->with('leaveType')
            ->where(function ($q) use ($startDate, $endDate) {
                // overlap: leave range intersect with date range
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            })
            ->get()
            ->groupBy('employee_id');

        // ── 4b. Preload consecutive days ────────────────────────
        $consecutives = \App\Modules\Attendance\Models\ConsecutiveDay::whereIn('employee_id', $allIds)
            ->where(function ($q) use ($startDate, $endDate) {
                // overlap: consecutive range intersect with date range
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            })
            ->get()
            ->groupBy('employee_id');

        // ── 5. Preload rosters + shifts ──────────────────────────
        $rosterMap = EmployeeShiftRoster::whereIn('employee_id', $allIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('shift')
            ->get()
            ->keyBy(fn ($r) => $r->employee_id . '_' . Carbon::parse($r->date)->toDateString());

        // ── 6. Proses tiap record ────────────────────────────────
        $stats = [
            'total'   => $prepares->count(),
            'filled'  => 0,
            'holiday' => 0,
            'off'     => 0,
            'absent'  => 0,
            'cuti'    => 0,
            'hadir'   => 0,
        ];

        DB::beginTransaction();
        try {
            foreach ($prepares as $p) {
                $dateStr    = Carbon::parse($p->date)->toDateString();
                $dateCarbon = Carbon::parse($p->date);

                $isHoliday   = $holidayDates->has($dateStr);
                $isSunday    = $dateCarbon->isSunday();
                $hasCheckIn  = ! is_null($p->check_in);
                $hasCheckOut = ! is_null($p->check_out);
                $hasAny      = $hasCheckIn || $hasCheckOut;

                // Dapatkan roster
                $key    = $p->employee_id . '_' . $dateStr;
                $roster = $rosterMap->get($key);
                $shift  = $roster?->shift;

                $workStart = $shift?->work_hour_start;
                $workEnd   = $shift?->work_hour_end;

                $update = [];

                // Cek consecutive day
                $hasConsecutiveWorked = false;
                $empConsecutives = $consecutives->get($p->employee_id);
                if ($empConsecutives) {
                    foreach ($empConsecutives as $cons) {
                        $consStart = Carbon::parse($cons->start_date);
                        $consEnd   = Carbon::parse($cons->end_date);
                        if ($dateCarbon->between($consStart, $consEnd)) {
                            $hasConsecutiveWorked = true;
                            break;
                        }
                    }
                }

                // ── HOLIDAY ──────────────────────────────────
                if ($isHoliday) {
                    if ($hasAny) {
                        if (! $hasCheckIn && $workStart) {
                            $update['check_in'] = Carbon::parse($dateStr . ' ' . $workStart);
                        }
                        if (! $hasCheckOut && $workEnd) {
                            $update['check_out'] = Carbon::parse($dateStr . ' ' . $workEnd);
                        }
                        $update['status'] = AttendancePrepare::STATUS_LIBUR;
                    } else {
                        // No scan on holiday → off (bukan absent/libur)
                        $update['status'] = AttendancePrepare::STATUS_OFF;
                    }
                    $update['review_status'] = AttendancePrepare::REVIEW_LENGKAP;
                    $stats['holiday']++;
                }
                // ── SUNDAY (tanpa roster) ───────────────────
                elseif ($isSunday && !$roster) {
                    if ($hasAny) {
                        if (! $hasCheckIn && $workStart) {
                            $update['check_in'] = Carbon::parse($dateStr . ' ' . $workStart);
                        }
                        if (! $hasCheckOut && $workEnd) {
                            $update['check_out'] = Carbon::parse($dateStr . ' ' . $workEnd);
                        }
                        $update['status'] = AttendancePrepare::STATUS_HADIR;
                    } else {
                        $update['status'] = AttendancePrepare::STATUS_OFF;
                    }
                    $update['review_status'] = AttendancePrepare::REVIEW_LENGKAP;
                    $stats['off']++;
                }
                // ── WEEKDAY (Mon-Sat) ────────────────────────
                else {
                    // Cek cuti
                    $hasLeave  = false;
                    $leaveCode = null;
                    $empLeaves = $leaves->get($p->employee_id);

                    if ($empLeaves) {
                        foreach ($empLeaves as $leave) {
                            $leaveStart = Carbon::parse($leave->start_date);
                            $leaveEnd   = Carbon::parse($leave->end_date);
                            if ($dateCarbon->between($leaveStart, $leaveEnd)) {
                                $hasLeave  = true;
                                $leaveCode = strtolower($leave->leaveType->code ?? 'ct');
                                break;
                            }
                        }
                    }

                    if ($hasLeave) {
                        $update['status']        = $leaveCode ?? 'ct';
                        $update['review_status'] = AttendancePrepare::REVIEW_LENGKAP;
                        $stats['leave'] = ($stats['leave'] ?? 0) + 1;
                    } elseif ($roster && !$workStart && !$workEnd && !$hasAny) {
                        // Roster Libur (no working hours) + no scan → off
                        $update['status']        = AttendancePrepare::STATUS_OFF;
                        $update['review_status'] = AttendancePrepare::REVIEW_LENGKAP;
                        $stats['off']++;
                    } elseif ($hasAny) {
                        if (! $hasCheckIn && $workStart) {
                            $update['check_in'] = Carbon::parse($dateStr . ' ' . $workStart);
                        }
                        if (! $hasCheckOut && $workEnd) {
                            $update['check_out'] = Carbon::parse($dateStr . ' ' . $workEnd);
                        }
                        $update['review_status'] = AttendancePrepare::REVIEW_LENGKAP;
                        $stats['hadir']++;
                    } else {
                        // No scan at all
                        if ($fillAbsent && $workStart && $workEnd) {
                            // Fill from roster meskipun nol scan
                            $update['check_in']  = Carbon::parse($dateStr . ' ' . $workStart);
                            $update['check_out'] = Carbon::parse($dateStr . ' ' . $workEnd);
                            $update['status']    = AttendancePrepare::STATUS_HADIR;
                            $stats['hadir']++;
                        } elseif ($hasConsecutiveWorked) {
                            // Ada di att_consecutive_days → hadir (libur berbayar)
                            $update['status']        = AttendancePrepare::STATUS_HADIR;
                            $update['review_status'] = AttendancePrepare::REVIEW_CSF;
                            $stats['hadir']++;
                        } elseif ($p->review_status === AttendancePrepare::REVIEW_CSF) {
                            // Hormati CSF dari Perbarui Status — jangan timpa
                            continue;
                        } else {
                            $update['status'] = AttendancePrepare::STATUS_ABSENT;
                            $stats['absent']++;
                        }
                        $update['review_status'] = AttendancePrepare::REVIEW_LENGKAP;
                    }
                }

                if (! empty($update)) {
                    $p->update($update);
                    $stats['filled']++;
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => "Lengkapi selesai. {$stats['filled']}/{$stats['total']} record diperbarui.",
                'stats'   => $stats,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Auto-lengkapi error: ' . $e->getMessage(), [
                'group_codes' => $groupCodes,
                'start_date'  => $startDate,
                'end_date'    => $endDate,
                'trace'       => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal: ' . $e->getMessage(),
                'stats'   => $stats,
            ];
        }
    }

    /**
     * Hitung lembur (overtime multiplier) untuk satu record.
     * Recalculate menggunakan AttendanceCalculatorService.
     *
     * Mengambil work_pattern_type, is_holiday, is_saturday, is_sunday dari roster
     * untuk menentukan logika perhitungan yang tepat (SHIFT vs FIXED).
     * work_pattern_id digunakan untuk lookup overtime_rule spesifik.
     */
    public function hitungLembur(AttendancePrepare $prepare, AttendanceCalculatorService $calculator): AttendancePrepare
    {
        $shift = null;
        $workPatternType = null;
        $workPatternId   = null;
        $isHoliday = false;
        $isSaturday = false;
        $isSunday  = false;

        // Ambil data dari roster (sumber utama)
        $roster = \App\Modules\Schedule\Models\EmployeeShiftRoster::where('employee_id', $prepare->employee_id)
            ->whereDate('date', $prepare->date)
            ->first();

        if ($roster) {
            $shift           = $roster->shift;
            $workPatternType = $roster->work_pattern_type;
            $workPatternId   = $roster->work_pattern_id;
            $isHoliday       = (bool) $roster->is_holiday;
            $isSaturday      = (bool) $roster->is_sat;
            $isSunday        = (bool) $roster->is_sun;
        }

        // Fallback: cek dari tabel Holiday dan Carbon
        if (! $isHoliday) {
            $isHoliday = \App\Modules\Schedule\Models\Holiday::where('date', $prepare->date->toDateString())->exists();
        }
        if (! $isSunday) {
            $isSunday = $prepare->date->isSunday();
        }
        if (! $isSaturday) {
            $isSaturday = $prepare->date->isSaturday();
        }

        $calc = $calculator->calculate(
            $prepare, $shift, $isHoliday, $isSunday,
            null,              // manualOvertime
            $workPatternType,  // workPatternType
            $isSaturday,       // isSaturday
            $workPatternId,    // workPatternId → lookup overtime_rule
        );

        $prepare->update([
            'late_minutes'   => $calc['late_minutes'],
            'lm'             => $calc['lm'],
            'lm_count'       => $calc['lm_count'],
            'overtime'       => $calc['overtime'],
            'overtime_count' => $calc['overtime_count'],
        ]);

        return $prepare->fresh();
    }

    /**
     * Bulk hitung lembur untuk rentang tanggal.
     *
     * Memproses SEMUA record unlocked yang punya check_in & check_out lengkap,
     * termasuk yang overtime/lm-nya masih 0 (belum pernah dikalkulasi).
     * Record libur/off/cuti/izin/sakit tanpa scan akan diskip otomatis
     * karena tidak punya check_in & check_out.
     */
    public function bulkHitungLembur(string $startDate, string $endDate, AttendanceCalculatorService $calculator): array
    {
        // Ambil semua record unlocked yang punya data scan lengkap
        $prepares = AttendancePrepare::whereBetween('date', [$startDate, $endDate])
            ->where('is_locked', false)
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($prepares as $prepare) {
            // Skip record cuti/izin/sakit saja.
            // LIBUR dan OFF tetap dihitung karena bisa ada lembur di hari libur.
            if ($prepare->isExcused() && !$prepare->isOffDay()) {
                $skipped++;
                continue;
            }

            $this->hitungLembur($prepare, $calculator);
            $updated++;
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * Migrasi status legacy (cuti/izin/sakit) → kode LeaveType spesifik.
     *
     * Cari record att_prepares yang statusnya masih generic, lalu cocokkan
     * dengan approved leave request untuk mendapatkan kode spesifiknya.
     *
     * Aman: tidak menyentuh check_in, check_out, late, overtime, LM.
     *        Hanya update field `status`.
     */
    public function migrateLegacyStatuses(string $startDate, string $endDate): array
    {
        $legacyStatuses = ['cuti', 'izin', 'sakit'];

        $prepares = AttendancePrepare::whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', $legacyStatuses)
            ->where('is_locked', false)
            ->get();

        $updated = 0;
        $skipped = 0;

        if ($prepares->isNotEmpty()) {
            // Preload approved leaves untuk range
            $leaves = \App\Modules\Leave\Models\LeaveRequest::with('leaveType')
                ->where('status', 'approved')
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($q2) use ($startDate, $endDate) {
                          $q2->where('start_date', '<=', $startDate)
                             ->where('end_date', '>=', $endDate);
                      });
                })
                ->get()
                ->groupBy('employee_id');

            foreach ($prepares as $prepare) {
                $empLeaves = $leaves->get($prepare->employee_id);

                if (!$empLeaves) {
                    $skipped++;
                    continue;
                }

                $matched = false;
                foreach ($empLeaves as $leave) {
                    $leaveStart = $leave->start_date instanceof \Carbon\Carbon
                        ? $leave->start_date
                        : \Carbon\Carbon::parse($leave->start_date);
                    $leaveEnd = $leave->end_date instanceof \Carbon\Carbon
                        ? $leave->end_date
                        : \Carbon\Carbon::parse($leave->end_date);
                    $dateCheck = $prepare->date instanceof \Carbon\Carbon
                        ? $prepare->date
                        : \Carbon\Carbon::parse($prepare->date);

                    if ($dateCheck->between($leaveStart, $leaveEnd)) {
                        $newStatus = strtolower($leave->leaveType->code ?? 'ct');
                        if ($newStatus !== $prepare->status) {
                            $prepare->update(['status' => $newStatus]);
                            $updated++;
                        }
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    $skipped++;
                }
            }
        }

        // TAMBAHAN: Sinkronisasi Status dari Consecutive Days
        $consecutives = \App\Modules\Attendance\Models\ConsecutiveDay::where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            })->get();

        $consecutiveUpdated = 0;

        foreach ($consecutives as $consecutive) {
            $consStart = \Carbon\Carbon::parse($consecutive->start_date)->max(\Carbon\Carbon::parse($startDate));
            $consEnd = \Carbon\Carbon::parse($consecutive->end_date)->min(\Carbon\Carbon::parse($endDate));

            $statusToSet = AttendancePrepare::STATUS_HADIR;

            // Override semua record di range konsekutif → hadir + csf
            $updatedRows = AttendancePrepare::where('employee_id', $consecutive->employee_id)
                ->whereBetween('date', [$consStart->toDateString(), $consEnd->toDateString()])
                ->where('is_locked', false)
                ->update([
                    'status' => $statusToSet,
                    'review_status' => AttendancePrepare::REVIEW_CSF
                ]);

            $consecutiveUpdated += $updatedRows;
            $updated += $updatedRows;
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'message' => "{$updated} record diperbarui (termasuk {$consecutiveUpdated} dari konsekutif), {$skipped} diskip.",
        ];
    }

    /**
     * Lock attendance record — hanya superadmin & hrmanager.
     */
    public function lock(int $prepareId, int $userId): AttendancePrepare
    {
        $prepare = AttendancePrepare::findOrFail($prepareId);
        $prepare->lock($userId);

        return $prepare->fresh();
    }

    /**
     * Unlock attendance record — hanya superadmin & hrmanager.
     */
    public function unlock(int $prepareId): AttendancePrepare
    {
        $prepare = AttendancePrepare::findOrFail($prepareId);
        $prepare->unlock();

        return $prepare->fresh();
    }

    /**
     * Bulk lock/unlock attendance records.
     */
    public function bulkLock(array $ids, int $userId, bool $lock = true): array
    {
        $updated = 0;
        foreach ($ids as $id) {
            $prepare = AttendancePrepare::find($id);
            if (!$prepare) continue;

            if ($lock) {
                $prepare->lock($userId);
            } else {
                $prepare->unlock();
            }
            $updated++;
        }

        return ['updated' => $updated];
    }



    /**
     * Dapatkan jadwal kerja karyawan untuk tanggal tertentu.
     */
    protected function getEmployeeSchedule(Employee $employee, Carbon $date): ?array
    {
        // 1. Cek roster shift
        try {
            $roster = \App\Modules\Schedule\Models\EmployeeShiftRoster::where('employee_id', $employee->id)
                ->where('date', $date->toDateString())
                ->first();

            if ($roster && $roster->shift) {
                return [
                    'shift_id' => $roster->shift_id,
                    'schedule_in' => $roster->shift->start_time,
                    'schedule_out' => $roster->shift->end_time,
                ];
            }
        } catch (\Throwable) {
            // Roster mungkin belum ada
        }

        // 2. Cek work pattern
        // Gunakan Employee Group untuk mapping ke work pattern
        try {
            $workPatternGroup = $employee->employeeGroups()
                ->whereHas('groupMaster', fn($q) => $q->where('code', 'work_pattern'))
                ->first();

            if ($workPatternGroup) {
                $workPattern = \App\Modules\Schedule\Models\WorkPattern::where('code', $workPatternGroup->reference_code)
                    ->first();

                if ($workPattern) {
                    $dayName = strtolower($date->englishDayOfWeek);
                    $detail = $workPattern->details()
                        ->where('day', $dayName)
                        ->first();

                    if ($detail) {
                        return [
                            'work_pattern_id' => $workPattern->id,
                            'schedule_in' => $detail->start_time,
                            'schedule_out' => $detail->end_time,
                        ];
                    }
                }
            }
        } catch (\Throwable) {
            // Work pattern mapping mungkin belum ada
        }

        // 3. Default: gunakan config
        return [
            'schedule_in' => '08:00',
            'schedule_out' => '17:00',
        ];
    }

    /**
     * Import data absensi dari RawLog ke AttendanceLog.
     * Memetakan PIN ke employee_id.
     */
    public function matchRawLogsToEmployees(?string $batch = null): array
    {
        $result = ['matched' => 0, 'errors' => []];

        $query = RawLog::whereNull('employee_name')->orWhere('employee_name', '');
        if ($batch) {
            $query->where('import_batch', $batch);
        }

        $logs = $query->get();

        foreach ($logs as $log) {
            try {
                $employee = Employee::where('nip', $log->employee_code)
                    ->orWhere('employee_code', $log->employee_code)
                    ->first();

                if ($employee) {
                    $log->update(['employee_name' => $employee->full_name]);
                    $result['matched']++;
                }
            } catch (\Throwable $e) {
                $result['errors'][] = "Gagal match {$log->employee_code}: " . $e->getMessage();
            }
        }

        return $result;
    }
}
