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

        Schema::create('course_offering_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_offering_id');
            $table->foreign('course_offering_id')->references('id')->on('course_offerings');
            $table->unsignedBigInteger('actor_user_id')->comment('\\u0e1c\\u0e39\\u0e49\\u0e14\\u0e33\\u0e40\\u0e19\\u0e34\\u0e19\\u0e01\\u0e32\\u0e23 (Course Head \\u0e2b\\u0e23\\u0e37\\u0e2d Executive)');
            $table->foreign('actor_user_id')->references('id')->on('users');
            $table->enum('action', ["submit","approve","reject","revise"]);
            $table->text('comment')->nullable()->comment('\\u0e40\\u0e2b\\u0e15\\u0e38\\u0e1c\\u0e25\\u0e15\\u0e35\\u0e01\\u0e25\\u0e31\\u0e1a \\u0e2b\\u0e23\\u0e37\\u0e2d\\u0e2b\\u0e21\\u0e32\\u0e22\\u0e40\\u0e2b\\u0e15\\u0e38\\u0e1b\\u0e23\\u0e30\\u0e01\\u0e2d\\u0e1a');
            $table->enum('from_status', ["draft","pending","published","rejected"])->nullable();
            $table->enum('to_status', ["draft","pending","published","rejected"]);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['course_offering_id', 'created_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_offering_approvals');
    }
};
