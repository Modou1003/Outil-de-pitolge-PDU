<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_lots', function (Blueprint $table) {
            // 'planning'  = lot du planning (Gantt + jalons, calendrier)
            // 'physical'  = ouvrage d'avancement (saisie physique/financière)
            $table->string('kind', 20)->default('planning')->after('building_work_id');
            $table->index('kind');
        });

        // Backfill : les ouvrages créés dans les onglets Avancement n'ont pas
        // d'ouvrage parent (building_work_id null) → 'physical' ; les lots du
        // planning sont rattachés à un ouvrage → 'planning'.
        DB::table('project_lots')->whereNull('building_work_id')->update(['kind' => 'physical']);
        DB::table('project_lots')->whereNotNull('building_work_id')->update(['kind' => 'planning']);
    }

    public function down(): void
    {
        Schema::table('project_lots', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
