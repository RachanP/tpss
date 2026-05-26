<?php

namespace App\Services;

use App\Http\Controllers\Admin\AlertController;
use App\Models\ActivityType;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\StudentGroup;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Model;

class PerformanceCacheInvalidator
{
    public static function flushForModel(Model $model): void
    {
        if ($model instanceof Schedule) {
            self::flushScheduleOwner($model);
        }

        if ($model instanceof CourseOffering) {
            NavigationBadgeService::flushCourseHead((int) $model->coordinator_id);
        }

        if ($model instanceof StudentGroup) {
            $model->loadMissing('courseOffering:id,coordinator_id');
            NavigationBadgeService::flushCourseHead((int) $model->courseOffering?->coordinator_id);
        }

        if ($model instanceof Room || $model instanceof LocationType || $model instanceof ActivityType || $model instanceof CourseRole) {
            ReferenceDataCache::flush();
            app(ReferenceDataCache::class)->clearMemo();
        }

        if (
            $model instanceof Room
            || $model instanceof LocationType
            || $model instanceof ActivityType
            || $model instanceof AcademicYear
            || $model instanceof Course
            || $model instanceof Curriculum
            || $model instanceof Department
            || $model instanceof InstructorProfile
            || $model instanceof SystemSetting
            || $model instanceof User
            || $model instanceof UserRole
        ) {
            AlertController::flushCache();
        }
    }

    private static function flushScheduleOwner(Schedule $schedule): void
    {
        $schedule->loadMissing('courseOffering:id,coordinator_id');
        NavigationBadgeService::flushCourseHead((int) $schedule->courseOffering?->coordinator_id);
    }
}
