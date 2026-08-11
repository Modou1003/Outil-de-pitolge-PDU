<?php

namespace Tests\Feature;

use App\Models\PduProject;
use App\Models\ProjectAmendment;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Séparation des compétences sur le marché : les avenants relèvent de la
 * section des marchés (manage_market), les décomptes de la section
 * administrative et financière (manage_finances). Cette dernière conserve
 * toutefois l'accès aux avenants.
 */
class MarketPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function project(): PduProject
    {
        $university = University::create(['name' => 'Université de Test', 'acronym' => 'UT', 'region' => 'Abidjan']);

        return PduProject::create([
            'code' => 'PRJ-MARCHE',
            'title' => 'Projet marché',
            'university_id' => $university->id,
            'created_by' => User::factory()->create()->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'in_progress',
            'type' => 'construction',
            'budget_allocated' => 100000000,
        ]);
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::firstOrCreate(['name' => 'role_test_'.uniqid(), 'guard_name' => 'web']);
        $role->syncPermissions(Permission::whereIn('name', $permissions)->get());
        $user->assignRole($role);

        return $user;
    }

    private function payload(): array
    {
        return ['number' => 'AV-01', 'object' => 'Travaux supplémentaires', 'amount' => 5000000];
    }

    public function test_la_permission_manage_market_existe(): void
    {
        $this->assertTrue(
            Permission::where('name', 'manage_market')->exists(),
            'La permission manage_market doit être créée par la matrice des rôles.',
        );
    }

    public function test_la_section_des_marches_peut_saisir_un_avenant(): void
    {
        $user = $this->userWithPermissions(['manage_market', 'view_project']);

        $this->actingAs($user)
            ->post(route('projects.amendments.store', $this->project()), $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ProjectAmendment::count());
    }

    public function test_la_section_financiere_conserve_lacces_aux_avenants(): void
    {
        $user = $this->userWithPermissions(['manage_finances', 'view_project']);

        $this->actingAs($user)
            ->post(route('projects.amendments.store', $this->project()), $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ProjectAmendment::count());
    }

    public function test_sans_lune_ni_lautre_la_saisie_est_refusee(): void
    {
        $user = $this->userWithPermissions(['view_project', 'manage_physical']);

        $this->actingAs($user)
            ->post(route('projects.amendments.store', $this->project()), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, ProjectAmendment::count());
    }

    public function test_la_section_des_marches_na_pas_acces_aux_decomptes(): void
    {
        $user = $this->userWithPermissions(['manage_market', 'view_project']);

        $this->actingAs($user)
            ->post(route('projects.payments.store', $this->project()), [
                'number' => 'D-01',
                'gross_amount' => 1000000,
                'net_paid' => 900000,
            ])
            ->assertForbidden();
    }

    public function test_le_role_charge_des_marches_instruit_les_avenants_sans_toucher_aux_decomptes(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('charge_marches');
        $project = $this->project();

        // Compétent pour l'acte contractuel.
        $this->actingAs($user)
            ->post(route('projects.amendments.store', $project), $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(1, ProjectAmendment::count());

        // Mais pas pour l'exécution de la dépense.
        $this->actingAs($user)
            ->post(route('projects.payments.store', $project), [
                'number' => 'D-01',
                'gross_amount' => 1000000,
                'net_paid' => 900000,
            ])
            ->assertForbidden();

        // Ni pour l'avancement physique.
        $permissions = $user->getAllPermissions()->pluck('name');
        $this->assertTrue($permissions->contains('manage_market'));
        $this->assertFalse($permissions->contains('manage_finances'));
        $this->assertFalse($permissions->contains('manage_physical'));
    }

    public function test_la_matrice_des_roles_respecte_la_separation(): void
    {
        $roles = Role::with('permissions')->get()->keyBy('name');

        // La section financière ne détient pas la compétence contractuelle,
        // mais garde l'accès aux avenants via manage_finances.
        $agent = $roles['agent_financier']->permissions->pluck('name');
        $this->assertFalse($agent->contains('manage_market'));
        $this->assertTrue($agent->contains('manage_finances'));

        // Le chef de projet ne touche ni au marché ni aux finances.
        $chef = $roles['chef_projet']->permissions->pluck('name');
        $this->assertFalse($chef->contains('manage_market'));
        $this->assertFalse($chef->contains('manage_finances'));

        // Le directeur (Resp. UGP) supervise les deux sections.
        $directeur = $roles['directeur']->permissions->pluck('name');
        $this->assertTrue($directeur->contains('manage_market'));
        $this->assertTrue($directeur->contains('manage_finances'));
    }
}
