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
            $table->integer('research_pct')->default(20)->after('teaching_pct')->comment('ภาระงานวิจัย (%)');
            $table->integer('service_pct')->default(10)->after('research_pct')->comment('ภาระงานบริการวิชาการ (%)');
            $table->integer('culture_pct')->default(10)->after('service_pct')->comment('ภาระงานศิลปวัฒนธรรม/พัฒนาองค์กร (%)');
            $table->integer('other_pct')->default(10)->after('culture_pct')->comment('ภาระงานอื่นๆ ที่ได้รับมอบหมาย (%)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instructor_profiles', function (Blueprint $table) {
            $table->dropColumn(['research_pct', 'service_pct', 'culture_pct', 'other_pct']);
        });
    }
};
