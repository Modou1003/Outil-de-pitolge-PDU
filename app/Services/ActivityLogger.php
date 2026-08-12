<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Journalisation des actions des utilisateurs.
 *
 * Centralise l'écriture du journal d'activité pour que les contrôleurs se
 * contentent d'une ligne. Chaque entrée retient l'auteur, la nature de
 * l'action, l'entité concernée, une description lisible et le projet de
 * rattachement — ce dernier servant de filtre principal à la consultation.
 *
 * La journalisation ne doit jamais faire échouer l'opération métier qu'elle
 * accompagne : toute erreur d'écriture est absorbée silencieusement.
 */
class ActivityLogger
{
    /** Intitulés des entités, pour un affichage en français. */
    public const MODELS = [
        'PduProject' => 'Projet',
        'PhysicalProgress' => 'Avancement physique',
        'FinancialProgress' => 'Avancement financier',
        'ProjectPayment' => 'Décompte',
        'ProjectAmendment' => 'Avenant',
        'ProjectAdvance' => 'Avances contractuelles',
        'User' => 'Utilisateur',
    ];

    /** Intitulés des actions. */
    public const ACTIONS = [
        'created' => 'Création',
        'updated' => 'Modification',
        'deleted' => 'Suppression',
    ];

    public function log(
        string $action,
        string $model,
        string $description,
        ?Model $subject = null,
        ?int $projectId = null,
        array $details = [],
    ): void {
        try {
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'model' => $model,
                'description' => $description,
                'subject_id' => $subject?->getKey(),
                'pdu_project_id' => $projectId,
                'details' => $details ?: null,
            ]);
        } catch (\Throwable) {
            // Le journal est un service d'observation : sa défaillance ne doit
            // pas empêcher l'enregistrement de la donnée métier.
        }
    }

    public function created(string $model, string $description, ?Model $subject = null, ?int $projectId = null, array $details = []): void
    {
        $this->log('created', $model, $description, $subject, $projectId, $details);
    }

    public function updated(string $model, string $description, ?Model $subject = null, ?int $projectId = null, array $details = []): void
    {
        $this->log('updated', $model, $description, $subject, $projectId, $details);
    }

    public function deleted(string $model, string $description, ?Model $subject = null, ?int $projectId = null, array $details = []): void
    {
        $this->log('deleted', $model, $description, $subject, $projectId, $details);
    }
}
