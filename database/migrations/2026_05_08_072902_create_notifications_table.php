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

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->foreign('schedule_id')->references('id')->on('schedules');
            $table->unsignedBigInteger('course_offering_id')->nullable()->comment('\\u0e43\\u0e0a\\u0e49\\u0e2a\\u0e33\\u0e2b\\u0e23\\u0e31\\u0e1a approval_update notification \\u0e23\\u0e30\\u0e14\\u0e31\\u0e1a\\u0e23\\u0e32\\u0e22\\u0e27\\u0e34\\u0e0a\\u0e32 (M11)');
            $table->foreign('course_offering_id')->references('id')->on('course_offerings');
            $table->enum('type', ["conflict","warning_quota_exceeded","warning_missing_info","warning_capacity","warning_no_schedule","approval_update"]);
            $table->string('message', 255);
            $table->boolean('is_read');
            $table->timestamp('created_at')->nullable();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
