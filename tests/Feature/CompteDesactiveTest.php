<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un compte désactivé depuis l'administration ne doit plus pouvoir ouvrir de
 * session : sans ce contrôle, la désactivation n'était qu'un indicateur
 * d'affichage et l'accès restait ouvert.
 */
class CompteDesactiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_compte_actif_peut_se_connecter(): void
    {
        $user = User::factory()->create(['is_active' => true, 'password' => bcrypt('motdepasse')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'motdepasse'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        $user = User::factory()->create(['is_active' => false, 'password' => bcrypt('motdepasse')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'motdepasse'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_le_refus_ne_revele_pas_lexistence_du_compte(): void
    {
        $desactive = User::factory()->create(['is_active' => false, 'password' => bcrypt('motdepasse')]);

        // Le message servi est celui de l'échec d'authentification ordinaire,
        // identique à celui d'une adresse inconnue : rien n'indique que le
        // compte existe mais qu'il est fermé.
        $this->post('/login', ['email' => $desactive->email, 'password' => 'motdepasse'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }
}
