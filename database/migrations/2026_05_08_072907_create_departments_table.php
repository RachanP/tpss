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

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique()->comment('\\u0e40\\u0e0a\\u0e48\\u0e19 \\u0e20\\u0e32\\u0e04\\u0e27\\u0e34\\u0e0a\\u0e32\\u0e01\\u0e32\\u0e23\\u0e1e\\u0e22\\u0e32\\u0e1a\\u0e32\\u0e25\\u0e2d\\u0e32\\u0e22\\u0e38\\u0e23\\u0e28\\u0e32\\u0e2a\\u0e15\\u0e23\\u0e4c\\u0e41\\u0e25\\u0e30\\u0e28\\u0e31\\u0e25\\u0e22\\u0e28\\u0e32\\u0e2a\\u0e15\\u0e23\\u0e4c');
            $table->unsignedBigInteger('head_user_id')->nullable()->comment('\\u0e2b\\u0e31\\u0e27\\u0e2b\\u0e19\\u0e49\\u0e32\\u0e20\\u0e32\\u0e04\\u0e27\\u0e34\\u0e0a\\u0e32');
            $table->foreign('head_user_id')->references('id')->on('users');
            $table->unsignedBigInteger('secretary_user_id')->nullable()->comment('\\u0e40\\u0e25\\u0e02\\u0e32\\u0e19\\u0e38\\u0e01\\u0e32\\u0e23\\u0e20\\u0e32\\u0e04\\u0e27\\u0e34\\u0e0a\\u0e32');
            $table->foreign('secretary_user_id')->references('id')->on('users');
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
        Schema::dropIfExists('departments');
    }
};
