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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type'); // quarterly, annual, progress, etc.
            $table->string('period'); // 2024-Q1, 2024, etc.

            // Relations
            $table->foreignId('pdu_project_id')->nullable()->constrained('pdu_projects')->onDelete('cascade');
            $table->foreignId('university_id')->nullable()->constrained('universities')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            // Contenu du rapport
            $table->json('summary_data')->nullable(); // Données résumées
            $table->json('indicators_data')->nullable(); // Données des indicateurs
            $table->json('charts_data')->nullable(); // Données pour graphiques
            $table->text('executive_summary')->nullable();
            $table->text('conclusions')->nullable();
            $table->text('recommendations')->nullable();

            // Statut et workflow
            $table->enum('status', [
                'draft',
                'review',
                'approved',
                'published',
                'archived'
            ])->default('draft');

            // Dates importantes
            $table->date('report_date');
            $table->date('submission_deadline')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            // Validation
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('approval_notes')->nullable();

            // Fichiers et pièces jointes
            $table->json('attachments')->nullable(); // Liste des fichiers joints
            $table->string('pdf_path')->nullable(); // Chemin du PDF généré

            // Métadonnées
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Index
            $table->index(['type']);
            $table->index(['period']);
            $table->index(['status']);
            $table->index(['pdu_project_id']);
            $table->index(['university_id']);
            $table->index(['created_by']);
            $table->index(['report_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};