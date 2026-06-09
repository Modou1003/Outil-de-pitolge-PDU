<?php

namespace App\Http\Controllers;

use App\Http\Requests\UniversityRequest;
use App\Models\University;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UniversityController extends Controller
{
    public function index(): Response
    {
        $universities = University::orderBy('name')->paginate(10);

        return Inertia::render('Universities/Index', [
            'universities' => $universities,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Universities/Create');
    }

    public function store(UniversityRequest $request): RedirectResponse
    {
        University::create($request->validated());

        return redirect()->route('universities.index')->with('success', 'Université créée avec succès.');
    }

    public function edit(University $university): Response
    {
        return Inertia::render('Universities/Edit', [
            'university' => $university,
        ]);
    }

    public function update(UniversityRequest $request, University $university): RedirectResponse
    {
        $university->update($request->validated());

        return redirect()->route('universities.index')->with('success', 'Université mise à jour avec succès.');
    }

    public function destroy(University $university): RedirectResponse
    {
        $university->delete();

        return redirect()->route('universities.index')->with('success', 'Université supprimée avec succès.');
    }
}
