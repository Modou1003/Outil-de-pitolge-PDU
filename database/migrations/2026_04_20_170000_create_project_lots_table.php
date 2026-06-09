<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdu_project_id')->constrained('pdu_projects')->onDelete('cascade');
            $table->string('code', 32);
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight_percentage', 5, 2)->default(0);
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->enum('status', ['not_started', 'in_progress', 'on_hold', 'completed', 'cancelled'])->default('not_started');
            $table->text('observations')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['pdu_project_id']);
            $table->index(['status']);
            $table->unique(['pdu_project_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_lots');
    }
};
