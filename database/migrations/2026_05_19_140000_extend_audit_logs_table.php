<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Human-readable Thai category label (e.g. 'ตารางสอน', 'การอนุมัติ')
            $table->string('category', 80)->nullable()->after('user_id');

            // Optional Thai description — display helper only, not source of truth
            $table->string('description', 500)->nullable()->after('new_values');

            $table->index('category');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['category', 'description']);
        });
    }
};
