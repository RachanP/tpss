<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_pa_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pa_round_id')->constrained('pa_rounds')->cascadeOnDelete();
            $table->unsignedTinyInteger('teaching_pct')->default(0);
            $table->unsignedTinyInteger('research_pct')->default(0);
            $table->unsignedTinyInteger('service_pct')->default(0);
            $table->unsignedTinyInteger('culture_pct')->default(0);
            $table->unsignedTinyInteger('other_pct')->default(0);
            $table->unsignedInteger('teaching_quota')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'pa_round_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_pa_allocations');
    }
};
