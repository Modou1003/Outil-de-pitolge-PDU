<?php

namespace App\Console\Commands;

use App\Services\AlerteService;
use Illuminate\Console\Command;

class GenererAlertes extends Command
{
    protected $signature = 'alertes:generer';

    protected $description = 'Analyse les projets PDU et génère les alertes (retard, dépassement, indicateurs, inactivité).';

    public function handle(AlerteService $service): int
    {
        $this->info('Analyse des projets en cours…');

        $summary = $service->generateForAll();

        $this->table(
            ['Projets analysés', 'Alertes créées', 'Alertes fermées'],
            [[$summary['scanned'], $summary['created'], $summary['closed']]],
        );

        $this->info('Terminé.');

        return self::SUCCESS;
    }
}
