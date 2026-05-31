<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$e = \App\Modules\Employee\Models\Employee::where('nip', '17')->orWhere('nik', '17')->first();
if($e){ 
    echo "Name: ".$e->name."\n";
    echo "Gaji Pokok (Des 2025): ".number_format($e->baseSalary('2025-12'), 2)."\n";
    echo "Premi (Des 2025): ".number_format($e->premi('2025-12'), 2)."\n"; 
    echo "Tunjangan Masa Kerja (Des 2025): ".number_format($e->tunjanganMasaKerja('2025-12'), 2)."\n";
    echo "Tunjangan Tetap (Des 2025): ".number_format($e->tunjangan('2025-12'), 2)."\n";
} else { 
    echo "Employee not found\n"; 
}
