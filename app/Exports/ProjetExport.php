<?php

namespace App\Exports;

use App\Models\PduProject;
use App\Exports\Sheets\ProjetJalonsSheet;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
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
use Maatwebsite\Excel\Concerns\WithCharts;

class ProjetExport implements WithMultipleSheets, WithCharts
{
    use Exportable;

    public function __construct(protected PduProject $project)
    {
    }

    public function sheets(): array
    {
        return [
            new ProjetInfoSheet($this->project),
            new ProjetOuvragesSheet($this->project),
            new ProjetPhysicalSheet($this->project),
            new ProjetFinancialSheet($this->project),
            new ProjetPaymentsSheet($this->project),
            new ProjetJalonsSheet($this->project),
            new ProjetCourbesSheet($this->project),
        ];
    }

    public function charts(): array
    {
        return [];
    }
}

class ProjetInfoSheet implements FromArray, WithTitle, WithHeadings, ShouldAutoSize, WithEvents
{
    public function __construct(protected PduProject $project)
    {
    }

    public function title(): string
    {
        return 'Infos';
    }

    public function headings(): array
    {
        return ['Champ', 'Valeur'];
    }

    public function array(): array
    {
        $moa = $this->project->financialMoa();

        return [
            ['Code', $this->project->code],
            ['Titre', $this->project->title],
            ['Université', $this->project->university?->name],
            ['Site', $this->project->university?->location],
            ['Région', $this->project->university?->region],
            ['Type', ucfirst(str_replace('_', ' ', $this->project->type))],
            ['Statut', ucfirst(str_replace('_', ' ', $this->project->status))],
            ['Date de début prévue', optional($this->project->start_date)->toDateString()],
            ['Date de fin prévue', optional($this->project->end_date)->toDateString()],
            ['Date de fin réelle', optional($this->project->planned_completion_date)->toDateString()],
            ['Avancement physique', $this->project->progress_percentage . '%'],
            ['Budget alloué', $this->formatCurrency($this->project->budget_allocated)],
            ['Budget dépensé (coût réel)', $this->formatCurrency($this->project->budget_spent)],
            ['Taux d’exécution budget', $this->project->budget_execution_rate . '%'],
            ['— Situation financière (MOA) —', ''],
            ['Facturé (HT)', $this->formatCurrency($moa['invoiced'])],
            ['Reste à facturer', $this->formatCurrency($moa['remaining_to_invoice'])],
            ['Encaissé (travaux + avances)', $this->formatCurrency($moa['encashed'])],
            ['Net décaissé', $this->formatCurrency($moa['net_paid'])],
            ['Avances versées', $this->formatCurrency($moa['advance_granted'])],
            ['Avances remboursées', $this->formatCurrency($moa['advance_recovered'])],
            ['Reste à rembourser (exposition)', $this->formatCurrency($moa['advance_remaining'])],
            ['Objectifs', $this->formatArray($this->project->objectives)],
            ['Parties prenantes', $this->formatArray($this->project->stakeholders)],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:B1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
                ]);
                $sheet->getStyle('A:B')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
            },
        ];
    }

    private function formatCurrency(?float $value): string
    {
        return $value === null ? '' : number_format($value, 2, ',', ' ') . ' FCFA';
    }

    private function formatArray(?array $values): string
    {
        if (empty($values)) {
            return '';
        }

        return implode(' / ', array_map(fn ($value) => (string) $value, $values));
    }
}

class ProjetPhysicalSheet implements FromArray, WithTitle, WithHeadings, ShouldAutoSize, WithEvents
{
    public function __construct(protected PduProject $project)
    {
    }

    public function title(): string
    {
        return 'Avancement Physique';
    }

    public function headings(): array
    {
        return ['Ouvrage', 'Période', 'Date mesure', 'Avancement prévu', 'Avancement réel', 'Écart', 'Observations'];
    }

    public function array(): array
    {
        return $this->project->physicalProgresses->map(function ($progress) {
            return [
                $progress->work?->name ?? '—',
                $progress->period,
                optional($progress->measurement_date)->toDateString(),
                $this->formatPercent($progress->planned_percentage),
                $this->formatPercent($progress->actual_percentage),
                $this->formatPercent($progress->actual_percentage - $progress->planned_percentage),
                $progress->observations,
            ];
        })->all();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:G1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
                ]);
                $sheet->getStyle('A:G')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

                // Conditional formatting: Écart column (F)
                $rows = $this->project->physicalProgresses->count();
                for ($row = 2; $row <= $rows + 1; $row++) {
                    $raw = $sheet->getCell("F{$row}")->getValue();
                    $val = (float) str_replace([',', ' ', '%'], ['.', '', ''], $raw);
                    if ($val < 0) {
                        $sheet->getStyle("F{$row}")->getFont()->getColor()->setARGB('FFFF0000');
                    } else {
                        $sheet->getStyle("F{$row}")->getFont()->getColor()->setARGB('FF00B050');
                    }
                }
            },
        ];
    }

    private function formatPercent(?float $value): string
    {
        return $value === null ? '' : number_format($value, 2, ',', ' ') . ' %';
    }
}

class ProjetFinancialSheet implements FromArray, WithTitle, WithHeadings, ShouldAutoSize, WithEvents
{
    public function __construct(protected PduProject $project)
    {
    }

    public function title(): string
    {
        return 'Avancement Financier';
    }

    public function headings(): array
    {
        return ['Période', 'Date mesure', 'Budget prévu', 'Valeur acquise', 'Coût réel', 'Cumul prévu', 'Cumul acquis', 'Cumul coût réel', 'Observations'];
    }

    public function array(): array
    {
        return $this->project->financialProgresses->map(function ($progress) {
            return [
                $progress->period,
                optional($progress->measurement_date)->toDateString(),
                $this->formatCurrency($progress->planned_value),
                $this->formatCurrency($progress->earned_value),
                $this->formatCurrency($progress->actual_cost),
                $this->formatCurrency($progress->cumulative_planned_value),
                $this->formatCurrency($progress->cumulative_earned_value),
                $this->formatCurrency($progress->cumulative_actual_cost),
                $progress->observations,
            ];
        })->all();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:I1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
                ]);
                $sheet->getStyle('A:I')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

                // Conditional formatting: highlight earned vs planned
                $rows = $this->project->financialProgresses->count();
                for ($row = 2; $row <= $rows + 1; $row++) {
                    $earnedRaw = $sheet->getCell("D{$row}")->getValue();
                    $plannedRaw = $sheet->getCell("C{$row}")->getValue();
                    $earned = (float) str_replace([',', ' ', 'FCFA'], ['.', '', ''], $earnedRaw);
                    $planned = (float) str_replace([',', ' ', 'FCFA'], ['.', '', ''], $plannedRaw);

                    if ($planned > 0 && $earned < $planned * 0.8) {
                        $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB('FFFF0000');
                    } elseif ($planned > 0 && $earned >= $planned) {
                        $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB('FF00B050');
                    }
                }
            },
        ];
    }

    private function formatCurrency(?float $value): string
    {
        return $value === null ? '' : number_format($value, 2, ',', ' ') . ' FCFA';
    }
}

class ProjetOuvragesSheet implements FromArray, WithTitle, WithHeadings, ShouldAutoSize, WithEvents
{
    public function __construct(protected PduProject $project)
    {
    }

    public function title(): string
    {
        return 'Ouvrages';
    }

    public function headings(): array
    {
        return ['Code', 'Ouvrage', 'Pondération', 'Avancement', 'Statut'];
    }

    public function array(): array
    {
        return $this->project->buildingWorks->map(fn ($w) => [
            $w->code,
            $w->name,
            number_format((float) $w->weight_percentage, 2, ',', ' ') . ' %',
            number_format((float) $w->progress_percentage, 2, ',', ' ') . ' %',
            \App\Models\BuildingWork::STATUSES[$w->status] ?? $w->status,
        ])->all();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:E1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
                ]);
            },
        ];
    }
}

class ProjetPaymentsSheet implements FromArray, WithTitle, WithHeadings, ShouldAutoSize, WithEvents
{
    public function __construct(protected PduProject $project)
    {
    }

    public function title(): string
    {
        return 'Décomptes';
    }

    public function headings(): array
    {
        return ['N° décompte', 'Période', 'Date', 'Brut HT', 'Remb. avance démarrage', 'Remb. avance appro.', 'Net payé', 'Statut'];
    }

    public function array(): array
    {
        $fmt = fn ($v) => $v === null ? '' : number_format((float) $v, 2, ',', ' ') . ' FCFA';

        return $this->project->payments->map(fn ($p) => [
            $p->number,
            $p->period,
            optional($p->payment_date)->format('d/m/Y'),
            $fmt($p->gross_amount),
            $fmt($p->startup_advance_recovery),
            $fmt($p->supply_advance_recovery),
            $fmt($p->net_paid),
            $p->is_paid ? 'Payé' : 'En attente',
        ])->all();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:H1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
                ]);
            },
        ];
    }
}

class ProjetCourbesSheet implements FromArray, WithTitle, ShouldAutoSize, WithEvents, WithCharts
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
        return 'Courbes';
    }

    // Positions de lignes déterministes (cf. layout dans array()).
    protected function phStart(): int { return 3; }
    protected function phEnd(): int { return 3 + count($this->phys); }        // T0 + n
    protected function evTitle(): int { return 5 + count($this->phys); }
    protected function evHeader(): int { return 6 + count($this->phys); }
    protected function evStart(): int { return 7 + count($this->phys); }      // T0 + n
    protected function evEnd(): int { return 7 + count($this->phys) + count($this->fin); }

    public function array(): array
    {
        $rows = [];

        // --- Section 1 : Courbe en S — avancement physique (moyenne par période) ---
        $rows[] = ['COURBE EN S — AVANCEMENT PHYSIQUE (%)'];
        $rows[] = ['Période', 'Prévu (%)', 'Réel (%)', 'Écart (%)'];
        $rows[] = ['T0', 0, 0, 0];
        foreach ($this->phys as $p) {
            $rows[] = [$p['period'], $p['planned'], $p['actual'], round($p['actual'] - $p['planned'], 2)];
        }

        $rows[] = []; // ligne vide

        // --- Section 2 : Courbe EVM — avancement financier (cumulé projet) ---
        $rows[] = ['COURBE EVM — AVANCEMENT FINANCIER (FCFA cumulés)'];
        $rows[] = ['Période', 'Valeur planifiée (PV)', 'Valeur acquise (EV)', 'Coût réel (AC)', 'SPI', 'CPI'];
        $rows[] = ['T0', 0, 0, 0, null, null];
        foreach ($this->fin as $f) {
            $rows[] = [$f['period'], $f['pv'], $f['ev'], $f['ac'], $f['spi'], $f['cpi']];
        }

        return $rows;
    }

    /** Avancement physique moyen par période (comme la courbe en S de l'app). */
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

    /** EVM cumulé au niveau projet (somme par période puis cumul). */
    protected function aggregateFinancial(): array
    {
        $grouped = [];
        foreach ($this->project->financialProgresses as $f) {
            $k = (string) $f->period;
            if ($k === '') continue;
            $grouped[$k] ??= ['pv' => 0.0, 'ev' => 0.0, 'ac' => 0.0];
            $grouped[$k]['pv'] += (float) $f->planned_value;
            $grouped[$k]['ev'] += (float) $f->earned_value;
            $grouped[$k]['ac'] += (float) $f->actual_cost;
        }
        ksort($grouped);
        $cumPv = $cumEv = $cumAc = 0.0;
        $out = [];
        foreach ($grouped as $period => $g) {
            $cumPv += $g['pv']; $cumEv += $g['ev']; $cumAc += $g['ac'];
            $out[] = [
                'period' => $period,
                'pv' => round($cumPv, 2),
                'ev' => round($cumEv, 2),
                'ac' => round($cumAc, 2),
                'spi' => $cumPv > 0 ? round($cumEv / $cumPv, 3) : null,
                'cpi' => $cumAc > 0 ? round($cumEv / $cumAc, 3) : null,
            ];
        }
        return $out;
    }

    public function charts(): array
    {
        $t = 'Courbes';
        $charts = [];
        $chartTop = $this->evEnd() + 3;

        // Courbe en S (physique) : colonnes B (Prévu), C (Réel) sur X = A.
        $phRows = $this->phEnd() - $this->phStart() + 1;
        if ($phRows >= 2) {
            $s = $this->phStart(); $e = $this->phEnd();
            $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$t}'!\$A\${$s}:\$A\${$e}", null, $phRows)];
            $values = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$t}'!\$B\${$s}:\$B\${$e}", null, $phRows),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$t}'!\$C\${$s}:\$C\${$e}", null, $phRows),
            ];
            $labels = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$t}'!\$B\$2", null, 1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$t}'!\$C\$2", null, 1),
            ];
            $series = new DataSeries(DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD, range(0, 1), $labels, $categories, $values);
            $chart = new Chart('courbe_s', new Title('Courbe en S — Avancement physique'), new Legend(Legend::POSITION_BOTTOM, null, false), new PlotArea(null, [$series]), true, DataSeries::EMPTY_AS_GAP, new Title('Période'), new Title('Avancement (%)'));
            $chart->setTopLeftPosition('A' . $chartTop);
            $chart->setBottomRightPosition('H' . ($chartTop + 18));
            $charts[] = $chart;
        }

        // Courbe EVM (financier) : PV, EV, AC cumulés.
        $evRows = $this->evEnd() - $this->evStart() + 1;
        if ($evRows >= 2) {
            $s = $this->evStart(); $e = $this->evEnd(); $hdr = $this->evHeader();
            $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$t}'!\$A\${$s}:\$A\${$e}", null, $evRows)];
            $values = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$t}'!\$B\${$s}:\$B\${$e}", null, $evRows),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$t}'!\$C\${$s}:\$C\${$e}", null, $evRows),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$t}'!\$D\${$s}:\$D\${$e}", null, $evRows),
            ];
            $labels = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$t}'!\$B\${$hdr}", null, 1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$t}'!\$C\${$hdr}", null, 1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$t}'!\$D\${$hdr}", null, 1),
            ];
            $series = new DataSeries(DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD, range(0, 2), $labels, $categories, $values);
            $chart = new Chart('courbe_evm', new Title('Courbe EVM — Avancement financier'), new Legend(Legend::POSITION_BOTTOM, null, false), new PlotArea(null, [$series]), true, DataSeries::EMPTY_AS_GAP, new Title('Période'), new Title('Montant (FCFA)'));
            $chart->setTopLeftPosition('J' . $chartTop);
            $chart->setBottomRightPosition('R' . ($chartTop + 18));
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

                // Section physique
                $sheet->getStyle('A1')->applyFromArray($sectionStyle);
                $sheet->getStyle('A2:D2')->applyFromArray($headerStyle);
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
