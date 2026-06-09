<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->last_welcomed_at && $user->last_welcomed_at->isToday()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Greeting', [
            'userName' => $user->name,
            'userRoles' => $user->getRoleNames(),
        ]);
    }

    public function acknowledge(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['last_welcomed_at' => now()])->save();

        return redirect()->route('dashboard');
    }
}
