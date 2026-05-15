<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Admin\AlertController;
use Illuminate\Support\Facades\Route;

// ── Root redirect ──────────────────────────────────────────────────
Route::get('/', fn() => redirect()->route('login'));

// ── Auth ───────────────────────────────────────────────────────────
Route::get('/login',  [AuthController::class, 'showLoginForm'])->name('login')->middleware(['guest', 'no-back']);
Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'no-back']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ── Authenticated ──────────────────────────────────────────────────
Route::middleware(['auth', 'no-back'])->group(function () {

    // Session keepalive — ใช้โดย session-warning.js เพื่อต่ออายุ session
    Route::get('/session/ping', fn() => response()->json(['ok' => true]))->name('session.ping');

    // Hub: redirect to role-specific dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Role-specific dashboards — guarded by role
    Route::get('/admin/dashboard',    [DashboardController::class, 'admin'])   ->name('admin.dashboard')   ->middleware('\App\Http\Middleware\CheckRole:admin');
    Route::get('/staff/dashboard',    [DashboardController::class, 'staff'])   ->name('staff.dashboard')   ->middleware('\App\Http\Middleware\CheckRole:staff');
    Route::get('/maker/dashboard',    [DashboardController::class, 'maker'])   ->name('maker.dashboard')   ->middleware('\App\Http\Middleware\CheckRole:course_head');
    Route::get('/approver/dashboard', [DashboardController::class, 'approver'])->name('approver.dashboard')->middleware('\App\Http\Middleware\CheckRole:executive');
    Route::get('/lecturer/dashboard', [DashboardController::class, 'lecturer'])->name('lecturer.dashboard')->middleware('\App\Http\Middleware\CheckRole:instructor');

    // Admin User Management
    // ── Admin only ─────────────────────────────────────────────────────
    Route::middleware(['\App\Http\Middleware\CheckRole:admin'])->group(function () {
        Route::get('/admin/users', 'App\Http\Controllers\AdminUserController@index')->name('admin.users');
        Route::post('/admin/users', 'App\Http\Controllers\AdminUserController@store')->name('admin.users.store');
        Route::post('/admin/users/import', 'App\Http\Controllers\AdminUserController@importUsers')->name('admin.users.import');
        Route::put('/admin/users/{user}', 'App\Http\Controllers\AdminUserController@update')->name('admin.users.update');
        Route::patch('/admin/users/{user}/toggle', 'App\Http\Controllers\AdminUserController@toggleStatus')->name('admin.users.toggle');
        Route::delete('/admin/users/{user}', 'App\Http\Controllers\AdminUserController@destroy')->name('admin.users.destroy');
        Route::get('/admin/settings', 'App\Http\Controllers\AdminSettingController@index')->name('admin.settings');
        Route::post('/admin/settings/academic-years', 'App\Http\Controllers\AdminSettingController@storeYear')->name('admin.settings.years.store');
        Route::put('/admin/settings/academic-years/{year}', 'App\Http\Controllers\AdminSettingController@updateYear')->name('admin.settings.years.update');
        Route::post('/admin/settings/update-constants', 'App\Http\Controllers\AdminSettingController@updateConstants')->name('admin.settings.constants.update');

        Route::get('/admin/master-data', 'App\Http\Controllers\Admin\MasterDataController@index')->name('admin.master_data');
        Route::get('/admin/alerts', [AlertController::class, 'index'])->name('admin.alerts');
        Route::post('/admin/alerts/dismissed', [AlertController::class, 'updateDismissed'])->name('admin.alerts.dismissed');
        Route::post('/admin/master-data/departments', 'App\Http\Controllers\Admin\MasterDataController@storeDepartment')->name('admin.departments.store');
        Route::put('/admin/master-data/departments/{department}', 'App\Http\Controllers\Admin\MasterDataController@updateDepartment')->name('admin.departments.update');
        Route::delete('/admin/master-data/departments/{department}', 'App\Http\Controllers\Admin\MasterDataController@destroyDepartment')->name('admin.departments.destroy');
        Route::put('/admin/master-data/instructors/{id}', 'App\Http\Controllers\Admin\MasterDataController@updateInstructor')->name('admin.instructors.update');
        Route::post('/admin/master-data/location-types', 'App\Http\Controllers\Admin\MasterDataController@storeLocationType')->name('admin.location_types.store');
        Route::put('/admin/master-data/location-types/{locationType}', 'App\Http\Controllers\Admin\MasterDataController@updateLocationType')->name('admin.location_types.update');
        Route::delete('/admin/master-data/location-types/{locationType}', 'App\Http\Controllers\Admin\MasterDataController@destroyLocationType')->name('admin.location_types.destroy');
        Route::post('/admin/master-data/rooms', 'App\Http\Controllers\Admin\MasterDataController@storeRoom')->name('admin.rooms.store');
        Route::post('/admin/master-data/rooms/import', 'App\Http\Controllers\Admin\MasterDataController@importRooms')->name('admin.rooms.import');
        Route::put('/admin/master-data/rooms/{room}', 'App\Http\Controllers\Admin\MasterDataController@updateRoom')->name('admin.rooms.update');
        Route::delete('/admin/master-data/rooms/{room}', 'App\Http\Controllers\Admin\MasterDataController@destroyRoom')->name('admin.rooms.destroy');
        Route::post('/admin/master-data/courses', 'App\Http\Controllers\Admin\MasterDataController@storeCourse')->name('admin.courses.store');
        Route::post('/admin/master-data/courses/import', 'App\Http\Controllers\Admin\MasterDataController@importCourses')->name('admin.courses.import');
        Route::put('/admin/master-data/courses/{course}', 'App\Http\Controllers\Admin\MasterDataController@updateCourse')->name('admin.courses.update');
        Route::delete('/admin/master-data/courses/{course}', 'App\Http\Controllers\Admin\MasterDataController@destroyCourse')->name('admin.courses.destroy');
        Route::post('/admin/master-data/curriculums', 'App\Http\Controllers\Admin\MasterDataController@storeCurriculum')->name('admin.curriculums.store');
        Route::put('/admin/master-data/curriculums/{curriculum}', 'App\Http\Controllers\Admin\MasterDataController@updateCurriculum')->name('admin.curriculums.update');
        Route::post('/admin/master-data/curriculums/{curriculum}/clone', 'App\Http\Controllers\Admin\MasterDataController@cloneCurriculum')->name('admin.curriculums.clone');
        Route::delete('/admin/master-data/curriculums/{curriculum}', 'App\Http\Controllers\Admin\MasterDataController@destroyCurriculum')->name('admin.curriculums.destroy');
        Route::post('/admin/master-data/activity-types', 'App\Http\Controllers\Admin\MasterDataController@storeActivityType')->name('admin.activity_types.store');
        Route::put('/admin/master-data/activity-types/{activityType}', 'App\Http\Controllers\Admin\MasterDataController@updateActivityType')->name('admin.activity_types.update');
        Route::delete('/admin/master-data/activity-types/{activityType}', 'App\Http\Controllers\Admin\MasterDataController@destroyActivityType')->name('admin.activity_types.destroy');
    });

    // ── Staff only ─────────────────────────────────────────────────────
    Route::middleware(['\App\Http\Middleware\CheckRole:staff'])->group(function () {
        Route::get('/staff/settings', 'App\Http\Controllers\Staff\SettingController@index')->name('staff.settings');
        Route::post('/staff/settings/academic-years', 'App\Http\Controllers\Staff\SettingController@storeYear')->name('staff.settings.years.store');
        Route::put('/staff/settings/academic-years/{year}', 'App\Http\Controllers\Staff\SettingController@updateYear')->name('staff.settings.years.update');

        Route::get('/staff/master-data', 'App\Http\Controllers\Staff\MasterDataController@index')->name('staff.master_data');
        Route::post('/staff/master-data/location-types', 'App\Http\Controllers\Staff\MasterDataController@storeLocationType')->name('staff.location_types.store');
        Route::put('/staff/master-data/location-types/{locationType}', 'App\Http\Controllers\Staff\MasterDataController@updateLocationType')->name('staff.location_types.update');
        Route::delete('/staff/master-data/location-types/{locationType}', 'App\Http\Controllers\Staff\MasterDataController@destroyLocationType')->name('staff.location_types.destroy');
        Route::post('/staff/master-data/rooms', 'App\Http\Controllers\Staff\MasterDataController@storeRoom')->name('staff.rooms.store');
        Route::post('/staff/master-data/rooms/import', 'App\Http\Controllers\Staff\MasterDataController@importRooms')->name('staff.rooms.import');
        Route::put('/staff/master-data/rooms/{room}', 'App\Http\Controllers\Staff\MasterDataController@updateRoom')->name('staff.rooms.update');
        Route::delete('/staff/master-data/rooms/{room}', 'App\Http\Controllers\Staff\MasterDataController@destroyRoom')->name('staff.rooms.destroy');
        Route::post('/staff/master-data/courses', 'App\Http\Controllers\Staff\MasterDataController@storeCourse')->name('staff.courses.store');
        Route::post('/staff/master-data/courses/import', 'App\Http\Controllers\Staff\MasterDataController@importCourses')->name('staff.courses.import');
        Route::put('/staff/master-data/courses/{course}', 'App\Http\Controllers\Staff\MasterDataController@updateCourse')->name('staff.courses.update');
        Route::delete('/staff/master-data/courses/{course}', 'App\Http\Controllers\Staff\MasterDataController@destroyCourse')->name('staff.courses.destroy');
    });

    // Role switcher
    Route::post('/switch-role', [DashboardController::class, 'switchRole'])->name('switch-role');

    // Profile Management
    Route::put('/profile/password', [AuthController::class, 'updatePassword'])->name('profile.password.update');
});

