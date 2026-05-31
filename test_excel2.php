<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$data = Maatwebsite\Excel\Facades\Excel::toArray(new App\Modules\Employee\Imports\EmployeeContractImport, 'hris-system/sample_kontrak.xlsx');
print_r(array_keys($data[0][0]));
