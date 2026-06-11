<?php

namespace App\Modules\Payroll\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\PayPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PayPeriodApiController extends Controller
{
    public function index()
    {
        $periods = PayPeriod::getPeriods();

        // Format return if needed to match frontend
        $formatted = $periods->map(function ($period) {
            return [
                'id' => $period->id,
                'period_code' => "PAY-" . $period->period_year . "-" . str_pad($period->period_month, 2, '0', STR_PAD_LEFT),
                'name' => $period->name,
                'start_date' => $period->start_date ? $period->start_date->format('Y-m-d') : null,
                'end_date' => $period->end_date ? $period->end_date->format('Y-m-d') : null,
                'status' => $period->status,
                'is_split' => $period->is_split,
                'total_employees' => 0, // Mock for now, will calculate in future
                'total_amount' => 0, // Mock for now, will calculate in future
                'date_range' => $period->start_date && $period->end_date ? $period->start_date->format('d M') . ' - ' . $period->end_date->format('d M Y') : '-',
            ];
        });

        return response()->json([
            'data' => $formatted
        ]);
    }

    public function show($id)
    {
        $period = PayPeriod::findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $period->id,
                'uuid' => $period->uuid,
                'period_code' => 'PAY-' . $period->period_year . '-' . str_pad($period->period_month, 2, '0', STR_PAD_LEFT),
                'name' => $period->name,
                'period_year' => $period->period_year,
                'period_month' => $period->period_month,
                'start_date' => $period->start_date?->format('Y-m-d'),
                'end_date' => $period->end_date?->format('Y-m-d'),
                'is_split' => $period->is_split,
                'status' => $period->status,
                'date_range' => $period->start_date && $period->end_date
                    ? $period->start_date->format('d M') . ' - ' . $period->end_date->format('d M Y')
                    : '-',
                'created_by' => $period->created_by,
                'updated_by' => $period->updated_by,
                'created_at' => $period->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $period->updated_at?->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_split' => 'boolean',
            'status' => 'in:active,inactive',
        ]);

        $endDate = Carbon::parse($validated['end_date']);
        
        $period = PayPeriod::create([
            'uuid' => (string) Str::uuid(),
            'name' => $validated['name'],
            'period_year' => $endDate->year,
            'period_month' => $endDate->month,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'is_split' => $validated['is_split'] ?? false,
            'status' => $validated['status'] ?? 'active',
            'created_by' => auth()->id() ?? 1,
        ]);

        return response()->json([
            'message' => 'Pay period created successfully',
            'data' => $period
        ]);
    }

    public function update(Request $request, $id)
    {
        $period = PayPeriod::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'is_split' => 'sometimes|boolean',
            'status' => 'sometimes|required|in:active,inactive'
        ]);

        $updateData = $validated;
        
        if (isset($validated['end_date'])) {
            $endDate = Carbon::parse($validated['end_date']);
            $updateData['period_year'] = $endDate->year;
            $updateData['period_month'] = $endDate->month;
        } else if (isset($validated['start_date'])) {
            // fallback if only start_date is updated (which shouldn't happen usually but just in case)
            $startDate = Carbon::parse($validated['start_date']);
            $updateData['period_year'] = $startDate->year;
            $updateData['period_month'] = $startDate->month;
        }

        $updateData['updated_by'] = auth()->id() ?? 1;

        $period->update($updateData);

        return response()->json([
            'message' => 'Pay period updated successfully',
            'data' => $period
        ]);
    }

    public function destroy($id)
    {
        $period = PayPeriod::findOrFail($id);
        $period->delete();

        return response()->json([
            'message' => 'Pay period deleted successfully'
        ]);
    }
}
