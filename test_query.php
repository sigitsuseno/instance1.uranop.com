<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$emp = DB::select('SELECT * FROM employees WHERE employee_code = ?', ['50046']);
if (empty($emp)) {
    echo "Employee not found.\n";
    exit;
}

echo "Employee:\n";
echo json_encode($emp[0], JSON_PRETTY_PRINT);
echo "\n\nAttendance Prepares:\n";
$prepares = DB::select('SELECT * FROM att_prepares WHERE employee_id = ? AND date = ?', [$emp[0]->id, '2026-06-12']);
echo json_encode($prepares, JSON_PRETTY_PRINT);

echo "\n\nRaw Logs:\n";
$logs = DB::select('SELECT * FROM att_raw_logs WHERE employee_code = ? AND DATE(scan_datetime) = ?', ['50046', '2026-06-12']);
echo json_encode($logs, JSON_PRETTY_PRINT);

echo "\n\nShift Roster:\n";
$rosters = DB::select('SELECT * FROM sch_employee_shift_rosters WHERE employee_id = ? AND date = ?', [$emp[0]->id, '2026-06-12']);
echo json_encode($rosters, JSON_PRETTY_PRINT);

echo "\n\nLeave Requests:\n";
$leaves = DB::select('SELECT * FROM leave_requests WHERE employee_id = ? AND start_date <= ? AND end_date >= ?', [$emp[0]->id, '2026-06-12', '2026-06-12']);
echo json_encode($leaves, JSON_PRETTY_PRINT);
