<?php

namespace Database\Seeders;

use App\Models\PduProject;
use App\Models\University;
use App\Models\User;
use App\Services\Import\BaseDeCalculImporter;
use Illuminate\Database\Seeder;

/**
 * Chantier de l'Université d'Odienné — données réelles.
 *
 * Rien n'est ici inventé : les ouvrages, leurs pondérations, leurs calendriers
 * contractuels, les vingt relevés mensuels et les quatorze décomptes
 * proviennent du rapport mensuel d'activité n° 14 du groupement TAEP/IETF,
 * arrêté au 30 juin 2026, et de sa base de calcul d'avancement physique.
 *
 * L'extraction du classeur vers database/data/odienne.json est faite par
 * scripts/extraire_odienne.py ; ce seeder ne fait que charger ce fichier.
 *
 *   php artisan db:seed --class=OdienneProjectSeeder
 *
 * Seul le projet portant le code PRJ-ODN est touché : il est recréé à chaque
 * exécution, le reste du portefeuille reste intact.
 */
class OdienneProjectSeeder extends Seeder
{
    private const CODE = 'PRJ-ODN';

    /** Montant du marché de travaux, hors taxes et hors douane. */
    private const MARCHE = 68_879_636_067;

    /** Avance de démarrage effectivement versée (décompte n° 0). */
    private const AVANCE_DEMARRAGE = 13_775_927_213;

    /** Acompte sur approvisionnement, versé au titre de juin 2026. */
    private const AVANCE_APPRO = 1_607_160_969;

    public function run(): void
    {
        $donnees = $this->donnees();
        if ($donnees === null) {
            return;
        }

        $auteur = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first()
            ?? User::query()->first();

        if (! $auteur) {
            $this->command?->error('Aucun utilisateur en base : lancez d’abord le seeder des rôles.');

            return;
        }

        PduProject::withTrashed()->where('code', self::CODE)->forceDelete();

        // L'écriture passe par l'importateur du classeur : le chargement initial
        // et l'import mensuel suivent ainsi exactement les mêmes règles.
        $projet = $this->projet($auteur);
        $compte = app(BaseDeCalculImporter::class)->import($projet, $donnees, $auteur, true);

        $this->rapport($projet->refresh(), $donnees, $compte);
    }

    /** @return array<string, mixed>|null */
    private function donnees(): ?array
    {
        $chemin = database_path('data/odienne.json');

        if (! is_file($chemin)) {
            $this->command?->error("Fichier introuvable : {$chemin}");
            $this->command?->line('Produisez-le avec : python scripts/extraire_odienne.py <classeur.xlsx> database/data/odienne.json');

            return null;
        }

        return json_decode(file_get_contents($chemin), true, 512, JSON_THROW_ON_ERROR);
    }

    private function projet(User $auteur): PduProject
    {
        $universite = University::firstOrCreate(
            ['acronym' => 'UODN'],
            [
                'name' => 'Université d’Odienné',
                'location' => 'Odienné',
                'region' => 'Kabadougou',
                'status' => 'active',
                'latitude' => 9.5052,
                'longitude' => -7.5640,
            ],
        );

        return PduProject::create([
            'code' => self::CODE,
            'title' => 'Travaux de construction de l’Université d’Odienné — tranche 1',
            'description' => "Marché n° 2024-0-00-00-2-0503/05-333. Construction de la première tranche du campus : bâtiments d'enseignement et de recherche, hébergement, logements de fonction, ouvrages de voirie et réseaux divers, sur une emprise de 400 hectares. Financement de la Banque islamique de développement avec contrepartie de l'État. Entreprise titulaire : PFO CONSTRUCTION. Assistance à maîtrise d'ouvrage : groupement TAEP/IETF.",
            'university_id' => $universite->id,
            'created_by' => $auteur->id,
            'start_date' => '2024-11-04',
            'end_date' => '2026-11-03',
            'planned_completion_date' => '2026-11-03',
            'status' => 'in_progress',
            'type' => 'construction',
            'budget_allocated' => self::MARCHE,
            'startup_advance_amount' => self::AVANCE_DEMARRAGE,
            'supply_advance_amount' => self::AVANCE_APPRO,
            'currency' => 'XOF',
            'objectives' => [
                'Ouvrir quatre composantes universitaires pour trois mille étudiants',
                'Doter la région du Kabadougou d’un campus complet',
                'Achever la première tranche dans le délai contractuel de vingt-quatre mois',
            ],
        ]);
    }

    /** Les intitulés du classeur sont en capitales et parfois accolés. */
    private function nomPropre(string $nom): string
    {
        $nom = preg_replace('/\bENVIRONNEMENTAMENAGEMENT\b/u', 'ENVIRONNEMENT — AMENAGEMENT', $nom);

        return mb_convert_case(trim($nom), MB_CASE_TITLE, 'UTF-8');
    }

    private function rapport(PduProject $projet, array $donnees, array $compte): void
    {
        $retards = $projet->buildingWorks
            ->filter(fn ($o) => $o->start_delay_days > 15)
            ->sortByDesc('start_delay_days');

        $this->command?->info('Chantier d’Odienné chargé : ' . $projet->code . ' (arrêté au ' . $donnees['arrete_au'] . ')');
        $this->command?->table(['Indicateur', 'Valeur'], [
            ['Avancement physique consolidé', number_format((float) $projet->progress_percentage, 2) . ' %'],
            ['Attendu au rapport n° 14', '32,13 %'],
            ['Ouvrages créés par l’import', $compte['ouvrages_crees']],
            ['Marché', number_format(self::MARCHE, 0, ',', ' ') . ' XOF'],
            ['Décaissé', number_format((float) $projet->budget_spent, 0, ',', ' ') . ' XOF'],
            ['Ouvrages', $projet->buildingWorks()->count()],
            ['Somme des pondérations', number_format((float) $projet->buildingWorks()->sum('weight_percentage'), 2) . ' %'],
            ['Relevés physiques', $projet->physicalProgresses()->count()],
            ['Relevés financiers', $projet->financialProgresses()->count()],
            ['Décomptes', $projet->payments()->count()],
            ['Ouvrages en retard de démarrage', $retards->count()],
            ['Alertes ouvertes', $projet->alerts()->where('is_resolved', false)->count()],
        ]);

        if ($retards->isNotEmpty()) {
            $this->command?->line('');
            $this->command?->info('Retards au démarrage relevés par l’application :');
            $this->command?->table(
                ['Ouvrage', 'Début prévu', 'Début réel', 'Retard (j)', 'Retard AMO'],
                $retards->take(12)->map(fn ($o) => [
                    mb_strimwidth($o->name, 0, 34, '…'),
                    $o->planned_start_date?->format('d/m/Y') ?? '—',
                    $o->actual_start_date?->format('d/m/Y') ?? 'non démarré',
                    $o->start_delay_days,
                    $this->retardSource($donnees, $o->name),
                ])->all(),
            );
        }
    }

    /** Retard annoncé par l'assistance à maîtrise d'ouvrage, pour comparaison. */
    private function retardSource(array $donnees, string $nom): string
    {
        foreach ($donnees['ouvrages'] as $d) {
            if ($this->nomPropre($d['nom']) === $nom) {
                return ($d['retard_mois_source'] ?? null) ? $d['retard_mois_source'] . ' mois' : '—';
            }
        }

        return '—';
    }
}
