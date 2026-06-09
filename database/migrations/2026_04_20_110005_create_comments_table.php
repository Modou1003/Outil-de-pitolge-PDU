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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();

            // Relations polymorphiques (commentaires sur différents types d'entités)
            $table->morphs('commentable'); // commentable_type, commentable_id

            // Contenu du commentaire
            $table->text('content');
            $table->text('formatted_content')->nullable(); // Version formatée (HTML/Markdown)

            // Auteur
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Hiérarchie des commentaires (réponses)
            $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade');

            // Statut et modération
            $table->enum('status', [
                'published',
                'pending',
                'rejected',
                'archived'
            ])->default('published');

            // Métadonnées
            $table->json('mentions')->nullable(); // Utilisateurs mentionnés
            $table->json('attachments')->nullable(); // Fichiers joints
            $table->json('metadata')->nullable();

            // Modération
            $table->text('moderation_reason')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('moderated_at')->nullable();

            $table->timestamps();

            // Index
            $table->index(['user_id']);
            $table->index(['parent_id']);
            $table->index(['status']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};