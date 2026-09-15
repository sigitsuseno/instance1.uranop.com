<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Employee\Models\Employee;

$names = ['DIAN ANDRIANI', 'DIAH SAFIRA', 'WISMA EDHUM KINASIH'];

foreach ($names as $name) {
    $employees = Employee::with(['department', 'position', 'contracts' => fn ($q) => $q->orderByDesc('start_date')])
        ->where('name', 'like', $name)
        ->get();

    echo "=== {$name} ===\n";

    if ($employees->isEmpty()) {
        echo "  (tidak ditemukan)\n\n";
        continue;
    }

    foreach ($employees as $e) {
        echo '  id=', $e->id,
            ' | nik=', $e->nik,
            ' | gender=', $e->gender,
            ' | alamat=', $e->address,
            ' | jabatan=', $e->position?->name ?? '-',
            ' | dept=', $e->department?->name ?? '-',
            ' | upah=', $e->gaji_pokok(),
            ' | aktif=', $e->is_active ? 'ya' : 'tidak',
            "\n";

        foreach ($e->contracts as $c) {
            echo '      kontrak: ', $c->contract_number,
                ' | tipe=', $c->contract_type,
                ' | ', $c->start_date?->format('d/m/Y'), ' - ', $c->end_date?->format('d/m/Y'),
                ' | durasi=', $c->duration_months,
                ' | latest=', $c->is_latest ? 'ya' : 'tidak',
                ' | status=', $c->status,
                "\n";
        }
    }

    echo "\n";
}
