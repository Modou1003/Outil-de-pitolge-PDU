<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('building_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdu_project_id')->constrained('pdu_projects')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['not_started', 'in_progress', 'on_hold', 'completed', 'cancelled'])->default('not_started');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('pdu_project_id');
            $table->index('status');
        });

        // Add building_work_id to project_lots
        Schema::table('project_lots', function (Blueprint $table) {
            $table->foreignId('building_work_id')->nullable()->constrained('building_works')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_lots', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['building_work_id']);
            $table->dropColumn('building_work_id');
        });

        Schema::dropIfExists('building_works');
    }
};
