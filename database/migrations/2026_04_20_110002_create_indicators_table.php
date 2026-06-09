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
        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('code')->unique(); // Code unique de l'indicateur
            $table->string('category'); // Catégorie (ex: académique, infrastructure, etc.)
            $table->string('subcategory')->nullable();

            // Type et unité de mesure
            $table->enum('type', [
                'quantitative',
                'qualitative',
                'percentage',
                'boolean',
                'currency'
            ])->default('quantitative');

            $table->string('unit')->nullable(); // %, XAF, étudiants, etc.
            $table->string('unit_symbol')->nullable(); // €, %, etc.

            // Valeurs cibles
            $table->decimal('target_value', 15, 4)->nullable();
            $table->decimal('minimum_value', 15, 4)->nullable();
            $table->decimal('maximum_value', 15, 4)->nullable();

            // Fréquence de mesure
            $table->enum('frequency', [
                'daily',
                'weekly',
                'monthly',
                'quarterly',
                'semesterly',
                'yearly',
                'once'
            ])->default('monthly');

            // Métadonnées
            $table->json('calculation_method')->nullable(); // Méthode de calcul
            $table->json('data_sources')->nullable(); // Sources de données
            $table->json('metadata')->nullable();

            // Gestion
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Index
            $table->index(['category']);
            $table->index(['type']);
            $table->index(['is_active']);
            $table->index(['code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicators');
    }
};