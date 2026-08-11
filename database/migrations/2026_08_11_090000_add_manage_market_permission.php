<?php

use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ajoute la permission « manage_market » et resynchronise la matrice des rôles.
 *
 * Dans l'organisation de l'UGP, deux sections distinctes interviennent sur le
 * marché : les affaires administratives et financières pour les décomptes
 * (exécution de la dépense), les marchés et affaires juridiques pour les
 * avenants (actes contractuels). La permission dédiée matérialise cette
 * séparation des tâches.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(RoleSeeder::class)) {
            return;
        }

        Artisan::call('db:seed', [
            '--class' => RoleSeeder::class,
            '--force' => true,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Pas de rollback : données de référence métier.
    }
};
