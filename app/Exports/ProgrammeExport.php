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
 * Feuille dédiée par projet : infos + Courbe S (physique) + Courbe EVM (financier)
 */
class ProgrammeProjetSheet implements FromArray, WithTitle, ShouldAutoSize, WithEvents
{
    protected int $physicalStartRow = 0;
    protected int $physicalEndRow = 0;
    protected int $evmStartRow = 0;
    protected int $evmEndRow = 0;

    public function __construct(protected PduProject $project)
    {
    }

    public function title(): string
    {
        // Excel sheet names max 31 chars
        $name = $this->project->code . ' - ' . $this->project->title;
        return mb_substr($name, 0, 31);
    }

    public function array(): array
    {
        $rows = [];

        // --- Section 1: Informations du projet ---
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

        // --- Section 2: Courbe S (Avancement Physique) ---
        $rows[] = ['COURBE S — AVANCEMENT PHYSIQUE'];
        $rows[] = ['Période', 'Prévu (%)', 'Réel (%)', 'Écart (%)'];
        $this->physicalStartRow = count($rows) + 1; // +1 car 1-indexed

        // Point de départ à zéro
        $rows[] = ['T0', 0, 0, 0];

        foreach ($this->project->physicalProgresses as $progress) {
            $planned = (float) $progress->planned_percentage;
            $actual = (float) $progress->actual_percentage;
            $rows[] = [$progress->period, $planned, $actual, round($actual - $planned, 2)];
        }
        $this->physicalEndRow = count($rows);

        $rows[] = []; // ligne vide

        // --- Section 3: Courbe EVM (Avancement Financier) ---
        $rows[] = ['COURBE EVM — AVANCEMENT FINANCIER'];
        $rows[] = ['Période', 'Valeur planifiée (PV)', 'Valeur acquise (EV)', 'Coût réel (AC)', 'SPI', 'CPI'];
        $this->evmStartRow = count($rows) + 1;

        // Point de départ à zéro
        $rows[] = ['T0', 0, 0, 0, null, null];

        foreach ($this->project->financialProgresses as $fp) {
            $rows[] = [
                $fp->period,
                (float) $fp->cumulative_planned_value,
                (float) $fp->cumulative_earned_value,
                (float) $fp->cumulative_actual_cost,
                $fp->spi !== null ? (float) $fp->spi : null,
                $fp->cpi !== null ? (float) $fp->cpi : null,
            ];
        }
        $this->evmEndRow = count($rows);

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheetTitle = $this->title();

                // Style section headers
                $sectionStyle = [
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1F4E79']],
                ];
                $sheet->getStyle('A1')->applyFromArray($sectionStyle);

                // Info section (rows 2-13) — bold labels col A
                $sheet->getStyle('A2:A13')->applyFromArray(['font' => ['bold' => true]]);

                // --- Courbe S section ---
                $physicalHeaderRow = $this->physicalStartRow - 1;
                $sheet->getStyle("A{$physicalHeaderRow}")->applyFromArray($sectionStyle);

                // Data header row for physical
                $physDataHeader = $physicalHeaderRow + 1;
                // Wait, physicalStartRow is already the first data row. The header is physicalStartRow - 1.
                // Actually let me recalculate: in array(), rows[] includes section title then headers then data.
                // physicalStartRow = first data row (1-indexed).
                // The header row is physicalStartRow - 1.
                $phHeaderRow = $this->physicalStartRow - 1;
                // The section title is phHeaderRow - 1
                $phTitleRow = $phHeaderRow - 1;

                $sheet->getStyle("A{$phTitleRow}")->applyFromArray($sectionStyle);
                $sheet->getStyle("A{$phHeaderRow}:D{$phHeaderRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Conditional formatting Écart (col D)
                for ($row = $this->physicalStartRow; $row <= $this->physicalEndRow; $row++) {
                    $val = $sheet->getCell("D{$row}")->getValue();
                    if ($val === null) continue;
                    if ($val < 0) {
                        $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB('FFFF0000');
                    } else {
                        $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB('FF00B050');
                    }
                }

                // --- Courbe EVM section ---
                $evmHeaderRow = $this->evmStartRow - 1;
                $evmTitleRow = $evmHeaderRow - 1;

                $sheet->getStyle("A{$evmTitleRow}")->applyFromArray($sectionStyle);
                $sheet->getStyle("A{$evmHeaderRow}:F{$evmHeaderRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Conditional formatting SPI/CPI (cols E, F)
                for ($row = $this->evmStartRow; $row <= $this->evmEndRow; $row++) {
                    foreach (['E', 'F'] as $col) {
                        $val = $sheet->getCell("{$col}{$row}")->getValue();
                        if ($val === null) continue;
                        if ($val < 0.8) {
                            $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setARGB('FFFF0000');
                        } elseif ($val >= 1) {
                            $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setARGB('FF00B050');
                        }
                    }
                }

                // --- Charts côte à côte: Courbe S à gauche, Courbe EVM à droite ---
                $chartStartRow = $this->evmEndRow + 3;
                $physRows = $this->physicalEndRow - $this->physicalStartRow + 1;
                $evmRows = $this->evmEndRow - $this->evmStartRow + 1;

                // Chart 1 (gauche): Courbe S — Avancement Physique
                if ($physRows >= 2) {
                    $pStart = $this->physicalStartRow;
                    $pEnd = $this->physicalEndRow;

                    $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetTitle}'!\$A\${$pStart}:\$A\${$pEnd}", null, $physRows)];
                    $values = [
                        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$sheetTitle}'!\$B\${$pStart}:\$B\${$pEnd}", null, $physRows),
                        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$sheetTitle}'!\$C\${$pStart}:\$C\${$pEnd}", null, $physRows),
                    ];
                    $phHdr = $this->physicalStartRow - 1;
                    $labels = [
                        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetTitle}'!\$B\${$phHdr}", null, 1),
                        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetTitle}'!\$C\${$phHdr}", null, 1),
                    ];

                    $series = new DataSeries(
                        DataSeries::TYPE_LINECHART,
                        DataSeries::GROUPING_STANDARD,
                        range(0, 1),
                        $labels,
                        $categories,
                        $values,
                    );

                    $plotArea = new PlotArea(null, [$series]);
                    $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
                    $chartTitle = new Title('Courbe en S — Avancement physique cumulé');
                    $xAxisLabel = new Title('Date');
                    $yAxisLabel = new Title('Avancement (%)');

                    $chart1 = new Chart('courbe_s_' . $this->project->id, $chartTitle, $legend, $plotArea, true, DataSeries::EMPTY_AS_GAP, $xAxisLabel, $yAxisLabel);
                    $chart1->setTopLeftPosition('A' . $chartStartRow);
                    $chart1->setBottomRightPosition('G' . ($chartStartRow + 15));
                    $sheet->addChart($chart1);
                }

                // Chart 2 (droite): Courbe EVM — Avancement Financier
                if ($evmRows >= 2) {
                    $eStart = $this->evmStartRow;
                    $eEnd = $this->evmEndRow;

                    $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetTitle}'!\$A\${$eStart}:\$A\${$eEnd}", null, $evmRows)];
                    $values = [
                        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$sheetTitle}'!\$B\${$eStart}:\$B\${$eEnd}", null, $evmRows),
                        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$sheetTitle}'!\$C\${$eStart}:\$C\${$eEnd}", null, $evmRows),
                        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$sheetTitle}'!\$D\${$eStart}:\$D\${$eEnd}", null, $evmRows),
                    ];
                    $evHdr = $this->evmStartRow - 1;
                    $labels = [
                        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetTitle}'!\$B\${$evHdr}", null, 1),
                        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetTitle}'!\$C\${$evHdr}", null, 1),
                        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetTitle}'!\$D\${$evHdr}", null, 1),
                    ];

                    $series = new DataSeries(
                        DataSeries::TYPE_LINECHART,
                        DataSeries::GROUPING_STANDARD,
                        range(0, 2),
                        $labels,
                        $categories,
                        $values,
                    );

                    $plotArea = new PlotArea(null, [$series]);
                    $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
                    $chartTitle = new Title('Courbe EVM — Avancement Financier');
                    $xAxisLabel = new Title('Date');
                    $yAxisLabel = new Title('Montant (FCFA)');

                    $chart2 = new Chart('courbe_evm_' . $this->project->id, $chartTitle, $legend, $plotArea, true, DataSeries::EMPTY_AS_GAP, $xAxisLabel, $yAxisLabel);
                    $chart2->setTopLeftPosition('H' . $chartStartRow);
                    $chart2->setBottomRightPosition('O' . ($chartStartRow + 15));
                    $sheet->addChart($chart2);
                }
            },
        ];
    }
}
