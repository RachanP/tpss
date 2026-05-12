<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\AcademicYear;
use App\Models\SystemSetting;

class AdminSettingController extends Controller
{
    public function index()
    {
        $academicYears = AcademicYear::orderBy('name', 'desc')->orderBy('semester', 'desc')->get();
        $paCriteria = json_decode(SystemSetting::get('pa_criteria_config', '{}'), true);
        
        $workloadWeeks = SystemSetting::get('teaching_quota_weeks', 46);
        $teachingWeeks = SystemSetting::get('teaching_load_weeks', 39);
        $workloadHoursPerWeek = SystemSetting::get('teaching_quota_hours_per_week', 35);
        $workloadQuota = SystemSetting::get('teaching_quota_hours', 1610);
        $teachingQuota = $teachingWeeks * $workloadHoursPerWeek;
        
        // Default PA if not set
        if (empty($paCriteria)) {
            $paCriteria = [
                'อาจารย์' => ['t' => '20-70%', 'r' => '20-70%', 's' => '5-20%', 'c' => '5-15%', 'o' => '0-20%'],
                'ผู้ช่วยอาจารย์' => ['t' => '≤ 70%', 'r' => '15-20%', 's' => '5-20%', 'c' => '5-20%', 'o' => '0-20%'],
                'ผู้ช่วยอาจารย์_ปตรี' => ['t' => '30-60%', 'r' => '0%', 's' => '10-30%', 'c' => '10-20%', 'o' => '0-30%'],
                'ผู้ช่วยอาจารย์_คลินิก' => ['t' => '≤ 10%', 'r' => '0-5%', 's' => '70-80%', 'c' => '0-5%', 'o' => '0-10%'],
                'ผู้ช่วยอาจารย์_ปฏิบัติ' => ['t' => '≤ 70%', 'r' => '0%', 's' => '5-20%', 'c' => '5-20%', 'o' => '0-20%'],
            ];
        }

        return view('admin.settings', compact('academicYears', 'paCriteria', 'workloadQuota', 'teachingQuota', 'workloadWeeks', 'teachingWeeks', 'workloadHoursPerWeek'));
    }

    public function storeYear(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string',
            'semester'   => 'required|integer',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        $validated['is_active'] = $request->has('is_active');

        if ($validated['is_active']) {
            AcademicYear::where('is_active', true)->update(['is_active' => false]);

            // Auto-sync courses status based on default_semester AND curriculum status
            // 1. Force inactive for any course whose curriculum is inactive
            \App\Models\Course::whereHas('curriculum', function($q) {
                $q->where('is_active', false);
            })->update(['status' => 'inactive']);

            // 2. For active curriculums, open courses matching the semester
            \App\Models\Course::whereHas('curriculum', function($q) {
                $q->where('is_active', true);
            })->where('default_semester', $validated['semester'])->update(['status' => 'active']);

            // 3. For active curriculums, close courses NOT matching the semester
            \App\Models\Course::whereHas('curriculum', function($q) {
                $q->where('is_active', true);
            })->where(function($query) use ($validated) {
                $query->where('default_semester', '!=', $validated['semester'])
                      ->orWhereNull('default_semester');
            })->update(['status' => 'inactive']);
        }

        AcademicYear::create($validated);

        return redirect()->back()->with('success', 'เพิ่มปีการศึกษาเรียบร้อยแล้ว');
    }

    public function updateYear(Request $request, AcademicYear $year)
    {
        $validated = $request->validate([
            'name'       => 'required|string',
            'semester'   => 'required|integer',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        $validated['is_active'] = $request->has('is_active');

        if ($validated['is_active']) {
            AcademicYear::where('id', '!=', $year->id)->where('is_active', true)->update(['is_active' => false]);

            // Auto-sync courses status based on default_semester AND curriculum status
            // 1. Force inactive for any course whose curriculum is inactive
            \App\Models\Course::whereHas('curriculum', function($q) {
                $q->where('is_active', false);
            })->update(['status' => 'inactive']);

            // 2. For active curriculums, open courses matching the semester
            \App\Models\Course::whereHas('curriculum', function($q) {
                $q->where('is_active', true);
            })->where('default_semester', $validated['semester'])->update(['status' => 'active']);

            // 3. For active curriculums, close courses NOT matching the semester
            \App\Models\Course::whereHas('curriculum', function($q) {
                $q->where('is_active', true);
            })->where(function($query) use ($validated) {
                $query->where('default_semester', '!=', $validated['semester'])
                      ->orWhereNull('default_semester');
            })->update(['status' => 'inactive']);
        }

        $year->update($validated);

        return redirect()->back()->with('success', 'อัปเดตปีการศึกษาเรียบร้อยแล้ว');
    }

    public function updateConstants(Request $request)
    {
        $request->validate([
            'teaching_quota_weeks' => 'required|numeric|min:1',
            'teaching_load_weeks' => 'required|numeric|min:1',
            'teaching_quota_hours_per_week' => 'required|numeric|min:1',
            'pa_criteria' => 'required|array'
        ]);

        // Calculate total hours
        $totalHours = $request->teaching_quota_weeks * $request->teaching_quota_hours_per_week;

        SystemSetting::set('teaching_quota_weeks', $request->teaching_quota_weeks);
        SystemSetting::set('teaching_load_weeks', $request->teaching_load_weeks);
        SystemSetting::set('teaching_quota_hours_per_week', $request->teaching_quota_hours_per_week);
        SystemSetting::set('teaching_quota_hours', $totalHours);
        SystemSetting::set('pa_criteria_config', json_encode($request->pa_criteria));

        return redirect()->route('admin.settings', ['tab' => 'pa'])->with('success', 'บันทึกค่าคงที่และเกณฑ์ PA เรียบร้อยแล้ว');
    }
}
