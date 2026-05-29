<?php

namespace App\Modules\Employee\Submodules\Import\Services;

use App\Modules\Employee\Submodules\Import\Imports\EmployeeImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ImportService
{
    /**
     * Handle the Excel file import.
     *
     * @param UploadedFile $file
     * @return array [success: bool, message: string]
     */
    public function importExcel(UploadedFile $file): array
    {
        try {
            Excel::import(new EmployeeImport, $file);
            return [
                'success' => true,
                'message' => 'Berhasil mengimpor data karyawan.',
            ];
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = "Baris {$failure->row()}: " . implode(', ', $failure->errors());
            }
            return [
                'success' => false,
                'message' => 'Terdapat kesalahan validasi pada file Excel.',
                'errors'  => $errors,
            ];
        } catch (\Exception $e) {
            Log::error("Employee Import Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal memproses file Excel: ' . $e->getMessage(),
            ];
        }
    }
}
