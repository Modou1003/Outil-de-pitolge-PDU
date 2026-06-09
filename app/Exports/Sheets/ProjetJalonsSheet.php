<?php

namespace App\Exports\Sheets;

use App\Models\PduProject;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class ProjetJalonsSheet implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    public function __construct(protected PduProject $project)
    {
    }

    public function title(): string
    {
        return 'Jalons';
    }

    public function collection(): Collection
    {
        return $this->project->milestones->map(function ($milestone) {
            return [
                $milestone->name,
                optional($milestone->planned_date)->toDateString(),
                optional($milestone->actual_date)->toDateString(),
                $milestone->status,
                $milestone->observations,
            ];
        });
    }

    public function headings(): array
    {
        return ['Jalon', 'Date prévue', 'Date réelle', 'Statut', 'Observations'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E79']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rows = $this->project->milestones->count();

                for ($row = 2; $row <= $rows + 1; $row++) {
                    $cell = $sheet->getCell("D{$row}")->getValue();
                    if ($cell === 'en_retard') {
                        $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB('FFFF0000');
                    }
                    if ($cell === 'atteint') {
                        $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB('FF00B050');
                    }
                }
            },
        ];
    }
}
