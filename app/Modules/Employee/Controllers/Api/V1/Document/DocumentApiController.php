<?php

namespace App\Modules\Employee\Controllers\Api\V1\Document;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentApiController extends Controller
{
    public function index(Employee $employee): JsonResponse
    {
        return response()->json([
            'data' => $employee->documents()->get(),
        ]);
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'document_type'   => 'required|in:ktp,kk,npwp,bpjs,ijazah,transkrip,sertifikat,kontrak,sk,other',
            'document_number' => 'nullable|string|max:100',
            'title'           => 'required|string|max:200',
            'description'     => 'nullable|string',
            'file'            => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png',
            'issue_date'      => 'nullable|date',
            'expiry_date'     => 'nullable|date',
            'issued_by'       => 'nullable|string|max:200',
        ]);

        $file = $request->file('file');
        $path = $file->store("employees/{$employee->id}/documents", 'public');

        $document = $employee->documents()->create([
            'document_type'   => $data['document_type'],
            'document_number' => $data['document_number'] ?? null,
            'title'           => $data['title'],
            'description'     => $data['description'] ?? null,
            'file_path'       => $path,
            'file_name'       => $file->getClientOriginalName(),
            'file_size'       => (string) $file->getSize(),
            'mime_type'       => $file->getMimeType(),
            'issue_date'      => $data['issue_date'] ?? null,
            'expiry_date'     => $data['expiry_date'] ?? null,
            'issued_by'       => $data['issued_by'] ?? null,
            'created_by'      => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Dokumen berhasil diunggah.',
            'data'    => $document,
        ], 201);
    }

    public function destroy(Employee $employee, EmployeeDocument $document): JsonResponse
    {
        abort_if($document->employee_id !== $employee->id, 404);

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Dokumen berhasil dihapus.']);
    }
}
