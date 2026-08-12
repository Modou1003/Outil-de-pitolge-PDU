<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Libère « alerts.type » de sa contrainte de type énuméré.
 *
 * La colonne avait été créée en enum sur les six types d'origine. Sur MySQL,
 * chaque nouveau type imposait un ALTER TABLE ; sur PostgreSQL et SQLite, où
 * Laravel traduit l'enum en contrainte CHECK, les migrations correctives ne
 * s'appliquaient pas et l'insertion d'un nouveau type échouait.
 *
 * Le catalogue des types est désormais porté par le modèle (Alert::TYPES) et
 * la colonne devient une simple chaîne, ce qui permet d'enrichir les règles
 * de détection sans migration de schéma.
 */
return new class extends Migration
{
    public function up(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => $this->relaxPostgres(),
            'mysql', 'mariadb' => DB::statement('ALTER TABLE alerts MODIFY COLUMN type VARCHAR(50) NOT NULL'),
            // SQLite : le schema builder reconstruit la table, ce qui fait
            // disparaître la contrainte CHECK héritée de l'enum.
            default => Schema::table('alerts', function (Blueprint $table) {
                $table->string('type', 50)->change();
            }),
        };
    }

    private function relaxPostgres(): void
    {
        // Contrainte CHECK générée implicitement par l'enum.
        DB::statement('ALTER TABLE alerts DROP CONSTRAINT IF EXISTS alerts_type_check');
        DB::statement('ALTER TABLE alerts ALTER COLUMN type TYPE VARCHAR(50)');
    }

    public function down(): void
    {
        // Pas de retour en arrière : réimposer l'énumération ferait échouer
        // les alertes déjà enregistrées sous les nouveaux types.
    }
};
