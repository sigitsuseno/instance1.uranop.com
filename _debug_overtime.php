<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$eid = 175;
$date = '2026-06-25';

// 1. AttendancePrepare
$p = \App\Modules\Attendance\Models\AttendancePrepare::where('employee_id', $eid)->whereDate('date', $date)->first();
if ($p) {
    echo "=== ATTENDANCE PREPARE ===\n";
    echo "ID: {$p->id}\n";
    echo "Date: {$p->date}\n";
    echo "check_in: " . ($p->check_in ? $p->check_in->format('Y-m-d H:i:s') : 'NULL') . "\n";
    echo "check_out: " . ($p->check_out ? $p->check_out->format('Y-m-d H:i:s') : 'NULL') . "\n";
    echo "status: {$p->status}\n";
    echo "is_locked: " . ($p->is_locked ? 'true' : 'false') . "\n";
    echo "late_minutes: {$p->late_minutes}\n";
    echo "lm: {$p->lm}\n";
    echo "lm_count: {$p->lm_count}\n";
    echo "overtime: {$p->overtime}\n";
    echo "overtime_count: {$p->overtime_count}\n";
    echo "review_status: {$p->review_status}\n";
    echo "periode_start: {$p->periode_start}\n";
    echo "periode_end: {$p->periode_end}\n";
} else {
    echo "AttendancePrepare not found\n";
}

// 2. Roster
echo "\n=== ROSTER ===\n";
$r = \App\Modules\Schedule\Models\EmployeeShiftRoster::where('employee_id', $eid)->whereDate('date', $date)->first();
if ($r) {
    echo "ID: {$r->id}\n";
    echo "shift_id: {$r->shift_id}\n";
    echo "work_pattern_type: {$r->work_pattern_type}\n";
    echo "work_pattern_id: {$r->work_pattern_id}\n";
    echo "is_holiday: " . ($r->is_holiday ? 'true' : 'false') . "\n";
    echo "is_sat: " . ($r->is_sat ? 'true' : 'false') . "\n";
    echo "is_sun: " . ($r->is_sun ? 'true' : 'false') . "\n";
    if ($r->shift) {
        echo "Shift external_code: {$r->shift->external_code}\n";
        echo "Shift work_hour_start: {$r->shift->work_hour_start}\n";
        echo "Shift work_hour_end: {$r->shift->work_hour_end}\n";
        echo "Shift tolerance: {$r->shift->tolerance_minutes}\n";
    }
} else {
    echo "Roster not found\n";
}

// 3. Check 2026-06-25 is what day of week
echo "\n=== DAY OF WEEK ===\n";
$dt = \Carbon\Carbon::parse('2026-06-25');
echo "Day: " . $dt->format('l') . "\n";
echo "isSaturday: " . ($dt->isSaturday() ? 'true' : 'false') . "\n";
echo "isSunday: " . ($dt->isSunday() ? 'true' : 'false') . "\n";

// 4. Holiday check
echo "\n=== HOLIDAY ===\n";
$holiday = \App\Modules\Schedule\Models\Holiday::where('date', $date)->first();
if ($holiday) {
    echo "Holiday: {$holiday->name}\n";
} else {
    echo "Not a holiday\n";
}

// 5. Overtime setting config
echo "\n=== OVERTIME SETTING CONFIG ===\n";
$config = \App\Modules\Payroll\Models\PayrollConfig::getConfig('attendance_overtime_setting');
echo "formulas: " . json_encode($config['formulas'] ?? []) . "\n";
echo "work_hours: " . json_encode($config['work_hours'] ?? []) . "\n";
echo "special_employees: " . json_encode($config['special_employees'] ?? []) . "\n";
echo "technician_rule: " . json_encode($config['technician_rule'] ?? []) . "\n";
echo "zero_late_shift_codes: " . json_encode($config['zero_late_shift_codes'] ?? []) . "\n";
echo "rounding_interval: " . ($config['rounding_interval'] ?? 'not set') . "\n";
echo "rounding_threshold: " . ($config['rounding_threshold'] ?? 'not set') . "\n";

// 6. Employee work pattern
echo "\n=== EMPLOYEE ===\n";
$emp = \App\Modules\Employee\Models\Employee::find($eid);
if ($emp) {
    echo "name: {$emp->name}\n";
    echo "employee_code: {$emp->employee_code}\n";
}

// 7. Overtime rules
echo "\n=== OVERTIME RULES ===\n";
$rules = \App\Modules\Settings\Models\OvertimeRule::where('is_active', true)->get();
foreach ($rules as $rule) {
    echo "Rule ID: {$rule->id}, is_holiday: " . ($rule->is_holiday ? 'true' : 'false') . ", is_saturday: " . ($rule->is_saturday ? 'true' : 'false') . ", work_pattern_id: " . ($rule->work_pattern_id ?: 'NULL') . "\n";
    $details = $rule->details()->orderBy('hour')->get();
    foreach ($details as $d) {
        echo "  hour={$d->hour}, multiplier={$d->multiplier}\n";
    }
}

// 8. Manual calculation trace
echo "\n=== CALCULATION TRACE ===\n";
if ($p && $r) {
    $calculator = new \App\Modules\Attendance\Services\AttendanceCalculatorService();

    $isHoliday = (bool) $r->is_holiday;
    $isSunday = (bool) $r->is_sun;
    if (!$isHoliday) {
        $isHoliday = \App\Modules\Schedule\Models\Holiday::where('date', $p->date->toDateString())->exists();
    }
    if (!$isSunday) {
        $isSunday = $p->date->isSunday();
    }
    $isSaturday = (bool) $r->is_sat;
    if (!$isSaturday) {
        $isSaturday = $p->date->isSaturday();
    }

    echo "isHoliday: " . ($isHoliday ? 'true' : 'false') . "\n";
    echo "isSunday: " . ($isSunday ? 'true' : 'false') . "\n";
    echo "isSaturday: " . ($isSaturday ? 'true' : 'false') . "\n";
    echo "workPatternType: {$r->work_pattern_type}\n";
    echo "workPatternId: {$r->work_pattern_id}\n";

    $result = $calculator->calculate(
        $p, $r->shift, $isHoliday, $isSunday,
        null, $r->work_pattern_type, $isSaturday, $r->work_pattern_id
    );

    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
}
