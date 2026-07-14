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

    // Ouvrages (BuildingWorks)
    Route::post('/projects/{project}/building-works', [BuildingWorkController::class, 'store'])->middleware('permission:manage_physical')->name('projects.building-works.store');
    Route::get('/projects/{project}/building-works/{work}', [BuildingWorkController::class, 'show'])->name('projects.building-works.show');
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
