<?php
require 'vendor/autoload.php';
$inputFileName = 'H:/laragon/www/instance1.uranop.com/hris-system/SE_Februari.xlsx';
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
$worksheet = $spreadsheet->getActiveSheet();
$rows = $worksheet->toArray();
print_r($rows[0]);
