<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Cek kondisi terkini di DB
$p = App\Modules\Attendance\Models\AttendancePrepare::where('employee_id', 175)->whereDate('date', '2026-06-25')->first();
if ($p) {
    echo "=== ATTENDANCE PREPARE (NOW) ===\n";
    echo 'ID: ' . $p->id . "\n";
    echo 'check_in: ' . ($p->check_in ? $p->check_in->format('Y-m-d H:i:s') : 'NULL') . "\n";
    echo 'check_out: ' . ($p->check_out ? $p->check_out->format('Y-m-d H:i:s') : 'NULL') . "\n";
    echo 'is_locked: ' . ($p->is_locked ? 'true' : 'false') . "\n";
    echo 'late_minutes: ' . $p->late_minutes . "\n";
    echo 'lm: ' . $p->lm . "\n";
    echo 'overtime: ' . $p->overtime . "\n";
    echo 'overtime_count: ' . $p->overtime_count . "\n";
    echo 'status: ' . $p->status . "\n";
}

// Work pattern
$wp = App\Modules\Schedule\Models\WorkPattern::find(5);
echo "\n--- WORK PATTERN ---\n";
echo 'code: ' . ($wp->code ?? 'NULL') . "\n";
echo 'name: ' . ($wp->name ?? 'NULL') . "\n";
echo 'work_day_hours: ' . ($wp->work_day_hours ?? 'NULL') . "\n";
echo 'wd_rest_hours: ' . ($wp->wd_rest_hours ?? 'NULL') . "\n";
echo 'half_day_hours: ' . ($wp->half_day_hours ?? 'NULL') . "\n";

// Re-kalkulasi
$calculator = new App\Modules\Attendance\Services\AttendanceCalculatorService();
$roster = App\Modules\Schedule\Models\EmployeeShiftRoster::where('employee_id', 175)->whereDate('date', '2026-06-25')->first();

$isHoliday = ($roster && $roster->is_holiday) ? true : false;
if (!$isHoliday) $isHoliday = App\Modules\Schedule\Models\Holiday::where('date', '2026-06-25')->exists();
$isSunday = ($roster && $roster->is_sun) ? true : \Carbon\Carbon::parse('2026-06-25')->isSunday();
$isSaturday = ($roster && $roster->is_sat) ? true : \Carbon\Carbon::parse('2026-06-25')->isSaturday();

$result = $calculator->calculate(
    $p, $roster?->shift, $isHoliday, $isSunday,
    null, $roster?->work_pattern_type, $isSaturday, $roster?->work_pattern_id
);

echo "\n--- REKALKULASI ---\n";
echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
