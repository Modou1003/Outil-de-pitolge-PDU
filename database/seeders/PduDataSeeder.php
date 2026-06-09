<?php

namespace Database\Seeders;

use App\Models\Indicator;
use App\Models\University;
use Illuminate\Database\Seeder;

class PduDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer les 8 universités des sites PDU de Côte d'Ivoire
        $universities = [
            [
                'name' => 'Université de Man',
                'acronym' => 'UMan',
                'location' => 'Man',
                'latitude' => 7.4,
                'longitude' => -7.55,
                'region' => 'Tonkpi',
                'status' => 'active',
            ],
            [
                'name' => 'Université de San Pedro',
                'acronym' => 'USP',
                'location' => 'San Pedro',
                'latitude' => 4.74,
                'longitude' => -6.64,
                'region' => 'San-Pédro',
                'status' => 'active',
            ],
            [
                'name' => 'Université de Bondoukou',
                'acronym' => 'UBdk',
                'location' => 'Bondoukou',
                'latitude' => 8.05,
                'longitude' => -2.8,
                'region' => 'Gontougo',
                'status' => 'active',
            ],
            [
                'name' => "Université d'Odienné",
                'acronym' => 'UOd',
                'location' => 'Odienné',
                'latitude' => 9.5,
                'longitude' => -7.56,
                'region' => 'Kabadougou',
                'status' => 'active',
            ],
            [
                'name' => 'Université Péléforo Gon Coulibaly de Korhogo',
                'acronym' => 'UPGC',
                'location' => 'Korhogo',
                'latitude' => 9.45,
                'longitude' => -5.63,
                'region' => 'Poro',
                'status' => 'active',
            ],
            [
                'name' => 'Université Jean Lorougnon Guédé de Daloa',
                'acronym' => 'UJLoG',
                'location' => 'Daloa',
                'latitude' => 6.89,
                'longitude' => -6.45,
                'region' => 'Haut-Sassandra',
                'status' => 'active',
            ],
            [
                'name' => "Université d'Adiaké",
                'acronym' => 'UAd',
                'location' => 'Adiaké',
                'latitude' => 5.29,
                'longitude' => -3.3,
                'region' => 'Sud-Comoé',
                'status' => 'active',
            ],
            [
                'name' => 'Université Alassane Ouattara de Bouaké',
                'acronym' => 'UAO',
                'location' => 'Bouaké',
                'latitude' => 7.69,
                'longitude' => -5.03,
                'region' => 'Gbêkê',
                'status' => 'active',
            ],
        ];

        foreach ($universities as $university) {
            University::firstOrCreate(['name' => $university['name']], $university);
        }

        // Créer des indicateurs PDU
        $indicators = [
            // Indicateurs académiques
            [
                'name' => 'Taux de réussite aux examens',
                'code' => 'ACADEMIC_SUCCESS_RATE',
                'category' => 'academic',
                'type' => 'percentage',
                'unit' => '%',
                'unit_symbol' => '%',
                'target_value' => 85.00,
                'frequency' => 'semesterly',
                'description' => 'Pourcentage d\'étudiants réussissant leurs examens',
            ],
            [
                'name' => 'Nombre d\'étudiants inscrits',
                'code' => 'STUDENT_ENROLLMENT',
                'category' => 'academic',
                'type' => 'quantitative',
                'unit' => 'étudiants',
                'target_value' => 5000.00,
                'frequency' => 'yearly',
                'description' => 'Nombre total d\'étudiants inscrits à l\'université',
            ],
            [
                'name' => 'Ratio étudiants/enseignant',
                'code' => 'STUDENT_TEACHER_RATIO',
                'category' => 'academic',
                'type' => 'quantitative',
                'unit' => 'ratio',
                'target_value' => 25.00,
                'frequency' => 'yearly',
                'description' => 'Nombre d\'étudiants par enseignant',
            ],

            // Indicateurs d'infrastructure
            [
                'name' => 'Taux d\'occupation des salles de cours',
                'code' => 'CLASSROOM_OCCUPANCY_RATE',
                'category' => 'infrastructure',
                'type' => 'percentage',
                'unit' => '%',
                'unit_symbol' => '%',
                'target_value' => 90.00,
                'frequency' => 'monthly',
                'description' => 'Pourcentage d\'utilisation des salles de cours',
            ],
            [
                'name' => 'Nombre d\'ordinateurs fonctionnels',
                'code' => 'FUNCTIONAL_COMPUTERS',
                'category' => 'infrastructure',
                'type' => 'quantitative',
                'unit' => 'ordinateurs',
                'target_value' => 500.00,
                'frequency' => 'quarterly',
                'description' => 'Nombre d\'ordinateurs en état de fonctionnement',
            ],
            [
                'name' => 'Couverture WiFi',
                'code' => 'WIFI_COVERAGE',
                'category' => 'infrastructure',
                'type' => 'percentage',
                'unit' => '%',
                'unit_symbol' => '%',
                'target_value' => 95.00,
                'frequency' => 'quarterly',
                'description' => 'Pourcentage de couverture WiFi sur le campus',
            ],

            // Indicateurs financiers
            [
                'name' => 'Budget exécuté',
                'code' => 'BUDGET_EXECUTED',
                'category' => 'financial',
                'type' => 'currency',
                'unit' => 'XAF',
                'unit_symbol' => 'FCFA',
                'frequency' => 'quarterly',
                'description' => 'Montant du budget effectivement dépensé',
            ],
            [
                'name' => 'Taux d\'exécution budgétaire',
                'code' => 'BUDGET_EXECUTION_RATE',
                'category' => 'financial',
                'type' => 'percentage',
                'unit' => '%',
                'unit_symbol' => '%',
                'target_value' => 100.00,
                'frequency' => 'quarterly',
                'description' => 'Pourcentage du budget exécuté',
            ],

            // Indicateurs de recherche
            [
                'name' => 'Nombre de publications scientifiques',
                'code' => 'SCIENTIFIC_PUBLICATIONS',
                'category' => 'research',
                'type' => 'quantitative',
                'unit' => 'publications',
                'target_value' => 50.00,
                'frequency' => 'yearly',
                'description' => 'Nombre de publications dans des revues scientifiques',
            ],
            [
                'name' => 'Nombre de projets de recherche',
                'code' => 'RESEARCH_PROJECTS',
                'category' => 'research',
                'type' => 'quantitative',
                'unit' => 'projets',
                'target_value' => 20.00,
                'frequency' => 'yearly',
                'description' => 'Nombre de projets de recherche actifs',
            ],
        ];

        foreach ($indicators as $indicator) {
            Indicator::firstOrCreate(['code' => $indicator['code']], $indicator);
        }

        $this->command->info('✅ Données PDU créées avec succès!');
        $this->command->table(
            ['Entité', 'Nombre créé'],
            [
                ['Universités', count($universities)],
                ['Indicateurs', count($indicators)],
            ]
        );
    }
}