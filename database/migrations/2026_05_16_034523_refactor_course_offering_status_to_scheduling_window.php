<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Map old values before changing enum
        DB::table('course_offerings')
            ->whereIn('status', ['active', 'archived'])
            ->update(['status' => 'locked']);

        Schema::table('course_offerings', function (Blueprint $table) {
            $table->enum('status', ['locked', 'open'])->default('locked')->change();

            foreach (['archived_at', 'archive_reason'] as $col) {
                if (Schema::hasColumn('course_offerings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('course_offerings', function (Blueprint $table) {
            if (Schema::hasColumn('course_offerings', 'archived_by')) {
                $table->dropForeign(['archived_by']);
                $table->dropColumn('archived_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->enum('status', ['active', 'archived'])->default('active')->change();
            $table->timestamp('archived_at')->nullable();
            $table->text('archive_reason')->nullable();
        });

        Schema::table('course_offerings', function (Blueprint $table) {
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->foreign('archived_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};
