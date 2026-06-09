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
        Schema::create('pdu_projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('code')->unique(); // Code unique du projet
            $table->foreignId('university_id')->constrained('universities')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            // Dates importantes
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('planned_completion_date')->nullable();

            // Statut et progression
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'in_progress',
                'on_hold',
                'completed',
                'cancelled',
                'archived'
            ])->default('draft');

            $table->decimal('progress_percentage', 5, 2)->default(0); // 0.00 à 100.00

            // Budget
            $table->decimal('budget_allocated', 15, 2)->nullable();
            $table->decimal('budget_spent', 15, 2)->default(0);
            $table->string('currency', 3)->default('XAF'); // Franc CFA

            // Métadonnées
            $table->json('objectives')->nullable(); // Objectifs spécifiques
            $table->json('stakeholders')->nullable(); // Parties prenantes
            $table->json('metadata')->nullable(); // Données supplémentaires

            // Gestion des rôles
            $table->foreignId('director_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('project_manager_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('financial_agent_id')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index(['university_id']);
            $table->index(['status']);
            $table->index(['created_by']);
            $table->index(['start_date']);
            $table->index(['end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdu_projects');
    }
};