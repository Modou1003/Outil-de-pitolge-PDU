<?php

namespace Tests\Feature;

use App\Models\PduProject;
use App\Models\University;
use App\Models\User;
use App\Services\Import\BaseDeCalculImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mesure du temps de traitement serveur — critère de performance du protocole.
 *
 * Les durées relevées ici sont celles du traitement seul, réseau et rendu du
 * navigateur exclus. Elles ne remplacent pas le chronométrage en conditions
 * réelles prévu au protocole ; elles en fixent la part incompressible, et
 * permettent de vérifier que le seuil d'acceptation retenu laisse au réseau
 * une marge réaliste.
 */
class PerformanceMesureTest extends TestCase
{
    use RefreshDatabase;

    public function test_mesure_des_temps_de_traitement(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $universite = University::create([
            'name' => 'Université d’Odienné', 'acronym' => 'UODN', 'region' => 'Kabadougou',
        ]);

        $projet = PduProject::create([
            'code' => 'PRJ-ODN',
            'title' => 'Travaux de construction — tranche 1',
            'university_id' => $universite->id,
            'created_by' => $admin->id,
            'start_date' => '2024-11-04',
            'end_date' => '2026-11-03',
            'planned_completion_date' => '2026-11-03',
            'status' => 'in_progress',
            'type' => 'construction',
            'budget_allocated' => 68_879_636_067,
            'startup_advance_amount' => 13_775_927_213,
            'supply_advance_amount' => 1_607_160_969,
        ]);

        $donnees = json_decode(file_get_contents(database_path('data/odienne.json')), true, 512, JSON_THROW_ON_ERROR);
        app(BaseDeCalculImporter::class)->import($projet, $donnees, $admin, true);

        $mesures = [
            'Tableau de bord consolidé' => fn () => $this->actingAs($admin)->get(route('dashboard')),
            'Fiche du projet d’Odienné' => fn () => $this->actingAs($admin)->get(route('projects.show', $projet)),
            'Rapport de projet exporté (PDF)' => fn () => $this->actingAs($admin)->get(route('rapports.projet', $projet)),
            'Rapport global exporté (PDF)' => fn () => $this->actingAs($admin)->get(route('rapports.global')),
        ];

        $resultats = [];
        foreach ($mesures as $libelle => $appel) {
            // Un premier appel réchauffe le cache de configuration et de vues.
            $appel();

            $durees = [];
            for ($i = 0; $i < 5; $i++) {
                $debut = microtime(true);
                $reponse = $appel();
                $durees[] = (microtime(true) - $debut) * 1000;
            }
            sort($durees);
            $resultats[$libelle] = [
                'statut' => $reponse->getStatusCode(),
                'median_ms' => round($durees[2], 1),
                'min_ms' => round($durees[0], 1),
                'max_ms' => round($durees[4], 1),
            ];
        }

        fwrite(STDERR, "\n" . json_encode($resultats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        foreach ($resultats as $libelle => $r) {
            $this->assertSame(200, $r['statut'], "La restitution « {$libelle} » n'a pas abouti.");
        }

        // Seuils du protocole, part serveur : l'affichage d'un écran doit laisser
        // au réseau l'essentiel du budget de trois secondes, l'export tenir très
        // en deçà des dix secondes au-delà desquelles l'attention se relâche.
        $this->assertLessThan(2000, $resultats['Tableau de bord consolidé']['median_ms']);
        $this->assertLessThan(2000, $resultats['Fiche du projet d’Odienné']['median_ms']);
        $this->assertLessThan(8000, $resultats['Rapport de projet exporté (PDF)']['median_ms']);
        $this->assertLessThan(8000, $resultats['Rapport global exporté (PDF)']['median_ms']);
    }
}
