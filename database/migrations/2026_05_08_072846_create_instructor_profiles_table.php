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

        Schema::create('instructor_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('title', 100)->nullable()->comment('\\u0e04\\u0e33\\u0e19\\u0e33\\u0e2b\\u0e19\\u0e49\\u0e32/\\u0e15\\u0e33\\u0e41\\u0e2b\\u0e19\\u0e48\\u0e07\\u0e17\\u0e32\\u0e07\\u0e27\\u0e34\\u0e0a\\u0e32\\u0e01\\u0e32\\u0e23');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreign('department_id')->references('id')->on('departments');
            $table->integer('teaching_quota')->nullable()->comment('\\u0e20\\u0e32\\u0e23\\u0e30\\u0e07\\u0e32\\u0e19\\u0e2a\\u0e2d\\u0e19\\u0e15\\u0e32\\u0e21\\u0e40\\u0e01\\u0e13\\u0e11\\u0e4c (\\u0e0a\\u0e31\\u0e48\\u0e27\\u0e42\\u0e21\\u0e07/\\u0e40\\u0e17\\u0e2d\\u0e21)');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instructor_profiles');
    }
};
