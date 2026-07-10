<?php

namespace App\Modules\Supervisor\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Supervisor\Models\SupervisorEmployeeGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupervisorEmployeeGroupController extends Controller
{
    /**
     * GET kanban data: roster employees + group members per pay period.
     */
    public function index(Request $request): JsonResponse
    {
        $year  = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', date('n'));

        // Cari pay period berdasarkan tahun & bulan
        $payPeriod = PayPeriod::where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        if (!$payPeriod) {
            return response()->json([
                'period'      => null,
                'roster_pool' => [],
                'group_pool'  => [],
                'message'     => 'Pay period tidak ditemukan.',
            ]);
        }

        $startDate = $payPeriod->start_date->format('Y-m-d');
        $endDate   = $payPeriod->end_date->format('Y-m-d');

        // ── Karyawan dengan roster di periode ini ──
        $rosterEmployeeIds = EmployeeShiftRoster::whereBetween('date', [$startDate, $endDate])
            ->distinct()
            ->pluck('employee_id');

        $employees = Employee::select('id', 'name', 'employee_code', 'nip', 'photo', 'no_urut', 'department_id', 'position_id', 'employment_status')
            ->whereIn('id', $rosterEmployeeIds)
            ->with(['department:id,name', 'position:id,name'])
            ->orderByRaw('no_urut IS NULL, no_urut ASC')
            ->orderBy('nip')
            ->get();

        // ── Karyawan yang sudah di-assign ke group periode ini ──
        $groupedRecords = SupervisorEmployeeGroup::where('period_start', $startDate)
            ->where('period_end', $endDate)
            ->with('employee:id,name,employee_code,nip,photo')
            ->get();

        $groupedEmployeeIds = $groupedRecords->pluck('employee_id')->unique()->toArray();

        // Roster pool: karyawan roster yang BELUM di-assign
        $rosterPool = $employees
            ->filter(fn($e) => !in_array($e->id, $groupedEmployeeIds))
            ->values();

        // Group pool: karyawan yang SUDAH di-assign
        $groupPool = $groupedRecords->map(function ($r) {
            return [
                'id'              => $r->employee_id,
                'employee_id'     => $r->employee_id,
                'name'            => $r->employee->name ?? '',
                'employee_code'   => $r->employee->employee_code ?? '',
                'nip'             => $r->employee->nip ?? '',
                'photo_url'       => $r->employee->photo_url ?? null,
                'department'      => $r->employee->relationLoaded('department') ? $r->employee->department : null,
                '_group_id'       => $r->id,
            ];
        })->values();

        return response()->json([
            'period'      => [
                'start' => $startDate,
                'end'   => $endDate,
                'name'  => $payPeriod->name,
                'year'  => $year,
                'month' => $month,
            ],
            'roster_pool' => $rosterPool,
            'group_pool'  => $groupPool,
        ]);
    }

    /**
     * POST assign karyawan ke group (single, dari drag & drop).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id'     => 'required|integer|exists:employees,id',
            'group_name'      => 'required|string|max:100',
            'group_code'      => 'required|string|max:50',
            'group_component' => 'nullable|array',
            'period_start'    => 'required|date',
            'period_end'      => 'required|date',
            'notes'           => 'nullable|string|max:500',
        ]);

        // Cek duplikat
        $exists = SupervisorEmployeeGroup::where('employee_id', $data['employee_id'])
            ->where('group_code', $data['group_code'])
            ->where('period_start', $data['period_start'])
            ->where('period_end', $data['period_end'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Karyawan sudah ada di grup ini.'], 409);
        }

        $group = SupervisorEmployeeGroup::create($data);

        $group->load('employee:id,name,employee_code,nip,photo');

        return response()->json([
            'message' => 'Karyawan berhasil ditambahkan ke grup.',
            'data'    => [
                'id'              => $group->id,
                'uuid'            => $group->uuid,
                'employee_id'     => $group->employee_id,
                'employee'        => $group->employee ? [
                    'id'            => $group->employee->id,
                    'name'          => $group->employee->name,
                    'employee_code' => $group->employee->employee_code,
                    'nip'           => $group->employee->nip,
                    'photo'         => $group->employee->photo_url ?? null,
                ] : null,
                'group_name'      => $group->group_name,
                'group_code'      => $group->group_code,
                'group_component' => $group->group_component,
                'notes'           => $group->notes,
            ],
        ], 201);
    }

    /**
     * DELETE hapus karyawan dari group.
     */
    public function destroy($id): JsonResponse
    {
        $group = SupervisorEmployeeGroup::findOrFail($id);
        $group->delete();

        return response()->json(['message' => 'Karyawan berhasil dihapus dari grup.']);
    }

    /**
     * POST bulk-update dari kanban (drag & drop).
     * Menerima array assignments (ke group) dan removals (keluar group).
     * group_code & group_name pakai nama pay period.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date',
            'group_name'   => 'required|string|max:100',
            'group_code'   => 'required|string|max:50',
            'assignments'  => 'nullable|array',
            'assignments.*.employee_id' => 'required|integer|exists:employees,id',
            'removals'     => 'nullable|array',
            'removals.*'   => 'integer|exists:supervisor_employee_groups,id',
        ]);

        DB::beginTransaction();
        try {
            // Process removals (drag dari group kembali ke roster)
            if (!empty($data['removals'])) {
                SupervisorEmployeeGroup::whereIn('id', $data['removals'])->delete();
            }

            // Process assignments (drag dari roster ke group)
            if (!empty($data['assignments'])) {
                foreach ($data['assignments'] as $assignment) {
                    SupervisorEmployeeGroup::updateOrCreate(
                        [
                            'employee_id'  => $assignment['employee_id'],
                            'period_start' => $data['period_start'],
                            'period_end'   => $data['period_end'],
                        ],
                        [
                            'group_name' => $data['group_name'],
                            'group_code' => $data['group_code'],
                        ]
                    );
                }
            }

            DB::commit();

            return response()->json(['message' => 'Perubahan berhasil disimpan.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET daftar group yang tersedia (untuk dropdown / modal).
     */
    public function groupOptions(Request $request): JsonResponse
    {
        $periodStart = $request->query('period_start');
        $periodEnd   = $request->query('period_end');

        $query = SupervisorEmployeeGroup::select('group_name', 'group_code')
            ->distinct()
            ->orderBy('group_name');

        if ($periodStart && $periodEnd) {
            $query->where('period_start', $periodStart)
                  ->where('period_end', $periodEnd);
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * POST preview import Excel: terima file, return data preview.
     */
    public function previewImport(Request $request): JsonResponse
    {
        $request->validate([
            'file'    => 'required|file|mimes:xlsx,csv,xls',
            'year'    => 'required|integer',
            'month'   => 'required|integer',
        ]);

        $year  = (int) $request->input('year');
        $month = (int) $request->input('month');

        $payPeriod = PayPeriod::where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        if (!$payPeriod) {
            return response()->json(['message' => 'Pay period tidak ditemukan.'], 404);
        }

        $file = $request->file('file');
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        array_shift($rows); // skip header

        $employees = Employee::pluck('name', 'nip')->toArray();
        $employeeIds = Employee::pluck('id', 'nip')->toArray();

        $previewData = [];
        foreach ($rows as $index => $row) {
            $nip = trim($row[0] ?? '');

            if (empty($nip)) continue;

            $isValid = true;
            $errorMsg = [];
            $empName = $employees[$nip] ?? null;
            $empId = $employeeIds[$nip] ?? null;

            if (!$empName) {
                $isValid = false;
                $errorMsg[] = 'NIP tidak ditemukan';
            }

            $previewData[] = [
                'row'           => $index + 2,
                'nip'           => $nip,
                'employee_name' => $empName ?? '-',
                'employee_id'   => $empId,
                'is_valid'      => $isValid,
                'error'         => implode(', ', $errorMsg),
            ];
        }

        return response()->json([
            'data'        => $previewData,
            'period_name' => $payPeriod->name,
            'period'      => [
                'start' => $payPeriod->start_date->format('Y-m-d'),
                'end'   => $payPeriod->end_date->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * POST process import: simpan data valid ke supervisor_employee_groups.
     */
    public function processImport(Request $request): JsonResponse
    {
        $request->validate([
            'items'       => 'required|array',
            'items.*.employee_id' => 'required|integer|exists:employees,id',
            'year'        => 'required|integer',
            'month'       => 'required|integer',
        ]);

        $year  = (int) $request->input('year');
        $month = (int) $request->input('month');

        $payPeriod = PayPeriod::where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        if (!$payPeriod) {
            return response()->json(['message' => 'Pay period tidak ditemukan.'], 404);
        }

        $startDate = $payPeriod->start_date->format('Y-m-d');
        $endDate   = $payPeriod->end_date->format('Y-m-d');
        $items     = $request->input('items', []);

        DB::beginTransaction();
        try {
            $imported = 0;
            foreach ($items as $item) {
                $exists = SupervisorEmployeeGroup::where('employee_id', $item['employee_id'])
                    ->where('period_start', $startDate)
                    ->where('period_end', $endDate)
                    ->exists();

                if (!$exists) {
                    SupervisorEmployeeGroup::create([
                        'employee_id'  => $item['employee_id'],
                        'group_name'   => $payPeriod->name,
                        'group_code'   => $payPeriod->name,
                        'period_start' => $startDate,
                        'period_end'   => $endDate,
                    ]);
                    $imported++;
                }
            }

            DB::commit();

            return response()->json([
                'message' => "{$imported} karyawan berhasil diimport ke periode {$payPeriod->name}.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal import: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET download template Excel untuk import.
     */
    public function downloadTemplate(Request $request): StreamedResponse
    {
        $year  = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', date('n'));

        $payPeriod = PayPeriod::where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        $periodName = $payPeriod ? $payPeriod->name : "{$year}-{$month}";

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'NIP');
        $sheet->setCellValue('B1', 'Nama Karyawan');
        $sheet->setCellValue('C1', 'Kode Grup');
        $sheet->setCellValue('D1', 'Periode');

        // Isi dengan data existing (jika ada)
        if ($payPeriod) {
            $startDate = $payPeriod->start_date->format('Y-m-d');
            $endDate   = $payPeriod->end_date->format('Y-m-d');

            $records = SupervisorEmployeeGroup::where('period_start', $startDate)
                ->where('period_end', $endDate)
                ->with('employee:id,nip,name')
                ->get();

            $row = 2;
            foreach ($records as $r) {
                $sheet->setCellValueExplicit('A' . $row, $r->employee->nip ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('B' . $row, $r->employee->name ?? '');
                $sheet->setCellValue('C' . $row, $payPeriod->name);
                $sheet->setCellValue('D' . $row, "{$startDate} s/d {$endDate}");
                $row++;
            }
        }

        // Styling header
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getStyle('A1:D1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = "Template_KaryawanGroup_{$periodName}.xlsx";

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $fileName . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
