<?php

namespace App\Modules\Settings\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Schedule\Models\WorkPatternType;
use Illuminate\Support\Str;

class SettingsApiController extends Controller
{
    public function getSystemSettings()
    {
        $settings = SystemSetting::where('group', 'general')->get()->pluck('value', 'key');
        
        // Provide defaults if missing
        $defaults = [
            'timezone' => 'Asia/Jakarta',
            'date_format' => 'DD/MM/YYYY',
            'language' => 'id',
            'currency' => 'IDR'
        ];

        foreach ($defaults as $key => $default) {
            if (!isset($settings[$key])) {
                $settings[$key] = $default;
            }
        }

        return response()->json(['data' => $settings]);
    }

    public function updateSystemSettings(Request $request)
    {
        $validated = $request->validate([
            'timezone' => 'required|string',
            'date_format' => 'required|string',
            'language' => 'required|string',
            'currency' => 'required|string',
        ]);

        foreach ($validated as $key => $value) {
            SystemSetting::updateOrCreate(
                ['key' => $key, 'group' => 'general'],
                [
                    'uuid' => (string) Str::uuid(),
                    'value' => $value,
                    'type' => 'string',
                    'updated_by' => auth()->id() ?? 1, // fallback for testing
                ]
            );
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }

    public function getEmployeeDefaults()
    {
        $settings = SystemSetting::where('group', 'employee')->get()->pluck('value', 'key');
        
        $defaults = [
            'contract_type' => 'permanent',
            'probation_period' => 3,
            'notice_period' => 30
        ];

        foreach ($defaults as $key => $default) {
            if (!isset($settings[$key])) {
                $settings[$key] = $default;
            }
        }

        return response()->json(['data' => $settings]);
    }

    public function updateEmployeeDefaults(Request $request)
    {
        $validated = $request->validate([
            'contract_type' => 'required|string',
            'probation_period' => 'required|numeric',
            'notice_period' => 'required|numeric',
        ]);

        foreach ($validated as $key => $value) {
            SystemSetting::updateOrCreate(
                ['key' => $key, 'group' => 'employee'],
                [
                    'uuid' => (string) Str::uuid(),
                    'value' => $value,
                    'type' => is_numeric($value) ? 'integer' : 'string',
                    'updated_by' => auth()->id() ?? 1,
                ]
            );
        }

        return response()->json(['message' => 'Employee settings updated successfully']);
    }

    public function getPayrollSettings()
    {
        // For payroll settings, we store them as physical columns in a specific row.
        $setting = SystemSetting::where('group', 'payroll')->where('key', 'payroll_config')->first();

        if (!$setting) {
            return response()->json([
                'data' => [
                    'cut_off_date' => null,
                    'working_day_type' => 'fixed',
                    'fixed_working_day' => 21,
                ]
            ]);
        }

        return response()->json([
            'data' => [
                'cut_off_date' => $setting->cut_off_date,
                'working_day_type' => $setting->working_day_type,
                'fixed_working_day' => $setting->fixed_working_day,
            ]
        ]);
    }

    public function updatePayrollSettings(Request $request)
    {
        $validated = $request->validate([
            'cut_off_date' => 'nullable|integer|min:1|max:31',
            'working_day_type' => 'required|string|in:fixed,calendar,flexible',
            'fixed_working_day' => 'nullable|integer|min:1|max:31',
        ]);

        $setting = SystemSetting::firstOrCreate(
            ['group' => 'payroll', 'key' => 'payroll_config'],
            ['uuid' => (string) Str::uuid()]
        );

        $setting->update([
            'cut_off_date' => $validated['cut_off_date'] ?? null,
            'working_day_type' => $validated['working_day_type'],
            'fixed_working_day' => $validated['fixed_working_day'] ?? null,
            'updated_by' => auth()->id() ?? 1,
        ]);

        return response()->json(['message' => 'Payroll settings updated successfully']);
    }

    public function getWorkPatternTypes()
    {
        $types = WorkPatternType::all();
        return response()->json(['data' => $types]);
    }

    public function updateWorkPatternType(Request $request, $id)
    {
        $validated = $request->validate([
            'label' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string|max:255',
        ]);

        $type = WorkPatternType::findOrFail($id);
        $type->update([
            'label' => $validated['label'],
            'keterangan' => $validated['keterangan'],
            'updated_by' => auth()->id() ?? 1,
        ]);

        return response()->json([
            'message' => 'Tipe pola kerja berhasil diupdate',
            'data' => $type
        ]);
    }
}
