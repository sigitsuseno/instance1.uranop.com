<?php

namespace App\Modules\Settings\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Settings\Models\SalaryComponent;
use App\Modules\Settings\Models\BpjsConfig;
use App\Modules\Settings\Models\PphConfig;
use App\Modules\Settings\Models\PtkpRate;
use App\Modules\Settings\Models\TerRate;
use App\Modules\Settings\Models\ProgressiveRate;
use App\Modules\Settings\Models\OvertimeRule;
use App\Modules\Settings\Models\OvertimeRuleDetail;
use App\Modules\Settings\Models\WorkPattern;
use App\Modules\Settings\Models\ServiceYearAllowance;
use App\Modules\Settings\Models\ThrConfig;

class PayrollConfigApiController extends Controller
{
    // === Salary Components ===
    public function getComponents()
    {
        return response()->json(['data' => SalaryComponent::latest()->get()]);
    }

    public function storeComponent(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:salary_components',
            'name' => 'required|string',
            'type' => 'required|in:allowance,deduction',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean'
        ]);

        $component = SalaryComponent::create($validated);
        return response()->json(['message' => 'Component created', 'data' => $component], 201);
    }

    public function updateComponent(Request $request, $id)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:salary_components,code,'.$id,
            'name' => 'required|string',
            'type' => 'required|in:allowance,deduction',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean'
        ]);

        $component = SalaryComponent::findOrFail($id);
        $component->update($validated);
        return response()->json(['message' => 'Component updated', 'data' => $component]);
    }

    public function destroyComponent($id)
    {
        SalaryComponent::findOrFail($id)->delete();
        return response()->json(['message' => 'Component deleted']);
    }

    // === BPJS ===
    public function getBpjs()
    {
        return response()->json(['data' => BpjsConfig::all()]);
    }

    public function updateBpjs(Request $request)
    {
        $validated = $request->validate([
            'configs' => 'required|array',
            'configs.*.id' => 'required|exists:bpjs_configs,id',
            'configs.*.company_percentage' => 'required|numeric',
            'configs.*.employee_percentage' => 'required|numeric',
        ]);

        foreach ($validated['configs'] as $configData) {
            BpjsConfig::where('id', $configData['id'])->update([
                'company_percentage' => $configData['company_percentage'],
                'employee_percentage' => $configData['employee_percentage']
            ]);
        }
        return response()->json(['message' => 'BPJS config updated']);
    }

    // === PTKP Rates ===
    public function getPtkp()
    {
        return response()->json(['data' => PtkpRate::all()]);
    }

    public function updatePtkp(Request $request)
    {
        $validated = $request->validate([
            'rates' => 'required|array',
            'rates.*.id' => 'required|exists:ptkp_rates,id',
            'rates.*.rate' => 'required|numeric',
        ]);

        foreach ($validated['rates'] as $rateData) {
            PtkpRate::where('id', $rateData['id'])->update(['rate' => $rateData['rate']]);
        }
        return response()->json(['message' => 'PTKP Rates updated']);
    }

    // === TER Rates ===
    public function getTer()
    {
        return response()->json(['data' => TerRate::all()]);
    }

    // === Progressive Rates ===
    public function getProgressive()
    {
        return response()->json(['data' => ProgressiveRate::orderBy('layer')->get()]);
    }

    // === Work Patterns ===
    public function getWorkPatterns()
    {
        return response()->json(['data' => WorkPattern::where('is_active', true)->get()]);
    }

    // === Overtime Rules ===
    public function getOvertime()
    {
        return response()->json([
            'data' => OvertimeRule::with(['details', 'workPattern'])->get()
        ]);
    }

    public function storeOvertime(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:overtime_rules,code',
            'name' => 'required|string',
            'work_pattern_id' => 'nullable|exists:work_patterns,id',
            'is_holiday' => 'boolean',
            'description' => 'nullable|string',
            'details' => 'required|array',
            'details.*.hour' => 'required|integer|min:1',
            'details.*.multiplier' => 'required|numeric|min:0'
        ]);

        $rule = OvertimeRule::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'code' => $validated['code'],
            'name' => $validated['name'],
            'work_pattern_id' => $validated['work_pattern_id'] ?? null,
            'is_holiday' => $validated['is_holiday'] ?? false,
            'description' => $validated['description'] ?? null,
            'is_active' => true
        ]);

        foreach ($validated['details'] as $detail) {
            $rule->details()->create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'hour' => $detail['hour'],
                'multiplier' => $detail['multiplier']
            ]);
        }

        return response()->json(['message' => 'Overtime rule created', 'data' => $rule->load('details')], 201);
    }

    public function updateOvertime(Request $request, $id)
    {
        $rule = OvertimeRule::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|unique:overtime_rules,code,'.$id,
            'name' => 'required|string',
            'work_pattern_id' => 'nullable|exists:work_patterns,id',
            'is_holiday' => 'boolean',
            'description' => 'nullable|string',
            'details' => 'required|array',
            'details.*.hour' => 'required|integer|min:1',
            'details.*.multiplier' => 'required|numeric|min:0'
        ]);

        $rule->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'work_pattern_id' => $validated['work_pattern_id'] ?? null,
            'is_holiday' => $validated['is_holiday'] ?? false,
            'description' => $validated['description'] ?? null,
        ]);

        // Sync details (delete old, insert new to keep it simple and ensure correct hours)
        $rule->details()->delete();
        foreach ($validated['details'] as $detail) {
            $rule->details()->create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'hour' => $detail['hour'],
                'multiplier' => $detail['multiplier']
            ]);
        }

        return response()->json(['message' => 'Overtime rule updated', 'data' => $rule->load('details')]);
    }

    public function destroyOvertime($id)
    {
        $rule = OvertimeRule::findOrFail($id);
        $rule->delete(); // details will cascade delete
        return response()->json(['message' => 'Overtime rule deleted']);
    }

    // === THR Configs ===
    public function getThrConfigs()
    {
        return response()->json(['data' => ThrConfig::orderBy('min_months')->get()]);
    }

    public function storeThrConfig(Request $request)
    {
        $validated = $request->validate([
            'min_months' => 'required|integer|min:0',
            'max_months' => 'nullable|integer|gt:min_months',
            'is_prorated' => 'boolean',
            'percentage' => 'required|numeric|min:0',
            'is_active' => 'boolean'
        ]);

        $thr = ThrConfig::create($validated);
        return response()->json(['message' => 'THR config created', 'data' => $thr], 201);
    }

    public function updateThrConfig(Request $request, $id)
    {
        $validated = $request->validate([
            'min_months' => 'required|integer|min:0',
            'max_months' => 'nullable|integer|gt:min_months',
            'is_prorated' => 'boolean',
            'percentage' => 'required|numeric|min:0',
            'is_active' => 'boolean'
        ]);

        $thr = ThrConfig::findOrFail($id);
        $thr->update($validated);
        return response()->json(['message' => 'THR config updated', 'data' => $thr]);
    }

    public function destroyThrConfig($id)
    {
        ThrConfig::findOrFail($id)->delete();
        return response()->json(['message' => 'THR config deleted']);
    }
}
