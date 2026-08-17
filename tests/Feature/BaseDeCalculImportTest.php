<?php

namespace Tests\Feature;

use App\Models\PduProject;
use App\Models\PhysicalProgress;
use App\Models\ProjectPayment;
use App\Models\University;
use App\Models\User;
use App\Services\Import\BaseDeCalculImporter;
use App\Services\Import\BaseDeCalculReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Import de la base de calcul d'avancement de la mission de contrôle.
 *
 * Les essais portent sur le classeur réel du chantier d'Odienné lorsqu'il est
 * disponible sur la machine ; à défaut, ils sont ignorés plutôt que faussés
 * par un fichier fabriqué qui ne prouverait rien de la lecture.
 */
class BaseDeCalculImportTest extends TestCase
{
    use RefreshDatabase;

    private const CLASSEUR = '01 Rapports/01 Rapports/BASE DE CALCUL D\'AVANCEMENT PHYSIQUE DU PROJET JUIN 2026.xlsx';

    protected function setUp(): void
    {
        parent::setUp();

        // La lecture d'un classeur Excel réclame l'extension zip de PHP.
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('Extension PHP « zip » absente : la lecture de classeurs est indisponible.');
        }
    }

    private function classeur(): string
    {
        $chemin = dirname(base_path(), 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CLASSEUR);

        if (! is_file($chemin)) {
            $this->markTestSkipped('Base de calcul absente de cette machine.');
        }

        return $chemin;
    }

    private function projet(): PduProject
    {
        $university = University::create(['name' => 'Université d’Odienné', 'acronym' => 'UODN', 'region' => 'Kabadougou']);

        return PduProject::create([
            'code' => 'PRJ-IMP',
            'title' => 'Travaux de construction — tranche 1',
            'university_id' => $university->id,
            'created_by' => User::factory()->create()->id,
            'start_date' => '2024-11-04',
            'end_date' => '2026-11-03',
            'status' => 'in_progress',
            'type' => 'construction',
            'budget_allocated' => 68_879_636_067,
        ]);
    }

    private function utilisateur(string $role = 'chef_projet'): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    // ─────────────────────────────────────────────────────────────── lecture

    public function test_la_lecture_retrouve_les_ouvrages_ponderes_et_les_periodes(): void
    {
        $lecture = app(BaseDeCalculReader::class)->read($this->classeur());

        $this->assertCount(30, $lecture['ouvrages'], 'Trente postes sont suivis dans ce classeur.');
        $this->assertEqualsWithDelta(100, $lecture['ponderation_totale'], 0.5);
        $this->assertSame('2026-06', $lecture['arrete_au']);
        $this->assertCount(14, $lecture['decomptes']);
    }

    public function test_la_lecture_retrouve_le_calendrier_contractuel(): void
    {
        $lecture = app(BaseDeCalculReader::class)->read($this->classeur());

        $hotel = collect($lecture['ouvrages'])->first(fn ($o) => str_contains($o['nom'], 'Hotel'));

        $this->assertNotNull($hotel, 'L’hôtel des enseignants doit être reconnu.');
        $this->assertSame('2025-04-18', $hotel['debut_prevu']);
        $this->assertSame('2025-12-01', $hotel['debut_reel'], 'Le mois « Déc. 2025 » se lit au premier du mois.');
        $this->assertSame(324, $hotel['duree_jours']);
    }

    public function test_un_classeur_etranger_est_refuse_sans_rien_ecrire(): void
    {
        $this->expectException(\RuntimeException::class);

        // Un classeur sans la feuille « progress » n'est pas une base de calcul.
        $vide = tempnam(sys_get_temp_dir(), 'xls') . '.xlsx';
        $doc = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $doc->getActiveSheet()->setCellValue('A1', 'Rien à voir');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($doc))->save($vide);

        try {
            app(BaseDeCalculReader::class)->read($vide);
        } finally {
            @unlink($vide);
        }
    }

    // ───────────────────────────────────────────────────────────── écriture

    public function test_le_premier_import_charge_tout_le_projet(): void
    {
        $projet = $this->projet();
        $lecture = app(BaseDeCalculReader::class)->read($this->classeur());

        $compte = app(BaseDeCalculImporter::class)->import($projet, $lecture, $this->utilisateur(), true);

        $this->assertSame(30, $compte['ouvrages_crees']);
        $this->assertSame(14, $compte['decomptes']);
        $this->assertGreaterThan(250, $compte['releves']);
        $this->assertEqualsWithDelta(32.13, $compte['avancement'], 0.05,
            'L’avancement consolidé doit retrouver celui du rapport mensuel.');
    }

    public function test_un_second_import_du_meme_fichier_najoute_rien(): void
    {
        $projet = $this->projet();
        $lecture = app(BaseDeCalculReader::class)->read($this->classeur());
        $importeur = app(BaseDeCalculImporter::class);
        $utilisateur = $this->utilisateur();

        $importeur->import($projet, $lecture, $utilisateur, true);
        $releves = PhysicalProgress::count();
        $decomptes = ProjectPayment::count();

        $second = $importeur->import($projet->fresh(), $lecture, $utilisateur, true);

        $this->assertSame(0, $second['ouvrages_crees']);
        $this->assertSame(0, $second['releves']);
        $this->assertSame(0, $second['decomptes']);
        $this->assertSame($releves, PhysicalProgress::count());
        $this->assertSame($decomptes, ProjectPayment::count());
    }

    public function test_seuls_les_mois_absents_sont_ajoutes(): void
    {
        $projet = $this->projet();
        $lecture = app(BaseDeCalculReader::class)->read($this->classeur());
        $importeur = app(BaseDeCalculImporter::class);

        // On simule un projet arrêté au mois précédent en retirant juin.
        $tronque = $lecture;
        $tronque['periodes'] = array_values(array_filter($lecture['periodes'], fn ($p) => $p < '2026-06'));
        $tronque['arrete_au'] = '2026-05';

        $importeur->import($projet, $tronque, $this->utilisateur(), true);
        $avant = PhysicalProgress::count();

        $apercu = $importeur->preview($projet->fresh(), $lecture);
        $this->assertSame(['2026-06'], $apercu['periodes_nouvelles']);

        $compte = $importeur->import($projet->fresh(), $lecture, $this->utilisateur(), true);

        $this->assertSame(30, $compte['releves'], 'Un seul mois pour chacun des trente ouvrages.');
        $this->assertSame($avant + 30, PhysicalProgress::count());
        $this->assertSame(0, $compte['ouvrages_crees']);
    }

    public function test_un_releve_corrige_a_la_main_survit_a_un_nouvel_import(): void
    {
        $projet = $this->projet();
        $lecture = app(BaseDeCalculReader::class)->read($this->classeur());
        $importeur = app(BaseDeCalculImporter::class);

        $importeur->import($projet, $lecture, $this->utilisateur(), true);

        $releve = PhysicalProgress::where('period', '2026-06')->firstOrFail();
        $releve->update(['actual_percentage' => 42.5, 'observations' => 'Corrigé après contradictoire']);

        $importeur->import($projet->fresh(), $lecture, $this->utilisateur(), true);

        $this->assertEqualsWithDelta(42.5, (float) $releve->fresh()->actual_percentage, 0.01,
            'Un import ne doit pas écraser une correction manuelle.');
    }

    public function test_un_decompte_depose_puis_regle_est_suivi(): void
    {
        $projet = $this->projet();
        $lecture = app(BaseDeCalculReader::class)->read($this->classeur());
        $importeur = app(BaseDeCalculImporter::class);

        // Le décompte n° 12 est déposé, non réglé, dans le classeur de juin.
        $importeur->import($projet, $lecture, $this->utilisateur(), true);
        $douze = ProjectPayment::where('number', '012')->firstOrFail();
        $this->assertFalse((bool) $douze->is_paid);

        // Le classeur du mois suivant l'annonce réglé.
        $suivant = $lecture;
        foreach ($suivant['decomptes'] as &$d) {
            if ($d['numero'] === '12') {
                $d['regle'] = true;
            }
        }
        unset($d);

        $compte = $importeur->import($projet->fresh(), $suivant, $this->utilisateur(), true);

        $this->assertSame(1, $compte['reglements']);
        $this->assertTrue((bool) $douze->fresh()->is_paid);
    }

    public function test_la_section_des_marches_nimporte_pas_les_decomptes(): void
    {
        $projet = $this->projet();
        $lecture = app(BaseDeCalculReader::class)->read($this->classeur());

        // Sans la compétence financière, les décomptes sont laissés de côté.
        $compte = app(BaseDeCalculImporter::class)->import($projet, $lecture, $this->utilisateur(), false);

        $this->assertSame(0, $compte['decomptes']);
        $this->assertSame(0, ProjectPayment::count());
        $this->assertGreaterThan(250, $compte['releves']);
    }

    // ────────────────────────────────────────────────────────────── accès

    public function test_limport_est_refuse_sans_la_permission_de_saisie(): void
    {
        $user = $this->utilisateur('comite_pilotage');

        $this->actingAs($user)
            ->post(route('projects.import.preview', $this->projet()), [])
            ->assertForbidden();
    }
}
