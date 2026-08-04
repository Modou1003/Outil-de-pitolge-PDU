<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Avenants au marché : le montant initial (pdu_projects.budget_allocated)
        // reste intact, le montant actualisé = initial + somme des avenants.
        Schema::create('project_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdu_project_id')->constrained('pdu_projects')->cascadeOnDelete();
            $table->string('number', 40);                 // N° d'avenant
            $table->string('object')->nullable();         // Objet de l'avenant
            $table->date('signed_date')->nullable();      // Date de signature
            $table->decimal('amount', 18, 2)->default(0); // + plus-value / − moins-value
            $table->text('observations')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('pdu_project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_amendments');
    }
};
