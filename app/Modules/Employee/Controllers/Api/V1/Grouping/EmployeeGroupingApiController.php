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

        $employees = Employee::select('id', 'name', 'employee_code', 'nik', 'photo', 'employment_status', 'join_date', 'end_date', 'is_active')
            ->orderBy('name')
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
}
