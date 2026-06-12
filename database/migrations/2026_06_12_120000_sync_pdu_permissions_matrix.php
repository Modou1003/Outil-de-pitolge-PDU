<?php

use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;

/**
 * Corrige les bases déployées avec une ancienne matrice (10 permissions)
 * en resynchronisant les 12 permissions PDU et leurs rattachements aux rôles.
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
