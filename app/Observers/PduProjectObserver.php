<?php

namespace App\Observers;

use App\Models\Indicator;
use App\Models\IndicatorTracking;
use App\Models\PduProject;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PduProjectObserver
{
    /**
     * Au moment où un projet PDU est créé, on attache automatiquement
     * tous les indicateurs actifs en créant un IndicatorTracking initial
     * (vide) afin que les personnes qualifiées puissent les renseigner.
     */
    public function created(PduProject $project): void
    {
        $indicators = Indicator::active()
            ->orderBy('sort_order')
            ->get(['id', 'target_value']);

        if ($indicators->isEmpty()) {
            return;
        }

        $now = Carbon::now();
        $measurementDate = $project->start_date ?: $now;
        $period = $this->defaultPeriod($measurementDate);
        $recordedBy = $project->created_by;

        $rows = $indicators->map(function (Indicator $indicator) use ($project, $measurementDate, $period, $recordedBy, $now) {
            return [
                'indicator_id' => $indicator->id,
                'pdu_project_id' => $project->id,
                'recorded_by' => $recordedBy,
                'measurement_date' => $measurementDate->toDateString(),
                'period' => $period,
                'actual_value' => null,
                'target_value' => $indicator->target_value,
                'previous_value' => null,
                'status' => 'draft',
                'comments' => null,
                'validation_notes' => null,
                'validated_by' => null,
                'validated_at' => null,
                'data_sources' => null,
                'attachments' => null,
                'metadata' => json_encode(['auto_generated' => true]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        DB::table('indicator_trackings')->insertOrIgnore($rows);
    }

    /**
     * Lors d'un soft delete, on libère le code projet pour pouvoir
     * le réutiliser sur un nouveau projet actif.
     */
    public function deleted(PduProject $project): void
    {
        if ($project->isForceDeleting()) {
            return;
        }

        if (! $project->trashed()) {
            return;
        }

        if (str_contains($project->code, '__deleted__')) {
            return;
        }

        $suffix = '__deleted__' . $project->id;
        $project->forceFill([
            'code' => Str::limit($project->code, 255 - strlen($suffix), '') . $suffix,
        ])->saveQuietly();
    }

    /**
     * Calcule la période par défaut d'une mesure.
     * Retourne un libellé type "2026-Q2" en fonction de la fréquence standard.
     */
    private function defaultPeriod(Carbon $date): string
    {
        $quarter = (int) ceil($date->month / 3);

        return $date->year . '-Q' . $quarter;
    }
}
