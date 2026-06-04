<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\AdminSettingController;
use App\Models\AcademicYear;
use App\Models\SystemSetting;

class SettingController extends AdminSettingController
{
    public function index()
    {
        $academicYears = AcademicYear::with(['terms', 'calendars' => fn ($q) => $q->orderByDesc('is_default')->orderBy('name'), 'calendars.terms', 'calendars.curriculum'])
            ->orderBy('name', 'desc')->get();
        $calendarCurriculums = \App\Models\Curriculum::orderBy('education_level')->orderBy('name')
            ->get(['id', 'name', 'uses_year_level', 'duration_years']);
        $holidays = \App\Models\Holiday::orderBy('date')->get();

        $paCriteria           = [];
        $workloadWeeks        = SystemSetting::get('teaching_quota_weeks', 46);
        $teachingWeeks        = SystemSetting::get('teaching_load_weeks', 39);
        $workloadHoursPerWeek = SystemSetting::get('teaching_quota_hours_per_week', 35);
        $workloadQuota        = $workloadWeeks * $workloadHoursPerWeek;
        $teachingQuota        = $teachingWeeks * $workloadHoursPerWeek;

        $isAdmin     = false;
        $routePrefix = 'staff';

        return view('staff.settings', compact(
            'academicYears', 'calendarCurriculums', 'holidays', 'paCriteria', 'workloadQuota', 'teachingQuota',
            'workloadWeeks', 'teachingWeeks', 'workloadHoursPerWeek', 'isAdmin', 'routePrefix'
        ));
    }
}
