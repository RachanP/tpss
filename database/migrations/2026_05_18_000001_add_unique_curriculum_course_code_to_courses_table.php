<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $indexName = 'courses_curriculum_id_course_code_unique';

    public function up(): void
    {
        if (!Schema::hasTable('courses') || $this->hasIndex()) {
            return;
        }

        $duplicates = DB::table('courses')
            ->select('curriculum_id', 'course_code', DB::raw('COUNT(*) as duplicate_count'))
            ->whereNull('deleted_at')
            ->groupBy('curriculum_id', 'course_code')
            ->having('duplicate_count', '>', 1)
            ->exists();

        if ($duplicates) {
            throw new RuntimeException('Cannot add unique index: duplicate course_code values exist in the same curriculum.');
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->unique(['curriculum_id', 'course_code'], $this->indexName);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('courses') || !$this->hasIndex()) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique($this->indexName);
        });
    }

    private function hasIndex(): bool
    {
        if (method_exists(Schema::getFacadeRoot(), 'hasIndex')) {
            return Schema::hasIndex('courses', $this->indexName)
                || Schema::hasIndex('courses', 'courses_course_code_curriculum_id_unique');
        }

        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'courses')
            ->where('index_name', $this->indexName)
            ->exists()
            || DB::table('information_schema.statistics')
                ->where('table_schema', $database)
                ->where('table_name', 'courses')
                ->where('index_name', 'courses_course_code_curriculum_id_unique')
                ->exists();
    }
};
