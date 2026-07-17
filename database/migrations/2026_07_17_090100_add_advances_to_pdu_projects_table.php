<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdu_projects', function (Blueprint $table) {
            // Avances contractuelles versées à l'entreprise.
            $table->decimal('startup_advance_amount', 18, 2)->default(0)->after('budget_spent');
            $table->decimal('supply_advance_amount', 18, 2)->default(0)->after('startup_advance_amount');
        });
    }

    public function down(): void
    {
        Schema::table('pdu_projects', function (Blueprint $table) {
            $table->dropColumn(['startup_advance_amount', 'supply_advance_amount']);
        });
    }
};
