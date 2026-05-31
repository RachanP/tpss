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

        Schema::create('activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('color_code', 10)->nullable()->default('#3498db');
            $table->enum('category', ["lecture","practicum","thesis","other"]);
            // V3 ข้อ 5.4: นับเป็นภาระงานสอนไหม — default ตามหมวด (other=ไม่นับ) · Admin override ได้
            $table->boolean('counts_toward_workload')->default(true);
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
        Schema::dropIfExists('activity_types');
    }
};
