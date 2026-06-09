<?php

namespace Database\Seeders;

use App\Models\Report;
use App\Models\PduProject;
use App\Models\University;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReportsSeeder extends Seeder
{
    public function run(): void
    {
        $projects = PduProject::all();
        $universities = University::all();

        // Créer des rapports de projet
        foreach ($projects as $project) {
            $this->createProjectReports($project);
        }

        // Créer des rapports d'université
        foreach ($universities as $university) {
            $this->createUniversityReports($university);
        }

        // Créer des rapports consolidés
        $this->createConsolidatedReports();
    }

    private function createProjectReports(PduProject $project): void
    {
        $reportTypes = [
            ['type' => 'progress', 'title' => 'Rapport d\'avancement mensuel', 'period' => 'monthly'],
            ['type' => 'financial', 'title' => 'Rapport financier trimestriel', 'period' => 'quarterly'],
            ['type' => 'technical', 'title' => 'Rapport technique semestriel', 'period' => 'semesterly'],
            ['type' => 'annual', 'title' => 'Rapport annuel d\'exécution', 'period' => 'annually'],
        ];

        foreach ($reportTypes as $reportConfig) {
            // Créer 2-4 rapports de chaque type
            $reportCount = rand(2, 4);

            for ($i = 1; $i <= $reportCount; $i++) {
                $startDate = $project->start_date->copy()->addMonths(($i - 1) * $this->getPeriodMonths($reportConfig['period']));
                $endDate = $startDate->copy()->addMonths($this->getPeriodMonths($reportConfig['period']))->subDay();

                if ($endDate->isAfter(now())) {
                    continue; // Ne pas créer de rapports futurs
                }

                Report::create([
                    'title' => "{$reportConfig['title']} - {$project->title} - Période {$i}",
                    'description' => "Rapport détaillé sur l'avancement du projet {$project->title} pour la période du {$startDate->format('d/m/Y')} au {$endDate->format('d/m/Y')}.",
                    'type' => $reportConfig['type'],
                    'period' => $startDate->format('Y-m'),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'university_id' => $project->university_id,
                    'pdu_project_id' => $project->id,
                    'created_by' => User::whereHas('roles', function($q) {
                        $q->where('name', 'chef_projet');
                    })->inRandomOrder()->first()->id ?? User::factory()->create()->id,
                    'approved_by' => rand(0, 1) ? User::whereHas('roles', function($q) {
                        $q->whereIn('name', ['directeur', 'admin']);
                    })->inRandomOrder()->first()->id : null,
                    'approved_at' => rand(0, 1) ? $endDate->copy()->addDays(rand(1, 15)) : null,
                    'status' => collect(['draft', 'submitted', 'approved', 'rejected'])->random(),
                    'data' => $this->generateReportData($reportConfig['type'], $project),
                    'summary' => [
                        'objectives_achieved' => rand(1, 5),
                        'challenges_encountered' => rand(0, 3),
                        'next_steps' => 'Continuer la mise en œuvre selon le planning établi',
                        'budget_utilization' => rand(60, 95) . '%'
                    ],
                    'recommendations' => [
                        'Renforcer le suivi des indicateurs',
                        'Améliorer la coordination inter-départements',
                        'Augmenter la fréquence des rapports',
                        'Optimiser l\'utilisation des ressources'
                    ],
                    'attachments' => [
                        'report_' . $startDate->format('Y_m') . '.pdf',
                        'annexes_' . $startDate->format('Y_m') . '.xlsx'
                    ],
                    'metadata' => [
                        'report_format' => 'standard',
                        'language' => 'french',
                        'confidentiality' => collect(['public', 'internal', 'confidential'])->random(),
                        'review_cycle' => rand(1, 4) . ' weeks'
                    ],
                    'is_public' => rand(0, 1),
                    'published_at' => rand(0, 1) ? $endDate->copy()->addDays(rand(1, 30)) : null,
                ]);
            }
        }
    }

    private function createUniversityReports(University $university): void
    {
        $reportTypes = [
            ['type' => 'institutional', 'title' => 'Rapport institutionnel'],
            ['type' => 'performance', 'title' => 'Rapport de performance'],
            ['type' => 'sustainability', 'title' => 'Rapport de durabilité'],
        ];

        foreach ($reportTypes as $reportConfig) {
            $reportCount = rand(1, 3);

            for ($i = 1; $i <= $reportCount; $i++) {
                $startDate = now()->subMonths(rand(1, 12));
                $endDate = $startDate->copy()->addMonths(6)->subDay();

                Report::create([
                    'title' => "{$reportConfig['title']} - {$university->name} - {$startDate->format('Y')}",
                    'description' => "Rapport {$reportConfig['type']} global pour {$university->name} couvrant la période du {$startDate->format('d/m/Y')} au {$endDate->format('d/m/Y')}.",
                    'type' => $reportConfig['type'],
                    'period' => $startDate->format('Y'),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'university_id' => $university->id,
                    'created_by' => User::whereHas('roles', function($q) {
                        $q->whereIn('name', ['directeur', 'admin']);
                    })->inRandomOrder()->first()->id ?? User::factory()->create()->id,
                    'approved_by' => User::whereHas('roles', function($q) {
                        $q->where('name', 'admin');
                    })->inRandomOrder()->first()->id ?? null,
                    'approved_at' => rand(0, 1) ? $endDate->copy()->addDays(rand(1, 15)) : null,
                    'status' => collect(['draft', 'submitted', 'approved'])->random(),
                    'data' => $this->generateUniversityReportData($reportConfig['type'], $university),
                    'summary' => [
                        'key_achievements' => rand(3, 8),
                        'challenges' => rand(1, 4),
                        'future_outlook' => 'Positif avec continuation des efforts',
                        'overall_rating' => collect(['excellent', 'good', 'satisfactory', 'needs_improvement'])->random()
                    ],
                    'recommendations' => [
                        'Renforcer les partenariats stratégiques',
                        'Investir dans la formation continue',
                        'Moderniser l\'infrastructure numérique',
                        'Développer la recherche appliquée'
                    ],
                    'attachments' => [
                        'university_report_' . $startDate->format('Y') . '.pdf',
                        'statistical_annex_' . $startDate->format('Y') . '.xlsx'
                    ],
                    'metadata' => [
                        'scope' => 'university_wide',
                        'stakeholders' => 'all',
                        'methodology' => 'mixed',
                        'data_sources' => 'multiple'
                    ],
                    'is_public' => rand(0, 1),
                    'published_at' => rand(0, 1) ? $endDate->copy()->addDays(rand(1, 30)) : null,
                ]);
            }
        }
    }

    private function createConsolidatedReports(): void
    {
        $reportTypes = [
            ['type' => 'national', 'title' => 'Rapport National PDU'],
            ['type' => 'sectorial', 'title' => 'Rapport Sectoriel'],
            ['type' => 'impact', 'title' => 'Rapport d\'Impact'],
        ];

        foreach ($reportTypes as $reportConfig) {
            $startDate = now()->subMonths(rand(6, 18));
            $endDate = $startDate->copy()->addMonths(12)->subDay();

            Report::create([
                'title' => "{$reportConfig['title']} - Programme de Décentralisation des Universités - {$startDate->format('Y')}",
                'description' => "Rapport {$reportConfig['type']} consolidé du Programme de Décentralisation des Universités pour l'année {$startDate->format('Y')}.",
                'type' => $reportConfig['type'],
                'period' => $startDate->format('Y'),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'created_by' => User::whereHas('roles', function($q) {
                    $q->where('name', 'admin');
                })->inRandomOrder()->first()->id ?? User::factory()->create()->id,
                'approved_by' => User::whereHas('roles', function($q) {
                    $q->where('name', 'admin');
                })->inRandomOrder()->first()->id,
                'approved_at' => $endDate->copy()->addDays(rand(1, 15)),
                'status' => 'approved',
                'data' => $this->generateConsolidatedReportData($reportConfig['type']),
                'summary' => [
                    'total_universities' => University::count(),
                    'total_projects' => PduProject::count(),
                    'overall_progress' => rand(65, 85) . '%',
                    'budget_executed' => rand(70, 90) . '%',
                    'key_success_factors' => 'Coordination efficace et engagement des parties prenantes'
                ],
                'recommendations' => [
                    'Renforcer le monitoring et l\'évaluation',
                    'Augmenter les ressources pour les universités prioritaires',
                    'Développer les capacités locales',
                    'Améliorer la communication et la transparence'
                ],
                'attachments' => [
                    'national_report_' . $startDate->format('Y') . '.pdf',
                    'executive_summary_' . $startDate->format('Y') . '.pdf',
                    'statistical_annex_' . $startDate->format('Y') . '.xlsx'
                ],
                'metadata' => [
                    'scope' => 'national',
                    'methodology' => 'comprehensive',
                    'data_quality' => 'high',
                    'validation_level' => 'expert_review'
                ],
                'is_public' => true,
                'published_at' => $endDate->copy()->addDays(rand(1, 30)),
            ]);
        }
    }

    private function getPeriodMonths(string $period): int
    {
        return match ($period) {
            'monthly' => 1,
            'quarterly' => 3,
            'semesterly' => 6,
            'annually' => 12,
            default => 1,
        };
    }

    private function generateReportData(string $type, PduProject $project): array
    {
        return match ($type) {
            'progress' => [
                'activities_completed' => rand(5, 15),
                'activities_ongoing' => rand(2, 8),
                'activities_planned' => rand(3, 10),
                'milestones_achieved' => rand(1, 5),
                'issues_resolved' => rand(0, 3),
                'risks_identified' => rand(0, 2),
            ],
            'financial' => [
                'budget_allocated' => $project->budget_allocated,
                'budget_spent' => $project->budget_spent,
                'budget_remaining' => $project->budget_allocated - $project->budget_spent,
                'expenses_by_category' => [
                    'infrastructure' => rand(30, 50),
                    'equipment' => rand(15, 30),
                    'training' => rand(10, 20),
                    'consulting' => rand(5, 15),
                ],
                'payment_delays' => rand(0, 2),
                'budget_variance' => rand(-10, 10) . '%',
            ],
            'technical' => [
                'technical_deliverables' => rand(3, 8),
                'quality_assessments' => rand(2, 5),
                'compliance_checks' => rand(1, 4),
                'technical_issues' => rand(0, 3),
                'innovations_implemented' => rand(0, 2),
                'capacity_building_sessions' => rand(2, 6),
            ],
            'annual' => [
                'year_highlights' => rand(5, 10),
                'challenges_overcome' => rand(2, 5),
                'lessons_learned' => rand(3, 7),
                'future_plans' => rand(4, 8),
                'stakeholder_feedback' => rand(50, 100) . '% positive',
                'sustainability_measures' => rand(2, 4),
            ],
            default => []
        };
    }

    private function generateUniversityReportData(string $type, University $university): array
    {
        return match ($type) {
            'institutional' => [
                'academic_programs' => rand(20, 50),
                'student_enrollment' => rand(5000, 25000),
                'teaching_staff' => rand(200, 800),
                'administrative_staff' => rand(100, 400),
                'research_projects' => rand(10, 30),
                'publications' => rand(50, 200),
            ],
            'performance' => [
                'graduation_rate' => rand(70, 95) . '%',
                'student_satisfaction' => rand(75, 95) . '%',
                'employer_satisfaction' => rand(70, 90) . '%',
                'research_productivity' => rand(60, 90) . '%',
                'international_collaborations' => rand(5, 20),
                'accreditation_status' => 'Maintained',
            ],
            'sustainability' => [
                'energy_consumption' => rand(500000, 2000000) . ' kWh',
                'waste_recycling_rate' => rand(30, 70) . '%',
                'green_spaces' => rand(5, 20) . ' hectares',
                'sustainable_projects' => rand(3, 10),
                'carbon_footprint' => rand(1000, 5000) . ' tons CO2',
                'environmental_certifications' => rand(0, 3),
            ],
            default => []
        };
    }

    private function generateConsolidatedReportData(string $type): array
    {
        return match ($type) {
            'national' => [
                'total_universities' => University::count(),
                'total_projects' => PduProject::count(),
                'total_budget_allocated' => PduProject::sum('budget_allocated'),
                'total_budget_spent' => PduProject::sum('budget_spent'),
                'average_progress' => rand(65, 85) . '%',
                'projects_completed' => PduProject::where('status', 'completed')->count(),
                'employment_generated' => rand(1000, 5000),
                'students_benefited' => rand(50000, 200000),
            ],
            'sectorial' => [
                'academic_excellence' => rand(70, 90) . '%',
                'research_output' => rand(60, 85) . '%',
                'infrastructure_development' => rand(75, 95) . '%',
                'governance_improvement' => rand(65, 85) . '%',
                'financial_sustainability' => rand(60, 80) . '%',
                'international_visibility' => rand(55, 75) . '%',
            ],
            'impact' => [
                'socioeconomic_impact' => 'High',
                'regional_development' => 'Significant',
                'knowledge_economy' => 'Growing',
                'innovation_ecosystem' => 'Emerging',
                'community_engagement' => 'Strong',
                'policy_influence' => 'Moderate',
                'long_term_sustainability' => 'Promising',
            ],
            default => []
        };
    }
}