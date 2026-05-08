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

        Schema::create('course_offering_instructors', function (Blueprint $table) {
            $table->id();
            $table->foreign('course_offering_id')->references('id')->on('course_offerings');
            $table->id();
            $table->foreign('user_id')->references('id')->on('users');
            $table->enum('role_in_course', ["coordinator","secretary","instructor","group_advisor","preceptor"])->nullable();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_offering_instructors');
    }
};
