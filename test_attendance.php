<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Supervisor\Attendance\Imports\AttendanceDataFixImport;
use Carbon\Carbon;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Leave\Models\LeaveRequest;

$employeeId = 57;
$dateStr = '2025-12-25';
$dates = [
    2 => Carbon::parse($dateStr)
];

$output = "";

$output .= "Roster: ";
$roster = EmployeeShiftRoster::where('employee_id', $employeeId)->where('date', $dateStr)->with('workPattern', 'shift')->first();
if ($roster) {
    $output .= json_encode(['shift' => $roster->shift, 'workPattern' => $roster->workPattern, 'is_holiday' => $roster->is_holiday, 'external_code' => $roster->external_code]);
} else {
    $output .= 'None';
}
$output .= "\n";

$output .= "Leave: ";
$leave = LeaveRequest::where('employee_id', $employeeId)->where('start_date', '<=', $dateStr)->where('end_date', '>=', $dateStr)->first();
$output .= $leave ? 'Yes' : 'None';
$output .= "\n";

$tester = new class extends AttendanceDataFixImport {
    public function testProcessRow($rowValues, $employeeId, $dates) {
        return $this->processRow($rowValues, 1, $employeeId, $dates);
    }
};

$statuses = ['', 'H', 'T', 'L', 'CT', 'SAKIT', 'IZIN', 'OFF', 'OUT', '-'];
$results = [];
foreach ($statuses as $status) {
    $rowValues = [
        2 => $status,
        3 => '1.5' // lembur 1.5 jam
    ];
    $res = $tester->testProcessRow($rowValues, $employeeId, $dates);
    if (!empty($res['records'])) {
        $results[$status] = $res['records'][0];
    } else {
        $results[$status] = ['errors' => $res['errors']];
    }
}

$output .= json_encode($results, JSON_PRETTY_PRINT);
file_put_contents('test_output.json', $output);
