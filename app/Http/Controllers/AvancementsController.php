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
        // Get all projects accessible to the current user
        $query = PduProject::query();

        // If user is not admin, filter by their role or permissions
        if (!Auth::user()->hasRole('admin')) {
            $query->where('created_by', Auth::id());
        }

        $projects = $query->with(['lots', 'university'])->get();

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
