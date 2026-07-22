<?php

namespace App\Http\Middleware;

use App\Modules\Sync\Models\DesktopLicense;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDesktopToken
{
    /**
     * Handle an incoming request.
     * Validates Bearer token against desktop_licenses table.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'message' => 'Token tidak ditemukan. Sertakan Authorization: Bearer <token>.',
            ], 401);
        }

        $license = DesktopLicense::where('token', $token)->first();

        if (!$license) {
            return response()->json([
                'message' => 'Token tidak valid.',
            ], 401);
        }

        if ($license->status === 'revoked') {
            return response()->json([
                'message' => 'Lisensi telah dicabut. Silakan hubungi administrator.',
                'status' => 'revoked',
            ], 403);
        }

        if ($license->licensed_until && $license->licensed_until < now()->toDateString()) {
            $license->update(['status' => 'expired']);
            return response()->json([
                'message' => 'Lisensi telah kedaluwarsa.',
                'status' => 'expired',
            ], 403);
        }

        // Attach license to request for downstream use
        $request->merge(['desktop_license' => $license]);
        $request->setUserResolver(fn () => $license);

        return $next($request);
    }
}
