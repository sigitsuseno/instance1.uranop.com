<?php

namespace App\Modules\Attendance\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Settings\Models\AttendanceConfig;

class AttendanceConfigController extends Controller
{
    /**
     * GET /api/v1/attendance/configs — list all page configs
     */
    public function index()
    {
        $configs = AttendanceConfig::orderBy('page')->get()
            ->map(fn($c) => [
                'id'    => $c->id,
                'page'  => $c->page,
                'config'=> $c->config,
                'updated_at' => $c->updated_at?->toDateTimeString(),
            ]);

        return response()->json(['data' => $configs]);
    }

    /**
     * GET /api/v1/attendance/configs/{page} — get single page config (with defaults merged)
     */
    public function show(string $page)
    {
        $defaults = $this->defaultsFor($page);
        $config = AttendanceConfig::forPage($page, $defaults);

        return response()->json([
            'data' => [
                'page'   => $page,
                'config' => $config,
            ],
        ]);
    }

    /**
     * PUT /api/v1/attendance/configs/{page} — save config for a page
     */
    public function update(Request $request, string $page)
    {
        $validated = $request->validate([
            'config' => 'required|array',
        ]);

        $config = AttendanceConfig::saveForPage(
            $page,
            $validated['config'],
            auth()->id()
        );

        return response()->json([
            'message' => 'Config saved.',
            'data'    => [
                'page'   => $config->page,
                'config' => $config->config,
            ],
        ]);
    }

    /**
     * Default config values per page.
     */
    protected function defaultsFor(string $page): array
    {
        return match ($page) {
            'manual-sync' => [
                'default_department'   => '',
                'auto_fill_absent'     => false,
                'show_log_table'       => true,
                'max_employees_in_list'=> 20,
            ],
            'sync-kehadiran' => [
                'auto_sync_on_period_change' => false,
                'show_lengkapi_absent'       => true,
            ],
            'import' => [
                'auto_process_after_upload' => false,
                'skip_duplicate_pins'       => true,
            ],
            'recap' => [
                'auto_generate_on_approve' => false,
            ],
            'consecutive' => [
                'max_days_threshold' => 7,
                'auto_approve'       => false,
            ],
            default => [],
        };
    }
}
