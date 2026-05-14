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

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_code', 255)->unique();
            $table->string('room_name', 255);
            $table->string('building', 100)->nullable();
            $table->integer('capacity')->nullable();
            $table->unsignedBigInteger('location_type_id');
            $table->foreign('location_type_id')->references('id')->on('location_types');
            $table->json('equipment_type')->nullable();
            $table->text('address')->nullable()->comment('\\u0e17\\u0e35\\u0e48\\u0e2d\\u0e22\\u0e39\\u0e48\\u0e41\\u0e2b\\u0e25\\u0e48\\u0e07\\u0e1d\\u0e36\\u0e01\\u0e20\\u0e32\\u0e22\\u0e19\\u0e2d\\u0e01 \\u0e40\\u0e0a\\u0e48\\u0e19 \\u0e42\\u0e23\\u0e07\\u0e1e\\u0e22\\u0e32\\u0e1a\\u0e32\\u0e25, \\u0e23\\u0e1e.\\u0e2a\\u0e15., \\u0e0a\\u0e38\\u0e21\\u0e0a\\u0e19');
            $table->enum('status', ["active","inactive","maintenance"]);
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
        Schema::dropIfExists('rooms');
    }
};
