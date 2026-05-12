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
    // ── Admin only ─────────────────────────────────────────────────────
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
        Route::delete('/admin/master-data/departments/{department}', 'App\Http\Controllers\Admin\MasterDataController@destroyDepartment')->name('admin.departments.destroy');
        Route::put('/admin/master-data/instructors/{id}', 'App\Http\Controllers\Admin\MasterDataController@updateInstructor')->name('admin.instructors.update');
        Route::post('/admin/master-data/location-types', 'App\Http\Controllers\Admin\MasterDataController@storeLocationType')->name('admin.location_types.store');
        Route::put('/admin/master-data/location-types/{locationType}', 'App\Http\Controllers\Admin\MasterDataController@updateLocationType')->name('admin.location_types.update');
        Route::delete('/admin/master-data/location-types/{locationType}', 'App\Http\Controllers\Admin\MasterDataController@destroyLocationType')->name('admin.location_types.destroy');
        Route::post('/admin/master-data/rooms', 'App\Http\Controllers\Admin\MasterDataController@storeRoom')->name('admin.rooms.store');
        Route::put('/admin/master-data/rooms/{room}', 'App\Http\Controllers\Admin\MasterDataController@updateRoom')->name('admin.rooms.update');
        Route::delete('/admin/master-data/rooms/{room}', 'App\Http\Controllers\Admin\MasterDataController@destroyRoom')->name('admin.rooms.destroy');
        Route::post('/admin/master-data/courses', 'App\Http\Controllers\Admin\MasterDataController@storeCourse')->name('admin.courses.store');
        Route::put('/admin/master-data/courses/{course}', 'App\Http\Controllers\Admin\MasterDataController@updateCourse')->name('admin.courses.update');
        Route::delete('/admin/master-data/courses/{course}', 'App\Http\Controllers\Admin\MasterDataController@destroyCourse')->name('admin.courses.destroy');
        Route::post('/admin/master-data/curriculums', 'App\Http\Controllers\Admin\MasterDataController@storeCurriculum')->name('admin.curriculums.store');
        Route::put('/admin/master-data/curriculums/{curriculum}', 'App\Http\Controllers\Admin\MasterDataController@updateCurriculum')->name('admin.curriculums.update');
        Route::post('/admin/master-data/curriculums/{curriculum}/clone', 'App\Http\Controllers\Admin\MasterDataController@cloneCurriculum')->name('admin.curriculums.clone');
        Route::delete('/admin/master-data/curriculums/{curriculum}', 'App\Http\Controllers\Admin\MasterDataController@destroyCurriculum')->name('admin.curriculums.destroy');
        Route::post('/admin/master-data/activity-types', 'App\Http\Controllers\Admin\MasterDataController@storeActivityType')->name('admin.activity_types.store');
        Route::put('/admin/master-data/activity-types/{activityType}', 'App\Http\Controllers\Admin\MasterDataController@updateActivityType')->name('admin.activity_types.update');
        Route::delete('/admin/master-data/activity-types/{activityType}', 'App\Http\Controllers\Admin\MasterDataController@destroyActivityType')->name('admin.activity_types.destroy');
        Route::post('/admin/master-data/student-groups', 'App\Http\Controllers\Admin\MasterDataController@storeStudentGroup')->name('admin.student_groups.store');
        Route::put('/admin/master-data/student-groups/{studentGroup}', 'App\Http\Controllers\Admin\MasterDataController@updateStudentGroup')->name('admin.student_groups.update');
        Route::delete('/admin/master-data/student-groups/{studentGroup}', 'App\Http\Controllers\Admin\MasterDataController@destroyStudentGroup')->name('admin.student_groups.destroy');
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
        Route::put('/staff/master-data/rooms/{room}', 'App\Http\Controllers\Staff\MasterDataController@updateRoom')->name('staff.rooms.update');
        Route::delete('/staff/master-data/rooms/{room}', 'App\Http\Controllers\Staff\MasterDataController@destroyRoom')->name('staff.rooms.destroy');
        Route::post('/staff/master-data/courses', 'App\Http\Controllers\Staff\MasterDataController@storeCourse')->name('staff.courses.store');
        Route::put('/staff/master-data/courses/{course}', 'App\Http\Controllers\Staff\MasterDataController@updateCourse')->name('staff.courses.update');
        Route::delete('/staff/master-data/courses/{course}', 'App\Http\Controllers\Staff\MasterDataController@destroyCourse')->name('staff.courses.destroy');
        Route::post('/staff/master-data/student-groups', 'App\Http\Controllers\Staff\MasterDataController@storeStudentGroup')->name('staff.student_groups.store');
        Route::put('/staff/master-data/student-groups/{studentGroup}', 'App\Http\Controllers\Staff\MasterDataController@updateStudentGroup')->name('staff.student_groups.update');
        Route::delete('/staff/master-data/student-groups/{studentGroup}', 'App\Http\Controllers\Staff\MasterDataController@destroyStudentGroup')->name('staff.student_groups.destroy');
    });

    // Role switcher
    Route::post('/switch-role', [DashboardController::class, 'switchRole'])->name('switch-role');
});

