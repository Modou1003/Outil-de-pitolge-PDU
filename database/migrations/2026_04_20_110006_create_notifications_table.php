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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // Type de notification (App\Notifications\...)
            $table->morphs('notifiable'); // notifiable_type, notifiable_id (utilisateur destinataire)

            // Contenu de la notification
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Données supplémentaires

            // Statut de lecture
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_read')->default(false);

            // Priorité et catégorie
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->string('category')->nullable(); // projet, indicateur, système, etc.

            // Actions possibles
            $table->json('actions')->nullable(); // Boutons d'action (URLs, etc.)

            // Métadonnées
            $table->json('metadata')->nullable();

            // Expiration
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            // Index
            $table->index(['type']);
            $table->index(['is_read']);
            $table->index(['priority']);
            $table->index(['category']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};