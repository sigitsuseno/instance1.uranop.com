<?php

namespace App\Modules\Leave\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePeriod;
use Illuminate\Http\Request;

class LeaveSettingApiController extends Controller
{
    // --- Leave Types ---
    public function getTypes()
    {
        $types = LeaveType::orderBy('name')->get();
        return response()->json(['data' => $types]);
    }

    public function storeType(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:leave_types,code',
            'name' => 'required|string',
            'category' => 'required|in:leave,permit,sick,special',
            'balance_type' => 'required|in:decrement,increment,none',
            'is_paid' => 'boolean',
            'max_days' => 'nullable|integer',
        ]);

        $type = LeaveType::create($validated);
        return response()->json(['message' => 'Leave type created', 'data' => $type], 201);
    }

    public function updateType(Request $request, LeaveType $type)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:leave_types,code,' . $type->id,
            'name' => 'required|string',
            'category' => 'required|in:leave,permit,sick,special',
            'balance_type' => 'required|in:decrement,increment,none',
            'is_paid' => 'boolean',
            'max_days' => 'nullable|integer',
        ]);

        $type->update($validated);
        return response()->json(['message' => 'Leave type updated', 'data' => $type]);
    }

    public function destroyType(LeaveType $type)
    {
        $type->delete();
        return response()->json(['message' => 'Leave type deleted']);
    }

    // --- Leave Policies ---
    public function getPolicies()
    {
        $policies = LeavePolicy::with('leaveType')->get();
        return response()->json(['data' => $policies]);
    }

    public function storePolicy(Request $request)
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'requires_one_year_service' => 'boolean',
            'can_carry_forward' => 'boolean',
            'max_carry_forward_days' => 'nullable|integer',
            'entitlement_days' => 'required|integer|min:0',
        ]);

        $policy = LeavePolicy::create($validated);
        return response()->json(['message' => 'Leave policy created', 'data' => $policy], 201);
    }

    public function updatePolicy(Request $request, LeavePolicy $policy)
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'requires_one_year_service' => 'boolean',
            'can_carry_forward' => 'boolean',
            'max_carry_forward_days' => 'nullable|integer',
            'entitlement_days' => 'required|integer|min:0',
        ]);

        $policy->update($validated);
        return response()->json(['message' => 'Leave policy updated', 'data' => $policy]);
    }

    public function destroyPolicy(LeavePolicy $policy)
    {
        $policy->delete();
        return response()->json(['message' => 'Leave policy deleted']);
    }

    // --- Leave Periods ---
    public function getPeriods()
    {
        $periods = LeavePeriod::orderBy('start_date', 'desc')->get();
        return response()->json(['data' => $periods]);
    }

    public function storePeriod(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_carry_forward' => 'boolean',
            'status' => 'required|in:active,recap,closed',
        ]);

        $period = LeavePeriod::create($validated);
        return response()->json(['message' => 'Leave period created', 'data' => $period], 201);
    }

    public function updatePeriod(Request $request, LeavePeriod $period)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_carry_forward' => 'boolean',
            'status' => 'required|in:active,recap,closed',
        ]);

        $period->update($validated);
        return response()->json(['message' => 'Leave period updated', 'data' => $period]);
    }

    public function destroyPeriod(LeavePeriod $period)
    {
        $period->delete();
        return response()->json(['message' => 'Leave period deleted']);
    }
}
