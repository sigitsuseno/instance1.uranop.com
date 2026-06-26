<?php

namespace App\Modules\Employee\Controllers\Api\V1\Grouping;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Settings\Models\EmployeeGroup;
use App\Modules\Settings\Models\EmployeeGroupMaster;
use App\Modules\Settings\Models\EmployeeGroupSetting;
use App\Modules\Payroll\Models\PayPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeGroupingApiController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->query('year', date('Y'));
        $month = $request->query('month', date('n'));

        // Start date adalah tanggal 25 bulan sebelumnya, End date adalah tanggal 24 bulan saat ini
        $periodDate = \Carbon\Carbon::createFromDate($year, $month, 1);
        $startDate = $periodDate->copy()->subMonth()->format('Y-m-25');
        $endDate = $periodDate->copy()->format('Y-m-24');

        $employees = Employee::select('id', 'name', 'employee_code', 'nik', 'photo', 'no_urut', 'employment_status', 'join_date', 'end_date', 'is_active')
            ->orderByRaw('no_urut IS NULL, no_urut ASC')
            ->orderBy('nip')
            ->get();

        $employees->transform(function ($emp) use ($startDate, $endDate) {
            $join = $emp->join_date;
            $end = $emp->end_date;
            
            $isActiveInPeriod = true;
            if ($join && $join > $endDate) {
                $isActiveInPeriod = false;
            }
            if ($end && $end < $startDate) {
                $isActiveInPeriod = false;
            }
            
            $emp->is_active_in_period = $isActiveInPeriod;
            return $emp;
        });

        // 1. Get Settings
        $tabSettings = EmployeeGroupSetting::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
            
        $dynamicGroupLabels = $tabSettings->pluck('group_label')->filter()->toArray();

        // 2. Get Employee Groups
        $groupsMaster = EmployeeGroupMaster::whereIn('group_label', $dynamicGroupLabels)
            ->orWhere('code', 'like', 'AUTH-%') // fallback
            ->get();
            
        // 3. Attach dynamic groups to employees
        $masterCodes = $groupsMaster->pluck('code')->toArray();
        $employeeGroupsData = EmployeeGroup::whereIn('reference_code', $masterCodes)
            ->get()
            ->groupBy('employee_id');

        $employees->transform(function ($emp) use ($employeeGroupsData, $groupsMaster, $tabSettings) {
            $dynamicGroups = [];
            $empAuths = $employeeGroupsData->get($emp->id, collect());
            
            foreach ($tabSettings as $setting) {
                if (!$setting->group_label) continue;
                
                $validMasterCodes = $groupsMaster->where('group_label', $setting->group_label)->pluck('code')->toArray();
                $auth = $empAuths->whereIn('reference_code', $validMasterCodes)->first();
                $authGroup = $auth ? $groupsMaster->where('code', $auth->reference_code)->first() : null;
                
                $dynamicGroups[$setting->tab_id] = $authGroup ? $authGroup->id : null;
            }
            
            $emp->dynamic_groups = $dynamicGroups;

            return $emp;
        });

        // 4. Get Enrollments (Siklus Payroll)
        $periodCode = "PAY-{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT);
        $payPeriod = PayPeriod::where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        $enrolledIds = EmployeeGroup::where('reference_code', $periodCode)
            ->pluck('employee_id')
            ->toArray();
            
        $enrolledData = [];
        foreach ($enrolledIds as $id) {
            $enrolledData[$id] = [
                'payroll_type' => 'monthly',
                'emp_group' => 'Terdaftar'
            ];
        }

        return response()->json([
            'employees' => $employees,
            'tabSettings' => $tabSettings,
            'groupsMaster' => $groupsMaster,
            'enrolledData' => (object)$enrolledData,
            'filters' => [
                'year' => (int)$year,
                'month' => (int)$month
            ],
            'pay_period' => $payPeriod,
            'periodCode' => $periodCode
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $tab = $request->input('tab');
        
        DB::beginTransaction();
        try {
            $tabSetting = EmployeeGroupSetting::where('tab_id', $tab)->first();
            
            if ($tabSetting && $tabSetting->group_label) {
                $changes = $request->input('changes', []);
                $allGroups = EmployeeGroupMaster::all();
                
                $validCodes = EmployeeGroupMaster::where('group_label', $tabSetting->group_label)
                    ->pluck('code')->toArray();
                    
                foreach ($changes as $change) {
                    $empId = $change['employee_id'];
                    $groupId = $change['group_id'];
                    
                    EmployeeGroup::where('employee_id', $empId)
                        ->whereIn('reference_code', $validCodes)
                        ->delete();
                        
                    if ($groupId) {
                        $groupMaster = $allGroups->where('id', $groupId)->first();
                        if ($groupMaster) {
                            EmployeeGroup::create([
                                'employee_id' => $empId,
                                'reference_code' => $groupMaster->code
                            ]);
                        }
                    }
                }
            }
            elseif ($tab === 'payroll_cycle') {
                $year = $request->input('year');
                $month = $request->input('month');
                $periodCode = "PAY-{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT);
                
                $enrollments = $request->input('enrollments', []);
                $disenrollments = $request->input('disenrollments', []);
                
                if (!empty($disenrollments)) {
                    EmployeeGroup::whereIn('employee_id', $disenrollments)
                        ->where('reference_code', $periodCode)
                        ->delete();
                }
                
                foreach ($enrollments as $enrollment) {
                    EmployeeGroup::updateOrCreate([
                        'employee_id' => $enrollment['employee_id'],
                        'reference_code' => $periodCode
                    ]);
                }
            }
            elseif ($tab === 'employment_type') {
                $changes = $request->input('changes', []);
                foreach ($changes as $change) {
                    Employee::where('id', $change['employee_id'])
                        ->update(['employment_status' => $change['employment_status']]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Berhasil menyimpan perubahan']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan perubahan: ' . $e->getMessage()], 500);
        }
    }

    public function autoEnroll(Request $request)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $periodCode = "PAY-{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT);
        
        $startDate = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $endDate = \Carbon\Carbon::parse($startDate)->endOfMonth()->format('Y-m-d');
        
        $activeEmployees = Employee::activeInPeriod($startDate, $endDate)->pluck('id');
        
        $enrolledCount = 0;
        foreach ($activeEmployees as $empId) {
            $created = EmployeeGroup::firstOrCreate([
                'employee_id' => $empId,
                'reference_code' => $periodCode
            ]);
            if ($created->wasRecentlyCreated) {
                $enrolledCount++;
            }
        }
        
        return response()->json([
            'message' => "Berhasil auto-enroll {$enrolledCount} karyawan ke periode {$periodCode}."
        ]);
    }

    public function export(Request $request)
    {
        $tab = $request->query('tab');
        $year = $request->query('year', date('Y'));
        $month = $request->query('month', date('n'));
        
        $employees = Employee::select('id', 'name', 'nip', 'employment_status')->orderBy('name')->get();
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Header (A: NIP, B: Kode Grup, C: Nama Karyawan, D: Nama Grup, E: Tab)
        $sheet->setCellValue('A1', 'NIP');
        $sheet->setCellValue('B1', 'Kode Grup');
        $sheet->setCellValue('C1', 'Nama Karyawan');
        $sheet->setCellValue('D1', 'Nama Grup');
        $sheet->setCellValue('E1', 'Kategori Tab');
        
        // Styling header
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->getStyle('A1:E1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
        
        $row = 2;
        
        if ($tab === 'employment_type') {
            $tabName = 'Tipe Karyawan';
            foreach ($employees as $emp) {
                $code = $emp->employment_status;
                $groupName = $code ? ucfirst($code) : '';
                
                $sheet->setCellValueExplicit('A'.$row, $emp->nip, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('B'.$row, $code);
                $sheet->setCellValue('C'.$row, $emp->name);
                $sheet->setCellValue('D'.$row, $groupName);
                $sheet->setCellValue('E'.$row, $tabName);
                $row++;
            }
        } elseif ($tab === 'payroll_cycle') {
            $tabName = 'Siklus Payroll';
            $periodCode = "PAY-{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT);
            $enrolledIds = EmployeeGroup::where('reference_code', $periodCode)->pluck('employee_id')->toArray();
            
            foreach ($employees as $emp) {
                $code = in_array($emp->id, $enrolledIds) ? $periodCode : '';
                $groupName = $code ? "Terdaftar Bulanan" : '';
                
                $sheet->setCellValueExplicit('A'.$row, $emp->nip, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('B'.$row, $code);
                $sheet->setCellValue('C'.$row, $emp->name);
                $sheet->setCellValue('D'.$row, $groupName);
                $sheet->setCellValue('E'.$row, $tabName);
                $row++;
            }
        } else {
            // Dynamic Tabs
            $tabSetting = EmployeeGroupSetting::where('tab_id', $tab)->first();
            $tabName = $tabSetting ? $tabSetting->tab_name : 'Grouping';
            $groupLabel = $tabSetting ? $tabSetting->group_label : null;
            
            $masterGroups = [];
            $masterCodes = [];
            if ($groupLabel) {
                $masterData = EmployeeGroupMaster::where('group_label', $groupLabel)->get();
                $masterGroups = $masterData->keyBy('code')->toArray();
                $masterCodes = $masterData->pluck('code')->toArray();
            }
            
            $employeeGroupsData = EmployeeGroup::whereIn('reference_code', $masterCodes)
                ->get()
                ->keyBy('employee_id');
                
            foreach ($employees as $emp) {
                $empGroup = $employeeGroupsData->get($emp->id);
                $code = $empGroup ? $empGroup->reference_code : '';
                $groupName = $code && isset($masterGroups[$code]) ? $masterGroups[$code]['name'] : '';
                
                $sheet->setCellValueExplicit('A'.$row, $emp->nip, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('B'.$row, $code);
                $sheet->setCellValue('C'.$row, $emp->name);
                $sheet->setCellValue('D'.$row, $groupName);
                $sheet->setCellValue('E'.$row, $tabName);
                $row++;
            }
        }
        
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $fileName = 'Export_Grouping_' . ($tab ?? 'All') . '_' . date('YmdHis') . '.xlsx';
        
        $response = new StreamedResponse(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });
        
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="'.$fileName.'"');
        $response->headers->set('Cache-Control', 'max-age=0');
        
        return $response;
    }

    public function previewImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,xls',
        ]);

        $file = $request->file('file');
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Asumsi header baris pertama
        array_shift($rows);
        
        $previewData = [];
        $validCodes = EmployeeGroupMaster::pluck('name', 'code')->toArray();
        $employees = Employee::pluck('name', 'nip')->toArray();
        $employeeIds = Employee::pluck('id', 'nip')->toArray();

        foreach ($rows as $index => $row) {
            $nip = trim($row[0] ?? '');
            $groupCode = trim($row[1] ?? '');

            if (empty($nip) && empty($groupCode)) {
                continue;
            }

            $isValid = true;
            $errorMsg = [];
            
            $empName = $employees[$nip] ?? null;
            $empId = $employeeIds[$nip] ?? null;
            if (!$empName) {
                $isValid = false;
                $errorMsg[] = "NIP tidak ditemukan";
            }

            $groupName = null;
            if (in_array(strtolower($groupCode), ['permanent', 'contract', 'freelance'])) {
                $groupCode = strtolower($groupCode);
                $groupName = ucfirst($groupCode);
            } elseif (preg_match('/^PAY-\d{4}-\d{2}$/i', $groupCode)) {
                $groupCode = strtoupper($groupCode);
                $groupName = 'Siklus Payroll ' . $groupCode;
            } elseif (isset($validCodes[$groupCode])) {
                $groupName = $validCodes[$groupCode];
            } else {
                $isValid = false;
                $errorMsg[] = "Kode Grup tidak valid";
            }

            $previewData[] = [
                'row' => $index + 2,
                'nip' => $nip,
                'employee_name' => $empName,
                'employee_id' => $empId,
                'group_code' => $groupCode,
                'group_name' => $groupName,
                'is_valid' => $isValid,
                'error' => implode(', ', $errorMsg),
            ];
        }

        return response()->json([
            'data' => $previewData,
        ]);
    }

    public function processImport(Request $request)
    {
        $items = $request->input('items', []);
        
        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                if (!$item['is_valid'] || empty($item['employee_id'])) continue;

                $empId = $item['employee_id'];
                $code = $item['group_code'];
                
                if (in_array($code, ['permanent', 'contract', 'freelance'])) {
                    Employee::where('id', $empId)->update(['employment_status' => $code]);
                } else {
                    $master = EmployeeGroupMaster::where('code', $code)->first();
                    if ($master) {
                        $labelCodes = EmployeeGroupMaster::where('group_label', $master->group_label)->pluck('code')->toArray();
                        EmployeeGroup::where('employee_id', $empId)
                            ->whereIn('reference_code', $labelCodes)
                            ->where('reference_code', '!=', $code)
                            ->delete();
                    }
                    
                    EmployeeGroup::updateOrCreate([
                        'employee_id' => $empId,
                        'reference_code' => $code
                    ]);
                }
            }
            DB::commit();
            return response()->json(['message' => 'Import berhasil disimpan']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal import: ' . $e->getMessage()], 500);
        }
    }
}
