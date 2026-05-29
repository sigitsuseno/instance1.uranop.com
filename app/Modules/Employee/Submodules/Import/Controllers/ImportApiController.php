<?php

namespace App\Modules\Employee\Submodules\Import\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Submodules\Import\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportApiController extends Controller
{
    public function __construct(
        protected ImportService $importService
    ) {}

    /**
     * POST /api/employees/import
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        $result = $this->importService->importExcel($request->file('file'));

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'errors'  => $result['errors'] ?? [],
            ], 422);
        }

        return response()->json([
            'message' => $result['message'],
        ]);
    }

    /**
     * GET /api/employees/import/template
     */
    public function template(): BinaryFileResponse
    {
        $path = public_path('templates/template_import_karyawan.xlsx');
        if (!file_exists($path)) {
            abort(404, 'Template tidak ditemukan.');
        }

        return response()->download($path, 'Template_Import_Karyawan.xlsx');
    }
}
