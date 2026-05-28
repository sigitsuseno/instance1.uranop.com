<?php

namespace App\Modules\AuditLog\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\AuditLog\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogApiController extends Controller
{
    /**
     * Get paginated audit logs
     */
    public function index(Request $request)
    {
        $query = AuditLog::with('user:id,name,email');

        // Filter by context if provided (main or shadow)
        if ($request->has('context')) {
            $query->where('context', $request->context);
        }

        // Filter by user if provided
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        // Filter by action if provided
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        $logs = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($logs);
    }
}
