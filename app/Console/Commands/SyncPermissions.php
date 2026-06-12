<?php

namespace App\Console\Commands;

use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Synchronise les rôles et permissions PDU (idempotent, pour prod Railway).';

    public function handle(): int
    {
        $this->info('Synchronisation des rôles et permissions…');

        $this->call('db:seed', [
            '--class' => RoleSeeder::class,
            '--force' => true,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Terminé. Les utilisateurs doivent se reconnecter si les droits ne s\'affichent pas tout de suite.');

        return self::SUCCESS;
    }
}
