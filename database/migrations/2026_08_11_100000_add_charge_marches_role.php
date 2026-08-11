<?php

use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crée le rôle « charge_marches » (section des marchés et affaires juridiques)
 * et resynchronise la matrice des rôles.
 *
 * Ce rôle porte manage_market sans manage_finances : son titulaire instruit les
 * avenants sans accéder aux décomptes, ce qui matérialise la séparation des
 * tâches entre l'acte contractuel et l'exécution de la dépense.
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
