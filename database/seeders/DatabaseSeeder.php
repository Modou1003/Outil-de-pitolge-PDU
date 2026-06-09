<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Créer les rôles et permissions d'abord
        $this->call(RoleSeeder::class);

        // Créer un utilisateur admin
        $admin = User::factory()->create([
            'name' => 'Admin PDU',
            'email' => 'admin@pdu-tracker.local',
        ]);
        $admin->assignRole('admin');

        // Créer des utilisateurs de test pour chaque rôle
        $directeur = User::factory()->create([
            'name' => 'Directeur PDU',
            'email' => 'directeur@pdu-tracker.local',
        ]);
        $directeur->assignRole('directeur');

        $chefProjet = User::factory()->create([
            'name' => 'Chef de Projet',
            'email' => 'chef@pdu-tracker.local',
        ]);
        $chefProjet->assignRole('chef_projet');

        $agentFinancier = User::factory()->create([
            'name' => 'Agent Financier',
            'email' => 'financier@pdu-tracker.local',
        ]);
        $agentFinancier->assignRole('agent_financier');

        $visiteur = User::factory()->create([
            'name' => 'Visiteur',
            'email' => 'visiteur@pdu-tracker.local',
        ]);
        $visiteur->assignRole('visiteur');

        // Créer les données PDU de base (universités, indicateurs)
        $this->call(PduDataSeeder::class);

        // Créer les projets PDU avec leurs trackings
        $this->call(PduProjectsSeeder::class);

        // Créer les rapports périodiques
        $this->call(ReportsSeeder::class);

        // Créer les documents et commentaires
        $this->call(DocumentsAndCommentsSeeder::class);

        // Créer les notifications et logs d'audit
        $this->call(NotificationsAndAuditSeeder::class);
    }
}
