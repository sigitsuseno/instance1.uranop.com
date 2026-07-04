<?php

namespace App\Modules\Instance\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Instance\Models\Instance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InstanceApiController extends Controller
{
    /**
     * Create or update instance from desktop app setup.
     * No auth required — this is the pre-auth setup flow.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'email'       => 'required|email|max:255',
            'phone'       => 'nullable|string|max:50',
            'address'     => 'nullable|string|max:1000',
        ]);

        // Check if instance already exists (by email)
        $instance = Instance::where('email', $validated['email'])->first();

        // Generate 6-char alphanumeric password
        $password = strtoupper(Str::random(6));

        if ($instance) {
            // Update existing
            $instance->update([
                'name'        => $validated['name'],
                'branch_name' => $validated['branch_name'] ?? $instance->branch_name,
                'phone'       => $validated['phone'] ?? $instance->phone,
                'address'     => $validated['address'] ?? $instance->address,
                'password'    => $password, // regenerate password on each save
            ]);
        } else {
            // Create new
            $instance = Instance::create([
                'name'        => $validated['name'],
                'branch_name' => $validated['branch_name'] ?? null,
                'email'       => $validated['email'],
                'phone'       => $validated['phone'] ?? null,
                'address'     => $validated['address'] ?? null,
                'code'        => $this->generateCode(),
                'slug'        => Str::slug($validated['name']),
                'password'    => $password,
                'is_active'   => true,
            ]);
        }

        return response()->json([
            'message' => 'Instance berhasil disimpan.',
            'data'    => [
                'code'        => $instance->code,
                'name'        => $instance->name,
                'branch_name' => $instance->branch_name,
                'password'    => $password, // return plain text password (only time it's visible)
            ],
        ], $instance->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Verify instance code + password (for desktop gembok unlock).
     * No auth required.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'     => 'required|string',
            'password' => 'required|string',
        ]);

        $instance = Instance::where('code', $validated['code'])->first();

        if (!$instance) {
            return response()->json([
                'message' => 'Kode atau password salah.',
            ], 422);
        }

        // Check password using Hash::check (since password is hashed cast)
        if (!password_verify($validated['password'], $instance->getRawOriginal('password'))) {
            return response()->json([
                'message' => 'Kode atau password salah.',
            ], 422);
        }

        return response()->json([
            'message' => 'Verified.',
            'data'    => [
                'code'        => $instance->code,
                'name'        => $instance->name,
                'branch_name' => $instance->branch_name,
                'email'       => $instance->email,
                'phone'       => $instance->phone,
                'address'     => $instance->address,
            ],
        ]);
    }

    /**
     * Get instance data for authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
        $instance = Instance::first();

        if (!$instance) {
            return response()->json([
                'message' => 'Instance belum dikonfigurasi.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'code'        => $instance->code,
                'name'        => $instance->name,
                'branch_name' => $instance->branch_name,
                'email'       => $instance->email,
                'phone'       => $instance->phone,
                'address'     => $instance->address,
            ],
        ]);
    }

    /**
     * Generate unique instance code.
     */
    private function generateCode(): string
    {
        $code = 'INST-' . strtoupper(Str::random(4));
        while (Instance::where('code', $code)->exists()) {
            $code = 'INST-' . strtoupper(Str::random(4));
        }
        return $code;
    }
}