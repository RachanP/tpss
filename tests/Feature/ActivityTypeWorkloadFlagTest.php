<?php

namespace Tests\Feature;

use App\Models\ActivityType;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ActivityTypeWorkloadFlagTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::create(['username' => 'admin_at', 'name' => 'Admin AT', 'email' => 'at@example.com', 'password' => Hash::make('p')]);
        UserRole::create(['user_id' => $u->id, 'role' => 'admin', 'is_primary' => true]);
        return $u;
    }

    public function test_create_activity_type_with_workload_flag_checked(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->post(route('admin.activity_types.store'), [
            'name' => 'บรรยาย X', 'color_code' => '#2563EB', 'category' => 'lecture',
            'counts_toward_workload' => '1',
        ])->assertRedirect(route('admin.master_data', ['tab' => 'activity_types']));

        $this->assertDatabaseHas('activity_types', ['name' => 'บรรยาย X', 'counts_toward_workload' => 1]);
    }

    public function test_create_activity_type_unchecked_is_not_counted(): void
    {
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        // ไม่ส่ง counts_toward_workload (checkbox ไม่ติ๊ก) → false
        $this->post(route('admin.activity_types.store'), [
            'name' => 'ปฐมนิเทศ X', 'color_code' => '#EA580C', 'category' => 'other',
        ])->assertRedirect(route('admin.master_data', ['tab' => 'activity_types']));

        $this->assertDatabaseHas('activity_types', ['name' => 'ปฐมนิเทศ X', 'counts_toward_workload' => 0]);
    }

    public function test_update_activity_type_can_toggle_flag(): void
    {
        $at = ActivityType::create(['name' => 'SDL X', 'color_code' => '#CA8A04', 'category' => 'other', 'counts_toward_workload' => false]);
        $this->actingAs($this->admin())->withSession(['active_role' => 'admin']);

        $this->put(route('admin.activity_types.update', $at), [
            'name' => 'SDL X', 'color_code' => '#CA8A04', 'category' => 'other',
            'counts_toward_workload' => '1',
        ])->assertRedirect(route('admin.master_data', ['tab' => 'activity_types']));

        $this->assertTrue((bool) $at->fresh()->counts_toward_workload);
    }

    public function test_seeder_sets_other_category_not_counted(): void
    {
        $this->seed(\Database\Seeders\ActivityTypeSeeder::class);

        $this->assertSame(0, (int) ActivityType::where('name', 'ปฐมนิเทศ')->value('counts_toward_workload'));
        $this->assertSame(1, (int) ActivityType::where('name', 'บรรยาย')->value('counts_toward_workload'));
    }
}
