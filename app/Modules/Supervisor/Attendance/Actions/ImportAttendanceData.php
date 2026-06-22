<?php

namespace App\Modules\Supervisor\Attendance\Actions;

use App\Modules\Supervisor\Attendance\Models\Attendance;
use App\Modules\Supervisor\Attendance\Services\FingerprintBinParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ImportAttendanceData
{
    protected FingerprintBinParser $parser;

    public function __construct(FingerprintBinParser $parser)
    {
        $this->parser = $parser;
    }

    public function import(UploadedFile $file)
    {
        $path = $file->getRealPath();
        $parsedLogs = $this->parser->parse($path);

        if (empty($parsedLogs)) {
            throw new \Exception('No valid records found in the file.');
        }

        $companyId = session('company_id');
        $branchId = session('branch_id');
        $userId = Auth::id();
        $batchId = 'BIN-'.date('Ymd-His');

        $inserted = 0;
        $skipped = 0;

        DB::beginTransaction();

        foreach ($parsedLogs as $log) {
            $exists = Attendance::where('employee_code', $log['employee_code'])
                ->where('scan_datetime', $log['scan_datetime'])
                ->where('branch_id', $branchId)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            Attendance::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'employee_code' => $log['employee_code'],
                'scan_datetime' => $log['scan_datetime'],
                'scan_type' => $log['scan_type'],
                'verify_type' => $log['verify_type'],
                'pin' => $log['pin'],
                'machine_sn' => 'IMPORTED',
                'machine_name' => $file->getClientOriginalName(),
                'import_batch' => $batchId,
                'source_file' => $file->getClientOriginalName(),
                'raw_data' => json_encode([
                    'mode_raw' => $log['mode_raw'],
                    'verify_raw' => $log['verify_raw'],
                    'date_value' => $log['date_value'],
                ]),
                'created_by' => $userId,
            ]);

            $inserted++;
        }

        DB::commit();

        return [
            'inserted' => $inserted,
            'skipped' => $skipped,
        ];
    }
}
