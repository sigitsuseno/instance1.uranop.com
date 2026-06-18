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
use App\Modules\Settings\Models\OvertimeCalculatorConfig;
use App\Modules\Schedule\Models\WorkPattern;
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
            PtkpRate::where('id', $rateData['id'])->update(['value' => $rateData['rate']]);
        }
        return response()->json(['message' => 'PTKP Rates updated']);
    }

    // === PPh Configs ===
    public function getPphConfig()
    {
        return response()->json(['data' => PphConfig::first()]);
    }

    public function updatePphConfig(Request $request)
    {
        $validated = $request->validate([
            'calculation_method' => 'required|in:ter,progressive',
            'pph_method' => 'required|in:gross,gross_up,net',
            'non_npwp_penalty' => 'boolean',
            'non_npwp_multiplier' => 'numeric',
            'description' => 'nullable|string',
        ]);

        $config = PphConfig::first();
        if ($config) {
            $config->update($validated);
        } else {
            $validated['uuid'] = (string) \Illuminate\Support\Str::uuid();
            $validated['effective_date'] = now();
            $config = PphConfig::create($validated);
        }
        
        return response()->json(['message' => 'PPh Config updated', 'data' => $config]);
    }

    // === TER Rates ===
    public function getTer()
    {
        return response()->json(['data' => TerRate::all()]);
    }

    public function updateTer(Request $request)
    {
        $validated = $request->validate([
            'rates' => 'required|array',
            'rates.*.id' => 'required|exists:ter_rates,id',
            'rates.*.min_income' => 'required|numeric',
            'rates.*.max_income' => 'nullable|numeric',
            'rates.*.rate' => 'required|numeric',
        ]);

        foreach ($validated['rates'] as $rateData) {
            TerRate::where('id', $rateData['id'])->update([
                'min_income' => $rateData['min_income'],
                'max_income' => $rateData['max_income'],
                'rate' => $rateData['rate'],
            ]);
        }
        return response()->json(['message' => 'TER Rates updated']);
    }

    // === Progressive Rates ===
    public function getProgressive()
    {
        return response()->json(['data' => ProgressiveRate::orderBy('sort_order')->get()]);
    }

    public function updateProgressive(Request $request)
    {
        $validated = $request->validate([
            'rates' => 'required|array',
            'rates.*.id' => 'required|exists:progressive_rates,id',
            'rates.*.min_income' => 'required|numeric',
            'rates.*.max_income' => 'nullable|numeric',
            'rates.*.rate' => 'required|numeric',
        ]);

        foreach ($validated['rates'] as $rateData) {
            ProgressiveRate::where('id', $rateData['id'])->update([
                'min_income' => $rateData['min_income'],
                'max_income' => $rateData['max_income'],
                'rate' => $rateData['rate'],
            ]);
        }
        return response()->json(['message' => 'Progressive Rates updated']);
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
            'work_pattern_id' => 'nullable|exists:sch_work_patterns,id',
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
            'work_pattern_id' => 'nullable|exists:sch_work_patterns,id',
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

    // === Kalkulator Configs ===

    public function indexCalculator()
    {
        return response()->json([
            'data' => OvertimeCalculatorConfig::with('workPattern')->get()
        ]);
    }

    public function storeCalculator(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'work_pattern_id' => 'nullable|exists:sch_work_patterns,id',
            'normal_work_minutes' => 'integer|min:0',
            'saturday_work_minutes' => 'integer|min:0',
            'holiday_max_minutes' => 'integer|min:0',
            'shift_saturday_flat' => 'integer|min:0',
            'late_deducts_overtime' => 'boolean',
            'late_tolerance' => 'integer|min:0',
            'lm_rest_deduction' => 'integer|min:0',
            'rounding_interval' => 'integer|min:1',
            'rounding_threshold' => 'integer|min:0',
            'hourly_divisor' => 'integer|min:1',
            'description' => 'nullable|string',
        ]);

        $validated['uuid'] = (string) \Illuminate\Support\Str::uuid();
        $validated['is_active'] = true;

        $config = OvertimeCalculatorConfig::create($validated);

        return response()->json([
            'message' => 'Konfigurasi kalkulasi dibuat',
            'data' => $config->load('workPattern'),
        ], 201);
    }

    public function updateCalculator(Request $request, $id)
    {
        $config = OvertimeCalculatorConfig::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'work_pattern_id' => 'nullable|exists:sch_work_patterns,id',
            'normal_work_minutes' => 'integer|min:0',
            'saturday_work_minutes' => 'integer|min:0',
            'holiday_max_minutes' => 'integer|min:0',
            'shift_saturday_flat' => 'integer|min:0',
            'late_deducts_overtime' => 'boolean',
            'late_tolerance' => 'integer|min:0',
            'lm_rest_deduction' => 'integer|min:0',
            'rounding_interval' => 'integer|min:1',
            'rounding_threshold' => 'integer|min:0',
            'hourly_divisor' => 'integer|min:1',
            'description' => 'nullable|string',
        ]);

        $config->update($validated);

        return response()->json([
            'message' => 'Konfigurasi kalkulasi diupdate',
            'data' => $config->load('workPattern'),
        ]);
    }

    public function destroyCalculator($id)
    {
        OvertimeCalculatorConfig::findOrFail($id)->delete();
        return response()->json(['message' => 'Konfigurasi kalkulasi dihapus']);
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
