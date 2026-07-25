<?php

namespace App\Modules\Settings\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\EmployeeReserve;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmployeeReserveController extends Controller
{
    /**
     * GET /api/v1/settings/employee-reserves?pay_periode_id=...
     * List employee reserves per periode.
     */
    public function index(Request $request)
    {
        $request->validate(['pay_periode_id' => 'nullable|integer|exists:pay_periods,id']);

        $query = EmployeeReserve::with(['employee:id,name,nip', 'payPeriod:id,name']);

        if ($request->filled('pay_periode_id')) {
            $query->where('pay_periode_id', $request->pay_periode_id);
        }

        $data = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/v1/settings/employee-reserves
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'pay_periode_id' => 'required|integer|exists:pay_periods,id',
            'employee_id'    => 'required|integer|exists:employees,id',
            'komponen'       => 'required|array|min:1',
            'komponen.*.nama'       => 'required|string|max:200',
            'komponen.*.nilai'      => 'required|numeric|min:0',
            'komponen.*.keterangan' => 'nullable|string|max:500',
        ]);

        // Cek duplicate per period+employee
        $exists = EmployeeReserve::where('pay_periode_id', $validated['pay_periode_id'])
            ->where('employee_id', $validated['employee_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Karyawan ini sudah memiliki data insentif di periode yang dipilih. Silakan edit data yang ada.',
            ], 422);
        }

        $validated['uuid'] = (string) Str::uuid();
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        $record = EmployeeReserve::create($validated);

        return response()->json([
            'message' => 'Data insentif berhasil ditambahkan',
            'data'    => $record->load(['employee:id,name,nip', 'payPeriod:id,name']),
        ], 201);
    }

    /**
     * GET /api/v1/settings/employee-reserves/{id}
     */
    public function show($id)
    {
        $record = EmployeeReserve::with(['employee:id,name,nip', 'payPeriod:id,name'])->findOrFail($id);

        return response()->json(['data' => $record]);
    }

    /**
     * PUT /api/v1/settings/employee-reserves/{id}
     */
    public function update(Request $request, $id)
    {
        $record = EmployeeReserve::findOrFail($id);

        $validated = $request->validate([
            'pay_periode_id' => 'sometimes|integer|exists:pay_periods,id',
            'employee_id'    => 'sometimes|integer|exists:employees,id',
            'komponen'       => 'sometimes|required|array|min:1',
            'komponen.*.nama'       => 'required|string|max:200',
            'komponen.*.nilai'      => 'required|numeric|min:0',
            'komponen.*.keterangan' => 'nullable|string|max:500',
        ]);

        $validated['updated_by'] = auth()->id();

        $record->update($validated);

        return response()->json([
            'message' => 'Data insentif berhasil diupdate',
            'data'    => $record->fresh()->load(['employee:id,name,nip', 'payPeriod:id,name']),
        ]);
    }

    /**
     * DELETE /api/v1/settings/employee-reserves/{id}
     */
    public function destroy($id)
    {
        $record = EmployeeReserve::findOrFail($id);
        $record->delete();

        return response()->json([
            'message' => 'Data insentif berhasil dihapus',
        ]);
    }

    /**
     * GET /api/v1/settings/employee-reserves/periods
     * Daftar periode yang memiliki data reserves.
     */
    public function periods()
    {
        $periods = EmployeeReserve::select('pay_periode_id')
            ->distinct()
            ->with('payPeriod:id,name,period_month,period_year,start_date,end_date')
            ->get()
            ->pluck('payPeriod')
            ->filter()
            ->values();

        return response()->json(['data' => $periods]);
    }
}
