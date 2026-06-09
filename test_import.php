<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;

class TestImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            if ($index === 1) { // second row, first data row
                print_r($row->toArray());
                echo "Row 3: " . ($row[3] ?? 'null') . "\n";
                break;
            }
        }
    }
}

Excel::import(new TestImport, 'H:/laragon/www/instance1.uranop.com/new_kontrak.xlsx');
