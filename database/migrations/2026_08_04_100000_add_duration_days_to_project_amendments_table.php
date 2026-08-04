<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_amendments', function (Blueprint $table) {
            // Prolongation de délai portée par l'avenant (négatif = réduction).
            // La date de fin initiale reste intacte ; la date actualisée =
            // date initiale + somme des prolongations.
            $table->integer('duration_days')->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('project_amendments', function (Blueprint $table) {
            $table->dropColumn('duration_days');
        });
    }
};
