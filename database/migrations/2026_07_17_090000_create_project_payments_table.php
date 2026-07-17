<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Décomptes / états de paiement de l'entreprise (suivi maître d'ouvrage).
        Schema::create('project_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdu_project_id')->constrained('pdu_projects')->cascadeOnDelete();
            $table->string('number', 40);                         // N° de décompte
            $table->string('period', 7)->nullable();              // YYYY-MM
            $table->date('payment_date')->nullable();
            $table->decimal('gross_amount', 18, 2)->default(0);           // Montant brut HT
            $table->decimal('startup_advance_recovery', 18, 2)->default(0); // Remb. avance démarrage
            $table->decimal('supply_advance_recovery', 18, 2)->default(0);  // Remb. avance approvisionnement
            $table->decimal('net_paid', 18, 2)->default(0);               // Net payé
            $table->boolean('is_paid')->default(false);
            $table->text('observations')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('pdu_project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_payments');
    }
};
