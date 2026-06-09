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
            new ProjetPhysicalSheet($this->project),
            new ProjetFinancialSheet($this->project),
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
            ['Budget dépensé', $this->formatCurrency($this->project->budget_spent)],
            ['Taux d’exécution budget', $this->project->budget_execution_rate . '%'],
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
        return ['Lot', 'Période', 'Date mesure', 'Avancement prévu', 'Avancement réel', 'Écart', 'Observations'];
    }

    public function array(): array
    {
        return $this->project->physicalProgresses->map(function ($progress) {
            return [
                $progress->lot?->name ?? '—',
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

class ProjetCourbesSheet implements FromArray, WithTitle, ShouldAutoSize, WithEvents
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
        return 'Courbes';
    }

    public function array(): array
    {
        $rows = [];

        // --- Section 1: Courbe S (Avancement Physique) ---
        $rows[] = ['COURBE S — AVANCEMENT PHYSIQUE'];
        $rows[] = ['Période', 'Prévu (%)', 'Réel (%)', 'Écart (%)'];
        $this->physicalStartRow = count($rows) + 1;

        // Point de départ à zéro
        $rows[] = ['T0', 0, 0, 0];

        foreach ($this->project->physicalProgresses as $progress) {
            $planned = (float) $progress->planned_percentage;
            $actual = (float) $progress->actual_percentage;
            $rows[] = [$progress->period, $planned, $actual, round($actual - $planned, 2)];
        }
        $this->physicalEndRow = count($rows);

        $rows[] = []; // ligne vide

        // --- Section 2: Courbe EVM (Avancement Financier) ---
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
                $sheetTitle = 'Courbes';

                $sectionStyle = [
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1F4E79']],
                ];

                // --- Style section Courbe S ---
                $phTitleRow = $this->physicalStartRow - 2;
                $phHeaderRow = $this->physicalStartRow - 1;

                $sheet->getStyle("A{$phTitleRow}")->applyFromArray($sectionStyle);
                $sheet->getStyle("A{$phHeaderRow}:D{$phHeaderRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Conditional: Écart (col D)
                for ($row = $this->physicalStartRow; $row <= $this->physicalEndRow; $row++) {
                    $val = $sheet->getCell("D{$row}")->getValue();
                    if ($val === null) continue;
                    if ($val < 0) {
                        $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB('FFFF0000');
                    } else {
                        $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB('FF00B050');
                    }
                }

                // --- Style section Courbe EVM ---
                $evmTitleRow = $this->evmStartRow - 2;
                $evmHeaderRow = $this->evmStartRow - 1;

                $sheet->getStyle("A{$evmTitleRow}")->applyFromArray($sectionStyle);
                $sheet->getStyle("A{$evmHeaderRow}:F{$evmHeaderRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Conditional: SPI/CPI (cols E, F)
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

                // --- Charts côte à côte ---
                $chartStartRow = $this->evmEndRow + 3;
                $physRows = $this->physicalEndRow - $this->physicalStartRow + 1;
                $evmRows = $this->evmEndRow - $this->evmStartRow + 1;

                // Chart gauche: Courbe S
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

                    $chart1 = new Chart('courbe_s', $chartTitle, $legend, $plotArea, true, DataSeries::EMPTY_AS_GAP, $xAxisLabel, $yAxisLabel);
                    $chart1->setTopLeftPosition('A' . $chartStartRow);
                    $chart1->setBottomRightPosition('G' . ($chartStartRow + 15));
                    $sheet->addChart($chart1);
                }

                // Chart droite: Courbe EVM
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

                    $chart2 = new Chart('courbe_evm', $chartTitle, $legend, $plotArea, true, DataSeries::EMPTY_AS_GAP, $xAxisLabel, $yAxisLabel);
                    $chart2->setTopLeftPosition('H' . $chartStartRow);
                    $chart2->setBottomRightPosition('O' . ($chartStartRow + 15));
                    $sheet->addChart($chart2);
                }
            },
        ];
    }
}
