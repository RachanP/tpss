<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('schedules', 'schedule_template_id')) {
                $table->unsignedBigInteger('schedule_template_id')->nullable()->after('practicum_series_id');
                $table->foreign('schedule_template_id')->references('id')->on('schedule_templates');
            }

            if (! Schema::hasColumn('schedules', 'series_week_number')) {
                $table->unsignedTinyInteger('series_week_number')->nullable()->after('schedule_template_id');
            }

            $table->index(['schedule_template_id', 'series_week_number'], 'schedules_template_week_index');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedules_template_week_index');

            if (Schema::hasColumn('schedules', 'schedule_template_id')) {
                $table->dropForeign(['schedule_template_id']);
            }

            $table->dropColumn(['schedule_template_id', 'series_week_number']);
        });
    }
};
