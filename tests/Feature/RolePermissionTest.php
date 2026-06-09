<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class]);
    }

    public function test_admin_has_all_permissions(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->hasPermissionTo('view_dashboard'));
        $this->assertTrue($admin->hasPermissionTo('manage_projects'));
        $this->assertTrue($admin->hasPermissionTo('manage_users'));
    }

    public function test_directeur_has_correct_permissions(): void
    {
        $directeur = User::factory()->create()->assignRole('directeur');

        $this->assertTrue($directeur->hasRole('directeur'));
        $this->assertTrue($directeur->hasPermissionTo('view_dashboard'));
        $this->assertTrue($directeur->hasPermissionTo('manage_projects'));
        $this->assertFalse($directeur->hasPermissionTo('manage_users'));
    }

    public function test_visiteur_has_only_view_permissions(): void
    {
        $visiteur = User::factory()->create()->assignRole('visiteur');

        $this->assertTrue($visiteur->hasRole('visiteur'));
        $this->assertTrue($visiteur->hasPermissionTo('view_dashboard'));
        $this->assertFalse($visiteur->hasPermissionTo('manage_projects'));
        $this->assertFalse($visiteur->hasPermissionTo('manage_users'));
    }

    public function test_user_can_have_multiple_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole(['directeur', 'agent_financier']);

        $this->assertTrue($user->hasRole('directeur'));
        $this->assertTrue($user->hasRole('agent_financier'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_all_pdu_roles_exist(): void
    {
        $roles = ['admin', 'directeur', 'chef_projet', 'agent_financier', 'visiteur'];

        foreach ($roles as $role) {
            $user = User::factory()->create()->assignRole($role);
            $this->assertTrue($user->hasRole($role));
        }
    }
}
