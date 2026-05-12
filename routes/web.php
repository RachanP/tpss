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

        Route::get('/admin/master-data', 'App\Http\Controllers\Admin\MasterDataController@index')->name('admin.master_data');
        Route::post('/admin/master-data/departments', 'App\Http\Controllers\Admin\MasterDataController@storeDepartment')->name('admin.departments.store');
        Route::put('/admin/master-data/departments/{department}', 'App\Http\Controllers\Admin\MasterDataController@updateDepartment')->name('admin.departments.update');
        Route::put('/admin/master-data/instructors/{id}', 'App\Http\Controllers\Admin\MasterDataController@updateInstructor')->name('admin.instructors.update');
        
        // Location Types
        Route::post('/admin/master-data/location-types', 'App\Http\Controllers\Admin\MasterDataController@storeLocationType')->name('admin.location_types.store');
        Route::put('/admin/master-data/location-types/{locationType}', 'App\Http\Controllers\Admin\MasterDataController@updateLocationType')->name('admin.location_types.update');

        // Rooms
        Route::post('/admin/master-data/rooms', 'App\Http\Controllers\Admin\MasterDataController@storeRoom')->name('admin.rooms.store');
        Route::put('/admin/master-data/rooms/{room}', 'App\Http\Controllers\Admin\MasterDataController@updateRoom')->name('admin.rooms.update');
    });

    // Role switcher
    Route::post('/switch-role', [DashboardController::class, 'switchRole'])->name('switch-role');
});

