<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // L'avancement physique/financier se saisit désormais au niveau de
        // l'OUVRAGE (building_work), partagé avec le planning.
        Schema::table('physical_progresses', function (Blueprint $table) {
            $table->foreignId('building_work_id')->nullable()->after('project_lot_id')
                ->constrained('building_works')->cascadeOnDelete();
        });
        Schema::table('financial_progresses', function (Blueprint $table) {
            $table->foreignId('building_work_id')->nullable()->after('project_lot_id')
                ->constrained('building_works')->cascadeOnDelete();
        });

        // Repartir à zéro pour l'avancement (choix utilisateur) : les anciens
        // relevés (rattachés aux lots) et les ouvrages d'avancement (kind =
        // physical) sont supprimés. Les ouvrages du planning sont conservés.
        DB::table('physical_progresses')->delete();
        DB::table('financial_progresses')->delete();
        DB::table('project_lots')->where('kind', 'physical')->delete();

        // Les agrégats dérivés de l'avancement repartent donc de zéro.
        DB::table('pdu_projects')->update([
            'progress_percentage' => 0,
            'budget_spent' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('physical_progresses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('building_work_id');
        });
        Schema::table('financial_progresses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('building_work_id');
        });
    }
};
