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

    // Indicateurs de pilotage repris de la fiche projet.
    $prev = $kpis['forecast'] ?? ['level' => 'none', 'delay_days' => null, 'projected_end_date' => null, 'planned_end_date' => null];
    $ecart = $kpis['physical_financial'] ?? ['level' => 'none', 'gap' => 0, 'direction' => 'aligned'];
    $frais = $kpis['data_freshness'] ?? ['level' => 'none', 'days_since' => null, 'last_update' => null, 'coverage_rate' => null, 'lots_recent' => 0, 'lots_total' => 0];

    $forecastValue = match ($prev['level']) {
        'done' => 'Terminé',
        'none' => '—',
        default => \Illuminate\Support\Carbon::parse($prev['projected_end_date'])->translatedFormat('M Y'),
    };
    $forecastClass = match ($prev['level']) {
        'on_track', 'done' => 'ok',
        'watch' => 'warn',
        'critical' => 'bad',
        default => '',
    };

    // Un décaissement en avance sur la réalisation est le sens préoccupant ;
    // l'inverse traduit des travaux faits mais pas encore payés.
    $ecartClass = match ($ecart['level']) {
        'aligned' => 'ok',
        'watch' => 'warn',
        'critical' => 'bad',
        default => '',
    };
    $ecartSens = match ($ecart['direction']) {
        'overspend' => 'décaissement en avance',
        'underspend' => 'réalisation en avance',
        default => 'physique et budget alignés',
    };

    $fraicheurClass = match ($frais['level']) {
        'fresh' => 'ok',
        'stale' => 'warn',
        'critical' => 'bad',
        default => '',
    };
@endphp

@section('content')
@if($show('identite'))
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
@endif

@if($show('indicateurs'))
    <h2>Indicateurs clés</h2>
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
            <td><div class="label">Marché actualisé</div><div class="value">{{ $fmt($project->budget_revised) }} {{ $project->currency }}</div></td>
            <td><div class="label">Budget consommé</div><div class="value">{{ $fmt($project->budget_spent) }} ({{ $kpis['budget_rate'] !== null ? number_format($kpis['budget_rate'], 1, ',', ' ') . ' %' : '—' }})</div></td>
        </tr>
        <tr>
            <td>
                <div class="label">Fin projetée</div>
                <div class="value {{ $forecastClass }}">{{ $forecastValue }}</div>
                @if($prev['level'] !== 'none' && $prev['level'] !== 'done')
                    <div class="muted" style="font-size: 8px;">
                        échéance {{ \Illuminate\Support\Carbon::parse($prev['planned_end_date'])->format('d/m/Y') }}
                    </div>
                @endif
            </td>
            <td>
                <div class="label">Retard projeté</div>
                <div class="value {{ $forecastClass }}">
                    {{ $prev['delay_days'] !== null ? ($prev['delay_days'] > 0 ? '+' : '') . $prev['delay_days'] . ' j' : '—' }}
                </div>
            </td>
            <td>
                <div class="label">Écart physique / budget</div>
                <div class="value {{ $ecartClass }}">
                    {{ $ecart['level'] !== 'none' ? ($ecart['gap'] > 0 ? '+' : '') . number_format($ecart['gap'], 1, ',', ' ') . ' pts' : '—' }}
                </div>
                @if($ecart['level'] !== 'none')
                    <div class="muted" style="font-size: 8px;">{{ $ecartSens }}</div>
                @endif
            </td>
            <td>
                <div class="label">Fraîcheur de la donnée</div>
                <div class="value {{ $fraicheurClass }}">
                    {{ $frais['days_since'] !== null ? $frais['days_since'] . ' j' : '—' }}
                </div>
                <div class="muted" style="font-size: 8px;">
                    @if($frais['days_since'] === null)
                        aucune saisie d'avancement
                    @else
                        dernière saisie le {{ \Illuminate\Support\Carbon::parse($frais['last_update'])->format('d/m/Y') }}{{ $frais['coverage_rate'] !== null ? ' · ' . $frais['lots_recent'] . '/' . $frais['lots_total'] . ' ouvrages à jour' : '' }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

@endif

@if($show('avenants') && $project->amendments->isNotEmpty())
        <h2>Avenants au marché</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 12%;">N°</th>
                    <th style="width: 15%;">Date</th>
                    <th>Objet</th>
                    <th class="right" style="width: 18%;">Montant</th>
                    <th class="right" style="width: 12%;">Délai</th>
                </tr>
            </thead>
            <tbody>
                @foreach($project->amendments as $a)
                    <tr>
                        <td><strong>{{ $a->number }}</strong></td>
                        <td class="muted">{{ $a->signed_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $a->object ?: '—' }}</td>
                        <td class="right {{ $a->amount < 0 ? 'warn' : '' }}">{{ $a->amount ? ($a->amount > 0 ? '+' : '') . $fmt($a->amount) : '—' }}</td>
                        <td class="right {{ $a->duration_days < 0 ? 'warn' : '' }}">{{ $a->duration_days ? ($a->duration_days > 0 ? '+' : '') . $a->duration_days . ' j' : '—' }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="3"><strong>Marché initial</strong></td>
                    <td class="right">{{ $fmt($project->budget_allocated) }}</td>
                    <td class="right muted">{{ $project->planned_end_date?->format('d/m/Y') ?? '—' }}</td>
                </tr>
                <tr>
                    <td colspan="3"><strong>Total avenants</strong></td>
                    <td class="right">{{ $project->amendments_total > 0 ? '+' : '' }}{{ $fmt($project->amendments_total) }}</td>
                    <td class="right">{{ $project->amendments_duration_days > 0 ? '+' : '' }}{{ $project->amendments_duration_days }} j</td>
                </tr>
                <tr>
                    <td colspan="3"><strong>Marché actualisé</strong></td>
                    <td class="right"><strong>{{ $fmt($project->budget_revised) }} {{ $project->currency }}</strong></td>
                    <td class="right"><strong>{{ $project->planned_end_date_revised?->format('d/m/Y') ?? '—' }}</strong></td>
                </tr>
            </tbody>
        </table>
@endif

@if($show('courbes'))
    <h2>Courbes d'avancement</h2>
    @if($physicalChartSvg)
        <p style="margin: 6px 0 1px 0; font-weight: bold; font-size: 11px;">Courbe en S — Avancement physique (%)</p>
        <p class="muted" style="margin: 0 0 3px 0; font-size: 9px;">
            <span style="color: #6366f1;">━━</span> Prévu moyen &nbsp;&nbsp;
            <span style="color: #10b981;">━━</span> Réel moyen
        </p>
        <img src="data:image/svg+xml;base64,{{ base64_encode($physicalChartSvg) }}" style="width: 100%; max-width: 700px;" alt="Courbe en S — avancement physique" />
    @else
        <p style="margin: 6px 0 1px 0; font-weight: bold; font-size: 11px;">Courbe en S — Avancement physique (%)</p>
        <p class="muted" style="margin: 0;">Aucune donnée d'avancement physique.</p>
    @endif

    @if($financialChartSvg)
        <p style="margin: 10px 0 1px 0; font-weight: bold; font-size: 11px;">Courbe EVM — Avancement financier (FCFA cumulés, en millions)</p>
        <p class="muted" style="margin: 0 0 3px 0; font-size: 9px;">
            <span style="color: #6366f1;">━━</span> Valeur planifiée (PV) &nbsp;&nbsp;
            <span style="color: #10b981;">━━</span> Valeur acquise (EV) &nbsp;&nbsp;
            <span style="color: #f59e0b;">━━</span> Coût réel (AC)
        </p>
        <img src="data:image/svg+xml;base64,{{ base64_encode($financialChartSvg) }}" style="width: 100%; max-width: 700px;" alt="Courbe EVM — avancement financier" />
    @else
        <p style="margin: 10px 0 1px 0; font-weight: bold; font-size: 11px;">Courbe EVM — Avancement financier</p>
        <p class="muted" style="margin: 0;">Aucune donnée d'avancement financier (EVM).</p>
    @endif
@endif

@if($show('equipe'))
    <h2>Équipe projet</h2>
    <table>
        <tr>
            <th style="width: 25%;">Rôle</th><th>Nom</th><th style="width: 30%;">Contact</th>
        </tr>
        <tr><td>Directeur</td><td>{{ $project->director?->name ?? '—' }}</td><td class="muted">{{ $project->director?->email ?? '' }}</td></tr>
        <tr><td>Chef de projet</td><td>{{ $project->projectManager?->name ?? '—' }}</td><td class="muted">{{ $project->projectManager?->email ?? '' }}</td></tr>
        <tr><td>Agent financier</td><td>{{ $project->financialAgent?->name ?? '—' }}</td><td class="muted">{{ $project->financialAgent?->email ?? '' }}</td></tr>
    </table>
@endif

@if($show('financier'))
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
@endif

@if($show('planning_contractuel') && $project->buildingWorks->whereNotNull('planned_start_date')->count())
    <h2>Planning contractuel par ouvrage</h2>
    <table>
        <thead>
            <tr>
                <th>Ouvrage</th>
                <th class="right" style="width: 10%;">Durée</th>
                <th class="center" style="width: 14%;">Début prévu</th>
                <th class="center" style="width: 14%;">Fin prévue</th>
                <th class="center" style="width: 14%;">Début réel</th>
                <th class="right" style="width: 14%;">Retard démarrage</th>
            </tr>
        </thead>
        <tbody>
            @foreach($project->buildingWorks as $w)
                @php
                    $retard = $w->start_delay_days;
                    $classeRetard = $retard === null ? 'muted' : ($retard > 90 ? 'bad' : ($retard > 15 ? 'warn' : 'ok'));
                @endphp
                <tr>
                    <td><strong>{{ $w->code }}</strong> — {{ $w->name }}</td>
                    <td class="right">{{ $w->duration_days ? $w->duration_days . ' j' : '—' }}</td>
                    <td class="center">{{ $w->planned_start_date?->format('d/m/Y') ?? '—' }}</td>
                    <td class="center">{{ $w->planned_end_date?->format('d/m/Y') ?? '—' }}</td>
                    <td class="center">
                        {{ $w->actual_start_date?->format('d/m/Y') ?? ($w->is_start_overdue ? 'non démarré' : '—') }}
                    </td>
                    <td class="right {{ $classeRetard }}">
                        {{ $retard === null ? '—' : ($retard > 0 ? '+' . $retard . ' j' : 'à l’heure') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="muted" style="margin-top: 4px; font-size: 8px;">
        Un ouvrage non démarré voit son retard mesuré par rapport à la date du jour.
    </p>
@endif

@if($show('ouvrages'))
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
@endif

@if($show('planning'))
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
@endif

@if($show('jalons'))
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
@endif

@if($show('alertes') && $project->alerts->count())
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
