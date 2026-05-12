<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\AdminSettingController;
use App\Models\AcademicYear;
use App\Models\SystemSetting;

class SettingController extends AdminSettingController
{
    public function index()
    {
        $academicYears = AcademicYear::orderBy('name', 'desc')->orderBy('semester', 'asc')->get();

        // PA data still needed so the shared view compiles without errors
        $paCriteria           = [];
        $workloadWeeks        = SystemSetting::get('teaching_quota_weeks', 46);
        $teachingWeeks        = SystemSetting::get('teaching_load_weeks', 39);
        $workloadHoursPerWeek = SystemSetting::get('teaching_quota_hours_per_week', 35);
        $workloadQuota        = $workloadWeeks * $workloadHoursPerWeek;
        $teachingQuota        = $teachingWeeks * $workloadHoursPerWeek;

        $isAdmin     = false;
        $routePrefix = 'staff';

        return view('staff.settings', compact(
            'academicYears', 'paCriteria', 'workloadQuota', 'teachingQuota',
            'workloadWeeks', 'teachingWeeks', 'workloadHoursPerWeek', 'isAdmin', 'routePrefix'
        ));
    }
}
