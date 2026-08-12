<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrichit le journal d'activité pour qu'il devienne consultable.
 *
 * La table ne portait que l'auteur, l'action et le nom du modèle : impossible
 * de savoir sur quel projet l'action avait eu lieu, ni de la présenter en
 * clair. On ajoute une description lisible, l'identifiant de l'enregistrement
 * concerné et le projet de rattachement, qui sert de filtre principal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('description')->nullable()->after('model');
            $table->unsignedBigInteger('subject_id')->nullable()->after('description');
            $table->foreignId('pdu_project_id')->nullable()->after('subject_id')
                ->constrained('pdu_projects')->nullOnDelete();

            $table->index(['user_id', 'created_at']);
            $table->index('model');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pdu_project_id');
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['model']);
            $table->dropColumn(['description', 'subject_id']);
        });
    }
};
