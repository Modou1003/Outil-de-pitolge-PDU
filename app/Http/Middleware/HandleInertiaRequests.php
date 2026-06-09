<?php

namespace App\Http\Middleware;

use App\Models\PduProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $roles = $user ? $user->getRoleNames()->values()->all() : [];
        $permissions = $user ? $user->getAllPermissions()->pluck('name')->values()->all() : [];

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'roles' => $roles,
                'permissions' => $permissions,
            ],
            'counters' => fn () => $user ? [
                'active_projects' => PduProject::whereIn('status', ['approved', 'in_progress'])->count(),
                'active_alerts' => $this->activeAlertsCount(),
            ] : ['active_projects' => 0, 'active_alerts' => 0],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }

    private function activeAlertsCount(): int
    {
        if (! Schema::hasTable('alerts')) {
            return 0;
        }

        return (int) DB::table('alerts')->where('is_resolved', false)->count();
    }
}
