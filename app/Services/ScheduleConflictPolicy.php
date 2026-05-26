<?php

namespace App\Services;

use App\Models\Schedule;

class ScheduleConflictPolicy
{
    public function suppresses(string $type, Schedule $left, Schedule $right): bool
    {
        return match ($type) {
            'instructor_overlap' => $this->isTeamSupervision($left, $right),
            'group_overlap' => $this->isSubgroupPracticumSplit($left, $right),
            default => false,
        };
    }

    private function isTeamSupervision(Schedule $left, Schedule $right): bool
    {
        return $this->sameDateTime($left, $right)
            && $this->samePracticumContext($left, $right)
            && $this->studentGroupsAreDisjoint($left, $right);
    }

    private function isSubgroupPracticumSplit(Schedule $left, Schedule $right): bool
    {
        $leftLabel = trim((string) $left->sub_group_label);
        $rightLabel = trim((string) $right->sub_group_label);

        return (int) $left->course_offering_id === (int) $right->course_offering_id
            && $leftLabel !== ''
            && $rightLabel !== ''
            && mb_strtolower($leftLabel) !== mb_strtolower($rightLabel)
            && $left->room_id
            && $right->room_id
            && (int) $left->room_id !== (int) $right->room_id
            && $this->sameDateTime($left, $right)
            && $this->samePracticumContext($left, $right)
            && $this->sharedStudentGroupIds($left, $right) !== [];
    }

    private function samePracticumContext(Schedule $left, Schedule $right): bool
    {
        if (
            $left->practicum_series_id
            && $right->practicum_series_id
            && (int) $left->practicum_series_id === (int) $right->practicum_series_id
        ) {
            return true;
        }

        return (int) $left->course_offering_id === (int) $right->course_offering_id
            && $this->isPracticumSchedule($left)
            && $this->isPracticumSchedule($right);
    }

    private function isPracticumSchedule(Schedule $schedule): bool
    {
        return $schedule->activityType?->category === 'practicum';
    }

    private function sameDateTime(Schedule $left, Schedule $right): bool
    {
        return $this->dateString($left->start_date ?? $left->teaching_date) === $this->dateString($right->start_date ?? $right->teaching_date)
            && $this->dateString($left->end_date ?? $left->teaching_date) === $this->dateString($right->end_date ?? $right->teaching_date)
            && substr((string) $left->start_time, 0, 5) === substr((string) $right->start_time, 0, 5)
            && substr((string) $left->end_time, 0, 5) === substr((string) $right->end_time, 0, 5);
    }

    private function studentGroupsAreDisjoint(Schedule $left, Schedule $right): bool
    {
        $leftIds = $this->studentGroupIdSet($left);
        $rightIds = $this->studentGroupIdSet($right);

        if ($leftIds === [] || $rightIds === []) {
            return false;
        }

        foreach ($leftIds as $id => $_) {
            if (isset($rightIds[$id])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int>
     */
    private function sharedStudentGroupIds(Schedule $left, Schedule $right): array
    {
        $leftIds = $this->studentGroupIdSet($left);
        $rightIds = $this->studentGroupIdSet($right);
        $shared = [];

        foreach ($leftIds as $id => $_) {
            if (isset($rightIds[$id])) {
                $shared[] = (int) $id;
            }
        }

        return $shared;
    }

    /**
     * @return array<int, true>
     */
    private function studentGroupIdSet(Schedule $schedule): array
    {
        return $schedule->studentGroups
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->mapWithKeys(fn (int $id) => [$id => true])
            ->all();
    }

    private function dateString($date): ?string
    {
        return $date ? $date->toDateString() : null;
    }
}
