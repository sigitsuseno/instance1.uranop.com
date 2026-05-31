<?php

namespace App\Modules\Employee\Controllers\Api\V1\Grouping;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Settings\Models\EmployeeGroup;
use App\Modules\Settings\Models\EmployeeGroupMaster;
use App\Modules\Payroll\Models\PayPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeGroupingApiController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->query('year', date('Y'));
        $month = $request->query('month', date('n'));

        // 1. Get employees (only active ones for period + those who have groups)
        // To simplify, we get all employees and let the frontend filter.
        $employees = Employee::select('id', 'name', 'employee_code', 'nik', 'photo', 'employment_status', 'join_date', 'end_date', 'is_active')
            ->orderBy('name')
            ->get();

        // 2. Get Employee Groups (Otoritas Manajemen)
        $groups = EmployeeGroupMaster::where('group_label', 'Otoritas Manajemen')
            ->orWhere('code', 'like', 'AUTH-%')
            ->get();
            
        // Fallback: If no groups defined, fetch all masters just in case
        if ($groups->isEmpty()) {
            $groups = EmployeeGroupMaster::all();
        }

        // 3. Get Enrollments (Siklus Payroll)
        $periodCode = "PAY-{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT);
        
        // Also fetch the actual pay period to know if it exists
        $payPeriod = PayPeriod::where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        // Get enrolled employees for this period
        $enrolledIds = EmployeeGroup::where('reference_code', $periodCode)
            ->pluck('employee_id')
            ->toArray();
            
        $enrolledData = [];
        foreach ($enrolledIds as $id) {
            $enrolledData[$id] = [
                'payroll_type' => 'monthly', // default
                'emp_group' => 'Terdaftar'
            ];
        }

        // 4. Attach management authority to employees
        $authorityCodes = $groups->pluck('code')->toArray();
        $employeeAuthorities = EmployeeGroup::whereIn('reference_code', $authorityCodes)
            ->get()
            ->keyBy('employee_id');

        $employees->transform(function ($emp) use ($employeeAuthorities, $groups) {
            $auth = $employeeAuthorities->get($emp->id);
            $authGroup = $auth ? $groups->where('code', $auth->reference_code)->first() : null;
            $emp->employee_group_id = $authGroup ? $authGroup->id : null;
            return $emp;
        });

        return response()->json([
            'employees' => $employees,
            'groups' => $groups,
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
            if ($tab === 'group_es') {
                $changes = $request->input('changes', []);
                $groups = EmployeeGroupMaster::all();
                
                foreach ($changes as $change) {
                    $empId = $change['employee_id'];
                    $groupId = $change['employee_group_id'];
                    
                    // Hapus authority lama
                    $authCodes = $groups->pluck('code')->toArray();
                    EmployeeGroup::where('employee_id', $empId)
                        ->whereIn('reference_code', $authCodes)
                        ->delete();
                        
                    if ($groupId) {
                        $groupMaster = $groups->where('id', $groupId)->first();
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
                
                // Remove disenrollments
                if (!empty($disenrollments)) {
                    EmployeeGroup::whereIn('employee_id', $disenrollments)
                        ->where('reference_code', $periodCode)
                        ->delete();
                }
                
                // Add enrollments
                foreach ($enrollments as $enrollment) {
                    EmployeeGroup::updateOrCreate([
                        'employee_id' => $enrollment['employee_id'],
                        'reference_code' => $periodCode
                    ]);
                }
            }
            elseif ($tab === 'employment_type') {
                // If we also handle employment_type change
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
        
        // Ambil semua karyawan yang aktif di periode tersebut
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
