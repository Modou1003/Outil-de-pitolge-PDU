<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\IndicatorTracking;
use App\Models\Notification;
use App\Models\PduProject;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationsAndAuditSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $projects = PduProject::all();
        $reports = Report::all();

        // Créer des notifications pour les utilisateurs
        foreach ($users as $user) {
            $this->createUserNotifications($user);
        }

        // Créer des logs d'audit pour les actions importantes
        $this->createAuditLogs($projects, $reports);
    }

    private function createUserNotifications(User $user): void
    {
        $notificationCount = rand(5, 15);

        for ($i = 0; $i < $notificationCount; $i++) {
            $notificationType = $this->getRandomNotificationType();
            $createdAt = now()->subDays(rand(1, 90));

            Notification::create([
                'type' => $notificationType['type'],
                'recipient_id' => $user->id,
                'sender_id' => $this->getRandomSender($user)->id,
                'title' => $notificationType['title'],
                'message' => $this->generateNotificationMessage($notificationType, $user),
                'data' => $this->generateNotificationData($notificationType),
                'priority' => collect(['low', 'normal', 'high', 'urgent'])->random(),
                'is_read' => rand(0, 1),
                'read_at' => rand(0, 1) ? $createdAt->copy()->addMinutes(rand(1, 1440)) : null,
                'expires_at' => rand(0, 1) ? $createdAt->copy()->addDays(rand(7, 30)) : null,
                'action_url' => $this->generateActionUrl($notificationType),
                'action_text' => $this->generateActionText($notificationType),
                'metadata' => [
                    'category' => $notificationType['category'],
                    'source' => 'system',
                    'importance' => collect(['low', 'medium', 'high'])->random(),
                ],
            ]);
        }
    }

    private function createAuditLogs($projects, $reports): void
    {
        // Logs pour les projets
        foreach ($projects as $project) {
            $this->createProjectAuditLogs($project);
        }

        // Logs pour les rapports
        foreach ($reports as $report) {
            $this->createReportAuditLogs($report);
        }

        // Logs pour les indicateurs
        $trackings = IndicatorTracking::all();
        foreach ($trackings as $tracking) {
            $this->createTrackingAuditLogs($tracking);
        }

        // Logs système généraux
        $this->createSystemAuditLogs();
    }

    private function createProjectAuditLogs(PduProject $project): void
    {
        $actions = [
            ['action' => 'created', 'description' => 'Projet créé'],
            ['action' => 'updated', 'description' => 'Projet mis à jour'],
            ['action' => 'status_changed', 'description' => 'Statut modifié'],
            ['action' => 'budget_updated', 'description' => 'Budget modifié'],
            ['action' => 'team_assigned', 'description' => 'Équipe assignée'],
            ['action' => 'document_uploaded', 'description' => 'Document ajouté'],
            ['action' => 'comment_added', 'description' => 'Commentaire ajouté'],
        ];

        foreach ($actions as $action) {
            AuditLog::create([
                'user_id' => User::inRandomOrder()->first()->id,
                'action' => $action['action'],
                'model_type' => PduProject::class,
                'model_id' => $project->id,
                'old_values' => $action['action'] === 'updated' ? ['status' => 'draft'] : null,
                'new_values' => $action['action'] === 'updated' ? ['status' => $project->status] : null,
                'ip_address' => $this->generateRandomIP(),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'context' => [
                    'project_code' => $project->code,
                    'university' => $project->university->name,
                    'timestamp' => now()->subDays(rand(1, 90))->toISOString(),
                ],
                'severity' => collect(['low', 'medium', 'high'])->random(),
                'category' => 'project_management',
                'metadata' => [
                    'module' => 'projects',
                    'action_type' => $action['action'],
                    'impact' => 'project_level',
                ],
            ]);
        }
    }

    private function createReportAuditLogs(Report $report): void
    {
        $actions = [
            ['action' => 'created', 'description' => 'Rapport créé'],
            ['action' => 'updated', 'description' => 'Rapport modifié'],
            ['action' => 'submitted', 'description' => 'Rapport soumis'],
            ['action' => 'approved', 'description' => 'Rapport approuvé'],
            ['action' => 'published', 'description' => 'Rapport publié'],
            ['action' => 'archived', 'description' => 'Rapport archivé'],
        ];

        foreach ($actions as $action) {
            AuditLog::create([
                'user_id' => $report->creator->id,
                'action' => $action['action'],
                'model_type' => Report::class,
                'model_id' => $report->id,
                'old_values' => $action['action'] === 'updated' ? ['status' => 'draft'] : null,
                'new_values' => $action['action'] === 'updated' ? ['status' => $report->status] : null,
                'ip_address' => $this->generateRandomIP(),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'context' => [
                    'report_type' => $report->type,
                    'period' => $report->period,
                    'title' => $report->title,
                ],
                'severity' => collect(['low', 'medium', 'high'])->random(),
                'category' => 'reporting',
                'metadata' => [
                    'module' => 'reports',
                    'action_type' => $action['action'],
                    'report_category' => $report->type,
                ],
            ]);
        }
    }

    private function createTrackingAuditLogs(IndicatorTracking $tracking): void
    {
        $actions = [
            ['action' => 'created', 'description' => 'Suivi créé'],
            ['action' => 'updated', 'description' => 'Suivi modifié'],
            ['action' => 'validated', 'description' => 'Suivi validé'],
            ['action' => 'rejected', 'description' => 'Suivi rejeté'],
        ];

        foreach ($actions as $action) {
            AuditLog::create([
                'user_id' => $tracking->recorder->id,
                'action' => $action['action'],
                'model_type' => IndicatorTracking::class,
                'model_id' => $tracking->id,
                'old_values' => $action['action'] === 'updated' ? ['status' => 'draft'] : null,
                'new_values' => $action['action'] === 'updated' ? ['status' => $tracking->status] : null,
                'ip_address' => $this->generateRandomIP(),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'context' => [
                    'indicator' => $tracking->indicator->name,
                    'project' => $tracking->pduProject->title,
                    'period' => $tracking->period,
                    'value' => $tracking->actual_value,
                ],
                'severity' => collect(['low', 'medium', 'high'])->random(),
                'category' => 'monitoring',
                'metadata' => [
                    'module' => 'indicators',
                    'action_type' => $action['action'],
                    'indicator_type' => $tracking->indicator->type,
                ],
            ]);
        }
    }

    private function createSystemAuditLogs(): void
    {
        $systemActions = [
            ['action' => 'user_login', 'description' => 'Connexion utilisateur'],
            ['action' => 'user_logout', 'description' => 'Déconnexion utilisateur'],
            ['action' => 'password_changed', 'description' => 'Mot de passe modifié'],
            ['action' => 'role_assigned', 'description' => 'Rôle assigné'],
            ['action' => 'permission_granted', 'description' => 'Permission accordée'],
            ['action' => 'system_backup', 'description' => 'Sauvegarde système'],
            ['action' => 'data_export', 'description' => 'Export de données'],
        ];

        foreach ($systemActions as $action) {
            AuditLog::create([
                'user_id' => User::inRandomOrder()->first()->id,
                'action' => $action['action'],
                'model_type' => null,
                'model_id' => null,
                'old_values' => null,
                'new_values' => null,
                'ip_address' => $this->generateRandomIP(),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'context' => [
                    'system_action' => $action['action'],
                    'timestamp' => now()->subDays(rand(1, 30))->toISOString(),
                ],
                'severity' => collect(['low', 'medium', 'high'])->random(),
                'category' => 'system',
                'metadata' => [
                    'module' => 'system',
                    'action_type' => $action['action'],
                    'automated' => rand(0, 1),
                ],
            ]);
        }
    }

    private function getRandomNotificationType(): array
    {
        $types = [
            [
                'type' => 'project_update',
                'title' => 'Mise à jour de projet',
                'category' => 'projects'
            ],
            [
                'type' => 'report_due',
                'title' => 'Rapport à remettre',
                'category' => 'reporting'
            ],
            [
                'type' => 'indicator_validation',
                'title' => 'Validation d\'indicateur requise',
                'category' => 'monitoring'
            ],
            [
                'type' => 'document_shared',
                'title' => 'Document partagé',
                'category' => 'documents'
            ],
            [
                'type' => 'comment_mention',
                'title' => 'Vous avez été mentionné',
                'category' => 'communication'
            ],
            [
                'type' => 'deadline_approaching',
                'title' => 'Échéance proche',
                'category' => 'planning'
            ],
            [
                'type' => 'system_maintenance',
                'title' => 'Maintenance système',
                'category' => 'system'
            ],
        ];

        return $types[array_rand($types)];
    }

    private function generateNotificationMessage(array $type, User $user): string
    {
        $messages = [
            'project_update' => [
                "Le projet PDU-{$this->generateRandomCode()} a été mis à jour.",
                "Nouveau statut pour le projet de {$this->getRandomUniversity()}.",
                "Avancement significatif sur le projet {$this->generateRandomCode()}.",
            ],
            'report_due' => [
                "Le rapport trimestriel doit être soumis avant le " . now()->addDays(rand(1, 30))->format('d/m/Y'),
                "Rappel: Rapport annuel à remettre d'ici 2 semaines.",
                "Soumission du rapport de progrès requise.",
            ],
            'indicator_validation' => [
                "Les données d'indicateur pour {$this->getRandomMonth()} nécessitent validation.",
                "Nouvelle mesure d'indicateur en attente d'approbation.",
                "Validation requise pour les indicateurs de performance.",
            ],
            'document_shared' => [
                "Un nouveau document a été partagé avec vous.",
                "Document '{$this->generateRandomDocument()}' ajouté au projet.",
                "Consultation requise: nouveau document disponible.",
            ],
            'comment_mention' => [
                "{$this->getRandomUserName()} vous a mentionné dans un commentaire.",
                "Vous avez été tagué dans la discussion du projet.",
                "Réponse à votre commentaire sur le rapport.",
            ],
            'deadline_approaching' => [
                "Échéance dans " . rand(1, 7) . " jours pour le projet {$this->generateRandomCode()}.",
                "Rappel: Date limite de soumission approche.",
                "Action requise avant la deadline.",
            ],
            'system_maintenance' => [
                "Maintenance système programmée pour ce soir.",
                "Mise à jour de sécurité disponible.",
                "Nouvelles fonctionnalités déployées.",
            ],
        ];

        $categoryMessages = $messages[$type['type']] ?? ['Notification système'];
        return $categoryMessages[array_rand($categoryMessages)];
    }

    private function generateNotificationData(array $type): array
    {
        return match ($type['type']) {
            'project_update' => [
                'project_id' => rand(1, 100),
                'old_status' => 'in_progress',
                'new_status' => 'completed',
                'updated_by' => $this->getRandomUserName(),
            ],
            'report_due' => [
                'report_type' => 'quarterly',
                'due_date' => now()->addDays(rand(1, 30))->format('Y-m-d'),
                'project_id' => rand(1, 100),
            ],
            'indicator_validation' => [
                'indicator_id' => rand(1, 50),
                'period' => $this->getRandomMonth(),
                'value' => rand(50, 100),
            ],
            'document_shared' => [
                'document_id' => rand(1, 200),
                'document_name' => $this->generateRandomDocument(),
                'shared_by' => $this->getRandomUserName(),
            ],
            'comment_mention' => [
                'comment_id' => rand(1, 500),
                'comment_author' => $this->getRandomUserName(),
                'project_id' => rand(1, 100),
            ],
            default => []
        };
    }

    private function generateActionUrl(array $type): ?string
    {
        return match ($type['type']) {
            'project_update' => '/projects/' . rand(1, 100),
            'report_due' => '/reports/create',
            'indicator_validation' => '/indicators/' . rand(1, 50) . '/validate',
            'document_shared' => '/documents/' . rand(1, 200),
            'comment_mention' => '/projects/' . rand(1, 100) . '#comments',
            default => null,
        };
    }

    private function generateActionText(array $type): ?string
    {
        return match ($type['type']) {
            'project_update' => 'Voir le projet',
            'report_due' => 'Soumettre le rapport',
            'indicator_validation' => 'Valider les données',
            'document_shared' => 'Consulter le document',
            'comment_mention' => 'Voir le commentaire',
            default => null,
        };
    }

    private function getRandomSender(User $recipient): User
    {
        return User::where('id', '!=', $recipient->id)->inRandomOrder()->first() ?? User::factory()->create();
    }

    private function generateRandomIP(): string
    {
        return rand(1, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
    }

    private function generateRandomCode(): string
    {
        return 'PDU-' . rand(100, 999);
    }

    private function getRandomUniversity(): string
    {
        $universities = ['Université de Yaoundé I', 'Université de Douala', 'Université de Dschang', 'Université de Ngaoundéré'];
        return $universities[array_rand($universities)];
    }

    private function getRandomMonth(): string
    {
        $months = ['2024-01', '2024-02', '2024-03', '2024-04', '2024-05', '2024-06'];
        return $months[array_rand($months)];
    }

    private function generateRandomDocument(): string
    {
        $documents = ['Rapport technique', 'Budget détaillé', 'Plan de travail', 'Étude d\'impact', 'Cahier des charges'];
        return $documents[array_rand($documents)];
    }

    private function getRandomUserName(): string
    {
        $names = ['Jean Dupont', 'Marie Claire', 'Pierre Martin', 'Sophie Dubois', 'Michel Leroy'];
        return $names[array_rand($names)];
    }
}