<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_instructors', function (Blueprint $table) {
            $table->foreignId('course_role_id')->nullable()->after('user_id')
                ->constrained('course_roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_instructors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_role_id');
        });
    }
};
