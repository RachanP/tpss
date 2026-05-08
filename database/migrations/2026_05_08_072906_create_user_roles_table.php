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

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreign('user_id')->references('id')->on('users');
            $table->enum('role', ["admin","staff","course_head","executive","instructor"])->primary();
            $table->boolean('is_primary')->comment('role \\u0e17\\u0e35\\u0e48 login \\u0e04\\u0e23\\u0e31\\u0e49\\u0e07\\u0e41\\u0e23\\u0e01\\u0e40\\u0e02\\u0e49\\u0e32\\u0e2b\\u0e19\\u0e49\\u0e32\\u0e08\\u0e2d\\u0e19\\u0e35\\u0e49\\u0e01\\u0e48\\u0e2d\\u0e19');
            $table->timestamp('created_at')->nullable();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
