<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendrier contractuel de l'ouvrage.
 *
 * Le planning initial du marché raisonne par ouvrage — bâtiment présidence,
 * administration, centre médical — avec pour chacun une durée, une date de
 * début et une date de fin prévues, puis un début réellement constaté.
 * L'ouvrage ne portait jusqu'ici aucune date : un ouvrage démarré avec
 * plusieurs mois de retard tirait l'avancement du projet vers le bas sans que
 * la cause en soit visible nulle part.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('building_works', function (Blueprint $table) {
            $table->unsignedInteger('duration_days')->nullable()->after('weight_percentage');
            $table->date('planned_start_date')->nullable()->after('duration_days');
            $table->date('planned_end_date')->nullable()->after('planned_start_date');
            $table->date('actual_start_date')->nullable()->after('planned_end_date');
            $table->date('actual_end_date')->nullable()->after('actual_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('building_works', function (Blueprint $table) {
            $table->dropColumn([
                'duration_days',
                'planned_start_date',
                'planned_end_date',
                'actual_start_date',
                'actual_end_date',
            ]);
        });
    }
};
