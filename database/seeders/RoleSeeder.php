<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Créer les rôles PDU (idempotent)
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $directeurRole = Role::firstOrCreate(['name' => 'directeur', 'guard_name' => 'web']); // Resp. UGP
        $chefProjetRole = Role::firstOrCreate(['name' => 'chef_projet', 'guard_name' => 'web']);
        $comitePilotageRole = Role::firstOrCreate(['name' => 'comite_pilotage', 'guard_name' => 'web']);
        $agentFinancierRole = Role::firstOrCreate(['name' => 'agent_financier', 'guard_name' => 'web']);
        // Section des marchés et affaires juridiques : actes contractuels.
        $chargeMarchesRole = Role::firstOrCreate(['name' => 'charge_marches', 'guard_name' => 'web']);
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

        // Créer les permissions générales (alignées sur la matrice des permissions)
        $permissions = [
            'view_dashboard',   // Tableau de bord
            'manage_projects',  // (conservée pour compatibilité)
            'manage_users',     // Gestion des utilisateurs
            'view_reports',     // Rapports & exports (lecture)
            'export_reports',   // Rapports & exports (export/génération)
            'manage_finances',  // Suivi financier : EVM, décomptes, avances (écriture)
            'manage_market',    // Marché : avenants (section des marchés et affaires juridiques)
            'manage_physical',  // Avancement physique / lots / jalons (écriture)
            'manage_alerts',    // Alertes & anomalies (résolution/génération/commentaires)
            'view_project',     // Portefeuille projets (lecture)
            'create_project',   // Portefeuille projets (création)
            'edit_project',     // Portefeuille projets (modification)
            'delete_project',   // Portefeuille projets (suppression)
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Attribuer les permissions aux rôles

        // Administrateur : TOUS les droits
        $adminRole->syncPermissions(Permission::all());

        // Directeur : tous les droits SAUF la gestion des utilisateurs
        $directeurRole->syncPermissions(
            Permission::whereNotIn('name', ['manage_users'])->get()
        );

        // Chef de projet : tous les droits SAUF la gestion des utilisateurs,
        // le suivi financier (manage_finances) et les actes contractuels
        // relevant de la section des marchés (manage_market)
        $chefProjetRole->syncPermissions(
            Permission::whereNotIn('name', ['manage_users', 'manage_finances', 'manage_market'])->get()
        );

        // Agent financier (section des affaires administratives et financières) :
        // tous les droits SAUF la gestion des utilisateurs, l'avancement physique
        // (manage_physical) et les actes contractuels (manage_market), qui relèvent
        // de la section des marchés. Il conserve néanmoins l'accès à la section
        // Marché par manage_finances, l'habilitation y étant vérifiée en « ou ».
        $agentFinancierRole->syncPermissions(
            Permission::whereNotIn('name', ['manage_users', 'manage_physical', 'manage_market'])->get()
        );

        // Chargé des marchés (section des marchés et affaires juridiques) :
        // compétent pour les actes contractuels (avenants), sans accès aux
        // décomptes ni à l'avancement, conformément à la séparation des tâches.
        $chargeMarchesRole->syncPermissions([
            'view_dashboard',
            'view_project',
            'view_reports',
            'manage_market',
        ]);

        // Comité de pilotage : tout en lecture, + export des rapports
        $comitePilotageRole->syncPermissions([
            'view_dashboard',
            'view_project',
            'view_reports',
            'export_reports',
        ]);

        // Visiteur : consultation seulement (lecture seule)
        $visiteurRole->syncPermissions([
            'view_dashboard',
            'view_project',
            'view_reports',
        ]);

        // Génie civil: mêmes droits que visiteur par défaut (lecture seule)
        foreach ($civilRoleModels as $r) {
            $r->syncPermissions($visiteurRole->permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($this->command) {
            $this->command->info('✅ Rôles PDU créés avec succès!');
            $this->command->table(
                ['Rôle', 'Permissions'],
                [
                    ['admin', count($adminRole->permissions)],
                    ['directeur (Resp. UGP)', count($directeurRole->permissions)],
                    ['chef_projet', count($chefProjetRole->permissions)],
                    ['comite_pilotage', count($comitePilotageRole->permissions)],
                    ['agent_financier', count($agentFinancierRole->permissions)],
                    ['visiteur', count($visiteurRole->permissions)],
                ]
            );
        }
    }
}
