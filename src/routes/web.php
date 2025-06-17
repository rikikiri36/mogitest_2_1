<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserAttendanceController;
use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StampCorrectionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/admin/login', function () {
    return app(\Laravel\Fortify\Contracts\LoginViewResponse::class)->toResponse(request());
})->name('admin.login')->middleware(['guest:admin']);

// Route::post('/admin/login', [AuthenticatedSessionController::class, 'store']);

Route::post('/logout', function (Request $request) {
    if (Auth::check()) {
        session(['last_role' => Auth::user()->role]);
    }

    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return app(LogoutResponseContract::class)->toResponse($request);
});

// 勤怠登録
Route::get('/attendance', [UserAttendanceController::class, 'create'])->middleware('auth');

// 勤怠更新処理
Route::post('/attendance/store', [UserAttendanceController::class, 'store']);

// 勤怠一覧
Route::get('/attendance/list', [UserAttendanceController::class, 'index'])->middleware('auth');

// 勤怠詳細
Route::get('/attendance/{id}', [UserAttendanceController::class, 'detail'])->middleware('auth');

// 勤怠申請処理
Route::post('/attendance/attendancerequestStore', [UserAttendanceController::class, 'attendancerequestStore']);

// 申請一覧
Route::get('/stamp_correction_request/list', [StampCorrectionController::class, 'requestList'])->middleware('auth');

// 勤怠一覧(管理者)
Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'index'])->middleware('auth:admin');

// スタッフ一覧(管理者)
Route::get('/admin/staff/list', [UserController::class, 'index'])->middleware('auth:admin');

// スタッフ別勤怠一覧(管理者)
Route::get('/admin/attendance/staff/{userId}', [AdminAttendanceController::class, 'userIndex'])->middleware('auth:admin');

// スタッフ別勤怠CSV出力(管理者)
Route::post('/admin/attendance/staff/exportcsv', [AdminAttendanceController::class, 'exportCsv']);

// 申請一覧(管理者)
Route::get('/admin/attendance/requests', [AdminAttendanceController::class, 'requestList'])->middleware('auth:admin');

// 勤怠詳細(管理者)
Route::get('/admin/attendance/{id}', [AdminAttendanceController::class, 'detail'])->middleware('auth:admin');

// 修正申請承認(管理者)
Route::get('/stamp_correction_request/approve/{id}', [AdminAttendanceController::class, 'detail'])->middleware('auth:admin');

// 勤怠承認処理(管理者)
Route::post('/admin/attendance/attendanceapproveStore', [AdminAttendanceController::class, 'attendanceapproveStore']);

// 勤怠修正処理(管理者)
Route::post('/admin/attendance/attendanceupdateStore', [AdminAttendanceController::class, 'attendanceupdateStore']);


Route::get('/', function () {
    return view('welcome');
});
