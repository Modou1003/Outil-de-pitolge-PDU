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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Utilisateur qui a effectué l'action
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('user_name')->nullable(); // Nom au moment de l'action

            // Action effectuée
            $table->string('action'); // create, update, delete, login, etc.
            $table->string('model_type')->nullable(); // App\Models\User, etc.
            $table->unsignedBigInteger('model_id')->nullable();

            // Description détaillée
            $table->string('description');
            $table->json('old_values')->nullable(); // Valeurs avant modification
            $table->json('new_values')->nullable(); // Valeurs après modification
            $table->json('metadata')->nullable(); // Informations supplémentaires

            // Contexte
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('context')->nullable(); // Contexte supplémentaire (session, etc.)

            // Catégorisation
            $table->string('category')->default('general'); // security, data, system, etc.
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');

            $table->timestamps();

            // Index
            $table->index(['user_id']);
            $table->index(['action']);
            $table->index(['model_type', 'model_id']);
            $table->index(['category']);
            $table->index(['severity']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};