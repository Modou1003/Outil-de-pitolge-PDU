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
        Schema::create('indicator_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')->constrained('indicators')->onDelete('cascade');
            $table->foreignId('pdu_project_id')->constrained('pdu_projects')->onDelete('cascade');
            $table->foreignId('recorded_by')->constrained('users')->onDelete('cascade');

            // Période de mesure
            $table->date('measurement_date');
            $table->string('period')->nullable(); // Ex: "2024-Q1", "2024-S1", etc.

            // Valeurs mesurées
            $table->decimal('actual_value', 15, 4)->nullable();
            $table->decimal('target_value', 15, 4)->nullable(); // Valeur cible pour cette période
            $table->decimal('previous_value', 15, 4)->nullable(); // Valeur précédente pour comparaison

            // Statut et commentaires
            $table->enum('status', [
                'draft',
                'submitted',
                'validated',
                'rejected'
            ])->default('draft');

            $table->text('comments')->nullable();
            $table->text('validation_notes')->nullable();

            // Validation
            $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('validated_at')->nullable();

            // Métadonnées
            $table->json('data_sources')->nullable(); // Sources utilisées pour cette mesure
            $table->json('attachments')->nullable(); // Fichiers joints (URLs ou chemins)
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Index
            $table->index(['indicator_id']);
            $table->index(['pdu_project_id']);
            $table->index(['recorded_by']);
            $table->index(['measurement_date']);
            $table->index(['status']);
            $table->index(['period']);
            $table->unique(['indicator_id', 'pdu_project_id', 'measurement_date', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicator_trackings');
    }
};