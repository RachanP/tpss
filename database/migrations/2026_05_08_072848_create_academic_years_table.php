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

        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('\\u0e40\\u0e0a\\u0e48\\u0e19 2569');
            $table->integer('semester')->comment('\\u0e40\\u0e0a\\u0e48\\u0e19 1, 2, 3');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['name', 'semester']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
