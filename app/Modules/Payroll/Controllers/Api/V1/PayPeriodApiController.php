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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        
        $period = PayPeriod::create([
            'uuid' => (string) Str::uuid(),
            'name' => $validated['name'],
            'period_year' => $startDate->year,
            'period_month' => $startDate->month,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'is_split' => false, // Default to false
            'status' => 'draft',
            'created_by' => auth()->id() ?? 1, // Fallback if auth missing in dev
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
            'status' => 'required|in:draft,in_progress,locked,closed,completed'
        ]);

        // Map frontend "completed" to "closed" or keep as is.
        $status = $validated['status'];
        if ($status === 'completed') {
            $status = 'closed';
        }

        $period->update([
            'status' => $status,
            'updated_by' => auth()->id() ?? 1,
        ]);

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
