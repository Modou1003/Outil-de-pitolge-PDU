<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Document;
use App\Models\PduProject;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;

class DocumentsAndCommentsSeeder extends Seeder
{
    public function run(): void
    {
        $projects = PduProject::all();
        $reports = Report::all();

        // Créer des documents pour les projets
        foreach ($projects as $project) {
            $this->createProjectDocuments($project);
            $this->createProjectComments($project);
        }

        // Créer des documents pour les rapports
        foreach ($reports as $report) {
            $this->createReportDocuments($report);
            $this->createReportComments($report);
        }
    }

    private function createProjectDocuments(PduProject $project): void
    {
        $documentTypes = [
            [
                'title' => 'Cahier des charges',
                'category' => 'contractual',
                'file_name' => 'cdc_' . $project->code . '.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => rand(500000, 2000000),
            ],
            [
                'title' => 'Étude de faisabilité',
                'category' => 'technical',
                'file_name' => 'etude_faisabilite_' . $project->code . '.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => rand(1000000, 5000000),
            ],
            [
                'title' => 'Plan de travail détaillé',
                'category' => 'planning',
                'file_name' => 'plan_travail_' . $project->code . '.xlsx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'file_size' => rand(200000, 1000000),
            ],
            [
                'title' => 'Rapport d\'avancement - ' . now()->subMonths(rand(1, 6))->format('M Y'),
                'category' => 'reporting',
                'file_name' => 'rapport_avancement_' . $project->code . '_' . now()->format('Y_m') . '.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => rand(300000, 1500000),
            ],
            [
                'title' => 'Budget détaillé',
                'category' => 'financial',
                'file_name' => 'budget_detaille_' . $project->code . '.xlsx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'file_size' => rand(150000, 800000),
            ],
            [
                'title' => 'Photos du chantier',
                'category' => 'media',
                'file_name' => 'photos_chantier_' . $project->code . '.zip',
                'mime_type' => 'application/zip',
                'file_size' => rand(5000000, 20000000),
            ],
        ];

        foreach ($documentTypes as $docType) {
            Document::create([
                'title' => $docType['title'],
                'description' => "Document {$docType['category']} pour le projet {$project->title}",
                'file_path' => 'documents/projects/' . $project->id . '/' . $docType['file_name'],
                'file_name' => $docType['file_name'],
                'mime_type' => $docType['mime_type'],
                'file_size' => $docType['file_size'],
                'documentable_type' => PduProject::class,
                'documentable_id' => $project->id,
                'category' => $docType['category'],
                'subcategory' => $this->getSubcategory($docType['category']),
                'tags' => $this->generateTags($docType['category']),
                'version' => '1.0',
                'is_latest_version' => true,
                'metadata' => [
                    'upload_date' => now()->subDays(rand(1, 180))->format('Y-m-d'),
                    'confidentiality' => collect(['public', 'internal', 'confidential'])->random(),
                    'retention_period' => rand(5, 10) . ' years',
                ],
                'notes' => rand(0, 1) ? 'Document validé et archivé selon les procédures PDU.' : null,
                'uploaded_by' => User::whereHas('roles', function($q) {
                    $q->whereIn('name', ['chef_projet', 'agent_financier']);
                })->inRandomOrder()->first()->id ?? User::factory()->create()->id,
                'uploaded_at' => now()->subDays(rand(1, 180)),
                'visibility' => collect(['public', 'internal', 'restricted'])->random(),
                'is_archived' => rand(0, 10) > 8, // 20% de chance d'être archivé
            ]);
        }
    }

    private function createReportDocuments(Report $report): void
    {
        $documentTypes = [
            [
                'title' => 'Rapport complet',
                'file_name' => 'rapport_complet_' . $report->id . '.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => rand(500000, 2000000),
            ],
            [
                'title' => 'Annexes statistiques',
                'file_name' => 'annexes_statistiques_' . $report->id . '.xlsx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'file_size' => rand(200000, 1000000),
            ],
            [
                'title' => 'Présentation exécutive',
                'file_name' => 'presentation_executive_' . $report->id . '.pptx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'file_size' => rand(1000000, 5000000),
            ],
        ];

        foreach ($documentTypes as $docType) {
            Document::create([
                'title' => $docType['title'],
                'description' => "Document annexe du rapport: {$report->title}",
                'file_path' => 'documents/reports/' . $report->id . '/' . $docType['file_name'],
                'file_name' => $docType['file_name'],
                'mime_type' => $docType['mime_type'],
                'file_size' => $docType['file_size'],
                'documentable_type' => Report::class,
                'documentable_id' => $report->id,
                'category' => 'reporting',
                'subcategory' => 'annex',
                'tags' => ['rapport', $report->type, 'annexe'],
                'version' => '1.0',
                'is_latest_version' => true,
                'metadata' => [
                    'report_type' => $report->type,
                    'period' => $report->period,
                    'confidentiality' => $report->is_public ? 'public' : 'internal',
                ],
                'uploaded_by' => $report->creator->id,
                'uploaded_at' => $report->created_at,
                'visibility' => $report->is_public ? 'public' : 'internal',
                'is_archived' => false,
            ]);
        }
    }

    private function createProjectComments(PduProject $project): void
    {
        $commentCount = rand(3, 10);

        for ($i = 0; $i < $commentCount; $i++) {
            $user = User::inRandomOrder()->first() ?? User::factory()->create();
            $createdAt = $project->created_at->copy()->addDays(rand(1, 180));

            Comment::create([
                'content' => $this->generateCommentContent($project, $i),
                'commentable_type' => PduProject::class,
                'commentable_id' => $project->id,
                'user_id' => $user->id,
                'parent_id' => $i > 2 && rand(0, 1) ? Comment::where('commentable_type', PduProject::class)
                    ->where('commentable_id', $project->id)
                    ->inRandomOrder()
                    ->first()->id : null,
                'is_edited' => rand(0, 1),
                'edited_at' => rand(0, 1) ? $createdAt->copy()->addHours(rand(1, 24)) : null,
                'is_deleted' => false,
                'mentions' => rand(0, 1) ? [$this->getRandomUser()->id] : [],
                'metadata' => [
                    'importance' => collect(['low', 'medium', 'high'])->random(),
                    'category' => collect(['general', 'technical', 'financial', 'planning'])->random(),
                ],
            ]);
        }
    }

    private function createReportComments(Report $report): void
    {
        $commentCount = rand(2, 6);

        for ($i = 0; $i < $commentCount; $i++) {
            $user = User::inRandomOrder()->first() ?? User::factory()->create();

            Comment::create([
                'content' => $this->generateReportCommentContent($report, $i),
                'commentable_type' => Report::class,
                'commentable_id' => $report->id,
                'user_id' => $user->id,
                'parent_id' => null, // Les commentaires de rapport sont généralement sans réponse
                'is_edited' => rand(0, 1),
                'edited_at' => rand(0, 1) ? now()->subDays(rand(1, 30)) : null,
                'is_deleted' => false,
                'mentions' => rand(0, 1) ? [$this->getRandomUser()->id] : [],
                'metadata' => [
                    'feedback_type' => collect(['general', 'technical', 'content', 'presentation'])->random(),
                    'sentiment' => collect(['positive', 'neutral', 'constructive'])->random(),
                ],
            ]);
        }
    }

    private function generateCommentContent(PduProject $project, int $index): string
    {
        $comments = [
            "Excellente progression sur ce projet. Les objectifs sont en train d'être atteints selon le planning prévu.",
            "Il faudrait renforcer le suivi des indicateurs de performance pour mieux mesurer l'impact.",
            "La coordination avec les équipes locales se passe bien. Bonne collaboration établie.",
            "Quelques défis logistiques ont été rencontrés, mais ils ont été résolus efficacement.",
            "Le budget est bien maîtrisé et les dépenses sont conformes aux prévisions.",
            "Proposition d'ajouter des formations complémentaires pour le personnel technique.",
            "Les livrables intermédiaires sont de bonne qualité. Continuez ainsi !",
            "Il serait utile d'organiser une réunion de suivi avec tous les partenaires.",
            "Les étudiants commencent à bénéficier des améliorations apportées.",
            "Recommandation: documenter davantage les leçons apprises pour les prochains projets.",
            "La communication avec le ministère est fluide et régulière.",
            "Besoin de clarifier certains points techniques dans le cahier des charges.",
        ];

        return $comments[$index % count($comments)];
    }

    private function generateReportCommentContent(Report $report, int $index): string
    {
        $comments = [
            "Rapport très complet et bien structuré. Bonne analyse des données.",
            "Les recommandations sont pertinentes et actionnables.",
            "Il serait utile d'ajouter plus de graphiques pour illustrer les tendances.",
            "Excellent travail sur l'analyse des indicateurs de performance.",
            "Suggestion: inclure une section sur les leçons apprises.",
            "Les données présentées sont cohérentes et bien sourcées.",
            "Très bonne couverture des différents aspects du projet.",
            "Proposition d'ajouter des KPI plus spécifiques dans la prochaine version.",
        ];

        return $comments[$index % count($comments)];
    }

    private function getSubcategory(string $category): string
    {
        return match ($category) {
            'contractual' => 'legal',
            'technical' => 'engineering',
            'planning' => 'management',
            'reporting' => 'monitoring',
            'financial' => 'budget',
            'media' => 'communication',
            default => 'general',
        };
    }

    private function generateTags(string $category): array
    {
        $baseTags = ['PDU', 'projet'];

        $categoryTags = match ($category) {
            'contractual' => ['contrat', 'juridique', 'engagement'],
            'technical' => ['technique', 'ingénierie', 'spécifications'],
            'planning' => ['planning', 'calendrier', 'jalons'],
            'reporting' => ['rapport', 'suivi', 'évaluation'],
            'financial' => ['budget', 'finances', 'coûts'],
            'media' => ['photos', 'vidéos', 'communication'],
            default => ['général'],
        };

        return array_merge($baseTags, $categoryTags);
    }

    private function getRandomUser(): User
    {
        return User::inRandomOrder()->first() ?? User::factory()->create();
    }
}