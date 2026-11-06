<?php

use App\Http\Controllers\Api\V1\AttendanceRecordController;
use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    // 勤怠一覧・詳細（認証不要）
    Route::get('/attendance-records', [AttendanceRecordController::class, 'index']);
    Route::get('/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'show']);

    // APIログイン・トークン発行
    Route::post('/login', [AuthController::class, 'login']);

    // 勤怠の登録・更新・削除（認証必須）
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/attendance-records', [AttendanceRecordController::class, 'store']);
        Route::put('/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'update']);
        Route::patch('/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'update']);
        Route::delete('/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'destroy']);
    });
});
