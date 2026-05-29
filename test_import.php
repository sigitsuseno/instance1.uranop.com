<?php
try {
    $file = new \Illuminate\Http\UploadedFile('h:/laragon/www/instance1.uranop.com/hris-system/DATA_KARYAWAN_UPDATE.xlsx', 'DATA_KARYAWAN_UPDATE.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    $import = new \App\Modules\Employee\Submodules\Import\Imports\EmployeeImport();
    \Maatwebsite\Excel\Facades\Excel::import($import, $file);
    echo "Import completed successfully!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
