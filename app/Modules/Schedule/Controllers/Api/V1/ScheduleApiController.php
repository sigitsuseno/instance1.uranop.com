<?php

namespace App\Modules\Schedule\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Schedule\Models\Shift;
use App\Modules\Schedule\Models\WorkPattern;
use App\Modules\Schedule\Resources\ScheduleResource;
use Illuminate\Http\Request;

class ScheduleApiController extends Controller
{
    /**
     * Get list of work patterns
     */
    public function getWorkPatterns(Request $request)
    {
        $patterns = WorkPattern::with(['details', 'workPatternType'])->get();
        return ScheduleResource::collection($patterns);
    }

    public function storeWorkPattern(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:sch_work_patterns,code',
            'name' => 'required|string',
            'employee_type' => 'required|string', // this stores the code of the type
            'work_day' => 'required|integer|min:1|max:7',
            'sat_type' => 'required|string|in:off,half,full',
            'cut_off_date' => 'required|integer|min:1|max:31',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['uuid'] = (string) \Illuminate\Support\Str::uuid();
        $validated['created_by'] = auth()->id() ?? 1;

        $pattern = WorkPattern::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pola kerja berhasil ditambahkan',
            'data' => new ScheduleResource($pattern->load(['details', 'workPatternType']))
        ]);
    }

    public function updateWorkPattern(Request $request, $id)
    {
        $pattern = WorkPattern::findOrFail($id);
        
        $validated = $request->validate([
            'code' => 'required|string|unique:sch_work_patterns,code,'.$id,
            'name' => 'required|string',
            'employee_type' => 'required|string',
            'work_day' => 'required|integer|min:1|max:7',
            'sat_type' => 'required|string|in:off,half,full',
            'cut_off_date' => 'required|integer|min:1|max:31',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['updated_by'] = auth()->id() ?? 1;

        $pattern->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pola kerja berhasil diperbarui',
            'data' => new ScheduleResource($pattern->load(['details', 'workPatternType']))
        ]);
    }

    public function destroyWorkPattern($id)
    {
        $pattern = WorkPattern::findOrFail($id);
        $pattern->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pola kerja berhasil dihapus'
        ]);
    }

    /**
     * Store details/cycle for a work pattern
     */
    public function storeWorkPatternDetails(Request $request, $id)
    {
        $pattern = WorkPattern::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string',
            'details' => 'required|array',
            'details.*.day_number' => 'required|integer',
            'details.*.day_name' => 'nullable|string',
            'details.*.day_type' => 'required|string',
            'details.*.shift_id' => 'nullable|integer|exists:sch_shifts,id',
        ]);

        $groupName = $validated['name'];
        
        // Delete existing details with the same group name
        \App\Modules\Schedule\Models\WorkPatternDetail::where('work_pattern_id', $pattern->id)
            ->where('name', $groupName)
            ->delete();

        $cycleDayCount = count($validated['details']);

        // Insert new details
        foreach ($validated['details'] as $detail) {
            \App\Modules\Schedule\Models\WorkPatternDetail::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'work_pattern_id' => $pattern->id,
                'name' => $groupName,
                'cycle_day' => $cycleDayCount,
                'day_number' => $detail['day_number'],
                'day_type' => $detail['day_type'],
                'shift_id' => $detail['shift_id'] ?? null,
                'is_workday' => $detail['day_type'] === 'work_day',
                'is_half_day' => $detail['day_type'] === 'half_day',
                'date_label' => $detail['day_name'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Siklus pola kerja berhasil disimpan',
            'data' => new ScheduleResource($pattern->load(['details', 'workPatternType']))
        ]);
    }

    /**
     * Delete a group of work pattern details by name
     */
    public function destroyWorkPatternDetailsGroup(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $pattern = WorkPattern::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        \App\Modules\Schedule\Models\WorkPatternDetail::where('work_pattern_id', $pattern->id)
            ->where('name', $request->name)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Grup siklus '{$request->name}' berhasil dihapus",
            'data' => new ScheduleResource($pattern->load(['details', 'workPatternType']))
        ]);
    }

    /**
     * Get list of shifts
     */
    public function getShifts(Request $request)
    {
        $shifts = Shift::get();
        return ScheduleResource::collection($shifts);
    }
    
    /**
     * Store a newly created shift
     */
    public function storeShift(Request $request)
    {
        $validated = $request->validate([
            'work_pattern_id' => 'nullable|integer|exists:sch_work_patterns,id',
            'code' => 'required|string|unique:sch_shifts,code',
            'name' => 'required|string',
            'external_code' => 'nullable|string',
            'work_hour_start' => 'required',
            'work_hour_end' => 'required',
            'check_in_start' => 'nullable',
            'check_in_end' => 'nullable',
            'check_out_start' => 'nullable',
            'check_out_end' => 'nullable',
            'is_overnight' => 'boolean',
            'check_out_overnight_start' => 'nullable',
            'check_out_overnight_end' => 'nullable',
            'tolerance_minutes' => 'integer',
            'min_work_hours' => 'integer',
            'has_overtime' => 'boolean',
            'overtime_multiplier' => 'nullable|numeric',
            'is_dayoff' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        $validated['uuid'] = (string) \Illuminate\Support\Str::uuid();
        $validated['created_by'] = auth()->id() ?? 1;

        $shift = Shift::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil ditambahkan',
            'data' => new ScheduleResource($shift)
        ]);
    }

    /**
     * Update the specified shift
     */
    public function updateShift(Request $request, $id)
    {
        $shift = Shift::findOrFail($id);

        $validated = $request->validate([
            'work_pattern_id' => 'nullable|integer|exists:sch_work_patterns,id',
            'code' => 'required|string|unique:sch_shifts,code,'.$id,
            'name' => 'required|string',
            'external_code' => 'nullable|string',
            'work_hour_start' => 'required',
            'work_hour_end' => 'required',
            'check_in_start' => 'nullable',
            'check_in_end' => 'nullable',
            'check_out_start' => 'nullable',
            'check_out_end' => 'nullable',
            'is_overnight' => 'boolean',
            'check_out_overnight_start' => 'nullable',
            'check_out_overnight_end' => 'nullable',
            'tolerance_minutes' => 'integer',
            'min_work_hours' => 'integer',
            'has_overtime' => 'boolean',
            'overtime_multiplier' => 'nullable|numeric',
            'is_dayoff' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        $validated['updated_by'] = auth()->id() ?? 1;

        $shift->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil diperbarui',
            'data' => new ScheduleResource($shift)
        ]);
    }

    /**
     * Remove the specified shift
     */
    public function destroyShift($id)
    {
        $shift = Shift::findOrFail($id);
        $shift->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil dihapus'
        ]);
    }
    /**
     * Get list of working calendars
     */
    public function getCalendars(Request $request)
    {
        $calendars = \App\Modules\Schedule\Models\WorkingCalendar::with('holidays')->get();
        return response()->json(['data' => $calendars]);
    }

    /**
     * Get roster data for specific period
     */
    public function getRoster(Request $request)
    {
        $year = $request->year ?? date('Y');
        $month = $request->month ?? date('m');

        $start = \Carbon\Carbon::create($year, $month, 25)->subMonth();
        $end = \Carbon\Carbon::create($year, $month, 24);

        $employees = \App\Modules\Employee\Models\Employee::with(['department'])->activeInPeriod($start, $end)->get();
        $rosters = \App\Modules\Schedule\Models\EmployeeShiftRoster::with('shift')
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get()
            ->groupBy('employee_id');

        $result = $employees->map(function ($emp) use ($rosters, $start, $end) {
            $empRosters = $rosters->get($emp->id, collect())->keyBy(function($r) {
                return $r->date->format('Y-m-d');
            });
            
            $schedule = [];
            $current = $start->copy();
            
            while ($current <= $end) {
                $dateStr = $current->format('Y-m-d');
                $dow = $current->dayOfWeek;
                
                if ($empRosters->has($dateStr)) {
                    $r = $empRosters[$dateStr];
                    $schedule[] = [
                        'date' => $dateStr,
                        'code' => $r->shift ? $r->shift->code : ($r->is_holiday ? 'L' : 'NS'),
                        'name' => $r->shift ? $r->shift->name : ($r->is_holiday ? 'Libur' : 'Non Shift'),
                        'external_code' => $r->external_code ?? ($r->is_holiday ? 'L' : '-'),
                        'is_off' => $r->is_holiday || $r->shift?->is_dayoff,
                        'shift_id' => $r->shift_id
                    ];
                } else {
                    $schedule[] = null;
                }
                $current->addDay();
            }

            return [
                'id' => $emp->id,
                'name' => $emp->name,
                'nik' => $emp->employee_code,
                'department' => $emp->department ? $emp->department->name : '-',
                'department_id' => $emp->department_id,
                'schedule' => $schedule
            ];
        });

        return response()->json(['data' => $result]);
    }

    public function importRoster(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
            'month' => 'required',
            'year' => 'required',
        ]);

        try {
            $import = new \App\Modules\Schedule\Imports\ShiftRosterMatrixImport($request->month, $request->year);
            \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => "Berhasil mengimpor {$import->getInserted()} data baru, {$import->getUpdated()} diupdate.",
                'errors' => $import->getErrors()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal import: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate Roster
     */
    public function generateRoster(Request $request)
    {
        $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
            'work_pattern_id' => 'required|exists:sch_work_patterns,id',
            'detail_group_name' => 'required|string',
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'integer|exists:employees,id',
        ]);

        $year = $request->year;
        $month = $request->month;
        $pattern = WorkPattern::findOrFail($request->work_pattern_id);
        $employees = \App\Modules\Employee\Models\Employee::whereIn('id', $request->employee_ids)->get();

        // Assuming cut off date is fixed 25 to 24
        $start = \Carbon\Carbon::create($year, $month, 25)->subMonth();
        $end = \Carbon\Carbon::create($year, $month, 24);

        $cycleDetails = \App\Modules\Schedule\Models\WorkPatternDetail::where('work_pattern_id', $pattern->id)
            ->where('name', $request->detail_group_name)
            ->orderBy('day_number')
            ->get();

        if ($cycleDetails->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Grup siklus tidak ditemukan pada pola kerja ini.'
            ], 404);
        }

        $cycleLength = $cycleDetails->count();
        $insertedCount = 0;

        foreach ($employees as $employee) {
            $current = $start->copy();
            $index = 0;
            
            while ($current <= $end) {
                $cycleConfig = $cycleDetails[$index % $cycleLength];
                
                $isOff = $cycleConfig->day_type === 'day_off' || $cycleConfig->day_type === 'is_sun';
                
                \App\Modules\Schedule\Models\EmployeeShiftRoster::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'date' => $current->format('Y-m-d'),
                    ],
                    [
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'work_pattern_id' => $pattern->id,
                        'shift_id' => $isOff ? null : $cycleConfig->shift_id,
                        'is_holiday' => $cycleConfig->day_type === 'day_off',
                        'is_sat' => $current->dayOfWeek === 6,
                        'is_sun' => $current->dayOfWeek === 0,
                        'is_half_day' => $cycleConfig->day_type === 'half_day',
                        'created_by' => auth()->id() ?? 1,
                    ]
                );

                $current->addDay();
                $index++;
                $insertedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil men-generate roster untuk {$employees->count()} karyawan ({$insertedCount} jadwal diperbarui)."
        ]);
    }

    /**
     * Override/update a single cell roster
     */
    public function overrideRoster(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'shift_id' => 'nullable|exists:sch_shifts,id',
            'is_off' => 'boolean'
        ]);

        $carbonDate = \Carbon\Carbon::parse($request->date);

        $shift = null;
        if ($request->shift_id) {
            $shift = \App\Modules\Schedule\Models\Shift::find($request->shift_id);
        }

        \App\Modules\Schedule\Models\EmployeeShiftRoster::updateOrCreate(
            [
                'employee_id' => $request->employee_id,
                'date' => $request->date,
            ],
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'shift_id' => $shift ? $shift->id : null,
                'shift_code' => $shift ? $shift->code : ($request->is_off ? 'L' : null),
                'is_holiday' => $request->boolean('is_off'),
                'is_sat' => $carbonDate->dayOfWeek === 6,
                'is_sun' => $carbonDate->dayOfWeek === 0,
                'status' => $request->is_off ? 'holiday' : 'scheduled',
                'source' => 'manual',
                'created_by' => auth()->id() ?? 1,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Jadwal berhasil diupdate'
        ]);
    }

    /**
     * Store a newly created holiday in storage.
     */
    public function storeHoliday(Request $request, $id)
    {
        $request->validate([
            'date' => 'required|date',
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'description' => 'nullable|string'
        ]);

        $calendar = \App\Modules\Schedule\Models\WorkingCalendar::findOrFail($id);

        $holiday = $calendar->holidays()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'date' => $request->date,
            'name' => $request->name,
            'type' => $request->type,
            'description' => $request->description,
            'is_national_holiday' => $request->type === 'Nasional',
            'is_company_holiday' => $request->type === 'Perusahaan' || $request->type === 'Cuti Bersama',
        ]);

        return response()->json([
            'success' => true,
            'data' => $holiday,
            'message' => 'Hari libur berhasil ditambahkan.'
        ]);
    }

    /**
     * Update the specified holiday in storage.
     */
    public function updateHoliday(Request $request, $id, $holiday_id)
    {
        $request->validate([
            'date' => 'required|date',
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'description' => 'nullable|string'
        ]);

        $calendar = \App\Modules\Schedule\Models\WorkingCalendar::findOrFail($id);
        $holiday = $calendar->holidays()->findOrFail($holiday_id);

        $holiday->update([
            'date' => $request->date,
            'name' => $request->name,
            'type' => $request->type,
            'description' => $request->description,
            'is_national_holiday' => $request->type === 'Nasional',
            'is_company_holiday' => $request->type === 'Perusahaan' || $request->type === 'Cuti Bersama',
        ]);

        return response()->json([
            'success' => true,
            'data' => $holiday,
            'message' => 'Hari libur berhasil diubah.'
        ]);
    }

    /**
     * Remove the specified holiday from storage.
     */
    public function destroyHoliday($id, $holiday_id)
    {
        $calendar = \App\Modules\Schedule\Models\WorkingCalendar::findOrFail($id);
        $holiday = $calendar->holidays()->findOrFail($holiday_id);
        
        $holiday->delete();

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil dihapus.'
        ]);
    }
}
