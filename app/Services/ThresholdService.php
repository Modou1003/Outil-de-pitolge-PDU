<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Seuils de déclenchement des alertes et bornes de coloration des indicateurs.
 *
 * Le catalogue ci-dessous fait autorité : il porte la valeur par défaut, les
 * bornes admissibles et le libellé de chaque seuil. Une valeur enregistrée en
 * base prend le pas sur la valeur par défaut ; en son absence, le comportement
 * historique est conservé à l'identique.
 *
 * Les seuils sont lus à chaque détection d'alerte et à chaque affichage de
 * fiche projet : la lecture est donc mise en cache et invalidée à l'écriture.
 */
class ThresholdService
{
    private const CACHE_KEY = 'settings.thresholds';

    /**
     * Catalogue des seuils modifiables.
     *
     * unit  : suffixe affiché dans le formulaire
     * step  : pas de saisie
     * group : regroupement à l'écran
     */
    public const CATALOG = [
        // ------------------------------------------------ règles d'alerte
        'budget_overrun_rate' => [
            'label' => 'Dépassement budgétaire imminent',
            'help' => "Part du marché actualisé décaissée au-delà de laquelle l'alerte s'ouvre.",
            'group' => 'Alertes — coût',
            'default' => 90, 'min' => 50, 'max' => 100, 'step' => 1, 'unit' => '%',
        ],
        'cpi_threshold' => [
            'label' => 'Dérive de coût (CPI)',
            'help' => "Indice de performance des coûts sous lequel la dérive est signalée.",
            'group' => 'Alertes — coût',
            'default' => 0.95, 'min' => 0.5, 'max' => 1, 'step' => 0.01, 'unit' => '',
        ],
        'amendments_ratio' => [
            'label' => 'Avenants cumulés excessifs',
            'help' => "Plus-value cumulée des avenants rapportée au montant initial du marché.",
            'group' => 'Alertes — coût',
            'default' => 15, 'min' => 1, 'max' => 100, 'step' => 1, 'unit' => '%',
        ],

        'progress_gap_points' => [
            'label' => "Écart d'avancement critique",
            'help' => "Retard de l'avancement réel sur l'avancement planifié, en points.",
            'group' => 'Alertes — délai',
            'default' => 15, 'min' => 1, 'max' => 60, 'step' => 1, 'unit' => 'points',
        ],
        'forecast_delay_days' => [
            'label' => 'Retard de livraison projeté',
            'help' => "Retard estimé par la méthode Earned Schedule ouvrant un avertissement.",
            'group' => 'Alertes — délai',
            'default' => 60, 'min' => 5, 'max' => 365, 'step' => 5, 'unit' => 'jours',
        ],
        'forecast_delay_critical' => [
            'label' => 'Retard projeté critique',
            'help' => "Retard estimé au-delà duquel l'alerte devient critique.",
            'group' => 'Alertes — délai',
            'default' => 120, 'min' => 10, 'max' => 730, 'step' => 5, 'unit' => 'jours',
        ],

        'phys_fin_gap_points' => [
            'label' => 'Décalage physico-financier',
            'help' => "Avance du décaissement sur la réalisation physique — effet de façade.",
            'group' => 'Alertes — cohérence',
            'default' => 20, 'min' => 5, 'max' => 60, 'step' => 1, 'unit' => 'points',
        ],
        'phys_fin_gap_critical' => [
            'label' => 'Décalage physico-financier critique',
            'help' => "Écart au-delà duquel le décalage devient critique.",
            'group' => 'Alertes — cohérence',
            'default' => 35, 'min' => 10, 'max' => 100, 'step' => 1, 'unit' => 'points',
        ],

        'stale_data_days' => [
            'label' => 'Donnée de terrain à rafraîchir',
            'help' => "Ancienneté de la dernière saisie d'avancement physique déclenchant un avertissement.",
            'group' => 'Alertes — fiabilité',
            'default' => 30, 'min' => 7, 'max' => 180, 'step' => 1, 'unit' => 'jours',
        ],
        'critical_data_days' => [
            'label' => 'Donnée de terrain périmée',
            'help' => "Ancienneté au-delà de laquelle la donnée est jugée périmée.",
            'group' => 'Alertes — fiabilité',
            'default' => 60, 'min' => 14, 'max' => 365, 'step' => 1, 'unit' => 'jours',
        ],
        'work_start_delay_days' => [
            'label' => 'Démarrage d’ouvrage en retard',
            'help' => "Tolérance au-delà de la date de démarrage prévue d'un ouvrage avant signalement.",
            'group' => 'Alertes — délai',
            'default' => 15, 'min' => 0, 'max' => 180, 'step' => 1, 'unit' => 'jours',
        ],
        'payment_pending_days' => [
            'label' => 'Décompte en attente',
            'help' => "Délai au-delà duquel un décompte non réglé est signalé.",
            'group' => 'Alertes — fiabilité',
            'default' => 30, 'min' => 7, 'max' => 180, 'step' => 1, 'unit' => 'jours',
        ],

        // ------------------------------- coloration des cartes d'indicateurs
        'forecast_watch_days' => [
            'label' => 'Fin projetée — seuil de vigilance',
            'help' => "Retard en deçà duquel la fiche projet affiche « dans les temps ».",
            'group' => 'Indicateurs — coloration',
            'default' => 15, 'min' => 0, 'max' => 120, 'step' => 1, 'unit' => 'jours',
        ],
        'freshness_fresh_days' => [
            'label' => 'Fraîcheur — donnée fraîche',
            'help' => "Ancienneté en deçà de laquelle la carte reste verte.",
            'group' => 'Indicateurs — coloration',
            'default' => 30, 'min' => 7, 'max' => 180, 'step' => 1, 'unit' => 'jours',
        ],
        'freshness_stale_days' => [
            'label' => 'Fraîcheur — donnée à rafraîchir',
            'help' => "Ancienneté au-delà de laquelle la carte passe au rouge.",
            'group' => 'Indicateurs — coloration',
            'default' => 60, 'min' => 14, 'max' => 365, 'step' => 1, 'unit' => 'jours',
        ],
        'physfin_aligned_points' => [
            'label' => 'Écart physique/budget — aligné',
            'help' => "Écart absolu en deçà duquel la carte est considérée alignée.",
            'group' => 'Indicateurs — coloration',
            'default' => 10, 'min' => 1, 'max' => 50, 'step' => 1, 'unit' => 'points',
        ],
    ];

    /** @return array<string, float> */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            // La table peut être absente — première installation, migration non
            // encore jouée, base restaurée. Les seuils ne sont pas une donnée
            // vitale : mieux vaut repartir des valeurs par défaut que d'empêcher
            // l'affichage d'une fiche projet ou l'édition d'un rapport.
            try {
                $enregistres = Setting::pluck('value', 'key');
            } catch (\Throwable) {
                $enregistres = collect();
            }

            $valeurs = [];
            foreach (self::CATALOG as $cle => $meta) {
                $valeurs[$cle] = isset($enregistres[$cle])
                    ? (float) $enregistres[$cle]
                    : (float) $meta['default'];
            }

            return $valeurs;
        });
    }

    public function get(string $cle): float
    {
        return $this->all()[$cle] ?? (float) (self::CATALOG[$cle]['default'] ?? 0);
    }

    /** Le même seuil exprimé en fraction, pour les valeurs saisies en pourcentage. */
    public function ratio(string $cle): float
    {
        return $this->get($cle) / 100;
    }

    public function days(string $cle): int
    {
        return (int) round($this->get($cle));
    }

    /**
     * Enregistre les seuils fournis, en ignorant les clés hors catalogue et
     * en bornant chaque valeur à son intervalle admissible.
     */
    public function save(array $valeurs, ?int $userId = null): void
    {
        foreach ($valeurs as $cle => $valeur) {
            if (! isset(self::CATALOG[$cle]) || $valeur === null || $valeur === '') {
                continue;
            }

            $meta = self::CATALOG[$cle];
            $borne = max((float) $meta['min'], min((float) $meta['max'], (float) $valeur));

            Setting::updateOrCreate(
                ['key' => $cle],
                ['value' => (string) $borne, 'updated_by' => $userId],
            );
        }

        $this->forget();
    }

    /** Rétablit les valeurs d'origine du catalogue. */
    public function reset(): void
    {
        Setting::whereIn('key', array_keys(self::CATALOG))->delete();
        $this->forget();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
