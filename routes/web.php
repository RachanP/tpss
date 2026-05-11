<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminUserController;
use Illuminate\Support\Facades\Route;

// ── Root redirect ──────────────────────────────────────────────────
Route::get('/', fn() => redirect()->route('login'));

// ── Auth ───────────────────────────────────────────────────────────
Route::get('/login',  [AuthController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ── Authenticated ──────────────────────────────────────────────────
Route::middleware(['auth'])->group(function () {

    // Hub: redirect to role-specific dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Role-specific dashboards
    Route::get('/admin/dashboard',    [DashboardController::class, 'admin'])   ->name('admin.dashboard');
    Route::get('/staff/dashboard',    [DashboardController::class, 'staff'])   ->name('staff.dashboard');
    Route::get('/maker/dashboard',    [DashboardController::class, 'maker'])   ->name('maker.dashboard');
    Route::get('/approver/dashboard', [DashboardController::class, 'approver'])->name('approver.dashboard');
    Route::get('/lecturer/dashboard', [DashboardController::class, 'lecturer'])->name('lecturer.dashboard');

    // Admin User Management
    Route::middleware(['\App\Http\Middleware\CheckRole:admin'])->group(function () {
        Route::get('/admin/users', 'App\Http\Controllers\AdminUserController@index')->name('admin.users');
        Route::post('/admin/users', 'App\Http\Controllers\AdminUserController@store')->name('admin.users.store');
        Route::put('/admin/users/{user}', 'App\Http\Controllers\AdminUserController@update')->name('admin.users.update');
        Route::patch('/admin/users/{user}/toggle', 'App\Http\Controllers\AdminUserController@toggleStatus')->name('admin.users.toggle');
        Route::delete('/admin/users/{user}', 'App\Http\Controllers\AdminUserController@destroy')->name('admin.users.destroy');
        Route::get('/admin/settings', 'App\Http\Controllers\AdminSettingController@index')->name('admin.settings');
        Route::post('/admin/settings/academic-years', 'App\Http\Controllers\AdminSettingController@storeYear')->name('admin.settings.years.store');
        Route::put('/admin/settings/academic-years/{year}', 'App\Http\Controllers\AdminSettingController@updateYear')->name('admin.settings.years.update');
        Route::post('/admin/settings/update-constants', 'App\Http\Controllers\AdminSettingController@updateConstants')->name('admin.settings.constants.update');
    });

    // Role switcher
    Route::post('/switch-role', [DashboardController::class, 'switchRole'])->name('switch-role');
});

