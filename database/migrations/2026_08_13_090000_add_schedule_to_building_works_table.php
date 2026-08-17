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
        // MySQL ne sait pas annuler une modification de schéma : si un
        // déploiement s'interrompt en cours de route, les colonnes déjà
        // ajoutées subsistent sans que la migration soit enregistrée. Chaque
        // colonne est donc posée seulement si elle manque.
        Schema::table('building_works', function (Blueprint $table) {
            if (! Schema::hasColumn('building_works', 'duration_days')) {
                $table->unsignedInteger('duration_days')->nullable()->after('weight_percentage');
            }
            if (! Schema::hasColumn('building_works', 'planned_start_date')) {
                $table->date('planned_start_date')->nullable();
            }
            if (! Schema::hasColumn('building_works', 'planned_end_date')) {
                $table->date('planned_end_date')->nullable();
            }
            if (! Schema::hasColumn('building_works', 'actual_start_date')) {
                $table->date('actual_start_date')->nullable();
            }
            if (! Schema::hasColumn('building_works', 'actual_end_date')) {
                $table->date('actual_end_date')->nullable();
            }
        });
    }

    public function down(): void
    {
        $colonnes = array_values(array_filter(
            ['duration_days', 'planned_start_date', 'planned_end_date', 'actual_start_date', 'actual_end_date'],
            fn ($colonne) => Schema::hasColumn('building_works', $colonne),
        ));

        if ($colonnes === []) {
            return;
        }

        Schema::table('building_works', function (Blueprint $table) use ($colonnes) {
            $table->dropColumn($colonnes);
        });
    }
};
