<?php

namespace App\Modules\Settings\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Settings\Models\SystemSetting;
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
}
