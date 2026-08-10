<?php

namespace App\Modules\Organization\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyProfileApiController extends Controller
{
    /**
     * Public endpoint (tanpa auth) untuk mengambil nama & logo perusahaan.
     * Dipakai oleh halaman landing, login, dan sidebar dashboard.
     */
    public function publicShow()
    {
        $company = Company::first();

        return response()->json([
            'data' => [
                'name' => $company->name ?? null,
                'logo_path' => $company->logo_path ?? null,
            ]
        ]);
    }

    public function show()
    {
        $company = Company::with('branches')->first();
        
        if (!$company) {
            return response()->json([
                'data' => [
                    'company' => null,
                    'branch' => null
                ]
            ]);
        }

        return response()->json([
            'data' => [
                'company' => $company,
                'branch' => $company->branches->first()
            ]
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'company_npwp' => 'nullable|string|max:255',
            'company_address' => 'nullable|string',
            'company_phone' => 'nullable|string|max:50',
            'company_email' => 'nullable|email|max:255',
            'company_website' => 'nullable|string|max:255',
            
            'branch_name' => 'required|string|max:255',
            'branch_code' => 'nullable|string|max:50',
            'branch_address' => 'nullable|string',
            'branch_phone' => 'nullable|string|max:50',
            'branch_email' => 'nullable|email|max:255',
            'branch_pic_name' => 'nullable|string|max:255',

            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $company = Company::first();
        if (!$company) {
            $company = new Company();
        }

        $company->name = $request->company_name;
        $company->npwp = $request->company_npwp;
        $company->address = $request->company_address;
        $company->phone = $request->company_phone;
        $company->email = $request->company_email;
        $company->website = $request->company_website;

        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $path = $request->file('logo')->store('logos', 'public');
            $company->logo_path = $path;
        }

        $company->save();

        $branch = $company->branches()->first();
        if (!$branch) {
            $branch = new Branch();
            $branch->company_id = $company->id;
        }

        $branch->name = $request->branch_name;
        $branch->code = $request->branch_code;
        $branch->address = $request->branch_address;
        $branch->phone = $request->branch_phone;
        $branch->email = $request->branch_email;
        $branch->pic_name = $request->branch_pic_name;
        $branch->save();

        return response()->json([
            'message' => 'Profil Perusahaan berhasil disimpan',
            'data' => [
                'company' => $company,
                'branch' => $branch
            ]
        ]);
    }
}
