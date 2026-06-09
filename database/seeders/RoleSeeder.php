<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Réinitialiser les rôles et permissions en cache
        app()['cache']->forget('spatie.permission.cache');

        // Créer les rôles PDU (idempotent)
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $directeurRole = Role::firstOrCreate(['name' => 'directeur', 'guard_name' => 'web']);
        $chefProjetRole = Role::firstOrCreate(['name' => 'chef_projet', 'guard_name' => 'web']);
        $agentFinancierRole = Role::firstOrCreate(['name' => 'agent_financier', 'guard_name' => 'web']);
        $visiteurRole = Role::firstOrCreate(['name' => 'visiteur', 'guard_name' => 'web']);

        // Rôles "génie civil" (utilisateurs) – lecture seule par défaut
        $civilRoles = [
            'gc_maitre_ouvrage',
            'gc_maitre_ouvrage_delegue',
            'gc_amo',
            'gc_maitre_oeuvre',
            'gc_architecte',
            'gc_bureau_etudes',
            'gc_ingenieur_structure',
            'gc_ingenieur_geotechnique',
            'gc_ingenieur_hydraulique',
            'gc_ingenieur_vrd',
            'gc_economiste',
            'gc_opc',
            'gc_controle_technique',
            'gc_coordonnateur_sps_hse',
            'gc_qhse',
            'gc_laboratoire_essais',
            'gc_topographe_geometre',
            'gc_entreprise_generale',
            'gc_conducteur_travaux',
            'gc_chef_chantier',
            'gc_responsable_methodes',
            'gc_fournisseur',
            'gc_sous_traitant',
            'gc_inspection_suivi',
            'gc_commission_reception',
        ];

        $civilRoleModels = collect($civilRoles)->map(fn ($name) => Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']))->all();

        // Créer les permissions générales
        $permissions = [
            'view_dashboard',
            'manage_projects',
            'manage_users',
            'view_reports',
            'manage_finances',
            'edit_project',
            'delete_project',
            'create_project',
            'view_project',
            'export_reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Attribuer les permissions aux rôles
        // Admin: tous les droits
        $adminRole->syncPermissions(Permission::all());

        // Directeur: gestion des projets et rapports
        $directeurRole->syncPermissions([
            'view_dashboard',
            'view_reports',
            'manage_projects',
            'edit_project',
            'create_project',
            'view_project',
            'export_reports',
        ]);

        // Chef de projet: gestion détaillée des projets
        $chefProjetRole->syncPermissions([
            'view_dashboard',
            'view_project',
            'edit_project',
            'view_reports',
            'export_reports',
        ]);

        // Agent financier: gestion des finances
        $agentFinancierRole->syncPermissions([
            'view_dashboard',
            'view_reports',
            'manage_finances',
            'view_project',
            'export_reports',
        ]);

        // Visiteur: consultation seulement (lecture seule)
        $visiteurRole->syncPermissions([
            'view_dashboard',
            'view_project',
            'view_reports',
        ]);

        // Génie civil: mêmes droits que visiteur par défaut (lecture seule)
        foreach ($civilRoleModels as $r) {
            $r->syncPermissions($visiteurRole->permissions);
        }

        $this->command->info('✅ Rôles PDU créés avec succès!');
        $this->command->table(
            ['Rôle', 'Permissions'],
            [
                ['admin', count($adminRole->permissions)],
                ['directeur', count($directeurRole->permissions)],
                ['chef_projet', count($chefProjetRole->permissions)],
                ['agent_financier', count($agentFinancierRole->permissions)],
                ['visiteur', count($visiteurRole->permissions)],
            ]
        );
    }
}
