<?php

use App\Http\Controllers\AlerteController;
use App\Http\Controllers\AvancementsController;
use App\Http\Controllers\BuildingWorkController;
use App\Http\Controllers\CarteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FinancialProgressController;
use App\Http\Controllers\PhysicalProgressController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectLotController;
use App\Http\Controllers\ProjectMilestoneController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

// ⚠️ ROUTE DE DIAGNOSTIC TEMPORAIRE — état des rôles/permissions en base.
// Visite : https://<ton-domaine-railway>/debug-perms?key=pdu-test-2026
Route::get('/debug-perms', function (\Illuminate\Http\Request $request) {
    if ($request->query('key') !== 'pdu-test-2026') {
        abort(403);
    }

    $roles = \Spatie\Permission\Models\Role::with('permissions')
        ->orderBy('name')
        ->get()
        ->mapWithKeys(fn ($r) => [
            $r->name => $r->permissions->pluck('name')->sort()->values()->all(),
        ]);

    $expected = [
        'view_dashboard', 'manage_projects', 'manage_users', 'view_reports', 'export_reports',
        'manage_finances', 'manage_physical', 'manage_alerts', 'view_project',
        'create_project', 'edit_project', 'delete_project',
    ];
    $existing = \Spatie\Permission\Models\Permission::pluck('name')->sort()->values()->all();
    $user = $request->user();

    return response()->json([
        'total_permissions' => count($existing),
        'permissions_attendues' => count($expected),
        'permissions_manquantes' => array_values(array_diff($expected, $existing)),
        'permissions_en_base' => $existing,
        'roles' => $roles,
        'utilisateur_connecte' => $user ? [
            'name' => $user->name,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
            'peut_manage_physical' => $user->can('manage_physical'),
            'peut_manage_finances' => $user->can('manage_finances'),
            'peut_manage_alerts' => $user->can('manage_alerts'),
        ] : 'non connecté',
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
});

// ⚠️ ROUTE TEMPORAIRE — exécute le seed des rôles/permissions sur la base en ligne.
// Visite UNE FOIS : https://<ton-domaine-railway>/debug-seed?key=pdu-test-2026
Route::get('/debug-seed', function (\Illuminate\Http\Request $request) {
    if ($request->query('key') !== 'pdu-test-2026') {
        abort(403);
    }

    \Illuminate\Support\Facades\Artisan::call('permissions:sync');

    $roles = \Spatie\Permission\Models\Role::with('permissions')
        ->whereIn('name', ['admin', 'directeur', 'chef_projet', 'comite_pilotage', 'agent_financier'])
        ->orderBy('name')
        ->get()
        ->mapWithKeys(fn ($r) => [
            $r->name => $r->permissions->pluck('name')->sort()->values()->all(),
        ]);

    return response()->json([
        'resultat' => '✅ Seed exécuté.',
        'total_permissions' => \Spatie\Permission\Models\Permission::count(),
        'roles' => $roles,
        'sortie_console' => trim(\Illuminate\Support\Facades\Artisan::output()),
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
});

// ⚠️ ROUTE DE DIAGNOSTIC TEMPORAIRE — À SUPPRIMER APRÈS LE TEST DES MAILS.
// Visite : https://<ton-domaine-railway>/debug-mail?key=pdu-test-2026
Route::get('/debug-mail', function (\Illuminate\Http\Request $request) {
    if ($request->query('key') !== 'pdu-test-2026') {
        abort(403);
    }

    $to = $request->query('to', config('mail.from.address'));

    $config = [
        'MAIL_MAILER'       => config('mail.default'),
        'MAIL_HOST'         => config('mail.mailers.smtp.host'),
        'MAIL_PORT'         => config('mail.mailers.smtp.port'),
        'MAIL_USERNAME'     => config('mail.mailers.smtp.username'),
        'MAIL_PASSWORD_set' => config('mail.mailers.smtp.password') ? 'OUI ('.strlen((string) config('mail.mailers.smtp.password')).' caractères)' : 'NON',
        'MAIL_FROM_ADDRESS' => config('mail.from.address'),
        'destinataire_test' => $to,
    ];

    try {
        \Illuminate\Support\Facades\Mail::raw(
            'Test d\'envoi depuis le site en ligne (PDU Tracker). Si tu reçois ce message, la configuration SMTP fonctionne.',
            function ($message) use ($to) {
                $message->to($to)->subject('[PDU Tracker] Test SMTP');
            }
        );

        return response()->json([
            'resultat' => '✅ Envoi accepté par le serveur SMTP sans erreur. Vérifie ta boîte de réception (et les SPAMS).',
            'config'   => $config,
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        return response()->json([
            'resultat'        => '❌ ÉCHEC de l\'envoi.',
            'erreur_classe'   => get_class($e),
            'erreur_message'  => $e->getMessage(),
            'config'          => $config,
        ], 500, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
});

// ⚠️ ROUTE TEMPORAIRE — génère un projet de démonstration complet et EN RETARD.
// Visite : https://<domaine>/debug-demo-projet?key=pdu-test-2026
// À SUPPRIMER après la démonstration.
Route::get('/debug-demo-projet', function (\Illuminate\Http\Request $request) {
    if ($request->query('key') !== 'pdu-test-2026') {
        abort(403);
    }

    try {

    $userId = \App\Models\User::query()->min('id');
    $university = \App\Models\University::query()->first();
    if (! $university) {
        $university = \App\Models\University::create([
            'name' => 'Université de Démonstration',
            'acronym' => 'UDEMO',
            'location' => 'Abidjan',
            'region' => 'Lagunes',
        ]);
    }

    $result = \Illuminate\Support\Facades\DB::transaction(function () use ($userId, $university) {
        // Repartir propre : supprime d'abord les ouvrages démo (codes réservés,
        // globalement uniques) qui auraient survécu à une suppression douce du
        // projet, puis le projet démo lui-même (soft-deleted inclus).
        \App\Models\BuildingWork::whereIn('code', ['OUV-D1', 'OUV-D2', 'OUV-D3', 'OUV-D4'])->delete();
        \App\Models\PduProject::withTrashed()->where('code', 'PRJ-DEMO')->forceDelete();

        $project = \App\Models\PduProject::create([
            'code' => 'PRJ-DEMO',
            'title' => 'Construction du Bâtiment Pédagogique (DÉMO)',
            'description' => "Projet de démonstration généré automatiquement — en retard, pour illustrer les indicateurs et alertes.",
            'university_id' => $university->id,
            'created_by' => $userId,
            'start_date' => '2025-02-01',
            'end_date' => '2026-05-31',
            'planned_completion_date' => '2026-05-31',
            'status' => 'in_progress',
            'type' => 'construction',
            'progress_percentage' => 0,
            'budget_allocated' => 500000000,
            'budget_spent' => 0,
            'startup_advance_amount' => 100000000,
            'supply_advance_amount' => 25000000,
            'currency' => 'XOF',
            'objectives' => "Livrer un bâtiment R+2 de 24 salles.",
        ]);

        // Décomptes / états de paiement.
        $payments = [
            ['001', '2025-12', '2025-12-20', 80, 16, 4, 60, true],
            ['002', '2026-03', '2026-03-25', 120, 24, 6, 90, true],
            ['003', '2026-06', '2026-06-28', 90, 18, 4.5, 67.5, false],
        ];
        foreach ($payments as $p) {
            \App\Models\ProjectPayment::create([
                'pdu_project_id' => $project->id,
                'number' => $p[0],
                'period' => $p[1],
                'payment_date' => $p[2],
                'gross_amount' => $p[3] * 1000000,
                'startup_advance_recovery' => $p[4] * 1000000,
                'supply_advance_recovery' => $p[5] * 1000000,
                'net_paid' => $p[6] * 1000000,
                'is_paid' => $p[7],
                'recorded_by' => $userId,
            ]);
        }

        // Ouvrages (pondérations = 100 %).
        $defs = [
            ['code' => 'OUV-D1', 'name' => 'Fondations',   'weight' => 25, 'status' => 'completed'],
            ['code' => 'OUV-D2', 'name' => 'Structure',    'weight' => 35, 'status' => 'in_progress'],
            ['code' => 'OUV-D3', 'name' => 'Second œuvre', 'weight' => 25, 'status' => 'in_progress'],
            ['code' => 'OUV-D4', 'name' => 'VRD',          'weight' => 15, 'status' => 'not_started'],
        ];
        $works = [];
        foreach ($defs as $i => $d) {
            $works[$d['code']] = \App\Models\BuildingWork::create([
                'pdu_project_id' => $project->id,
                'code' => $d['code'],
                'name' => $d['name'],
                'description' => 'Ouvrage de démonstration',
                'status' => $d['status'],
                'weight_percentage' => $d['weight'],
                'sort_order' => $i,
            ]);
        }

        // Saisies physiques (planifié vs réel — le réel est en retard).
        $physical = [
            'OUV-D1' => [['2025-06', 40, 35], ['2025-09', 80, 70], ['2025-12', 100, 100]],
            'OUV-D2' => [['2025-09', 20, 15], ['2025-12', 50, 40], ['2026-03', 85, 60], ['2026-06', 100, 70]],
            'OUV-D3' => [['2025-12', 10, 5], ['2026-03', 45, 25], ['2026-06', 80, 40]],
            'OUV-D4' => [['2026-03', 30, 10], ['2026-06', 70, 20]],
        ];
        foreach ($physical as $code => $rows) {
            foreach ($rows as $r) {
                \App\Models\PhysicalProgress::create([
                    'pdu_project_id' => $project->id,
                    'building_work_id' => $works[$code]->id,
                    'period' => $r[0],
                    'measurement_date' => $r[0] . '-15',
                    'planned_percentage' => $r[1],
                    'actual_percentage' => $r[2],
                    'recorded_by' => $userId,
                    'status' => 'submitted',
                ]);
            }
        }

        // Saisies financières (increments par période ; CPI et SPI < 1).
        $financial = [
            'OUV-D1' => [['2025-09', 60, 55, 65], ['2025-12', 65, 70, 70]],
            'OUV-D2' => [['2025-12', 50, 35, 45], ['2026-03', 60, 45, 60], ['2026-06', 65, 40, 55]],
            'OUV-D3' => [['2026-03', 40, 20, 30], ['2026-06', 45, 25, 40]],
            'OUV-D4' => [['2026-06', 40, 12, 25]],
        ];
        foreach ($financial as $code => $rows) {
            foreach ($rows as $r) {
                \App\Models\FinancialProgress::create([
                    'pdu_project_id' => $project->id,
                    'building_work_id' => $works[$code]->id,
                    'period' => $r[0],
                    'measurement_date' => $r[0] . '-15',
                    'planned_value' => $r[1] * 1000000,
                    'earned_value' => $r[2] * 1000000,
                    'actual_cost' => $r[3] * 1000000,
                    'recorded_by' => $userId,
                    'status' => 'submitted',
                ]);
            }
        }

        // Lots de planning (Gantt) sous chaque ouvrage.
        $lots = [
            ['OUV-D1', 'L01', 'Terrassement', '2025-02-01', '2025-05-31', 'completed'],
            ['OUV-D1', 'L02', 'Semelles & longrines', '2025-05-01', '2025-12-15', 'completed'],
            ['OUV-D2', 'L03', 'Poteaux & poutres', '2025-09-01', '2026-04-30', 'in_progress'],
            ['OUV-D2', 'L04', 'Dalles & planchers', '2025-12-01', '2026-06-30', 'in_progress'],
            ['OUV-D3', 'L05', 'Cloisons & enduits', '2026-01-01', '2026-06-30', 'in_progress'],
            ['OUV-D3', 'L06', 'Finitions', '2026-03-01', '2026-09-30', 'not_started'],
            ['OUV-D4', 'L07', 'Voiries & réseaux', '2026-03-01', '2026-08-31', 'not_started'],
        ];
        $lotModels = [];
        foreach ($lots as $i => $l) {
            $lotModels[$l[1]] = \App\Models\ProjectLot::create([
                'pdu_project_id' => $project->id,
                'building_work_id' => $works[$l[0]]->id,
                'kind' => 'planning',
                'code' => $l[1],
                'name' => $l[2],
                'weight_percentage' => 0,
                'planned_start_date' => $l[3],
                'planned_end_date' => $l[4],
                'status' => $l[5],
                'sort_order' => $i,
            ]);
        }

        // Jalons (certains atteints, d'autres manqués / en retard).
        $milestones = [
            ['OUV-D1', 'L02', 'Réception des fondations', '2025-12-15', '2025-12-20', 'reached', false],
            ['OUV-D2', 'L03', 'Achèvement de la structure', '2026-04-30', null, 'pending', true],
            ['OUV-D3', 'L05', 'Réception provisoire second œuvre', '2026-05-31', null, 'pending', false],
            ['OUV-D4', 'L07', 'Mise en service VRD', '2026-09-30', null, 'pending', false],
        ];
        foreach ($milestones as $i => $m) {
            \App\Models\ProjectMilestone::create([
                'pdu_project_id' => $project->id,
                'building_work_id' => $works[$m[0]]->id,
                'project_lot_id' => $lotModels[$m[1]]->id ?? null,
                'name' => $m[2],
                'planned_date' => $m[3],
                'actual_date' => $m[4],
                'status' => $m[5],
                'is_critical' => $m[6],
                'sort_order' => $i,
            ]);
        }

        // Recalcul des agrégats.
        $agg = app(\App\Services\ProjectAggregationService::class);
        $agg->recomputeFinancialCumulatives($project);
        $agg->recomputeProjectBudgetSpent($project);
        $agg->recomputeProjectProgress($project);
        $project->refresh();

        return $project;
    });

    // Génération des alertes. On neutralise l'envoi de notifications le temps
    // de la requête pour que toutes les alertes soient bien créées.
    $alerteError = null;
    try {
        \Illuminate\Support\Facades\Notification::fake();
        app(\App\Services\AlerteService::class)->generateForProject($result);
    } catch (\Throwable $e) {
        $alerteError = $e->getMessage();
    }

    return response()->json([
        'resultat' => '✅ Projet de démonstration créé.',
        'projet' => [
            'id' => $result->id,
            'code' => $result->code,
            'titre' => $result->title,
            'avancement_pondere' => (float) $result->fresh()->progress_percentage,
            'budget_decaisse' => (float) $result->fresh()->budget_spent,
            'en_retard' => $result->fresh()->is_overdue,
        ],
        'lien' => url('/projects/' . $result->id),
        'alertes' => $alerteError ? ('⚠️ '.$alerteError) : '✅ générées',
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } catch (\Throwable $e) {
        return response()->json([
            'resultat' => '❌ ÉCHEC de la génération.',
            'classe' => get_class($e),
            'message' => $e->getMessage(),
            'fichier' => $e->getFile() . ':' . $e->getLine(),
            'trace' => collect($e->getTrace())->take(6)->map(fn ($t) => ($t['file'] ?? '?') . ':' . ($t['line'] ?? '?'))->all(),
        ], 500, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/greeting', [WelcomeController::class, 'show'])->name('greeting');
    Route::post('/greeting/acknowledge', [WelcomeController::class, 'acknowledge'])->name('greeting.acknowledge');

    Route::get('/carte', [CarteController::class, 'index'])->name('carte');

    Route::get('/alertes', [AlerteController::class, 'index'])->name('alertes.index');
    Route::delete('/alertes/{alert}', [AlerteController::class, 'destroy'])->middleware('permission:manage_alerts')->name('alertes.destroy');
    Route::post('/alertes/generer', [AlerteController::class, 'generate'])->middleware('permission:manage_alerts')->name('alertes.generate');
    Route::post('/alertes/{alert}/commentaires', [AlerteController::class, 'addComment'])->name('alertes.comments.store');
    Route::delete('/alertes/{alert}/commentaires/{comment}', [AlerteController::class, 'deleteComment'])->name('alertes.comments.destroy');

    Route::get('/avancements', [AvancementsController::class, 'index'])->name('avancements.index');

    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('permission:create_project')->name('projects.store');
    Route::patch('/projects/{project}/status', [ProjectController::class, 'updateStatus'])->middleware('permission:edit_project')->name('projects.status.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('permission:delete_project')->name('projects.destroy');
    Route::patch('/projects/{project}/team', [ProjectController::class, 'updateTeam'])->middleware('permission:edit_project')->name('projects.team.update');
    Route::patch('/projects/{project}/team-members', [ProjectController::class, 'updateTeamMembers'])->middleware('permission:edit_project')->name('projects.team-members.update');
    Route::post('/projects/{project}/indicators', [ProjectController::class, 'addIndicator'])->middleware('permission:edit_project')->name('projects.indicators.store');
    Route::delete('/projects/{project}/indicators/{indicator}', [ProjectController::class, 'removeIndicator'])->middleware('permission:edit_project')->name('projects.indicators.destroy');
    Route::get('/projects/{project}/export-excel', [ProjectController::class, 'exportExcel'])->middleware('permission:export_reports')->name('projects.export');

    // Saisie avancement physique
    Route::post('/projects/{project}/physical', [PhysicalProgressController::class, 'store'])->middleware('permission:manage_physical')->name('projects.physical.store');
    Route::put('/projects/{project}/physical/{progress}', [PhysicalProgressController::class, 'update'])->middleware('permission:manage_physical')->name('projects.physical.update');
    Route::delete('/projects/{project}/physical/{progress}', [PhysicalProgressController::class, 'destroy'])->middleware('permission:manage_physical')->name('projects.physical.destroy');

    // Saisie avancement financier
    Route::post('/projects/{project}/financial', [FinancialProgressController::class, 'store'])->middleware('permission:manage_finances')->name('projects.financial.store');
    Route::put('/projects/{project}/financial/{progress}', [FinancialProgressController::class, 'update'])->middleware('permission:manage_finances')->name('projects.financial.update');
    Route::delete('/projects/{project}/financial/{progress}', [FinancialProgressController::class, 'destroy'])->middleware('permission:manage_finances')->name('projects.financial.destroy');

    // Ouvrages
    Route::post('/projects/{project}/building-works', [BuildingWorkController::class, 'store'])->middleware('permission:manage_physical')->name('projects.building-works.store');
    Route::put('/projects/{project}/building-works/{work}', [BuildingWorkController::class, 'update'])->middleware('permission:manage_physical')->name('projects.building-works.update');
    Route::delete('/projects/{project}/building-works/{work}', [BuildingWorkController::class, 'destroy'])->middleware('permission:manage_physical')->name('projects.building-works.destroy');

    // Lots
    Route::post('/projects/{project}/lots', [ProjectLotController::class, 'store'])->middleware('permission:manage_physical')->name('projects.lots.store');
    Route::put('/projects/{project}/lots/{lot}', [ProjectLotController::class, 'update'])->middleware('permission:manage_physical')->name('projects.lots.update');
    Route::delete('/projects/{project}/lots/{lot}', [ProjectLotController::class, 'destroy'])->middleware('permission:manage_physical')->name('projects.lots.destroy');
    Route::patch('/projects/{project}/lots/{lot}/progress', [ProjectLotController::class, 'updateProgress'])->middleware('permission:manage_physical')->name('projects.lots.progress');

    // Jalons
    Route::post('/projects/{project}/milestones', [ProjectMilestoneController::class, 'store'])->middleware('permission:manage_physical')->name('projects.milestones.store');
    Route::put('/projects/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'update'])->middleware('permission:manage_physical')->name('projects.milestones.update');
    Route::delete('/projects/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'destroy'])->middleware('permission:manage_physical')->name('projects.milestones.destroy');
    Route::patch('/projects/{project}/milestones/{milestone}/reach', [ProjectMilestoneController::class, 'markReached'])->middleware('permission:manage_physical')->name('projects.milestones.reach');

    // Décomptes / paiements (suivi financier maître d'ouvrage)
    Route::patch('/projects/{project}/advances', [\App\Http\Controllers\ProjectPaymentController::class, 'updateAdvances'])->middleware('permission:manage_finances')->name('projects.advances.update');
    Route::post('/projects/{project}/payments', [\App\Http\Controllers\ProjectPaymentController::class, 'store'])->middleware('permission:manage_finances')->name('projects.payments.store');
    Route::put('/projects/{project}/payments/{payment}', [\App\Http\Controllers\ProjectPaymentController::class, 'update'])->middleware('permission:manage_finances')->name('projects.payments.update');
    Route::delete('/projects/{project}/payments/{payment}', [\App\Http\Controllers\ProjectPaymentController::class, 'destroy'])->middleware('permission:manage_finances')->name('projects.payments.destroy');

    // Documents
    Route::post('/projects/{project}/documents', [DocumentController::class, 'store'])->name('projects.documents.store');
    Route::get('/projects/{project}/documents/{document}/download', [DocumentController::class, 'download'])->name('projects.documents.download');
    Route::delete('/projects/{project}/documents/{document}', [DocumentController::class, 'destroy'])->name('projects.documents.destroy');

    // Rapports
    Route::get('/rapports', [RapportController::class, 'index'])->name('rapports.index');
    Route::get('/rapports/projet/{project}', [RapportController::class, 'projet'])->name('rapports.projet');
    Route::get('/rapports/global', [RapportController::class, 'globalReport'])->name('rapports.global');

    // Exports Excel
    Route::get('/rapports/excel/projet/{project}', [RapportController::class, 'excelProjet'])->middleware('permission:export_reports')->name('rapports.excel.projet');
    Route::get('/rapports/excel/global', [RapportController::class, 'excelGlobal'])->middleware('permission:export_reports')->name('rapports.excel.global');

    // Administration (Gestion des utilisateurs)
    Route::middleware('permission:manage_users')->group(function () {
        Route::get('/admin/users', [App\Http\Controllers\Admin\UserController::class, 'index'])->name('admin.users.index');
        Route::post('/admin/users', [App\Http\Controllers\Admin\UserController::class, 'store'])->name('admin.users.store');
        Route::put('/admin/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'update'])->name('admin.users.update');
        Route::patch('/admin/users/{user}/toggle-active', [App\Http\Controllers\Admin\UserController::class, 'toggleActive'])->name('admin.users.toggle-active');
        Route::delete('/admin/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('admin.users.destroy');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
