<?php

namespace App\Modules\Settings\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExtraEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExtraEmployeeController extends Controller
{
    public function index()
    {
        $data = ExtraEmployee::orderBy('nama')->get();

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama'           => 'required|string|max:200',
            'kode'           => 'nullable|string|max:50|unique:extra_employees,kode',
            'komponen_gaji'  => 'nullable|array',
        ]);

        $validated['uuid'] = (string) Str::uuid();

        if (empty($validated['komponen_gaji'])) {
            $validated['komponen_gaji'] = ExtraEmployee::defaultKomponenGaji();
        }

        $record = ExtraEmployee::create($validated);

        return response()->json([
            'message' => 'Karyawan titipan berhasil ditambahkan',
            'data'    => $record,
        ], 201);
    }

    public function show($id)
    {
        $record = ExtraEmployee::findOrFail($id);

        return response()->json(['data' => $record]);
    }

    public function update(Request $request, $id)
    {
        $record = ExtraEmployee::findOrFail($id);

        $validated = $request->validate([
            'nama'           => 'required|string|max:200',
            'kode'           => 'nullable|string|max:50|unique:extra_employees,kode,' . $record->id,
            'komponen_gaji'  => 'nullable|array',
        ]);

        $record->update($validated);

        return response()->json([
            'message' => 'Karyawan titipan berhasil diupdate',
            'data'    => $record,
        ]);
    }

    public function destroy($id)
    {
        $record = ExtraEmployee::findOrFail($id);
        $record->delete();

        return response()->json([
            'message' => 'Karyawan titipan berhasil dihapus',
        ]);
    }
}
