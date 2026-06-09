<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <title>@yield('title', 'Rapport PDU-CI')</title>
    <style>
        @page { margin: 1.5cm 1.2cm 1.8cm 1.2cm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 4px 0; color: #312e81; }
        h2 { font-size: 13px; margin: 16px 0 6px 0; color: #312e81; border-bottom: 1px solid #c7d2fe; padding-bottom: 3px; }
        h3 { font-size: 11px; margin: 10px 0 4px 0; color: #4338ca; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th { background: #eef2ff; color: #312e81; font-size: 9px; text-align: left; padding: 5px 6px; border-bottom: 1px solid #c7d2fe; text-transform: uppercase; letter-spacing: 0.03em; }
        td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .center { text-align: center; }
        .ok { color: #047857; font-weight: bold; }
        .warn { color: #b45309; font-weight: bold; }
        .bad { color: #b91c1c; font-weight: bold; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 8px; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .badge-emerald { background: #d1fae5; color: #065f46; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-indigo { background: #e0e7ff; color: #3730a3; }
        .badge-gray { background: #f3f4f6; color: #374151; }
        .kpi-grid { width: 100%; margin-top: 6px; }
        .kpi-grid td { border: 1px solid #e5e7eb; padding: 6px; width: 25%; }
        .kpi-grid .label { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        .kpi-grid .value { font-size: 14px; font-weight: bold; color: #111827; margin-top: 2px; }
        .progress-bar { width: 100%; background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: #4f46e5; }
        .progress-fill.completed { background: #10b981; }
        header.pdf-header { border-bottom: 2px solid #4f46e5; padding-bottom: 8px; margin-bottom: 12px; }
        header.pdf-header table { border-collapse: collapse; }
        header.pdf-header td { border: none; padding: 0; }
        .logo { width: 44px; height: 44px; background: #4f46e5; color: white; font-size: 20px; font-weight: bold; text-align: center; line-height: 44px; border-radius: 6px; }
        footer.pdf-footer { position: fixed; bottom: -1cm; left: 0; right: 0; font-size: 8px; color: #9ca3af; padding: 6px 0; border-top: 1px solid #e5e7eb; text-align: center; }
        .page-number:before { content: counter(page); }
        .page-total:before { content: counter(pages); }
    </style>
</head>
<body>
    <header class="pdf-header">
        <table>
            <tr>
                <td style="width: 60px;"><div class="logo">P</div></td>
                <td style="padding-left: 10px;">
                    <h1>@yield('title', 'Rapport PDU-CI')</h1>
                    <p class="muted" style="margin: 0; font-size: 9px;">
                        Programme de Décentralisation des Universités de Côte d'Ivoire &middot; Outil de pilotage
                    </p>
                </td>
                <td class="right" style="width: 200px;">
                    <p class="muted" style="margin: 0; font-size: 9px;">Généré le</p>
                    <p style="margin: 2px 0 0 0; font-weight: bold;">{{ $generatedAt->format('d/m/Y à H:i') }}</p>
                </td>
            </tr>
        </table>
    </header>

    @yield('content')

    <footer class="pdf-footer">
        Outil de pilotage &middot; Document confidentiel &middot; Page <span class="page-number"></span> / <span class="page-total"></span>
    </footer>
</body>
</html>
