<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DocumentController as DocCtrl;
use App\Models\BuildingWork;
use App\Models\Document;
use App\Models\FinancialProgress;
use App\Models\Indicator;
use App\Models\IndicatorTracking;
use App\Models\PduProject;
use App\Models\PhysicalProgress;
use App\Models\ProjectLot;
use App\Models\ProjectMilestone;
use App\Models\ProjectTeamMember;
use App\Models\University;
use App\Models\User;
use App\Services\AlerteService;
use App\Exports\ProjetExport;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Maatwebsite\Excel\Facades\Excel;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function __construct(protected AlerteService $alerteService) {}

    public function show(PduProject $project): Response
    {
        $project->load([
            'university:id,name,acronym,location,region,latitude,longitude',
            'creator:id,name',
            'director:id,name',
            'projectManager:id,name',
            'financialAgent:id,name',
            'teamMembers.user:id,name',
            'buildingWorks',
            'lots',
            'milestones',
            'physicalProgresses.lot',
            'financialProgresses.lot',
            'indicatorTrackings.indicator',
            'alerts' => fn ($q) => $q->open()->orderByDesc('severity'),
            'documents' => fn ($q) => $q->where('is_archived', false)->with('uploader:id,name')->orderByDesc('uploaded_at'),
        ]);

        return Inertia::render('Projects/Show', [
            'project' => $this->transformProject($project),
            'building_works' => $project->buildingWorks->map(fn ($w) => $this->transformBuildingWork($w))->values()->all(),
            'lots' => $project->lots->map(fn (ProjectLot $l) => $this->transformLot($l))->values()->all(),
            'milestones' => $project->milestones->map(fn (ProjectMilestone $m) => $this->transformMilestone($m))->values()->all(),
            'physical_progresses' => $project->physicalProgresses->map(fn (PhysicalProgress $p) => $this->transformPhysical($p))->values()->all(),
            'financial_progresses' => $project->financialProgresses->map(fn (FinancialProgress $f) => $this->transformFinancial($f))->values()->all(),
            'indicator_trackings' => $project->indicatorTrackings->map(fn ($t) => [
                'id' => $t->id,
                'indicator_id' => $t->indicator_id,
                'code' => $t->indicator?->code,
                'name' => $t->indicator?->name,
                'unit' => $t->indicator?->unit_symbol,
                'target_value' => $t->target_value !== null ? (float) $t->target_value : null,
                'actual_value' => $t->actual_value !== null ? (float) $t->actual_value : null,
                'previous_value' => $t->previous_value !== null ? (float) $t->previous_value : null,
                'status' => $t->status,
                'period' => $t->period,
                'measurement_date' => $t->measurement_date?->toDateString(),
                'achievement_rate' => $this->achievementRate($t->actual_value, $t->target_value),
            ])->values()->all(),
            'alerts' => $project->alerts->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'type_label' => $a->type_label,
                'severity' => $a->severity,
                'severity_label' => $a->severity_label,
                'title' => $a->title,
                'message' => $a->message,
                'detected_at' => $a->detected_at?->toIso8601String(),
            ])->values()->all(),
            'documents' => $project->documents->map(fn (Document $d) => $this->transformDocument($d))->values()->all(),
            'document_categories' => DocCtrl::CATEGORIES,
            'kpis' => $this->computeKpis($project),
            'indicator_catalog' => Indicator::query()
                ->where('is_active', true)
                ->select('id', 'code', 'name', 'unit_symbol', 'target_value')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Indicator $i) => [
                    'id' => $i->id,
                    'code' => $i->code,
                    'name' => $i->name,
                    'unit_symbol' => $i->unit_symbol,
                    'target_value' => $i->target_value !== null ? (float) $i->target_value : null,
                ])->values()->all(),
            'team_candidates' => User::query()->select('id', 'name')->orderBy('name')->get()->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
            ])->values()->all(),
            'can_manage_team' => (bool) request()->user()?->can('edit_project'),
            'can_manage_indicators' => (bool) request()->user()?->can('edit_project'),
        ]);
    }

    private function transformDocument(Document $d): array
    {
        return [
            'id' => $d->id,
            'title' => $d->title,
            'description' => $d->description,
            'file_name' => $d->file_name,
            'mime_type' => $d->mime_type,
            'file_size' => $d->file_size,
            'file_size_human' => $d->file_size_human,
            'extension' => $d->file_extension,
            'is_image' => $d->is_image,
            'is_pdf' => $d->is_pdf,
            'category' => $d->category,
            'visibility' => $d->visibility,
            'uploaded_at' => $d->uploaded_at?->toIso8601String(),
            'uploader' => $d->uploader ? ['id' => $d->uploader->id, 'name' => $d->uploader->name] : null,
        ];
    }

    private function transformProject(PduProject $p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'title' => $p->title,
            'description' => $p->description,
            'status' => $p->status,
            'type' => $p->type,
            'progress_percentage' => (float) $p->progress_percentage,
            'planned_progress' => $p->planned_progress,
            'budget_allocated' => (float) $p->budget_allocated,
            'budget_spent' => (float) $p->budget_spent,
            'budget_execution_rate' => $p->budget_execution_rate,
            'currency' => $p->currency,
            'start_date' => $p->start_date?->toDateString(),
            'end_date' => $p->end_date?->toDateString(),
            'planned_completion_date' => $p->planned_completion_date?->toDateString(),
            'is_overdue' => $p->is_overdue,
            'objectives' => $p->objectives,
            'stakeholders' => $p->stakeholders,
            'university' => $p->university ? [
                'id' => $p->university->id,
                'name' => $p->university->name,
                'acronym' => $p->university->acronym,
                'location' => $p->university->location,
                'region' => $p->university->region,
                'latitude' => $p->university->latitude !== null ? (float) $p->university->latitude : null,
                'longitude' => $p->university->longitude !== null ? (float) $p->university->longitude : null,
            ] : null,
            'creator' => $p->creator ? ['id' => $p->creator->id, 'name' => $p->creator->name] : null,
            'director' => $p->director ? ['id' => $p->director->id, 'name' => $p->director->name] : null,
            'director_name' => $p->director_name,
            'director_email' => $p->director_email,
            'project_manager' => $p->projectManager ? ['id' => $p->projectManager->id, 'name' => $p->projectManager->name] : null,
            'project_manager_name' => $p->project_manager_name,
            'project_manager_email' => $p->project_manager_email,
            'financial_agent' => $p->financialAgent ? ['id' => $p->financialAgent->id, 'name' => $p->financialAgent->name] : null,
            'financial_agent_name' => $p->financial_agent_name,
            'financial_agent_email' => $p->financial_agent_email,
            'team_members' => $p->teamMembers->map(fn (ProjectTeamMember $m) => [
                'id' => $m->id,
                'role_key' => $m->role_key,
                'role_label' => $m->role_label,
                'user' => $m->user ? ['id' => $m->user->id, 'name' => $m->user->name] : null,
                'name' => $m->name,
                'organization' => $m->organization,
                'phone' => $m->phone,
                'email' => $m->email,
                'notes' => $m->notes,
                'sort_order' => (int) $m->sort_order,
            ])->values()->all(),
        ];
    }

    private function transformBuildingWork(BuildingWork $w): array
    {
        return [
            'id' => $w->id,
            'code' => $w->code,
            'name' => $w->name,
            'description' => $w->description,
            'status' => $w->status,
            'status_label' => BuildingWork::STATUSES[$w->status] ?? $w->status,
            'sort_order' => (int) $w->sort_order,
        ];
    }

    private function transformLot(ProjectLot $l): array
    {
        return [
            'id' => $l->id,
            'building_work_id' => $l->building_work_id,
            'code' => $l->code,
            'name' => $l->name,
            'description' => $l->description,
            'weight_percentage' => (float) $l->weight_percentage,
            'progress_percentage' => (float) $l->progress_percentage,
            'status' => $l->status,
            'status_label' => ProjectLot::STATUSES[$l->status] ?? $l->status,
            'planned_start_date' => $l->planned_start_date?->toDateString(),
            'planned_end_date' => $l->planned_end_date?->toDateString(),
            'actual_start_date' => $l->actual_start_date?->toDateString(),
            'actual_end_date' => $l->actual_end_date?->toDateString(),
            'observations' => $l->observations,
            'sort_order' => $l->sort_order,
        ];
    }

    private function transformMilestone(ProjectMilestone $m): array
    {
        return [
            'id' => $m->id,
            'building_work_id' => $m->building_work_id,
            'project_lot_id' => $m->project_lot_id,
            'name' => $m->name,
            'description' => $m->description,
            'planned_date' => $m->planned_date?->toDateString(),
            'actual_date' => $m->actual_date?->toDateString(),
            'status' => $m->status,
            'status_label' => ProjectMilestone::STATUSES[$m->status] ?? $m->status,
            'is_critical' => $m->is_critical,
            'is_late' => $m->is_late,
            'observations' => $m->observations,
        ];
    }

    private function transformPhysical(PhysicalProgress $p): array
    {
        return [
            'id' => $p->id,
            'project_lot_id' => $p->project_lot_id,
            'lot' => $p->lot ? [
                'id' => $p->lot->id,
                'code' => $p->lot->code,
                'name' => $p->lot->name,
            ] : null,
            'period' => $p->period,
            'measurement_date' => $p->measurement_date?->toDateString(),
            'planned_percentage' => (float) $p->planned_percentage,
            'actual_percentage' => (float) $p->actual_percentage,
            'variance' => $p->variance,
            'observations' => $p->observations,
        ];
    }

    private function transformFinancial(FinancialProgress $f): array
    {
        return [
            'id' => $f->id,
            'project_lot_id' => $f->project_lot_id,
            'lot' => $f->lot ? [
                'id' => $f->lot->id,
                'code' => $f->lot->code,
                'name' => $f->lot->name,
            ] : null,
            'period' => $f->period,
            'measurement_date' => $f->measurement_date?->toDateString(),
            'planned_value' => (float) $f->planned_value,
            'earned_value' => (float) $f->earned_value,
            'actual_cost' => (float) $f->actual_cost,
            'cumulative_planned_value' => (float) $f->cumulative_planned_value,
            'cumulative_earned_value' => (float) $f->cumulative_earned_value,
            'cumulative_actual_cost' => (float) $f->cumulative_actual_cost,
            'cpi' => $f->cpi,
            'spi' => $f->spi,
            'cv' => $f->cv,
            'sv' => $f->sv,
            'observations' => $f->observations,
        ];
    }

    private function achievementRate(?float $actual, ?float $target): ?float
    {
        if ($actual === null || $target === null || $target <= 0) return null;
        return round(($actual / $target) * 100, 1);
    }

    private function computeKpis(PduProject $project): array
    {
        $allFinancial = $project->financialProgresses;
        $sumPv = (float) $allFinancial->sum('planned_value');
        $sumEv = (float) $allFinancial->sum('earned_value');
        $sumAc = (float) $allFinancial->sum('actual_cost');
        $cpi = $sumAc > 0 ? round($sumEv / $sumAc, 3) : null;
        $spi = $sumPv > 0 ? round($sumEv / $sumPv, 3) : null;

        return [
            'cpi' => $cpi,
            'spi' => $spi,
            'cv' => $sumEv - $sumAc,
            'sv' => $sumEv - $sumPv,
            'eac' => $this->computeEacFromCpi($cpi, (float) $project->budget_allocated),
            'alerts_open' => $project->alerts->count(),
            'milestones_total' => $project->milestones->count(),
            'milestones_reached' => $project->milestones->where('status', 'reached')->count(),
            'milestones_missed' => $project->milestones->where('status', 'missed')->count(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->user() || ! $request->user()->can('create_project')) {
            abort(403);
        }

        $data = $request->validate([
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('pdu_projects', 'code')->whereNull('deleted_at'),
            ],
            'title' => 'required|string|max:255',
            'university_id' => 'required|exists:universities,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'planned_completion_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:draft,submitted,approved,in_progress,on_hold,completed,cancelled,archived',
            'type' => 'required|in:construction,rehabilitation,equipement,formation,recherche,numerique',
            'budget_allocated' => 'required|numeric|min:0.01',
        ]);

        $data['created_by'] = auth()->id();
        $data['progress_percentage'] = 0;
        $data['budget_spent'] = 0;
        $data['currency'] = 'XOF';

        // Auto-generate code if not provided
        if (empty($data['code'])) {
            $maxCode = PduProject::whereNull('deleted_at')
                ->get()
                ->map(fn($p) => (int) preg_replace('/[^0-9]/', '', $p->code))
                ->max() ?? 0;
            $data['code'] = 'PRJ-' . str_pad($maxCode + 1, 3, '0', STR_PAD_LEFT);
        }

        PduProject::create($data);
        $this->alerteService->generateForAll();

        return redirect()->route('dashboard');
    }

    public function updateStatus(Request $request, PduProject $project): RedirectResponse
    {
        if (! $request->user() || ! $request->user()->can('edit_project')) {
            abort(403);
        }

        $data = $request->validate([
            'status' => 'required|in:draft,submitted,approved,in_progress,on_hold,completed,cancelled,archived',
        ]);

        $project->update($data);

        return redirect()->back();
    }

    public function destroy(Request $request, PduProject $project): RedirectResponse
    {
        if (! $request->user() || ! $request->user()->can('delete_project')) {
            abort(403);
        }

        $project->delete();

        return redirect()->route('dashboard');
    }

    public function updateTeam(Request $request, PduProject $project): RedirectResponse
    {
        if (! $request->user() || ! $request->user()->can('edit_project')) {
            abort(403);
        }

        $data = $request->validate([
            'director_id' => 'nullable|exists:users,id',
            'project_manager_id' => 'nullable|exists:users,id',
            'financial_agent_id' => 'nullable|exists:users,id',
            'director_name' => 'nullable|string|max:255',
            'project_manager_name' => 'nullable|string|max:255',
            'financial_agent_name' => 'nullable|string|max:255',
            'director_email' => 'nullable|email|max:255',
            'project_manager_email' => 'nullable|email|max:255',
            'financial_agent_email' => 'nullable|email|max:255',
        ]);

        // If an ID is provided, prefer it over the free-text name (and clear the name).
        if (!empty($data['director_id'])) {
            $data['director_name'] = null;
            $data['director_email'] = null;
        }
        if (!empty($data['project_manager_id'])) {
            $data['project_manager_name'] = null;
            $data['project_manager_email'] = null;
        }
        if (!empty($data['financial_agent_id'])) {
            $data['financial_agent_name'] = null;
            $data['financial_agent_email'] = null;
        }

        // If a free-text name is provided, clear the corresponding ID.
        if (!empty($data['director_name'])) $data['director_id'] = null;
        if (!empty($data['project_manager_name'])) $data['project_manager_id'] = null;
        if (!empty($data['financial_agent_name'])) $data['financial_agent_id'] = null;

        $data['director_name'] = isset($data['director_name']) ? (trim($data['director_name']) ?: null) : null;
        $data['project_manager_name'] = isset($data['project_manager_name']) ? (trim($data['project_manager_name']) ?: null) : null;
        $data['financial_agent_name'] = isset($data['financial_agent_name']) ? (trim($data['financial_agent_name']) ?: null) : null;
        $data['director_email'] = isset($data['director_email']) ? (trim($data['director_email']) ?: null) : null;
        $data['project_manager_email'] = isset($data['project_manager_email']) ? (trim($data['project_manager_email']) ?: null) : null;
        $data['financial_agent_email'] = isset($data['financial_agent_email']) ? (trim($data['financial_agent_email']) ?: null) : null;

        $project->update([
            'director_id' => $data['director_id'] ?? null,
            'project_manager_id' => $data['project_manager_id'] ?? null,
            'financial_agent_id' => $data['financial_agent_id'] ?? null,
            'director_name' => $data['director_name'] ?? null,
            'project_manager_name' => $data['project_manager_name'] ?? null,
            'financial_agent_name' => $data['financial_agent_name'] ?? null,
            'director_email' => $data['director_email'] ?? null,
            'project_manager_email' => $data['project_manager_email'] ?? null,
            'financial_agent_email' => $data['financial_agent_email'] ?? null,
        ]);

        return back()->with('success', 'Equipe du projet mise a jour.');
    }

    public function updateTeamMembers(Request $request, PduProject $project): RedirectResponse
    {
        if (! $request->user() || ! $request->user()->can('edit_project')) {
            abort(403);
        }

        $data = $request->validate([
            'members' => 'array',
            'members.*.id' => 'nullable|integer|exists:project_team_members,id',
            'members.*.role_key' => 'required|string|max:80',
            'members.*.role_label' => 'required|string|max:120',
            'members.*.user_id' => 'nullable|exists:users,id',
            'members.*.name' => 'nullable|string|max:255',
            'members.*.organization' => 'nullable|string|max:255',
            'members.*.phone' => 'nullable|string|max:50',
            'members.*.email' => 'nullable|email|max:255',
            'members.*.notes' => 'nullable|string|max:2000',
            'members.*.sort_order' => 'nullable|integer|min:0|max:1000000',
        ]);

        $incoming = collect($data['members'] ?? []);

        // Normalize: user_id OR name, not both. If user_id exists -> clear name.
        $normalized = $incoming->map(function (array $m) {
            $m['user_id'] = $m['user_id'] ?? null;
            $m['name'] = isset($m['name']) ? (trim((string) $m['name']) ?: null) : null;
            if (!empty($m['user_id'])) $m['name'] = null;
            $m['organization'] = isset($m['organization']) ? (trim((string) $m['organization']) ?: null) : null;
            $m['phone'] = isset($m['phone']) ? (trim((string) $m['phone']) ?: null) : null;
            $m['email'] = isset($m['email']) ? (trim((string) $m['email']) ?: null) : null;
            $m['notes'] = isset($m['notes']) ? (trim((string) $m['notes']) ?: null) : null;
            $m['sort_order'] = isset($m['sort_order']) ? (int) $m['sort_order'] : 0;
            return $m;
        })->filter(fn ($m) => !empty($m['user_id']) || !empty($m['name']))->values();

        // Only allow editing members belonging to this project.
        $existing = ProjectTeamMember::where('pdu_project_id', $project->id)->get()->keyBy('id');
        $keepIds = [];

        foreach ($normalized as $row) {
            $id = $row['id'] ?? null;
            if ($id && $existing->has($id)) {
                $existing[$id]->update([
                    'role_key' => $row['role_key'],
                    'role_label' => $row['role_label'],
                    'user_id' => $row['user_id'] ?? null,
                    'name' => $row['name'] ?? null,
                    'organization' => $row['organization'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'email' => $row['email'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'sort_order' => $row['sort_order'] ?? 0,
                ]);
                $keepIds[] = $id;
            } else {
                $m = ProjectTeamMember::create([
                    'pdu_project_id' => $project->id,
                    'role_key' => $row['role_key'],
                    'role_label' => $row['role_label'],
                    'user_id' => $row['user_id'] ?? null,
                    'name' => $row['name'] ?? null,
                    'organization' => $row['organization'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'email' => $row['email'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'sort_order' => $row['sort_order'] ?? 0,
                ]);
                $keepIds[] = $m->id;
            }
        }

        ProjectTeamMember::where('pdu_project_id', $project->id)
            ->whereNotIn('id', $keepIds ?: [-1])
            ->delete();

        return back()->with('success', "Équipe étendue mise à jour.");
    }

    public function addIndicator(Request $request, PduProject $project): RedirectResponse
    {
        if (! $request->user() || ! $request->user()->can('edit_project')) {
            abort(403);
        }

        $data = $request->validate([
            'indicator_id' => [
                'required',
                'exists:indicators,id',
                Rule::unique('indicator_trackings')->where(fn ($q) => $q->where('pdu_project_id', $project->id)),
            ],
        ]);

        $indicator = Indicator::findOrFail($data['indicator_id']);

        IndicatorTracking::create([
            'indicator_id' => $indicator->id,
            'pdu_project_id' => $project->id,
            'recorded_by' => $request->user()->id,
            'measurement_date' => now()->toDateString(),
            'period' => null,
            'actual_value' => null,
            'target_value' => $indicator->target_value,
            'previous_value' => null,
            'status' => 'draft',
        ]);

        return back()->with('success', 'Indicateur ajoute au projet.');
    }

    public function removeIndicator(Request $request, PduProject $project, Indicator $indicator): RedirectResponse
    {
        if (! $request->user() || ! $request->user()->can('edit_project')) {
            abort(403);
        }

        IndicatorTracking::query()
            ->where('pdu_project_id', $project->id)
            ->where('indicator_id', $indicator->id)
            ->delete();

        return back()->with('success', 'Indicateur supprime du projet.');
    }

    public function exportExcel(PduProject $project)
    {
        $project->load(['lots', 'milestones', 'physicalProgresses.lot', 'financialProgresses']);

        return Excel::download(
            new ProjetExport($project),
            sprintf('projet-%s.xlsx', $project->id)
        );
    }

    private function computeEacFromCpi(?float $cpi, float $bac): ?float
    {
        if (! $cpi || $cpi <= 0) return null;
        return round($bac / $cpi, 2);
    }
}
