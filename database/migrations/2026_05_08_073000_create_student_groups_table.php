<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('student_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_offering_id');
            $table->foreign('course_offering_id')
                ->references('id')->on('course_offerings')
                ->restrictOnDelete();
            $table->string('group_code', 255);
            $table->integer('student_count');
            $table->string('color_code', 10)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['course_offering_id', 'group_code'], 'student_groups_offering_group_code_unique');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('student_groups');
    }
};
