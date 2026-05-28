<?php

namespace App\Services;

use App\Models\ActivityType;
use App\Models\CourseRole;
use App\Models\Room;
use Illuminate\Support\Facades\Cache;

class ReferenceDataCache
{
    public const TTL = 120;

    private const ACTIVITY_TYPES_KEY = 'reference.v2.activity_types';
    private const ACTIVE_ROOMS_KEY = 'reference.v2.active_rooms';
    private const COURSE_ROLES_KEY = 'reference.v2.course_roles';

    private array $memo = [];

    public function activityTypes()
    {
        return $this->remember(__FUNCTION__, self::ACTIVITY_TYPES_KEY, fn () => ActivityType::query()
            ->select(['id', 'name', 'color_code', 'category'])
            ->orderBy('name')
            ->get()
            ->map(fn (ActivityType $type) => $type->only(['id', 'name', 'color_code', 'category']))
            ->values()
            ->all());
    }

    public function activeRooms()
    {
        return $this->remember(__FUNCTION__, self::ACTIVE_ROOMS_KEY, fn () => Room::query()
            ->select(['id', 'location_type_id', 'room_code', 'room_name', 'building', 'capacity', 'status'])
            ->with('locationType:id,name,is_shared')
            ->where('status', 'active')
            ->orderBy('room_code')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'location_type_id' => $room->location_type_id,
                'room_code' => $room->room_code,
                'room_name' => $room->room_name,
                'building' => $room->building,
                'capacity' => $room->capacity,
                'status' => $room->status,
                'locationType' => $room->locationType?->only(['id', 'name', 'is_shared']),
            ])
            ->values()
            ->all());
    }

    public function courseRoles()
    {
        return $this->remember(__FUNCTION__, self::COURSE_ROLES_KEY, fn () => CourseRole::query()
            ->select(['id', 'name_th', 'sort_order'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CourseRole $role) => $role->only(['id', 'name_th', 'sort_order']))
            ->values()
            ->all());
    }

    public static function flush(): void
    {
        Cache::forget(self::ACTIVITY_TYPES_KEY);
        Cache::forget(self::ACTIVE_ROOMS_KEY);
        Cache::forget(self::COURSE_ROLES_KEY);
    }

    public function clearMemo(): void
    {
        $this->memo = [];
    }

    private function remember(string $memoKey, string $cacheKey, callable $loader)
    {
        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        return $this->memo[$memoKey] = collect(Cache::remember($cacheKey, self::TTL, $loader))
            ->map(fn ($item) => $this->toObject($item))
            ->values();
    }

    private function toObject($value)
    {
        if (is_array($value)) {
            return (object) collect($value)
                ->map(fn ($item) => is_array($item) ? $this->toObject($item) : $item)
                ->all();
        }

        return $value;
    }
}
