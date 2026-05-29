<?php

namespace App\Modules\Employee\Controllers\Api\V1\Bpjs;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeBpjs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BpjsApiController extends Controller
{
    public function show(Employee $employee): JsonResponse
    {
        $bpjs = $employee->bpjs;

        if (! $bpjs) {
            return response()->json(['data' => null, 'message' => 'Data BPJS belum ada.'], 404);
        }

        return response()->json(['data' => $bpjs]);
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $this->validatedData($request);
        $data['created_by'] = Auth::id();

        $bpjs = $employee->bpjs()->create($data);

        return response()->json([
            'message' => 'Data BPJS berhasil ditambahkan.',
            'data'    => $bpjs,
        ], 201);
    }

    public function update(Request $request, Employee $employee, EmployeeBpjs $bpjs): JsonResponse
    {
        abort_if($bpjs->employee_id !== $employee->id, 404);

        $data = $this->validatedData($request);
        $data['updated_by'] = Auth::id();
        $bpjs->update($data);

        return response()->json([
            'message' => 'Data BPJS berhasil diperbarui.',
            'data'    => $bpjs->fresh(),
        ]);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'bpjs_ketenagakerjaan_no'       => 'nullable|string|max:50',
            'bpjs_kesehatan_no'             => 'nullable|string|max:50',
            'bpjs_base_type'                => 'nullable|in:gaji_pokok,umk,custom',
            'jht_setting'                   => 'nullable|in:ditanggung_perusahaan,potong_gaji,tidak_ikut',
            'jp_setting'                    => 'nullable|in:ditanggung_perusahaan,potong_gaji,tidak_ikut',
            'jkk_setting'                   => 'nullable|in:ditanggung_perusahaan,tidak_ikut',
            'jkm_setting'                   => 'nullable|in:ditanggung_perusahaan,tidak_ikut',
            'kesehatan_setting'             => 'nullable|in:ditanggung_perusahaan,potong_gaji,tidak_ikut',
            'kesehatan_dependents'          => 'nullable|integer|min:0',
            'status_ketenagakerjaan'        => 'nullable|in:active,inactive,suspended',
            'status_kesehatan'              => 'nullable|in:active,inactive,suspended',
            'date_joined_ketenagakerjaan'   => 'nullable|date',
            'date_joined_kesehatan'         => 'nullable|date',
            'date_left_ketenagakerjaan'     => 'nullable|date',
            'date_left_kesehatan'           => 'nullable|date',
            'bpjs_kesehatan_class'          => 'nullable|in:Kelas I,Kelas II,Kelas III',
            'faskes_tingkat_1'              => 'nullable|string|max:100',
            'faskes_tingkat_1_code'         => 'nullable|string|max:20',
            'potongan_jht'                  => 'nullable|numeric|min:0',
            'potongan_jp'                   => 'nullable|numeric|min:0',
            'potongan_kesehatan'            => 'nullable|numeric|min:0',
            'tanggungan_jht'                => 'nullable|numeric|min:0',
            'tanggungan_jp'                 => 'nullable|numeric|min:0',
            'tanggungan_jkk'                => 'nullable|numeric|min:0',
            'tanggungan_jkm'                => 'nullable|numeric|min:0',
            'tanggungan_kesehatan'          => 'nullable|numeric|min:0',
            'bpjs_base_salary'              => 'nullable|numeric|min:0',
            'notes'                         => 'nullable|string',
        ]);
    }
}
