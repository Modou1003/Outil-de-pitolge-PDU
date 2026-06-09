@extends('pdf._layout')

@section('title', 'Rapport global PDU-CI')

@php
    $statusLabels = [
        'draft' => 'Brouillon', 'submitted' => 'Soumis', 'approved' => 'Approuvé',
        'in_progress' => 'En cours', 'on_hold' => 'En pause', 'completed' => 'Terminé',
        'cancelled' => 'Annulé', 'archived' => 'Archivé',
    ];
    $statusBadge = [
        'in_progress' => 'badge-indigo', 'completed' => 'badge-emerald',
        'on_hold' => 'badge-amber', 'cancelled' => 'badge-red',
        'approved' => 'badge-indigo', 'draft' => 'badge-gray',
        'submitted' => 'badge-gray', 'archived' => 'badge-gray',
    ];
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $fmtPct = fn ($n) => number_format((float) $n, 1, ',', ' ') . ' %';
@endphp

@section('content')
    <h2 style="margin-top: 0;">Synthèse du portefeuille</h2>
    <table class="kpi-grid">
        <tr>
            <td><div class="label">Projets totaux</div><div class="value">{{ $stats['total_projects'] }}</div></td>
            <td><div class="label">Projets actifs</div><div class="value ok">{{ $stats['active_projects'] }}</div></td>
            <td><div class="label">Projets terminés</div><div class="value">{{ $stats['completed_projects'] }}</div></td>
            <td><div class="label">En pause</div><div class="value warn">{{ $stats['on_hold_projects'] }}</div></td>
        </tr>
        <tr>
            <td><div class="label">Budget alloué total</div><div class="value">{{ $fmt($stats['total_budget']) }} FCFA</div></td>
            <td><div class="label">Budget consommé</div><div class="value">{{ $fmt($stats['total_spent']) }} FCFA</div></td>
            <td><div class="label">Avancement moyen</div><div class="value">{{ $fmtPct($stats['avg_progress']) }}</div></td>
            <td><div class="label">Alertes ouvertes</div><div class="value {{ $stats['critical_alerts'] > 0 ? 'bad' : '' }}">{{ $stats['open_alerts'] }} <span style="font-size: 9px; font-weight: normal;">dont {{ $stats['critical_alerts'] }} critiques</span></div></td>
        </tr>
    </table>

    <h2>Répartition par région</h2>
    @if($byRegion->count())
        <table>
            <thead>
                <tr><th>Région</th><th class="right">Nb projets</th><th class="right">Budget alloué</th><th class="right">Avancement moyen</th></tr>
            </thead>
            <tbody>
                @foreach($byRegion as $row)
                    <tr>
                        <td><strong>{{ $row['region'] }}</strong></td>
                        <td class="right">{{ $row['count'] }}</td>
                        <td class="right">{{ $fmt($row['budget']) }} FCFA</td>
                        <td class="right">{{ $fmtPct($row['avg_progress']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Liste détaillée des projets ({{ $projects->count() }})</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">Code</th>
                <th style="width: 22%;">Titre</th>
                <th style="width: 18%;">Université</th>
                <th style="width: 12%;">Statut</th>
                <th class="right" style="width: 10%;">Avancement</th>
                <th class="right" style="width: 15%;">Budget alloué</th>
                <th class="right" style="width: 15%;">Consommé</th>
            </tr>
        </thead>
        <tbody>
            @foreach($projects as $p)
                @php
                    $rate = $p->budget_allocated > 0 ? ($p->budget_spent / $p->budget_allocated * 100) : 0;
                    $rateClass = $rate >= 95 ? 'bad' : ($rate >= 80 ? 'warn' : '');
                @endphp
                <tr>
                    <td><strong>{{ $p->code }}</strong></td>
                    <td>{{ \Illuminate\Support\Str::limit($p->title, 60) }}</td>
                    <td class="muted">{{ $p->university?->acronym ?? $p->university?->name ?? '—' }}</td>
                    <td><span class="badge {{ $statusBadge[$p->status] ?? 'badge-gray' }}">{{ $statusLabels[$p->status] ?? $p->status }}</span></td>
                    <td class="right">
                        <div class="progress-bar" style="width: 70px; display: inline-block; vertical-align: middle;">
                            <div class="progress-fill{{ $p->progress_percentage >= 100 ? ' completed' : '' }}" style="width: {{ min(100, (float) $p->progress_percentage) }}%"></div>
                        </div>
                        <span style="margin-left: 4px;">{{ number_format((float) $p->progress_percentage, 0) }}%</span>
                    </td>
                    <td class="right">{{ $fmt($p->budget_allocated) }}</td>
                    <td class="right {{ $rateClass }}">{{ $fmt($p->budget_spent) }} <span class="muted" style="font-size: 8px;">({{ number_format($rate, 0) }}%)</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
