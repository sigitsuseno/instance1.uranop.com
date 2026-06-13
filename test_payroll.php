<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$period = \App\Modules\Payroll\Models\PayPeriod::latest('end_date')->first();
echo "Latest period ID: " . ($period ? $period->id : 'null') . "\n";

$records1 = \App\Modules\Payroll\Models\PayRecord::where('pay_period_id', $period->id ?? null)->count();
echo "Records in latest period: " . $records1 . "\n";

$latestHasRecords = \App\Modules\Payroll\Models\PayRecord::latest('id')->first();
$latestPeriodWithRecords = $latestHasRecords ? $latestHasRecords->pay_period_id : 'null';
echo "Latest period with records: " . $latestPeriodWithRecords . "\n";

$groupCodes = ['GRP-ALLIN'];
$c = \App\Modules\Payroll\Models\PayRecord::whereHas('employee', function ($q) use ($groupCodes) {
    $q->whereHas('groups', function ($gq) use ($groupCodes) {
        $gq->whereIn('reference_code', $groupCodes);
    });
})->where('pay_period_id', $latestPeriodWithRecords)->count();

echo "Records matching ALLIN in period $latestPeriodWithRecords: $c\n";

$groupCodes2 = ['GRP-SS', 'GRP-PS1', 'GRP-GD', 'GRP-SPR'];
$c2 = \App\Modules\Payroll\Models\PayRecord::whereHas('employee', function ($q) use ($groupCodes2) {
    $q->whereHas('groups', function ($gq) use ($groupCodes2) {
        $gq->whereIn('reference_code', $groupCodes2);
    });
})->where('pay_period_id', $latestPeriodWithRecords)->count();

echo "Records matching Print in period $latestPeriodWithRecords: $c2\n";
