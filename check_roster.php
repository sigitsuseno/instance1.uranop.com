<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Employee\Models\Employee;

$employeeId = 57; // or 47
$date = '2025-12-25';

$roster = EmployeeShiftRoster::where('employee_id', $employeeId)->where('date', $date)->first();
echo "--- Roster for $date ---\n";
if ($roster) {
    echo json_encode($roster->toArray(), JSON_PRETTY_PRINT) . "\n";
    echo "work_pattern_id in roster: " . $roster->work_pattern_id . "\n";
    $wp = \App\Modules\Schedule\Models\WorkPattern::find($roster->work_pattern_id);
    echo "WorkPattern found in DB: " . ($wp ? json_encode($wp->toArray()) : 'No') . "\n";
} else {
    echo "No roster found.\n";
}

$employee = Employee::find($employeeId);
echo "\n--- Employee Data ---\n";
echo "Does Employee have work_pattern_id column? " . (array_key_exists('work_pattern_id', $employee->toArray()) ? "Yes (".$employee->work_pattern_id.")" : "No") . "\n";

// Let's also check what kind of shifts they have in December 2025
$rosters = EmployeeShiftRoster::where('employee_id', $employeeId)
    ->whereBetween('date', ['2025-12-01', '2025-12-31'])
    ->get(['date', 'shift_id', 'work_pattern_id', 'is_holiday']);

echo "\n--- Rosters for December 2025 ---\n";
foreach($rosters as $r) {
    echo "Date: {$r->date}, Shift: {$r->shift_id}, WP: {$r->work_pattern_id}, Holiday: {$r->is_holiday}\n";
}

