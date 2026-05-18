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
        // Drop code from course_roles — use id as reference instead
        Schema::table('course_roles', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });

        // Add course_role_id FK to course_offering_instructors
        Schema::table('course_offering_instructors', function (Blueprint $table) {
            $table->foreignId('course_role_id')->nullable()->constrained('course_roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_offering_instructors', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\CourseRole::class);
        });

        Schema::table('course_roles', function (Blueprint $table) {
            $table->string('code')->unique()->nullable();
        });
    }
};
