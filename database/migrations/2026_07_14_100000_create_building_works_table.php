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
            $table->foreignId('pdu_project_id')->constrained('pdu_projects')->onDelete('cascade');
            $table->string('code', 32)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('budget', 15, 2)->default(0);
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->enum('status', ['not_started', 'in_progress', 'on_hold', 'completed', 'cancelled'])->default('not_started');
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->text('observations')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('pdu_project_id');
            $table->index('status');
        });

        // Ajouter colonne building_work_id aux lots existants
        Schema::table('project_lots', function (Blueprint $table) {
            $table->foreignId('building_work_id')->nullable()->constrained('building_works')->onDelete('cascade')->after('pdu_project_id');
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
