<?php

namespace App\Http\Controllers;

use App\Models\PduProject;
use App\Models\PhysicalProgress;
use App\Models\FinancialProgress;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AvancementsController extends Controller
{
    public function index()
    {
        // Le portefeuille de projets est consultable par tous les rôles (cf. matrice
        // des permissions). La saisie d'avancement reste protégée au niveau des routes
        // (permissions manage_physical / manage_finances).
        $projects = PduProject::query()->with(['lots', 'university'])->get();

        // Get all physical and financial progresses
        $physicalProgresses = PhysicalProgress::whereIn('project_id', $projects->pluck('id'))->get();
        $financialProgresses = FinancialProgress::whereIn('project_id', $projects->pluck('id'))->get();

        return Inertia::render('Avancements/Index', [
            'projects' => $projects,
            'physicalProgresses' => $physicalProgresses,
            'financialProgresses' => $financialProgresses,
        ]);
    }
}
