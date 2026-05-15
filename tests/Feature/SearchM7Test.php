<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SearchM7Test extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::create([
            'username' => 'admin_m7',
            'name' => 'Admin M7',
            'email' => 'admin_m7@example.com',
            'password' => Hash::make('password'),
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role' => 'admin',
            'is_primary' => true,
        ]);

        return $user;
    }

    public function test_master_data_page_has_independent_m7_filters_per_tab(): void
    {
        $admin = $this->makeAdmin();

        $response = $this
            ->actingAs($admin)
            ->withSession(['active_role' => 'admin'])
            ->get(route('admin.master_data'));

        $response->assertOk();
        $response->assertSee('filters.instructors.keyword', false);
        $response->assertSee('filters.departments.keyword', false);
        $response->assertSee('filters.location_types.keyword', false);
        $response->assertSee('filters.courses.keyword', false);
        $response->assertSee('filters.curriculums.keyword', false);
        $response->assertSee('filters.activity_types.keyword', false);
        $response->assertDontSee('searchQuery', false);
    }
}
