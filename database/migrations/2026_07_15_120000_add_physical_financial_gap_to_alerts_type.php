<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $withNew = [
        'delay',
        'budget_overrun',
        'indicator_off_target',
        'milestone_missed',
        'no_update',
        'progress_gap',
        'physical_financial_gap',
    ];

    private array $original = [
        'delay',
        'budget_overrun',
        'indicator_off_target',
        'milestone_missed',
        'no_update',
        'progress_gap',
    ];

    public function up(): void
    {
        // La colonne `type` est un ENUM MySQL : on l'étend avec la nouvelle valeur.
        // Sur les autres pilotes (SQLite en local), la colonne n'impose pas de
        // contrainte stricte au niveau applicatif — rien à faire.
        if (DB::getDriverName() === 'mysql') {
            $values = "'" . implode("','", $this->withNew) . "'";
            DB::statement("ALTER TABLE alerts MODIFY COLUMN type ENUM($values) NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // Referme les alertes du nouveau type avant de retirer la valeur de l'enum.
            DB::table('alerts')->where('type', 'physical_financial_gap')->delete();
            $values = "'" . implode("','", $this->original) . "'";
            DB::statement("ALTER TABLE alerts MODIFY COLUMN type ENUM($values) NOT NULL");
        }
    }
};
