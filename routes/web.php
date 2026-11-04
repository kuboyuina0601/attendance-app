<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;
use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\AdminStaffAttendanceController;
use App\Http\Controllers\AdminStampCorrectionRequestController;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;


// 一般ユーザー：ログイン画面
Route::get('/login', function () {
    return view('user.user-login');
})->name('login');

// 管理者：ログイン画面
Route::get('/admin/login', function () {
    return view('admin.admin-login');
})->name('admin.login');

// 管理者：ログイン処理
Route::post('/admin/login', [
    AuthenticatedSessionController::class,
    'store',
])->middleware('throttle:login')
    ->name('admin.login.store');

// 管理者：ログアウト処理
Route::post('/admin/logout', [
    AuthenticatedSessionController::class,
    'destroy',
])->middleware(['auth', 'admin'])
    ->name('admin.logout');

// 一般ユーザー：会員登録画面
Route::get('/register', function () {
    return view('user.register');
})->name('register');

// 一般ユーザー用の認証・権限チェック
Route::middleware(['auth', 'verified', 'general.user'])->group(function () {
    // 一般ユーザー：勤怠登録画面
    Route::get('/attendance', [
        AttendanceController::class,
        'create',
    ])->name('attendance.create');

    // 一般ユーザー：勤怠登録処理
    Route::post('/attendance', [
        AttendanceController::class,
        'store',
    ])->name('attendance.store');

    // 一般ユーザー：勤怠一覧
    Route::get('/attendance/list', [
        AttendanceController::class,
        'index',
    ])->name('attendance.index');

    // 一般ユーザー：マイ勤怠レポート
    Route::get('/attendance/report', [
        \App\Http\Controllers\AttendanceReportController::class,
        'index',
    ])->name('attendance.report');

    // 一般ユーザー：申請詳細
    Route::get('/application/{id}', [
        StampCorrectionRequestController::class,
        'show',
    ])->whereNumber('id')
        ->name('application.show');
});

// 管理者用の認証・権限チェック
Route::middleware(['auth', 'admin'])->group(function () {
    // 管理者：勤怠一覧
    Route::get('/admin/attendance/list', [
        AdminAttendanceController::class,
        'index',
    ])->name('admin.attendance.index');

    // 管理者：勤怠詳細
    Route::get('/admin/attendance/{id}', [
        AdminAttendanceController::class,
        'show',
    ])->whereNumber('id')
        ->name('admin.attendance.show');

    // 管理者：勤怠修正処理
    Route::post('/admin/attendance/{id}', [
        AdminAttendanceController::class,
        'update',
    ])->whereNumber('id')
        ->name('admin.attendance.update');

    // 管理者：スタッフ一覧
    Route::get('/admin/staff/list', [
        AdminStaffController::class,
        'index',
    ])->name('admin.staff.index');

    // 管理者：スタッフ別勤怠一覧
    Route::get('/admin/attendance/staff/{id}', [
        AdminStaffAttendanceController::class,
        'index',
    ])->whereNumber('id')
        ->name('admin.staff.attendance.index');

    // 管理者：CSV出力
    Route::post('/export', [
        AdminStaffAttendanceController::class,
        'export',
    ])->name('admin.staff.attendance.export');

    // 管理者：修正申請承認画面
    Route::get(
        '/stamp_correction_request/approve/{stamp_correction_request_id}',
        [
            AdminStampCorrectionRequestController::class,
            'show',
        ]
    )->whereNumber('stamp_correction_request_id')
        ->name('admin.stamp-correction-request.show');

    // 管理者：修正申請承認処理
    Route::post(
        '/stamp_correction_request/approve/{stamp_correction_request_id}',
        [
            AdminStampCorrectionRequestController::class,
            'update',
        ]
    )->whereNumber('stamp_correction_request_id')
        ->name('admin.stamp-correction-request.update');
});

// 勤怠詳細：ログインユーザーの権限に応じて表示先を振り分ける
Route::get('/attendance/{id}', function ($id) {
    $user = auth()->user();

    if ($user->admin_status) {
        return redirect('/admin/attendance/' . $id);
    }

    return app(AttendanceController::class)->show($id);
})->middleware('auth')
    ->whereNumber('id')
    ->name('attendance.show');

// 勤怠修正：ログインユーザーの権限に応じて処理を振り分ける
Route::post('/attendance/{id}', function ($id) {
    $user = auth()->user();

    if ($user->admin_status) {
        return app()->call(
            [app(AdminAttendanceController::class), 'update'],
            ['id' => $id]
        );
    }

    return app()->call(
        [app(StampCorrectionRequestController::class), 'store'],
        ['id' => $id]
    );
})->middleware('auth')
    ->whereNumber('id')
    ->name('attendance.update');

// 一般ユーザー・管理者共通の申請一覧
Route::get('/stamp_correction_request/list', function () {
    if (auth()->user()->admin_status) {
        return app(AdminStampCorrectionRequestController::class)->index();
    }

    return app(StampCorrectionRequestController::class)->index();
})->middleware('auth')
    ->name('stamp-correction-request.index');
