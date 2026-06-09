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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path'); // Chemin du fichier stocké
            $table->string('file_name'); // Nom original du fichier
            $table->string('mime_type');
            $table->integer('file_size'); // Taille en bytes

            // Relations polymorphiques (peut être attaché à plusieurs types d'entités)
            $table->morphs('documentable'); // documentable_type, documentable_id

            // Catégorisation
            $table->string('category')->nullable(); // rapport, contrat, photo, etc.
            $table->string('subcategory')->nullable();
            $table->json('tags')->nullable(); // Tags pour recherche

            // Gestion des versions
            $table->string('version')->default('1.0');
            $table->boolean('is_latest_version')->default(true);

            // Métadonnées
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();

            // Upload et gestion
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('uploaded_at')->useCurrent();

            // Visibilité et permissions
            $table->enum('visibility', [
                'public',
                'internal',
                'confidential',
                'restricted'
            ])->default('internal');

            $table->boolean('is_archived')->default(false);

            $table->timestamps();

            // Index
            $table->index(['category']);
            $table->index(['uploaded_by']);
            $table->index(['visibility']);
            $table->index(['is_archived']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};