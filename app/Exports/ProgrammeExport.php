<?php

namespace App\Exports;

use App\Models\PduProject;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProgrammeExport implements WithMultipleSheets, WithCharts
{
    use Exportable;

    public function sheets(): array
    {
        $projects = PduProject::with([
            'university:id,name,acronym,region',
            'financialProgresses',
            'physicalProgresses',
            'milestones',
            'alerts' => fn ($q) => $q->where('is_resolved', false),
        ])->orderBy('code')->get();

        $sheets = [
            new ProgrammeSyntheseSheet($projects),
        ];

        foreach ($projects as $project) {
            $sheets[] = new ProgrammeProjetSheet($project);
        }

        return $sheets;
    }

    public function charts(): array
    {
        return [];
    }
}

class ProgrammeSyntheseSheet implements FromArray, WithTitle, WithHeadings, ShouldAutoSize, WithEvents
{
    public function __construct(protected $projects)
    {
    }

    public function title(): string
    {
        return 'Synthèse Programme';
    }

    public function headings(): array
    {
        return [
            'Code',
            'Titre du projet',
            'Université',
            'Région',
            'Type',
            'Statut',
            'Avancement physique (%)',
            'Avancement prévu (%)',
            'Écart (%)',
            'Budget alloué (FCFA)',
            'Budget dépensé (FCFA)',
            'Taux exécution budget (%)',
            'Reste à payer (FCFA)',
            'CPI',
            'SPI',
            'Jalons atteints',
            'Jalons total',
            'Alertes ouvertes',
            'Date début',
            'Date fin prévue',
        ];
    }

    public function array(): array
    {
        return $this->projects->map(function (PduProject $p) {
            $latestFinancial = $p->financialProgresses->last();
            $planned = $p->planned_progress;
            $actual = (float) $p->progress_percentage;
            $ecart = round($actual - $planned, 2);

            return [
                $p->code,
                $p->title,
                $p->university?->name,
                $p->university?->region,
                ucfirst(str_replace('_', ' ', $p->type)),
                ucfirst(str_replace('_', ' ', $p->status)),
                number_format($actual, 1, ',', ' '),
                number_format($planned, 1, ',', ' '),
                number_format($ecart, 1, ',', ' '),
                number_format((float) $p->budget_allocated, 2, ',', ' '),
                number_format((float) $p->budget_spent, 2, ',', ' '),
                number_format($p->budget_execution_rate, 1, ',', ' '),
                number_format((float) $p->remaining_budget, 2, ',', ' '),
                $latestFinancial?->cpi !== null ? number_format($latestFinancial->cpi, 3, ',', ' ') : '—',
                $latestFinancial?->spi !== null ? number_format($latestFinancial->spi, 3, ',', ' ') : '—',
                $p->milestones->where('status', 'reached')->count(),
                $p->milestones->count(),
                $p->alerts->count(),
                optional($p->start_date)->toDateString(),
                optional($p->end_date)->toDateString(),
            ];
        })->all();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'T';
                $rowCount = $this->projects->count();

                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);

                if ($rowCount > 0) {
                    $sheet->getStyle("A1:{$lastCol}" . ($rowCount + 1))->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
                    ]);
                }

                for ($row = 2; $row <= $rowCount + 1; $row++) {
                    $ecartRaw = $sheet->getCell("I{$row}")->getValue();
                    $ecartVal = (float) str_replace([',', ' '], ['.', ''], $ecartRaw);
                    if ($ecartVal < 0) {
                        $sheet->getStyle("I{$row}")->getFont()->getColor()->setARGB('FFFF0000');
                    } else {
                        $sheet->getStyle("I{$row}")->getFont()->getColor()->setARGB('FF00B050');
                    }

                    $status = strtolower(trim($sheet->getCell("F{$row}")->getValue()));
                    if (str_contains($status, 'termin')) {
                        $sheet->getStyle("F{$row}")->applyFromArray([
                            'font' => ['color' => ['rgb' => '00B050'], 'bold' => true],
                        ]);
                    } elseif (str_contains($status, 'suspendu') || str_contains($status, 'hold') || str_contains($status, 'pause')) {
                        $sheet->getStyle("F{$row}")->applyFromArray([
                            'font' => ['color' => ['rgb' => 'FF0000'], 'bold' => true],
                        ]);
                    }

                    foreach (['N', 'O'] as $col) {
                        $raw = $sheet->getCell("{$col}{$row}")->getValue();
                        if ($raw === '—') continue;
                        $val = (float) str_replace([',', ' '], ['.', ''], $raw);
                        if ($val < 0.8) {
                            $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setARGB('FFFF0000');
                        } elseif ($val >= 1) {
                            $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setARGB('FF00B050');
                        }
                    }

                    $alertVal = (int) $sheet->getCell("R{$row}")->getValue();
                    if ($alertVal > 0) {
                        $sheet->getStyle("R{$row}")->applyFromArray([
                            'font' => ['color' => ['rgb' => 'FF0000'], 'bold' => true],
                        ]);
                    }
                }

                $sheet->freezePane('A2');
                $sheet->getRowDimension(1)->setRowHeight(30);
            },
        ];
    }
}

/**
 * Feuille dédiée par projet : infos + Courbe en S (physique) + Courbe EVM (financier),
 * en courbes lissées, données agrégées par période.
 */
class ProgrammeProjetSheet implements FromArray, WithTitle, ShouldAutoSize, WithEvents, WithCharts
{
    protected array $phys;
    protected array $fin;

    public function __construct(protected PduProject $project)
    {
        $this->phys = $this->aggregatePhysical();
        $this->fin = $this->aggregateFinancial();
    }

    public function title(): string
    {
        // Noms de feuille Excel : 31 caractères max.
        return mb_substr($this->project->code . ' - ' . $this->project->title, 0, 31);
    }

    // Positions de lignes déterministes (bloc infos = 13 lignes + 1 vide).
    protected function phStart(): int { return 17; }
    protected function phEnd(): int { return 16 + count($this->phys); }
    protected function evTitle(): int { return 18 + count($this->phys); }
    protected function evHeader(): int { return 19 + count($this->phys); }
    protected function evStart(): int { return 20 + count($this->phys); }
    protected function evEnd(): int { return 19 + count($this->phys) + count($this->fin); }

    public function array(): array
    {
        $rows = [];

        // --- Bloc infos (13 lignes) ---
        $rows[] = ['INFORMATIONS DU PROJET'];
        $rows[] = ['Code', $this->project->code];
        $rows[] = ['Titre', $this->project->title];
        $rows[] = ['Université', $this->project->university?->name];
        $rows[] = ['Région', $this->project->university?->region];
        $rows[] = ['Type', ucfirst(str_replace('_', ' ', $this->project->type))];
        $rows[] = ['Statut', ucfirst(str_replace('_', ' ', $this->project->status))];
        $rows[] = ['Date début', optional($this->project->start_date)->toDateString()];
        $rows[] = ['Date fin prévue', optional($this->project->end_date)->toDateString()];
        $rows[] = ['Budget alloué (FCFA)', (float) $this->project->budget_allocated];
        $rows[] = ['Budget dépensé (FCFA)', (float) $this->project->budget_spent];
        $rows[] = ['Avancement physique (%)', (float) $this->project->progress_percentage];
        $rows[] = ['Avancement prévu (%)', (float) $this->project->planned_progress];
        $rows[] = []; // ligne vide

        // --- Courbe en S : avancement physique moyen par période ---
        $rows[] = ['COURBE EN S — AVANCEMENT PHYSIQUE (%)'];
        $rows[] = ['Période', 'Prévu (%)', 'Réel (%)', 'Écart (%)'];
        foreach ($this->phys as $p) {
            $rows[] = [$p['period'], $p['planned'], $p['actual'], round($p['actual'] - $p['planned'], 2)];
        }

        $rows[] = []; // ligne vide

        // --- Courbe EVM : moyenne des ouvrages par période ---
        $rows[] = ['COURBE EVM — AVANCEMENT FINANCIER (moyenne des ouvrages)'];
        $rows[] = ['Période', 'Valeur planifiée (PV)', 'Valeur acquise (EV)', 'Coût réel (AC)', 'SPI', 'CPI'];
        foreach ($this->fin as $f) {
            $rows[] = [$f['period'], $f['pv'], $f['ev'], $f['ac'], $f['spi'], $f['cpi']];
        }

        return $rows;
    }

    protected function aggregatePhysical(): array
    {
        $grouped = [];
        foreach ($this->project->physicalProgresses as $p) {
            $k = (string) $p->period;
            if ($k === '') continue;
            $grouped[$k] ??= ['planned' => 0.0, 'actual' => 0.0, 'count' => 0];
            $grouped[$k]['planned'] += (float) $p->planned_percentage;
            $grouped[$k]['actual'] += (float) $p->actual_percentage;
            $grouped[$k]['count']++;
        }
        ksort($grouped);
        $out = [];
        foreach ($grouped as $period => $g) {
            if ($g['count'] === 0) continue;
            $out[] = [
                'period' => $period,
                'planned' => round($g['planned'] / $g['count'], 2),
                'actual' => round($g['actual'] / $g['count'], 2),
            ];
        }
        return $out;
    }

    protected function aggregateFinancial(): array
    {
        $grouped = [];
        foreach ($this->project->financialProgresses as $f) {
            $k = (string) $f->period;
            if ($k === '') continue;
            $grouped[$k] ??= ['pv' => 0.0, 'ev' => 0.0, 'ac' => 0.0, 'count' => 0];
            $grouped[$k]['pv'] += (float) $f->planned_value;
            $grouped[$k]['ev'] += (float) $f->earned_value;
            $grouped[$k]['ac'] += (float) $f->actual_cost;
            $grouped[$k]['count']++;
        }
        ksort($grouped);
        $out = [];
        foreach ($grouped as $period => $g) {
            if ($g['count'] === 0) continue;
            $pv = $g['pv'] / $g['count'];
            $ev = $g['ev'] / $g['count'];
            $ac = $g['ac'] / $g['count'];
            $out[] = [
                'period' => $period,
                'pv' => round($pv, 2),
                'ev' => round($ev, 2),
                'ac' => round($ac, 2),
                'spi' => $pv > 0 ? round($ev / $pv, 3) : null,
                'cpi' => $ac > 0 ? round($ev / $ac, 3) : null,
            ];
        }
        return $out;
    }

    /** Ligne lissée, épaisse, colorée, sans marqueurs. */
    protected function line(string $range, int $count, string $hex): DataSeriesValues
    {
        $dsv = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $range, null, $count);
        $dsv->setFillColor($hex);
        $dsv->setLineWidth(28000);
        $dsv->setPointMarker('none');
        $dsv->setSmoothLine(true);
        return $dsv;
    }

    public function charts(): array
    {
        $t = str_replace("'", "''", $this->title()); // échappe les apostrophes dans les références
        $charts = [];
        $chartTop = $this->evEnd() + 3;
        $suffix = $this->project->id;

        // Courbe en S — avancement physique (Prévu orange / Réel bordeaux).
        $phRows = $this->phEnd() - $this->phStart() + 1;
        if ($phRows >= 2) {
            $s = $this->phStart(); $e = $this->phEnd();
            $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$t}'!\$A\${$s}:\$A\${$e}", null, $phRows)];
            $values = [
                $this->line("'{$t}'!\$B\${$s}:\$B\${$e}", $phRows, 'ED7D31'),
                $this->line("'{$t}'!\$C\${$s}:\$C\${$e}", $phRows, '9E2B25'),
            ];
            $labels = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$t}'!\$B\$16", null, 1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$t}'!\$C\$16", null, 1),
            ];
            $series = new DataSeries(DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD, range(0, 1), $labels, $categories, $values, null, true);
            $chart = new Chart('courbe_s_' . $suffix, new Title('Courbe en S — Avancement physique'), new Legend(Legend::POSITION_BOTTOM, null, false), new PlotArea(null, [$series]), true, DataSeries::EMPTY_AS_GAP, new Title('Période'), new Title('Avancement (%)'));
            $chart->setTopLeftPosition('A' . $chartTop);
            $chart->setBottomRightPosition('K' . ($chartTop + 20));
            $charts[] = $chart;
        }

        // Courbe EVM — avancement financier (PV bleu / EV vert / AC rouge).
        $evRows = $this->evEnd() - $this->evStart() + 1;
        if ($evRows >= 2) {
            $s = $this->evStart(); $e = $this->evEnd(); $hdr = $this->evHeader();
            $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$t}'!\$A\${$s}:\$A\${$e}", null, $evRows)];
            $values = [
                $this->line("'{$t}'!\$B\${$s}:\$B\${$e}", $evRows, '4E79A7'),
                $this->line("'{$t}'!\$C\${$s}:\$C\${$e}", $evRows, '59A14F'),
                $this->line("'{$t}'!\$D\${$s}:\$D\${$e}", $evRows, 'E15759'),
            ];
            $labels = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$t}'!\$B\${$hdr}", null, 1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$t}'!\$C\${$hdr}", null, 1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$t}'!\$D\${$hdr}", null, 1),
            ];
            $series = new DataSeries(DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD, range(0, 2), $labels, $categories, $values, null, true);
            $chart = new Chart('courbe_evm_' . $suffix, new Title('Courbe EVM — Avancement financier'), new Legend(Legend::POSITION_BOTTOM, null, false), new PlotArea(null, [$series]), true, DataSeries::EMPTY_AS_GAP, new Title('Période'), new Title('Montant (FCFA)'));
            $chart->setTopLeftPosition('A' . ($chartTop + 23));
            $chart->setBottomRightPosition('K' . ($chartTop + 43));
            $charts[] = $chart;
        }

        return $charts;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sectionStyle = ['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1F4E79']]];
                $headerStyle = [
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ];

                // Bloc infos
                $sheet->getStyle('A1')->applyFromArray($sectionStyle);
                $sheet->getStyle('A2:A13')->applyFromArray(['font' => ['bold' => true]]);

                // Section physique
                $sheet->getStyle('A15')->applyFromArray($sectionStyle);
                $sheet->getStyle('A16:D16')->applyFromArray($headerStyle);
                for ($row = $this->phStart(); $row <= $this->phEnd(); $row++) {
                    $val = $sheet->getCell("D{$row}")->getValue();
                    if ($val === null) continue;
                    $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB($val < 0 ? 'FFFF0000' : 'FF00B050');
                }

                // Section EVM
                $sheet->getStyle('A' . $this->evTitle())->applyFromArray($sectionStyle);
                $sheet->getStyle('A' . $this->evHeader() . ':F' . $this->evHeader())->applyFromArray($headerStyle);
                $sheet->getStyle('B' . $this->evStart() . ':D' . $this->evEnd())->getNumberFormat()->setFormatCode('#,##0');
                for ($row = $this->evStart(); $row <= $this->evEnd(); $row++) {
                    foreach (['E', 'F'] as $col) {
                        $val = $sheet->getCell("{$col}{$row}")->getValue();
                        if ($val === null) continue;
                        $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setARGB($val < 0.8 ? 'FFFF0000' : ($val >= 1 ? 'FF00B050' : 'FFB07D00'));
                    }
                }
            },
        ];
    }
}
