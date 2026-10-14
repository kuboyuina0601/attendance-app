```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;
use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\AdminStaffAttendanceController;
use App\Http\Controllers\AdminStampCorrectionRequestController;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

// トップページ
Route::get('/', function () {
    return view('welcome');
});

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

// 一般ユーザー：会員登録画面
Route::get('/register', function () {
    return view('user.register');
})->name('register');

// 一般ユーザー：勤怠登録
Route::get('/attendance', [AttendanceController::class, 'create'])
    ->name('attendance.create');

Route::post('/attendance', [AttendanceController::class, 'store'])
    ->name('attendance.store');

// 一般ユーザー：勤怠一覧
Route::get('/attendance/list', [AttendanceController::class, 'index'])
    ->name('attendance.index');

// 一般ユーザー：勤怠詳細
Route::get('/attendance/detail/{id}', [AttendanceController::class, 'show'])
    ->name('attendance.show');

// 一般ユーザー：勤怠修正申請
Route::post('/attendance/detail/{id}', [StampCorrectionRequestController::class, 'store'])
    ->name('stamp-correction-request.store');

// 一般ユーザー：修正申請一覧
Route::get('/stamp_correction_request/list', [StampCorrectionRequestController::class, 'index'])
    ->name('stamp-correction-request.index');

// 管理者：勤怠一覧
Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'index'])
    ->name('admin.attendance.index');

// 管理者：勤怠詳細
Route::get('/admin/attendance/detail/{id}', [AdminAttendanceController::class, 'show'])
    ->name('admin.attendance.show');

// 管理者：スタッフ一覧
Route::get('/admin/staff/list', [AdminStaffController::class, 'index'])
    ->name('admin.staff.index');

// 管理者：スタッフ別勤怠一覧
Route::get('/admin/attendance/staff/{id}', [AdminStaffAttendanceController::class, 'index'])
    ->name('admin.staff.attendance.index');

// 管理者：修正申請一覧
Route::get('/admin/stamp_correction_request/list', [AdminStampCorrectionRequestController::class, 'index'])
    ->name('admin.stamp-correction-request.index');

// 管理者：修正申請承認画面
Route::get(
    '/stamp_correction_request/approve/attendance_correct_request/{attendance_correct_request_id}',
    [AdminStampCorrectionRequestController::class, 'edit']
)->name('admin.stamp-correction-request.edit');

// 管理者：修正申請承認処理
Route::post(
    '/stamp_correction_request/approve/attendance_correct_request/{attendance_correct_request_id}',
    [AdminStampCorrectionRequestController::class, 'update']
)->name('admin.stamp-correction-request.update');
