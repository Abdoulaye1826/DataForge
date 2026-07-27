@extends('layouts.app')

@section('title', "Rapport d'intelligence · {$report->dataset->name}")

@section('content')
@php
    $dataset = $report->dataset;
    $fileMeta = $dataset->import_meta['file_meta'] ?? [];
    $language = $dataset->import_meta['language'] ?? null;
@endphp

<div class="mb-3">
    <a href="{{ route('projects.datasets.show', [$project, $dataset]) }}" class="text-decoration-none small text-secondary">&larr; {{ $dataset->name }}</a>
</div>

<div class="mb-4">
    <div class="small fw-bold text-uppercase" style="letter-spacing:.06em; color: var(--df-ember-strong);">✦ Rapport d'intelligence</div>
    <h1 class="h4 fw-bold mb-1">{{ $dataset->name }}</h1>
    <p class="text-secondary small mb-0">Ce que DataForge a compris de ce dataset, sans aucune action exécutée automatiquement.</p>
</div>

{{-- 1. Compréhension du dataset --}}
<div class="df-card mb-4">
    <h2 class="h6 fw-bold mb-3">Compréhension</h2>
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="df-stat-label">Format</div>
            <div class="fw-semibold">{{ $dataset->format->label() }}</div>
        </div>
        <div class="col-6 col-md-3">
            <div class="df-stat-label">Poids</div>
            <div class="fw-semibold">{{ number_format($dataset->size_bytes / 1024, 0, ',', ' ') }} Ko</div>
        </div>
        <div class="col-6 col-md-3">
            <div class="df-stat-label">Encodage</div>
            <div class="fw-semibold">{{ $fileMeta['encoding'] ?? '—' }}</div>
        </div>
        <div class="col-6 col-md-3">
            <div class="df-stat-label">Séparateur</div>
            <div class="fw-semibold">{{ isset($fileMeta['delimiter']) ? "« {$fileMeta['delimiter']} »" : '—' }}</div>
        </div>
        <div class="col-6 col-md-3">
            <div class="df-stat-label">Feuilles Excel</div>
            <div class="fw-semibold">{{ $fileMeta['sheet_count'] ?? '—' }}</div>
        </div>
        <div class="col-6 col-md-3">
            <div class="df-stat-label">Langue détectée</div>
            <div class="fw-semibold">{{ $language ?? '—' }}</div>
        </div>
        <div class="col-6 col-md-3">
            <div class="df-stat-label">Tables</div>
            <div class="fw-semibold">{{ $report->tables->count() }}</div>
        </div>
        <div class="col-6 col-md-3">
            <div class="df-stat-label">Importé</div>
            <div class="fw-semibold">{{ $dataset->created_at->diffForHumans() }}</div>
        </div>
    </div>
</div>

{{-- 2. Domaine métier détecté --}}
<div class="df-card mb-4">
    <h2 class="h6 fw-bold mb-3">Domaine métier détecté</h2>
    @if ($dataset->detected_domain)
        <div class="d-flex align-items-center gap-3">
            <span class="badge text-bg-light border fs-6 fw-semibold px-3 py-2">{{ $dataset->detected_domain->label() }}</span>
            <div class="flex-fill" style="max-width: 240px">
                <div class="d-flex justify-content-between small text-secondary mb-1">
                    <span>Confiance</span>
                    <span>{{ round($dataset->detected_domain_confidence * 100) }}%</span>
                </div>
                <div class="df-mp-bar"><span style="width: {{ round($dataset->detected_domain_confidence * 100) }}%"></span></div>
            </div>
        </div>
        <p class="small text-secondary mt-2 mb-0">
            Détecté automatiquement à partir des noms de colonnes et des valeurs réelles — indépendant du domaine choisi manuellement pour le projet.
        </p>
    @else
        <p class="text-secondary small mb-0">Non déterminé pour le moment.</p>
    @endif
</div>

{{-- 3. Colonnes --}}
@foreach ($report->tables as $table)
    <div class="df-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 fw-bold mb-0">Colonnes — {{ $table->name }}</h2>
            <span class="text-secondary small">{{ number_format($table->row_count, 0, ',', ' ') }} lignes · {{ $table->column_count }} colonnes</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr class="small text-secondary">
                        <th>Colonne</th>
                        <th>Nom interprété</th>
                        <th>Catégorie métier</th>
                        <th>Confiance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($table->columns as $column)
                        <tr>
                            <td class="small text-secondary font-monospace">{{ $column->name }}</td>
                            <td class="fw-semibold" @if ($column->semantic_reasoning) title="{{ $column->semantic_reasoning }}" @endif>
                                {{ $column->semantic_label ?? '—' }}
                            </td>
                            <td>
                                @if ($column->business_category)
                                    <span class="badge {{ $column->business_category->badgeClass() }}">{{ $column->business_category->label() }}</span>
                                @else
                                    <span class="text-secondary small">—</span>
                                @endif
                            </td>
                            <td class="small text-secondary">
                                {{ $column->semantic_confidence !== null ? round($column->semantic_confidence * 100) . '%' : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endforeach

{{-- 4. Relations détectées --}}
<div class="df-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">Relations détectées</h2>
        <a href="{{ route('projects.relationships.index', $project) }}" class="small text-decoration-none">Gérer les relations &rarr;</a>
    </div>
    @if ($report->relationships->isEmpty())
        <p class="text-secondary small mb-0">Aucune relation détectée impliquant ce dataset.</p>
    @else
        <ul class="list-unstyled mb-0 small">
            @foreach ($report->relationships as $relationship)
                <li class="d-flex justify-content-between align-items-center gap-2 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <span>
                        <span class="fw-semibold">{{ $relationship->sourceTable?->name }}.{{ $relationship->sourceColumn?->name }}</span>
                        ⇄
                        <span class="fw-semibold">{{ $relationship->targetTable?->name }}.{{ $relationship->targetColumn?->name }}</span>
                        <span class="badge text-bg-light border ms-1">{{ $relationship->relationship_type->label() }}</span>
                    </span>
                    <span class="text-secondary text-nowrap">{{ round($relationship->confidence_score * 100) }}% confiance</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>

{{-- 5. Qualité --}}
<div class="df-card mb-4">
    <h2 class="h6 fw-bold mb-3">Audit qualité</h2>
    <div class="row g-3">
        @foreach ($report->tables as $table)
            @php $quality = $table->latestQualityReport; @endphp
            <div class="col-md-6">
                <div class="d-flex justify-content-between align-items-center border rounded-3 p-3">
                    <div>
                        <div class="fw-semibold">{{ $table->name }}</div>
                        @if ($quality)
                            <div class="small text-secondary">{{ $quality->narrative ?? 'Audit calculé.' }}</div>
                        @endif
                    </div>
                    @if ($quality)
                        <a href="{{ route('projects.datasets.tables.quality.show', [$project, $dataset, $table]) }}"
                           class="badge text-decoration-none {{ $quality->grade->badgeClass() }}">
                            {{ $quality->score }}/100
                        </a>
                    @else
                        <span class="text-secondary small">—</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- 6. Résumé intelligent --}}
<div class="df-card mb-4">
    <h2 class="h6 fw-bold mb-3">Résumé intelligent</h2>
    @if ($report->summaryInsights->isEmpty())
        <p class="text-secondary small mb-0">Aucun résumé généré pour le moment.</p>
    @else
        <ul class="mb-0 ps-3">
            @foreach ($report->summaryInsights as $insight)
                <li class="mb-1">{{ $insight->content }}</li>
            @endforeach
        </ul>
    @endif
</div>

{{-- 7. Suggestions --}}
<div class="df-card">
    <h2 class="h6 fw-bold mb-1">Suggestions</h2>
    <p class="small text-secondary mb-3">Rien n'est exécuté automatiquement — chaque suggestion attend votre décision.</p>

    @if ($report->recommendationInsights->isEmpty() && $report->pendingSuggestions->isEmpty())
        <p class="text-secondary small mb-0">Aucune suggestion pour le moment.</p>
    @else
        <ul class="list-unstyled mb-0 small">
            @foreach ($report->recommendationInsights as $insight)
                <li class="d-flex align-items-start gap-2 py-2 border-bottom">
                    <span>✅</span>
                    <span>{{ $insight->content }}</span>
                </li>
            @endforeach
            @foreach ($report->pendingSuggestions as $suggestion)
                <li class="d-flex align-items-start gap-2 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <span>🧠</span>
                    <span>
                        <span class="fw-semibold">{{ $suggestion->step_type->label() }}</span>
                        — {{ $suggestion->rationale }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
