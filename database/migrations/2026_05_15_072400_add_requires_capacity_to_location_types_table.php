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
        Schema::table('location_types', function (Blueprint $table) {
            $table->boolean('requires_capacity')->default(true)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('location_types', function (Blueprint $table) {
            $table->dropColumn('requires_capacity');
        });
    }
};
