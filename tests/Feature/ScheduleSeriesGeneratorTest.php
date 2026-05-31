<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\ScheduleTemplate;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ScheduleSeriesGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ScheduleSeriesGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_weekly_schedule_instances_from_template(): void
    {
        [$offering, $instructor, $group, $activityType, $room] = $this->fixture();
        $template = ScheduleTemplate::create([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityType->id,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'start_week' => 1,
            'end_week' => 3,
            'topic' => 'Weekly practicum',
        ]);

        $instances = app(ScheduleSeriesGenerator::class)->generateFromTemplate($template, [
            'room_id' => $room->id,
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ]);

        $this->assertCount(3, $instances);
        $this->assertSame(['2026-08-03', '2026-08-10', '2026-08-17'], $instances->pluck('start_date')->map->toDateString()->all());
        $this->assertSame([1, 2, 3], $instances->pluck('series_week_number')->all());
        $this->assertDatabaseHas('schedules', [
            'schedule_template_id' => $template->id,
            'series_week_number' => 2,
            'topic' => 'Weekly practicum',
        ]);
        $this->assertTrue($instances->first()->instructors()->where('users.id', $instructor->id)->wherePivot('is_lead', true)->exists());
        $this->assertTrue($instances->first()->studentGroups()->where('student_groups.id', $group->id)->exists());
    }

    public function test_syncing_template_preserves_instance_room_and_groups(): void
    {
        [$offering, $instructor, $group, $activityType, $room] = $this->fixture();
        $otherRoom = Room::create([
            'room_code' => 'R2',
            'room_name' => 'Room 2',
            'location_type_id' => LocationType::first()->id,
            'status' => 'active',
        ]);
        $otherGroup = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'B1',
            'student_count' => 12,
        ]);
        $otherActivity = ActivityType::create([
            'name' => 'Lab',
            'color_code' => '#16a34a',
            'category' => 'practicum',
        ]);
        $template = ScheduleTemplate::create([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityType->id,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'start_week' => 1,
            'end_week' => 2,
            'topic' => 'Original topic',
        ]);

        $instances = app(ScheduleSeriesGenerator::class)->generateFromTemplate($template, [
            'room_id' => $room->id,
            'instructor_ids' => [$instructor->id],
            'student_group_ids' => [$group->id],
        ]);
        $edited = $instances->last();
        $edited->update(['room_id' => $otherRoom->id]);
        $edited->studentGroups()->sync([$otherGroup->id]);

        $template->update([
            'activity_type_id' => $otherActivity->id,
            'weekday' => 2,
            'start_time' => '13:00',
            'end_time' => '15:00',
            'topic' => 'Updated topic',
        ]);

        app(ScheduleSeriesGenerator::class)->syncInstancesFromTemplate($template);
        $edited->refresh();

        $this->assertSame($otherRoom->id, (int) $edited->room_id);
        $this->assertSame([$otherGroup->id], $edited->studentGroups()->pluck('student_groups.id')->all());
        $this->assertSame($otherActivity->id, (int) $edited->activity_type_id);
        $this->assertSame('2026-08-11', $edited->start_date->toDateString());
        $this->assertSame('13:00:00', (string) $edited->start_time);
        $this->assertSame('Updated topic', $edited->topic);
    }

    public function test_course_head_can_create_weekly_series_through_route(): void
    {
        [$offering, $instructor, $group, $activityType, $room, $head] = $this->fixture();

        $this->actingAs($head);
        $this->withSession(['active_role' => 'course_head']);

        $this->post(route('maker.course_offerings.schedules.series.store', $offering), [
            'weekday' => 1,
            'start_week' => 1,
            'end_week' => 2,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'topic' => 'Route weekly practicum',
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedule_templates', [
            'course_offering_id' => $offering->id,
            'topic' => 'Route weekly practicum',
            'start_week' => 1,
            'end_week' => 2,
        ]);

        $instances = $offering->schedules()
            ->where('topic', 'Route weekly practicum')
            ->orderBy('series_week_number')
            ->get();

        $this->assertCount(2, $instances);
        $this->assertSame($room->id, (int) $instances->first()->room_id);
        $this->assertTrue($instances->first()->instructors()->where('users.id', $instructor->id)->wherePivot('is_lead', true)->exists());
        $this->assertTrue($instances->first()->studentGroups()->where('student_groups.id', $group->id)->exists());
        $this->assertNull($instances->last()->room_id);
        $this->assertSame(0, $instances->last()->instructors()->count());
        $this->assertSame(0, $instances->last()->studentGroups()->count());
    }

    public function test_course_head_can_create_weekly_series_without_room_instructors_or_groups(): void
    {
        [$offering, $instructor, $group, $activityType, $room, $head] = $this->fixture();

        $this->actingAs($head);
        $this->withSession(['active_role' => 'course_head']);

        $this->post(route('maker.course_offerings.schedules.series.store', $offering), [
            'weekday' => 1,
            'start_week' => 1,
            'end_week' => 2,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'activity_type_id' => $activityType->id,
            'topic' => 'Rotation shell',
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $instances = $offering->schedules()->where('topic', 'Rotation shell')->orderBy('series_week_number')->get();

        $this->assertCount(2, $instances);
        $this->assertTrue($instances->every(fn ($schedule) => $schedule->room_id === null));
        $this->assertSame(0, $instances->first()->instructors()->count());
        $this->assertSame(0, $instances->first()->studentGroups()->count());

        $this->get(route('maker.course_offerings.schedules.index', $offering))
            ->assertOk()
            ->assertSee('ข้อมูลยังไม่ครบ')
            ->assertSee('รอกำหนดผู้สอน')
            ->assertSee('รอกำหนดกลุ่ม');
    }

    public function test_weekly_series_is_clamped_to_academic_year_dates(): void
    {
        [$offering, $instructor, $group, $activityType, $room] = $this->fixture();
        $template = ScheduleTemplate::create([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityType->id,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'start_week' => 1,
            'end_week' => 30,
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-02-28',
            'topic' => 'Clamped practicum',
        ]);

        $instances = app(ScheduleSeriesGenerator::class)->generateFromTemplate($template, [
            'room_id' => $room->id,
            'instructor_ids' => [$instructor->id],
            'student_group_ids' => [$group->id],
        ]);

        $this->assertCount(22, $instances);
        $this->assertSame('2026-08-03', $instances->first()->start_date->toDateString());
        $this->assertSame('2026-12-28', $instances->last()->start_date->toDateString());
        $this->assertTrue($instances->every(fn ($schedule) => $schedule->start_date->betweenIncluded('2026-08-01', '2026-12-31')));
    }

    public function test_schedule_create_modal_includes_weekly_series_action(): void
    {
        [$offering, $instructor, $group, $activityType, $room, $head] = $this->fixture();

        $this->actingAs($head);
        $this->withSession(['active_role' => 'course_head']);

        $response = $this->get(route('maker.course_offerings.schedules.index', [$offering, 'modal' => 'create']));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertTrue(str_contains($html, 'data-testid="schedule-repeat-weekly"'), 'Missing weekly repeat controls');
        $this->assertTrue(str_contains($html, 'seriesCreateAction'), 'Missing series create action binding');
        $this->assertTrue(str_contains($html, 'data-testid="schedule-series-toggle"'), 'Missing weekly series toggle');
        $this->assertTrue(str_contains($html, 'ซ้ำรายสัปดาห์'), 'Missing weekly series label');
    }

    public function test_series_instance_update_preserves_template_owned_fields(): void
    {
        [$offering, $instructor, $group, $activityType, $room, $head] = $this->fixture();
        $otherActivity = ActivityType::create([
            'name' => 'Other',
            'color_code' => '#a87600',
            'category' => 'other',
        ]);
        $template = ScheduleTemplate::create([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityType->id,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'start_week' => 1,
            'end_week' => 1,
            'topic' => 'Template topic',
        ]);
        $instance = app(ScheduleSeriesGenerator::class)->generateFromTemplate($template, [
            'room_id' => $room->id,
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ])->first();

        $this->actingAs($head);
        $this->withSession(['active_role' => 'course_head']);

        $this->put(route('maker.course_offerings.schedules.update', [$offering, $instance]), [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'start_time' => '13:00',
            'end_time' => '15:00',
            'activity_type_id' => $otherActivity->id,
            'room_id' => null,
            'topic' => 'Tampered topic',
            'capacity_required' => 99,
            'sub_group_label' => 'Z',
            'remark' => 'Weekly room pending',
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $instance->refresh();

        $this->assertSame($activityType->id, (int) $instance->activity_type_id);
        $this->assertSame('2026-08-03', $instance->start_date->toDateString());
        $this->assertSame('09:00:00', (string) $instance->start_time);
        $this->assertSame('Template topic', $instance->topic);
        $this->assertNull($instance->room_id);
        $this->assertSame('Weekly room pending', $instance->remark);
    }

    public function test_series_instance_update_can_change_weekly_instructors_and_groups(): void
    {
        [$offering, $instructor, $group, $activityType, $room, $head] = $this->fixture();
        $otherInstructor = $this->user('instructor', $offering->course->department);
        $offering->instructorPool()->attach($otherInstructor->id, ['role_in_course' => 'instructor']);
        $otherGroup = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'B1',
            'student_count' => 12,
        ]);
        $template = ScheduleTemplate::create([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityType->id,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'start_week' => 1,
            'end_week' => 1,
            'topic' => 'Template topic',
        ]);
        $instance = app(ScheduleSeriesGenerator::class)->generateFromTemplate($template, [
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ])->first();

        $this->actingAs($head);
        $this->withSession(['active_role' => 'course_head']);

        $this->put(route('maker.course_offerings.schedules.update', [$offering, $instance]), [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'start_time' => '13:00',
            'end_time' => '15:00',
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'topic' => 'Tampered topic',
            'instructor_ids' => [$otherInstructor->id],
            'lead_instructor_id' => $otherInstructor->id,
            'student_group_ids' => [$otherGroup->id],
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $instance->refresh();

        $this->assertSame([$otherInstructor->id], $instance->instructors()->pluck('users.id')->all());
        $this->assertTrue($instance->instructors()->where('users.id', $otherInstructor->id)->wherePivot('is_lead', true)->exists());
        $this->assertSame([$otherGroup->id], $instance->studentGroups()->pluck('student_groups.id')->all());
        $this->assertSame('Template topic', $instance->topic);
        $this->assertSame('09:00:00', (string) $instance->start_time);
    }

    public function test_course_head_can_update_weekly_series_template_through_route(): void
    {
        [$offering, $instructor, $group, $activityType, $room, $head] = $this->fixture();
        $otherRoom = Room::create([
            'room_code' => 'R3',
            'room_name' => 'Room 3',
            'location_type_id' => LocationType::first()->id,
            'status' => 'active',
        ]);
        $otherActivity = ActivityType::create([
            'name' => 'Updated Activity',
            'color_code' => '#2d7a3d',
            'category' => 'practicum',
        ]);
        $template = ScheduleTemplate::create([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityType->id,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'start_week' => 1,
            'end_week' => 2,
            'topic' => 'Before update',
        ]);
        $instances = app(ScheduleSeriesGenerator::class)->generateFromTemplate($template, [
            'room_id' => $room->id,
            'instructor_ids' => [$instructor->id],
            'student_group_ids' => [$group->id],
        ]);
        $edited = $instances->last();
        $edited->update(['room_id' => $otherRoom->id]);

        $this->actingAs($head);
        $this->withSession(['active_role' => 'course_head']);

        $this->put(route('maker.course_offerings.schedules.templates.update', [$offering, $template]), [
            'weekday' => 3,
            'start_week' => 1,
            'end_week' => 2,
            'start_time' => '13:00',
            'end_time' => '16:00',
            'activity_type_id' => $otherActivity->id,
            'topic' => 'After update',
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $edited->refresh();

        $this->assertSame($otherRoom->id, (int) $edited->room_id);
        $this->assertSame($otherActivity->id, (int) $edited->activity_type_id);
        $this->assertSame('After update', $edited->topic);
        $this->assertSame('2026-08-12', $edited->start_date->toDateString());
        $this->assertSame('13:00:00', (string) $edited->start_time);
    }

    public function test_course_head_can_delete_weekly_series_from_current_week(): void
    {
        [$offering, $instructor, $group, $activityType, $room, $head] = $this->fixture();
        $template = ScheduleTemplate::create([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityType->id,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'start_week' => 1,
            'end_week' => 4,
            'topic' => 'Delete from week',
        ]);
        app(ScheduleSeriesGenerator::class)->generateFromTemplate($template, [
            'room_id' => $room->id,
            'instructor_ids' => [$instructor->id],
            'student_group_ids' => [$group->id],
        ]);
        $weekThree = $template->schedules()->where('series_week_number', 3)->firstOrFail();

        $this->actingAs($head);
        $this->withSession(['active_role' => 'course_head']);

        $this->delete(route('maker.course_offerings.schedules.destroy', [$offering, $weekThree]), [
            'series_delete_scope' => 'from_current',
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedule_templates', [
            'id' => $template->id,
            'end_week' => 2,
        ]);
        $this->assertSame([1, 2], $template->schedules()->orderBy('series_week_number')->pluck('series_week_number')->all());
    }

    public function test_course_head_can_delete_entire_weekly_series(): void
    {
        [$offering, $instructor, $group, $activityType, $room, $head] = $this->fixture();
        $template = ScheduleTemplate::create([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityType->id,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'start_week' => 1,
            'end_week' => 3,
            'topic' => 'Delete all weeks',
        ]);
        app(ScheduleSeriesGenerator::class)->generateFromTemplate($template, [
            'room_id' => $room->id,
            'instructor_ids' => [$instructor->id],
            'student_group_ids' => [$group->id],
        ]);
        $firstSchedule = $template->schedules()->where('series_week_number', 1)->firstOrFail();

        $this->actingAs($head);
        $this->withSession(['active_role' => 'course_head']);

        $this->delete(route('maker.course_offerings.schedules.destroy', [$offering, $firstSchedule]), [
            'series_delete_scope' => 'all',
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('schedule_templates', ['id' => $template->id]);
        $this->assertDatabaseMissing('schedules', ['schedule_template_id' => $template->id]);
    }

    public function test_weekly_series_supports_custom_date_range(): void
    {
        [$offering, $instructor, $group, $activityType, $room, $head] = $this->fixture();

        $this->actingAs($head);
        $this->withSession(['active_role' => 'course_head']);

        $this->post(route('maker.course_offerings.schedules.series.store', $offering), [
            'weekday' => 1,
            'start_week' => 1,
            'end_week' => 10,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'activity_type_id' => $activityType->id,
            'topic' => 'Custom range series',
            'use_custom_series_range' => '1',
            'starts_on' => '17/08/2569',
            'ends_on' => '07/09/2569',
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $template = ScheduleTemplate::where('topic', 'Custom range series')->firstOrFail();
        $this->assertSame('2026-08-17', $template->starts_on?->toDateString());
        $this->assertSame('2026-09-07', $template->ends_on?->toDateString());

        $instances = $offering->schedules()->where('topic', 'Custom range series')->orderBy('start_date')->get();
        $this->assertSame(['2026-08-17', '2026-08-24', '2026-08-31', '2026-09-07'], $instances->pluck('start_date')->map->toDateString()->all());
    }

    public function test_weekly_series_detects_instructor_conflict_across_offerings(): void
    {
        [$offering, $instructor, $group, $activityType, $room, $head] = $this->fixture();
        [$otherOffering, $otherInstructor, $otherGroup] = $this->secondOffering($head, $offering, $instructor);

        // series ใน offering แรก: Monday 09:00-11:00 — สัปดาห์ 1-3 (3 instances)
        $this->actingAs($head);
        $this->withSession(['active_role' => 'course_head']);
        $this->post(route('maker.course_offerings.schedules.series.store', $offering), [
            'weekday' => 1,
            'start_week' => 1,
            'end_week' => 3,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'topic' => 'Series A',
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ])->assertSessionHasNoErrors();

        // slot ใน offering อื่น Monday เดียวกัน 10:00-12:00 ทับเวลา — อาจารย์คนเดียวกัน
        $response = $this->post(route('maker.course_offerings.schedules.store', $otherOffering), [
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'start_date' => '03/08/2569',
            'end_date' => '03/08/2569',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'topic' => 'Cross-course instructor slot',
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$otherGroup->id],
        ]);
        $response->assertRedirect()
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseMissing('schedules', [
            'course_offering_id' => $otherOffering->id,
            'topic' => 'Cross-course instructor slot',
        ]);

    }

    public function test_weekly_series_detects_room_conflict_across_offerings(): void
    {
        [$offering, $instructor, $group, $activityType, $room, $head] = $this->fixture();
        [$otherOffering, $otherInstructor, $otherGroup] = $this->secondOffering($head, $offering, $instructor);

        // series A — Monday 13:00-15:00 ในห้อง R1
        $this->actingAs($head);
        $this->withSession(['active_role' => 'course_head']);
        $this->post(route('maker.course_offerings.schedules.series.store', $offering), [
            'weekday' => 1,
            'start_week' => 1,
            'end_week' => 2,
            'start_time' => '13:00',
            'end_time' => '15:00',
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'topic' => 'Series room A',
            'instructor_ids' => [$instructor->id],
            'lead_instructor_id' => $instructor->id,
            'student_group_ids' => [$group->id],
        ])->assertSessionHasNoErrors();

        // slot ใน offering อื่น ห้องเดียวกัน 14:00-16:00 — อาจารย์คนละคน
        $this->post(route('maker.course_offerings.schedules.store', $otherOffering), [
            'activity_type_id' => $activityType->id,
            'room_id' => $room->id,
            'start_date' => '03/08/2569',
            'end_date' => '03/08/2569',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'topic' => 'Cross-course room slot',
            'instructor_ids' => [$otherInstructor->id],
            'lead_instructor_id' => $otherInstructor->id,
            'student_group_ids' => [$otherGroup->id],
        ])->assertRedirect()
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseMissing('schedules', [
            'course_offering_id' => $otherOffering->id,
            'topic' => 'Cross-course room slot',
        ]);
    }

    public function test_course_head_cannot_update_series_template_of_other_coordinator(): void
    {
        [$offering, $instructor, $group, $activityType, $room, $head] = $this->fixture();
        $template = ScheduleTemplate::create([
            'course_offering_id' => $offering->id,
            'activity_type_id' => $activityType->id,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'start_week' => 1,
            'end_week' => 2,
            'topic' => 'Owned by head A',
        ]);

        // user ที่ไม่ใช่ coordinator ของ offering
        $otherHead = $this->user('course_head', Department::firstOrFail());
        $this->actingAs($otherHead);
        $this->withSession(['active_role' => 'course_head']);

        $this->put(route('maker.course_offerings.schedules.templates.update', [$offering, $template]), [
            'weekday' => 2,
            'start_week' => 1,
            'end_week' => 2,
            'start_time' => '13:00',
            'end_time' => '15:00',
            'activity_type_id' => $activityType->id,
            'topic' => 'Hacked',
        ])
            ->assertStatus(403);

        $template->refresh();
        $this->assertSame('Owned by head A', $template->topic);
    }

    public function test_course_head_cannot_delete_series_template_of_other_coordinator(): void
    {
        [$offering, $instructor, $group, $activityType, $room, $head] = $this->fixture();
        app(ScheduleSeriesGenerator::class)->generateFromTemplate(
            ScheduleTemplate::create([
                'course_offering_id' => $offering->id,
                'activity_type_id' => $activityType->id,
                'weekday' => 1,
                'start_time' => '09:00',
                'end_time' => '11:00',
                'start_week' => 1,
                'end_week' => 2,
                'topic' => 'Cannot touch',
            ]),
            ['room_id' => $room->id, 'instructor_ids' => [$instructor->id], 'student_group_ids' => [$group->id]]
        );
        $firstSchedule = $offering->schedules()->where('topic', 'Cannot touch')->orderBy('series_week_number')->firstOrFail();

        $otherHead = $this->user('course_head', Department::firstOrFail());
        $this->actingAs($otherHead);
        $this->withSession(['active_role' => 'course_head']);

        $this->delete(route('maker.course_offerings.schedules.destroy', [$offering, $firstSchedule]), [
            'series_delete_scope' => 'all',
        ])->assertStatus(403);

        $this->assertDatabaseHas('schedule_templates', ['topic' => 'Cannot touch']);
        $this->assertDatabaseHas('schedules', ['id' => $firstSchedule->id]);
    }

    private function secondOffering(User $head, CourseOffering $primary, User $sharedInstructor): array
    {
        $department = Department::firstOrFail();
        $curriculum = Curriculum::firstOrFail();
        $course = Course::create([
            'course_code' => 'SER102',
            'curriculum_id' => $curriculum->id,
            'department_id' => $department->id,
            'head_instructor_id' => $head->id,
            'name_th' => 'Series Course B',
            'name_en' => 'Series Course B',
            'course_type' => 'theory',
            'default_year_level' => 2,
            'default_semester' => 1,
            'requires_practicum_rotation' => false,
            'credits' => 3,
            'lecture_hours' => 3,
            'lab_hours' => 0,
            'self_study_hours' => 6,
            'status' => 'active',
        ]);
        $offering = CourseOffering::create([
            'course_id' => $course->id,
            'academic_year_id' => $primary->academic_year_id,
            'coordinator_id' => $head->id,
            'approval_status' => 'draft',
            'total_student_count' => 30,
            'teaching_weeks' => 16,
        ]);
        $otherInstructor = $this->user('instructor', $department);
        $offering->instructorPool()->attach([
            $sharedInstructor->id => ['role_in_course' => 'instructor'],
            $otherInstructor->id => ['role_in_course' => 'instructor'],
        ]);
        $group = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'B1',
            'student_count' => 15,
        ]);

        return [$offering, $otherInstructor, $group];
    }

    private function fixture(): array
    {
        $department = Department::create(['name' => 'Nursing']);
        $curriculum = Curriculum::create([
            'name' => 'BN',
            'effective_year' => 2569,
            'is_active' => true,
        ]);
        $head = $this->user('course_head', $department);
        $instructor = $this->user('instructor', $department);
        $year = AcademicYear::create([
            'name' => '2570',
            'semester' => 1,
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
            'phase' => 'scheduling',
        ]);
        $course = Course::create([
            'course_code' => 'SER101',
            'curriculum_id' => $curriculum->id,
            'department_id' => $department->id,
            'head_instructor_id' => $head->id,
            'name_th' => 'Series Course',
            'name_en' => 'Series Course',
            'course_type' => 'theory_practicum',
            'default_year_level' => 2,
            'default_semester' => 1,
            'requires_practicum_rotation' => true,
            'credits' => 3,
            'lecture_hours' => 2,
            'lab_hours' => 1,
            'self_study_hours' => 3,
            'status' => 'active',
        ]);
        $offering = CourseOffering::create([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id' => $head->id,
            'approval_status' => 'draft',
            'total_student_count' => 30,
            'teaching_weeks' => 16,
        ]);
        $offering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);
        $group = StudentGroup::create([
            'course_offering_id' => $offering->id,
            'group_code' => 'A1',
            'student_count' => 15,
        ]);
        $activityType = ActivityType::create([
            'name' => 'Practicum',
            'color_code' => '#2563eb',
            'category' => 'practicum',
        ]);
        $locationType = LocationType::create(['name' => 'Classroom']);
        $room = Room::create([
            'room_code' => 'R1',
            'room_name' => 'Room 1',
            'location_type_id' => $locationType->id,
            'status' => 'active',
        ]);

        return [$offering, $instructor, $group, $activityType, $room, $head];
    }

    private function user(string $role, Department $department): User
    {
        $user = User::create([
            'username' => $role . '_' . uniqid(),
            'name' => $role,
            'email' => $role . '_' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_primary' => true]);
        InstructorProfile::create([
            'user_id' => $user->id,
            'title' => 'Instructor',
            'department_id' => $department->id,
        ]);

        return $user;
    }
}
