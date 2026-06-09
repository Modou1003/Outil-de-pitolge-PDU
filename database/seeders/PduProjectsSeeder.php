<?php

namespace Database\Seeders;

use App\Models\Indicator;
use App\Models\IndicatorTracking;
use App\Models\PduProject;
use App\Models\University;
use App\Models\User;
use Illuminate\Database\Seeder;

class PduProjectsSeeder extends Seeder
{
    public function run(): void
    {
        // Créer des projets PDU pour chaque université
        $universities = University::all();

        foreach ($universities as $university) {
            // Créer 2-3 projets par université
            $projectCount = rand(2, 3);

            for ($i = 1; $i <= $projectCount; $i++) {
                $startDate = now()->subMonths(rand(1, 12));
                $endDate = $startDate->copy()->addYears(rand(1, 3));

                $project = PduProject::create([
                    'title' => "Projet PDU {$university->acronym} - Phase {$i}",
                    'description' => "Projet de développement universitaire pour {$university->name}. Cette initiative vise à améliorer l'infrastructure, les programmes académiques et la recherche.",
                    'code' => "PDU-{$university->acronym}-{$i}" . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
                    'university_id' => $university->id,
                    'created_by' => User::whereHas('roles', function($q) {
                        $q->whereIn('name', ['admin', 'directeur']);
                    })->inRandomOrder()->first()->id ?? User::factory()->create()->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'planned_completion_date' => $endDate->copy()->subMonths(rand(1, 6)),
                    'status' => collect(['draft', 'submitted', 'approved', 'in_progress', 'completed'])->random(),
                    'type' => collect(['construction', 'rehabilitation', 'equipement', 'formation', 'recherche', 'numerique'])->random(),
                    'progress_percentage' => rand(0, 100),
                    'budget_allocated' => rand(50000000, 500000000), // 50M - 500M FCFA
                    'budget_spent' => rand(0, 500000000),
                    'currency' => 'XOF',
                    'objectives' => [
                        'Améliorer les infrastructures pédagogiques',
                        'Développer les programmes de recherche',
                        'Renforcer les capacités du personnel académique',
                        'Moderniser les systèmes d\'information'
                    ],
                    'stakeholders' => [
                        'ministry' => 'Ministère de l\'Enseignement Supérieur',
                        'university_admin' => $university->name,
                        'project_coordinator' => 'Coordonnateur PDU ' . $university->acronym,
                        'technical_partner' => collect(['UNESCO', 'Banque Mondiale', 'UE', 'AFD'])->random()
                    ],
                    'metadata' => [
                        'priority_level' => collect(['high', 'medium', 'low'])->random(),
                        'funding_source' => collect(['government', 'world_bank', 'eu', 'afd', 'mixed'])->random(),
                        'implementation_partner' => collect(['UNESCO', 'Banque Mondiale', 'UE', 'AFD', 'Local'])->random(),
                        'monitoring_frequency' => collect(['monthly', 'quarterly', 'semesterly'])->random()
                    ],
                    'director_id' => User::whereHas('roles', function($q) {
                        $q->where('name', 'directeur');
                    })->inRandomOrder()->first()->id ?? User::factory()->create()->id,
                    'project_manager_id' => User::whereHas('roles', function($q) {
                        $q->where('name', 'chef_projet');
                    })->inRandomOrder()->first()->id ?? User::factory()->create()->id,
                    'financial_agent_id' => User::whereHas('roles', function($q) {
                        $q->where('name', 'agent_financier');
                    })->inRandomOrder()->first()->id ?? User::factory()->create()->id,
                ]);

                // Créer des trackings d'indicateurs pour ce projet
                $this->createIndicatorTrackings($project);
            }
        }
    }

    private function createIndicatorTrackings(PduProject $project): void
    {
        $indicators = Indicator::inRandomOrder()->take(rand(3, 8))->get();

        foreach ($indicators as $indicator) {
            // Créer 3-6 trackings par indicateur (différentes périodes)
            $trackingCount = rand(3, 6);

            $monthOffset = 0;
            for ($i = 0; $i < $trackingCount; $i++) {
                $monthOffset += $i === 0 ? 0 : rand(1, 3);
                $measurementDate = $project->start_date->copy()->addMonths($monthOffset);

                if ($measurementDate->isAfter(now())) {
                    continue; // Ne pas créer de données futures
                }

                $actualValue = $this->generateRealisticValue($indicator);

                IndicatorTracking::create([
                    'indicator_id' => $indicator->id,
                    'pdu_project_id' => $project->id,
                    'recorded_by' => User::whereHas('roles', function($q) {
                        $q->whereIn('name', ['chef_projet', 'agent_financier']);
                    })->inRandomOrder()->first()->id ?? User::factory()->create()->id,
                    'measurement_date' => $measurementDate,
                    'period' => $measurementDate->format('Y-m'),
                    'actual_value' => $actualValue,
                    'target_value' => $indicator->target_value,
                    'previous_value' => $i > 0 ? rand(0, 100) : null,
                    'status' => collect(['draft', 'submitted', 'validated', 'rejected'])->random(),
                    'comments' => rand(0, 1) ? "Mesure effectuée selon la méthodologie définie. Valeur {$actualValue} " . ($indicator->unit_symbol ?? '') : null,
                    'validation_notes' => rand(0, 1) ? "Validé par l'équipe de suivi PDU." : null,
                    'validated_by' => rand(0, 1) ? User::whereHas('roles', function($q) {
                        $q->whereIn('name', ['directeur', 'admin']);
                    })->inRandomOrder()->first()->id : null,
                    'validated_at' => rand(0, 1) ? $measurementDate->copy()->addDays(rand(1, 30)) : null,
                    'data_sources' => [
                        'primary' => 'Rapport mensuel de l\'université',
                        'secondary' => 'Base de données institutionnelle',
                        'verification' => 'Contrôle sur site'
                    ],
                    'attachments' => rand(0, 1) ? ['report_' . $measurementDate->format('Y_m') . '.pdf'] : [],
                    'metadata' => [
                        'data_quality' => collect(['high', 'medium', 'low'])->random(),
                        'collection_method' => collect(['manual', 'automated', 'survey'])->random(),
                        'confidence_level' => rand(70, 100) . '%'
                    ]
                ]);
            }
        }
    }

    private function generateRealisticValue(Indicator $indicator): float
    {
        if (!$indicator->target_value) {
            return rand(1, 100);
        }

        $target = $indicator->target_value;
        $variance = rand(-30, 30); // -30% à +30% de variation

        $value = $target * (1 + $variance / 100);

        // Respecter les limites min/max si définies
        if ($indicator->minimum_value && $value < $indicator->minimum_value) {
            $value = $indicator->minimum_value;
        }
        if ($indicator->maximum_value && $value > $indicator->maximum_value) {
            $value = $indicator->maximum_value;
        }

        return round($value, 2);
    }
}