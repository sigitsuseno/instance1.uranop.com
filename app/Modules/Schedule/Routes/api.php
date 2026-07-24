<?php

use App\Modules\Schedule\Controllers\Api\V1\ScheduleApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('schedule')->group(function () {
    Route::get('/work-patterns', [ScheduleApiController::class, 'getWorkPatterns']);
    Route::post('/work-patterns', [ScheduleApiController::class, 'storeWorkPattern']);
    Route::put('/work-patterns/{id}', [ScheduleApiController::class, 'updateWorkPattern']);
    Route::delete('/work-patterns/{id}', [ScheduleApiController::class, 'destroyWorkPattern']);
    Route::delete('/work-patterns/{id}/details/group', [ScheduleApiController::class, 'destroyWorkPatternDetailsGroup']);
    Route::post('/work-patterns/{id}/details', [ScheduleApiController::class, 'storeWorkPatternDetails']);
    
    Route::get('/shifts', [ScheduleApiController::class, 'getShifts']);
    Route::post('/shifts', [ScheduleApiController::class, 'storeShift']);
    Route::put('/shifts/{id}', [ScheduleApiController::class, 'updateShift']);
    Route::delete('/shifts/{id}', [ScheduleApiController::class, 'destroyShift']);
    
    Route::get('/calendars', [ScheduleApiController::class, 'getCalendars']);
    Route::get('/roster', [ScheduleApiController::class, 'getRoster']);
    Route::post('/roster/generate', [ScheduleApiController::class, 'generateRoster']);
    Route::post('/roster/import', [ScheduleApiController::class, 'importRoster']);
    Route::post('/roster/override', [ScheduleApiController::class, 'overrideRoster']);
    Route::post('/roster/update-cuti', [ScheduleApiController::class, 'updateRosterCuti']);
    // Holiday CRUD
    Route::post('/calendars/{id}/holidays', [ScheduleApiController::class, 'storeHoliday']);
    Route::put('/calendars/{id}/holidays/{holiday_id}', [ScheduleApiController::class, 'updateHoliday']);
    Route::delete('/calendars/{id}/holidays/{holiday_id}', [ScheduleApiController::class, 'destroyHoliday']);
});
