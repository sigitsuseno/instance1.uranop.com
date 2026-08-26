<?php
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendance as Autolog;

$start='2026-07-25'; $end='2026-08-24';
$logs = Autolog::where('employee_id',270)->whereBetween('date',[$start,$end])->orderBy('date')->get(['date','status']);
echo "=== Tanggal & status emp270 Agustus ===\n";
foreach($logs as $l){
  printf("%s  %-8s\n", $l->date->toDateString(), $l->status);
}
echo "\nDAFTAR HARI absent: ";
echo $logs->where('status','absent')->map(fn($l)=>$l->date->toDateString())->implode(', ')."\n";

// hari kerja kalender sebelum 03-08 (pro-rata window 25 Jul - 2 Agu)
echo "\nHari Senin-Sabtu antara 07/25..08/02:\n";
$c = new DateTime('2026-07-25'); $e = new DateTime('2026-08-02');
$list=[];
while($c<=$e){ if($c->format('w')!==0) $list[]=$c->format('Y-m-d'); $c->modify('+1 day'); }
echo implode(', ', $list)." (".count($list)." hari)\n";