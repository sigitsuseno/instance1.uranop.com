<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
$spreadsheet = IOFactory::load('H:/laragon/www/instance1.uranop.com/new_kontrak.xlsx');
$data = $spreadsheet->getActiveSheet()->toArray();
print_r(array_slice($data, 0, 5));
