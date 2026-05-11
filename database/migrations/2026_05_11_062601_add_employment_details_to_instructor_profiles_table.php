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
        Schema::table('instructor_profiles', function (Blueprint $table) {
            $table->string('employment_type')->nullable()->after('department_id'); // พนักงานมหาวิทยาลัย, ข้าราชการ
            $table->date('hired_at')->nullable()->after('employment_type'); // วันบรรจุ
            $table->string('academic_degree')->nullable()->after('hired_at'); // วุฒิการศึกษา (ป.ตรี, ป.โท, ป.เอก)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instructor_profiles', function (Blueprint $table) {
            $table->dropColumn(['employment_type', 'hired_at', 'academic_degree']);
        });
    }
};
