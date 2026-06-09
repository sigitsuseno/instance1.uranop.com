<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;

class TestImport2 implements ToCollection
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            if ($index >= 173 && $index <= 176) {
                echo "Row ".($index+2).": count=".count($row)." val3=".($row[3]??'null')."\n";
                print_r($row->toArray());
            }
        }
    }
}

Excel::import(new TestImport2, 'H:/laragon/www/instance1.uranop.com/new_kontrak.xlsx');
