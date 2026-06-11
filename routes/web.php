<?php

use App\Http\Controllers\AlerteController;
use App\Http\Controllers\AvancementsController;
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

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/greeting', [WelcomeController::class, 'show'])->name('greeting');
    Route::post('/greeting/acknowledge', [WelcomeController::class, 'acknowledge'])->name('greeting.acknowledge');

    Route::get('/carte', [CarteController::class, 'index'])->name('carte');

    Route::get('/alertes', [AlerteController::class, 'index'])->name('alertes.index');
    Route::post('/alertes/{alert}/resolve', [AlerteController::class, 'resolve'])->name('alertes.resolve');
    Route::delete('/alertes/{alert}', [AlerteController::class, 'destroy'])->name('alertes.destroy');
    Route::post('/alertes/generer', [AlerteController::class, 'generate'])->name('alertes.generate');
    Route::post('/alertes/{alert}/commentaires', [AlerteController::class, 'addComment'])->name('alertes.comments.store');
    Route::delete('/alertes/{alert}/commentaires/{comment}', [AlerteController::class, 'deleteComment'])->name('alertes.comments.destroy');

    Route::get('/avancements', [AvancementsController::class, 'index'])->name('avancements.index');

    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::patch('/projects/{project}/status', [ProjectController::class, 'updateStatus'])->name('projects.status.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::patch('/projects/{project}/team', [ProjectController::class, 'updateTeam'])->name('projects.team.update');
    Route::patch('/projects/{project}/team-members', [ProjectController::class, 'updateTeamMembers'])->name('projects.team-members.update');
    Route::post('/projects/{project}/indicators', [ProjectController::class, 'addIndicator'])->name('projects.indicators.store');
    Route::delete('/projects/{project}/indicators/{indicator}', [ProjectController::class, 'removeIndicator'])->name('projects.indicators.destroy');
    Route::get('/projects/{project}/export-excel', [ProjectController::class, 'exportExcel'])->name('projects.export');

    // Saisie avancement physique
    Route::post('/projects/{project}/physical', [PhysicalProgressController::class, 'store'])->name('projects.physical.store');
    Route::put('/projects/{project}/physical/{progress}', [PhysicalProgressController::class, 'update'])->name('projects.physical.update');
    Route::delete('/projects/{project}/physical/{progress}', [PhysicalProgressController::class, 'destroy'])->name('projects.physical.destroy');

    // Saisie avancement financier
    Route::post('/projects/{project}/financial', [FinancialProgressController::class, 'store'])->name('projects.financial.store');
    Route::put('/projects/{project}/financial/{progress}', [FinancialProgressController::class, 'update'])->name('projects.financial.update');
    Route::delete('/projects/{project}/financial/{progress}', [FinancialProgressController::class, 'destroy'])->name('projects.financial.destroy');

    // Lots
    Route::post('/projects/{project}/lots', [ProjectLotController::class, 'store'])->name('projects.lots.store');
    Route::put('/projects/{project}/lots/{lot}', [ProjectLotController::class, 'update'])->name('projects.lots.update');
    Route::delete('/projects/{project}/lots/{lot}', [ProjectLotController::class, 'destroy'])->name('projects.lots.destroy');
    Route::patch('/projects/{project}/lots/{lot}/progress', [ProjectLotController::class, 'updateProgress'])->name('projects.lots.progress');

    // Jalons
    Route::post('/projects/{project}/milestones', [ProjectMilestoneController::class, 'store'])->name('projects.milestones.store');
    Route::put('/projects/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'update'])->name('projects.milestones.update');
    Route::delete('/projects/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'destroy'])->name('projects.milestones.destroy');
    Route::patch('/projects/{project}/milestones/{milestone}/reach', [ProjectMilestoneController::class, 'markReached'])->name('projects.milestones.reach');

    // Documents
    Route::post('/projects/{project}/documents', [DocumentController::class, 'store'])->name('projects.documents.store');
    Route::get('/projects/{project}/documents/{document}/download', [DocumentController::class, 'download'])->name('projects.documents.download');
    Route::delete('/projects/{project}/documents/{document}', [DocumentController::class, 'destroy'])->name('projects.documents.destroy');

    // Rapports
    Route::get('/rapports', [RapportController::class, 'index'])->name('rapports.index');
    Route::get('/rapports/projet/{project}', [RapportController::class, 'projet'])->name('rapports.projet');
    Route::get('/rapports/global', [RapportController::class, 'globalReport'])->name('rapports.global');

    // Exports Excel
    Route::get('/rapports/excel/projet/{project}', [RapportController::class, 'excelProjet'])->name('rapports.excel.projet');
    Route::get('/rapports/excel/global', [RapportController::class, 'excelGlobal'])->name('rapports.excel.global');

    // Administration (Admin seulement)
    Route::middleware([App\Http\Middleware\CheckRole::class . ':admin'])->group(function () {
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
