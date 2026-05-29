<?php

namespace App\Modules\Settings\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Settings\Models\SalaryGrade;
use Illuminate\Support\Str;

class SalaryGradeApiController extends Controller
{
    public function index()
    {
        $grades = SalaryGrade::all();
        // Provide mock employee count since we don't have employees yet
        $grades->map(function($grade) {
            $grade->employee_count = 0;
            return $grade;
        });
        return response()->json(['data' => $grades]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'min_salary' => 'required|numeric',
            'max_salary' => 'required|numeric',
            'description' => 'nullable|string',
        ]);

        $code = strtoupper(Str::slug($validated['name'], ''));

        $grade = SalaryGrade::create(array_merge($validated, [
            'uuid' => (string) Str::uuid(),
            'code' => $code,
            'is_active' => true,
            'created_by' => auth()->id() ?? 1
        ]));

        return response()->json(['data' => $grade]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'min_salary' => 'required|numeric',
            'max_salary' => 'required|numeric',
            'description' => 'nullable|string',
        ]);

        $grade = SalaryGrade::findOrFail($id);
        $grade->update(array_merge($validated, [
            'updated_by' => auth()->id() ?? 1
        ]));

        return response()->json(['data' => $grade]);
    }

    public function destroy($id)
    {
        $grade = SalaryGrade::findOrFail($id);
        $grade->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
