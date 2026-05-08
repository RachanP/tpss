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

        Schema::create('schedule_instructors', function (Blueprint $table) {
            $table->id();
            $table->foreign('schedule_id')->references('id')->on('schedules');
            $table->id()->index();
            $table->foreign('user_id')->references('id')->on('users');
            $table->boolean('is_lead')->nullable()->comment('\\u0e23\\u0e30\\u0e1a\\u0e38\\u0e27\\u0e48\\u0e32\\u0e40\\u0e1b\\u0e47\\u0e19\\u0e1c\\u0e39\\u0e49\\u0e2a\\u0e2d\\u0e19\\u0e2b\\u0e25\\u0e31\\u0e01\\u0e43\\u0e19\\u0e04\\u0e32\\u0e1a\\u0e19\\u0e31\\u0e49\\u0e19\\u0e2b\\u0e23\\u0e37\\u0e2d\\u0e44\\u0e21\\u0e48');
            $table->index(['schedule_id', 'is_lead']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_instructors');
    }
};
