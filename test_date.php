<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Shared\Date;

try {
    $val = "44371";
    $dt = Date::excelToDateTimeObject($val);
    print_r($dt);
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
