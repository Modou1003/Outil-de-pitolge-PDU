@extends('pdf._layout')

@section('title', 'Fiche projet — ' . $project->code)

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
    $cpiClass = ($kpis['cpi'] ?? 0) >= 1 ? 'ok' : (($kpis['cpi'] ?? 0) >= 0.9 ? 'warn' : 'bad');
    $spiClass = ($kpis['spi'] ?? 0) >= 1 ? 'ok' : (($kpis['spi'] ?? 0) >= 0.9 ? 'warn' : 'bad');
    $fmt = fn ($n) => $n !== null ? number_format((float) $n, 0, ',', ' ') : '—';
@endphp

@section('content')
    <table style="margin-bottom: 10px;">
        <tr>
            <td style="width: 70%; border: 0; padding: 0;">
                <h2 style="margin-top: 0; border: 0;">{{ $project->title }}</h2>
                <p class="muted" style="margin: 2px 0;">
                    Code : <strong>{{ $project->code }}</strong> &middot;
                    Type : {{ $project->type }} &middot;
                    Statut : <span class="badge {{ $statusBadge[$project->status] ?? 'badge-gray' }}">{{ $statusLabels[$project->status] ?? $project->status }}</span>
                </p>
                <p style="margin: 4px 0;">{{ $project->description }}</p>
            </td>
            <td style="width: 30%; border: 0; padding: 0; vertical-align: top;">
                <p class="muted" style="margin: 0;">Université</p>
                <p style="margin: 0; font-weight: bold;">{{ $project->university?->name ?? '—' }}</p>
                <p class="muted" style="margin: 4px 0 0 0; font-size: 9px;">
                    {{ $project->university?->location ?? '' }}@if($project->university?->region) &middot; {{ $project->university->region }}@endif
                </p>
            </td>
        </tr>
    </table>

    <h2>Indicateurs clés (EVM)</h2>
    <table class="kpi-grid">
        <tr>
            <td><div class="label">Avancement réel</div><div class="value">{{ number_format((float) $project->progress_percentage, 1, ',', ' ') }} %</div></td>
            <td><div class="label">Avancement prévu</div><div class="value">{{ $kpis['planned_progress'] !== null ? number_format((float) $kpis['planned_progress'], 1, ',', ' ') . ' %' : '—' }}</div></td>
            <td><div class="label">Jalons atteints</div><div class="value">{{ $kpis['milestones_reached'] }} / {{ $kpis['milestones_total'] }}</div></td>
            <td><div class="label">Alertes ouvertes</div><div class="value {{ $project->alerts->count() ? 'bad' : '' }}">{{ $project->alerts->count() }}</div></td>
        </tr>
        <tr>
            <td><div class="label">CPI (coût)</div><div class="value {{ $cpiClass }}">{{ $kpis['cpi'] ? number_format($kpis['cpi'], 2) : '—' }}</div></td>
            <td><div class="label">SPI (délai)</div><div class="value {{ $spiClass }}">{{ $kpis['spi'] ? number_format($kpis['spi'], 2) : '—' }}</div></td>
            <td><div class="label">Budget alloué</div><div class="value">{{ $fmt($project->budget_allocated) }} {{ $project->currency }}</div></td>
            <td><div class="label">Budget consommé</div><div class="value">{{ $fmt($project->budget_spent) }} ({{ $kpis['budget_rate'] !== null ? number_format($kpis['budget_rate'], 1, ',', ' ') . ' %' : '—' }})</div></td>
        </tr>
    </table>

    <h2>Courbes d'avancement</h2>
    @if($physicalChartSvg)
        <p style="margin: 6px 0 1px 0; font-weight: bold; font-size: 11px;">Courbe en S — Avancement physique (%)</p>
        <p class="muted" style="margin: 0 0 3px 0; font-size: 9px;">
            <span style="color: #6366f1;">━━</span> Prévu moyen &nbsp;&nbsp;
            <span style="color: #10b981;">━━</span> Réel moyen
        </p>
        <img src="data:image/svg+xml;base64,{{ base64_encode($physicalChartSvg) }}" style="width: 100%; max-width: 700px;" alt="Courbe en S — avancement physique" />
    @else
        <p class="muted">Aucune donnée d'avancement physique.</p>
    @endif

    @if($financialChartSvg)
        <p style="margin: 10px 0 1px 0; font-weight: bold; font-size: 11px;">Courbe EVM — Avancement financier (FCFA cumulés, en millions)</p>
        <p class="muted" style="margin: 0 0 3px 0; font-size: 9px;">
            <span style="color: #6366f1;">━━</span> Valeur planifiée (PV) &nbsp;&nbsp;
            <span style="color: #10b981;">━━</span> Valeur acquise (EV) &nbsp;&nbsp;
            <span style="color: #f59e0b;">━━</span> Coût réel (AC)
        </p>
        <img src="data:image/svg+xml;base64,{{ base64_encode($financialChartSvg) }}" style="width: 100%; max-width: 700px;" alt="Courbe EVM — avancement financier" />
    @endif

    <h2>Équipe projet</h2>
    <table>
        <tr>
            <th style="width: 25%;">Rôle</th><th>Nom</th><th style="width: 30%;">Contact</th>
        </tr>
        <tr><td>Directeur</td><td>{{ $project->director?->name ?? '—' }}</td><td class="muted">{{ $project->director?->email ?? '' }}</td></tr>
        <tr><td>Chef de projet</td><td>{{ $project->projectManager?->name ?? '—' }}</td><td class="muted">{{ $project->projectManager?->email ?? '' }}</td></tr>
        <tr><td>Agent financier</td><td>{{ $project->financialAgent?->name ?? '—' }}</td><td class="muted">{{ $project->financialAgent?->email ?? '' }}</td></tr>
    </table>

    <h2>Situation financière (maître d'ouvrage)</h2>
    <table class="kpi-grid">
        <tr>
            <td><div class="label">Marché (budget)</div><div class="value">{{ $fmt($moa['budget']) }} {{ $project->currency }}</div></td>
            <td><div class="label">Facturé</div><div class="value">{{ $fmt($moa['invoiced']) }} ({{ $moa['invoice_rate'] !== null ? number_format($moa['invoice_rate'], 1, ',', ' ') . ' %' : '—' }})</div></td>
            <td><div class="label">Reste à facturer</div><div class="value warn">{{ $fmt($moa['remaining_to_invoice']) }} ({{ $moa['remaining_to_invoice_rate'] !== null ? number_format($moa['remaining_to_invoice_rate'], 1, ',', ' ') . ' %' : '—' }})</div></td>
            <td><div class="label">Encaissé (travaux + avances)</div><div class="value">{{ $fmt($moa['encashed']) }} ({{ $moa['encashment_rate'] !== null ? number_format($moa['encashment_rate'], 1, ',', ' ') . ' %' : '—' }})</div></td>
        </tr>
        <tr>
            <td><div class="label">Avances versées</div><div class="value">{{ $fmt($moa['advance_granted']) }}</div></td>
            <td><div class="label">Avances remboursées</div><div class="value">{{ $fmt($moa['advance_recovered']) }}</div></td>
            <td><div class="label">Reste à rembourser (exposition)</div><div class="value {{ $moa['advance_remaining'] > 0 ? 'bad' : '' }}">{{ $fmt($moa['advance_remaining']) }} ({{ $moa['advance_remaining_rate'] !== null ? number_format($moa['advance_remaining_rate'], 1, ',', ' ') . ' %' : '—' }})</div></td>
            <td><div class="label">Net décaissé</div><div class="value">{{ $fmt($moa['net_paid']) }}</div></td>
        </tr>
    </table>

    @if($project->payments->count())
        <table style="margin-top: 8px;">
            <thead>
                <tr><th>N° décompte</th><th>Période</th><th>Date</th><th class="right">Brut HT</th><th class="right">Remb. avances</th><th class="right">Net payé</th><th>Statut</th></tr>
            </thead>
            <tbody>
                @foreach($project->payments as $p)
                    <tr>
                        <td><strong>{{ $p->number }}</strong></td>
                        <td class="muted">{{ $p->period ?? '—' }}</td>
                        <td class="muted">{{ $p->payment_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="right">{{ $fmt($p->gross_amount) }}</td>
                        <td class="right">{{ $fmt($p->startup_advance_recovery + $p->supply_advance_recovery) }}</td>
                        <td class="right">{{ $fmt($p->net_paid) }}</td>
                        <td>{{ $p->is_paid ? 'Payé' : 'En attente' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Ouvrages ({{ $project->buildingWorks->count() }})</h2>
    @if($project->buildingWorks->count())
        <table>
            <thead>
                <tr><th>Code</th><th>Ouvrage</th><th class="right">Pondération</th><th class="right">Avancement</th><th>Statut</th></tr>
            </thead>
            <tbody>
                @foreach($project->buildingWorks as $w)
                    <tr>
                        <td><strong>{{ $w->code }}</strong></td>
                        <td>{{ $w->name }}</td>
                        <td class="right">{{ number_format((float) $w->weight_percentage, 1, ',', ' ') }} %</td>
                        <td class="right">{{ number_format((float) $w->progress_percentage, 1, ',', ' ') }} %</td>
                        <td>{{ $statusLabels[$w->status] ?? $w->status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Aucun ouvrage défini.</p>
    @endif

    <h2>Planning &middot; Lots ({{ $project->lots->count() }})</h2>
    @if($project->lots->count())
        <table>
            <thead>
                <tr><th>Code</th><th>Lot</th><th>Statut</th><th>Période prévue</th></tr>
            </thead>
            <tbody>
                @foreach($project->lots as $lot)
                    <tr>
                        <td><strong>{{ $lot->code }}</strong></td>
                        <td>{{ $lot->name }}</td>
                        <td>{{ $statusLabels[$lot->status] ?? $lot->status }}</td>
                        <td class="muted">{{ $lot->planned_start_date?->format('d/m/Y') }} → {{ $lot->planned_end_date?->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Aucun lot défini.</p>
    @endif

    <h2>Jalons clés ({{ $project->milestones->count() }})</h2>
    @if($project->milestones->count())
        <table>
            <thead>
                <tr><th>Jalon</th><th>Prévu</th><th>Atteint</th><th>Statut</th><th>Critique</th></tr>
            </thead>
            <tbody>
                @foreach($project->milestones as $m)
                    <tr>
                        <td>{{ $m->name }}</td>
                        <td class="muted">{{ $m->planned_date?->format('d/m/Y') }}</td>
                        <td class="muted">{{ $m->actual_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $m->status }}</td>
                        <td class="center">{{ $m->is_critical ? '★' : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Aucun jalon.</p>
    @endif

    <h2>Indicateurs PDU ({{ $project->indicatorTrackings->count() }})</h2>
    @if($project->indicatorTrackings->count())
        <table>
            <thead>
                <tr><th>Indicateur</th><th class="right">Cible</th><th class="right">Réalisé</th><th class="right">Taux</th></tr>
            </thead>
            <tbody>
                @foreach($project->indicatorTrackings as $t)
                    @php
                        $rate = ($t->target_value && (float) $t->target_value > 0) ? ((float) $t->actual_value / (float) $t->target_value * 100) : null;
                        $cls = $rate === null ? '' : ($rate >= 90 ? 'ok' : ($rate >= 70 ? 'warn' : 'bad'));
                    @endphp
                    <tr>
                        <td>{{ $t->indicator?->name ?? '—' }}</td>
                        <td class="right">{{ $t->target_value !== null ? number_format((float) $t->target_value, 1, ',', ' ') . ' ' . ($t->indicator?->unit_symbol ?? '') : '—' }}</td>
                        <td class="right">{{ $t->actual_value !== null ? number_format((float) $t->actual_value, 1, ',', ' ') . ' ' . ($t->indicator?->unit_symbol ?? '') : '—' }}</td>
                        <td class="right {{ $cls }}">{{ $rate !== null ? number_format($rate, 1, ',', ' ') . ' %' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Aucun indicateur tracké.</p>
    @endif

    @if($project->alerts->count())
        <h2>Alertes ouvertes ({{ $project->alerts->count() }})</h2>
        <table>
            <thead><tr><th>Sévérité</th><th>Type</th><th>Titre</th><th>Détectée le</th></tr></thead>
            <tbody>
                @foreach($project->alerts as $a)
                    <tr>
                        <td><span class="badge badge-{{ $a->severity === 'critical' ? 'red' : ($a->severity === 'warning' ? 'amber' : 'indigo') }}">{{ $a->severity_label }}</span></td>
                        <td>{{ $a->type_label }}</td>
                        <td>{{ $a->title }}</td>
                        <td class="muted">{{ $a->detected_at?->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
