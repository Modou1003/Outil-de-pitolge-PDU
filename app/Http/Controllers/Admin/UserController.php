<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PduProject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const BASE_ROLES = [
        'admin',
        'directeur',
        'chef_projet',
        'agent_financier',
        'visiteur',
    ];

    private const CIVIL_ROLES = [
        'gc_maitre_ouvrage',
        'gc_maitre_ouvrage_delegue',
        'gc_amo',
        'gc_maitre_oeuvre',
        'gc_architecte',
        'gc_bureau_etudes',
        'gc_ingenieur_structure',
        'gc_ingenieur_geotechnique',
        'gc_ingenieur_hydraulique',
        'gc_ingenieur_vrd',
        'gc_economiste',
        'gc_opc',
        'gc_controle_technique',
        'gc_coordonnateur_sps_hse',
        'gc_qhse',
        'gc_laboratoire_essais',
        'gc_topographe_geometre',
        'gc_entreprise_generale',
        'gc_conducteur_travaux',
        'gc_chef_chantier',
        'gc_responsable_methodes',
        'gc_fournisseur',
        'gc_sous_traitant',
        'gc_inspection_suivi',
        'gc_commission_reception',
    ];

    private function ensureManagedRolesExist(): void
    {
        $roles = array_merge(self::BASE_ROLES, self::CIVIL_ROLES);

        foreach ($roles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }
    }

    public function index(): Response
    {
        $this->ensureManagedRolesExist();

        $users = User::with('roles')
            ->paginate(15)
            ->through(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
                    'last_login_at' => $user->last_login_at,
                    'is_active' => $user->is_active ?? true,
                    'created_at' => $user->created_at,
                ];
            });

        return Inertia::render('Admin/UserManagement', [
            'users' => $users,
            'projects' => PduProject::select('id', 'code', 'title')->get(),
            'roles' => Role::query()
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->pluck('name')
                ->values()
                ->all(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureManagedRolesExist();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where(fn ($q) => $q->where('guard_name', 'web')),
            ],
            'assigned_projects' => 'array',
            'assigned_projects.*' => 'exists:pdu_projects,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        $user->assignRole($validated['role']);

        if (!empty($validated['assigned_projects'])) {
            // Si nécessaire, assigner des projets (peut-être via une relation ou permission)
            // Pour l'instant, on peut stocker dans meta ou créer une table pivot si besoin
        }

        // Log activity
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'created',
            'model' => 'User',
            'details' => ['created_user_id' => $user->id, 'role' => $validated['role']],
        ]);

        return redirect()->back()->with('success', 'Utilisateur créé avec succès.');
    }

    public function update(Request $request, User $user)
    {
        $this->ensureManagedRolesExist();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where(fn ($q) => $q->where('guard_name', 'web')),
            ],
            'assigned_projects' => 'array',
            'assigned_projects.*' => 'exists:pdu_projects,id',
        ]);

        $oldRole = $user->roles->first()?->name;

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        $user->syncRoles([$validated['role']]);

        if (!empty($validated['assigned_projects'])) {
            // Mettre à jour les projets assignés si applicable
        }

        // Log activity
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'updated',
            'model' => 'User',
            'details' => [
                'updated_user_id' => $user->id,
                'old_role' => $oldRole,
                'new_role' => $validated['role'],
            ],
        ]);

        return redirect()->back()->with('success', 'Utilisateur mis à jour avec succès.');
    }

    public function toggleActive(User $user)
    {
        $user->update(['is_active' => !$user->is_active]);

        // Log activity
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $user->is_active ? 'activated' : 'deactivated',
            'model' => 'User',
            'details' => ['user_id' => $user->id],
        ]);

        return redirect()->back()->with('success', 'Statut utilisateur mis à jour.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->withErrors(['error' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }

        $user->delete();

        // Log activity
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'deleted',
            'model' => 'User',
            'details' => ['deleted_user_id' => $user->id],
        ]);

        return redirect()->back()->with('success', 'Utilisateur supprimé avec succès.');
    }
}