<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('course_offering_instructors', function (Blueprint $table) {
            $table->string('role_in_course', 100)->default('instructor')->change();
        });
    }

    public function down(): void
    {
        Schema::table('course_offering_instructors', function (Blueprint $table) {
            $table->enum('role_in_course', ['coordinator', 'secretary', 'instructor', 'group_advisor', 'preceptor'])
                ->default('instructor')->change();
        });
    }
};
