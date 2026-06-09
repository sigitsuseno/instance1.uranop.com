<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$data = \Maatwebsite\Excel\Facades\Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\ToArray {
    public function array(array $array) {}
}, 'H:/laragon/www/instance1.uranop.com/new_kontrak.xlsx');

print_r(array_slice($data[0], 0, 2));
