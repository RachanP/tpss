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
        Schema::disableForeignKeyConstraints();

        Schema::create('schedule_conflicts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id')->comment('schedule \\u0e17\\u0e35\\u0e48\\u0e15\\u0e23\\u0e27\\u0e08\\u0e1e\\u0e1a\\u0e1b\\u0e31\\u0e0d\\u0e2b\\u0e32');
            $table->foreign('schedule_id')->references('id')->on('schedules');
            $table->unsignedBigInteger('conflicting_schedule_id')->nullable()->comment('schedule \\u0e17\\u0e35\\u0e48\\u0e0a\\u0e19\\u0e01\\u0e31\\u0e19 (NULL \\u0e16\\u0e49\\u0e32\\u0e40\\u0e1b\\u0e47\\u0e19 warning \\u0e44\\u0e21\\u0e48\\u0e43\\u0e0a\\u0e48 conflict)');
            $table->foreign('conflicting_schedule_id')->references('id')->on('schedules');
            $table->enum('conflict_type', ["instructor_overlap","room_overlap","group_overlap"])->nullable();
            $table->enum('warning_type', ["quota_exceeded","capacity_exceeded","missing_info","no_schedule","outside_availability"])->nullable();
            $table->enum('severity', ["conflict","warning"]);
            $table->string('message', 255);
            $table->boolean('is_resolved');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['severity', 'is_resolved']);
            $table->index(['schedule_id', 'is_resolved']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_conflicts');
    }
};
