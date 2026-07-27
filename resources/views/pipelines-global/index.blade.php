@extends('layouts.app')

@section('title', 'Pipelines')

@section('content')
<div class="mb-4">
    <h1 class="h5 fw-bold mb-0">Pipelines</h1>
    <p class="text-secondary small mb-0">Suggestions de nettoyage en attente et historique des étapes appliquées, à travers tous vos projets.</p>
</div>

<h2 class="h6 fw-bold mb-2">Suggestions en attente</h2>
@if ($pendingSuggestions->isEmpty())
    <div class="df-card mb-4">
        <p class="text-secondary small mb-0">Aucune suggestion en attente.</p>
    </div>
@else
    <div class="df-card p-0 mb-4">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr class="small text-secondary">
                    <th class="ps-3">Suggestion</th>
                    <th>Projet</th>
                    <th class="pe-3">Proposée</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pendingSuggestions as $suggestion)
                    <tr>
                        <td class="ps-3">{{ $suggestion->rationale }}</td>
                        <td>
                            @if ($suggestion->table?->dataset)
                                <a href="{{ route('projects.datasets.show', [$suggestion->project, $suggestion->table->dataset]) }}" class="text-secondary small text-decoration-none">
                                    {{ $suggestion->project->name }}
                                </a>
                            @else
                                <span class="text-secondary small">{{ $suggestion->project->name }}</span>
                            @endif
                        </td>
                        <td class="pe-3 small text-secondary text-nowrap">{{ $suggestion->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<h2 class="h6 fw-bold mb-2">Historique des étapes</h2>
@if ($recentSteps->isEmpty())
    <div class="df-card">
        <p class="text-secondary small mb-0">Aucune étape de pipeline appliquée pour le moment.</p>
    </div>
@else
    <div class="df-card p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr class="small text-secondary">
                    <th class="ps-3">Étape</th>
                    <th>Projet</th>
                    <th>Statut</th>
                    <th class="pe-3">Appliquée</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recentSteps as $step)
                    <tr>
                        <td class="ps-3">{{ $step->label ?? $step->step_type->label() }}</td>
                        <td>
                            @if ($step->table?->dataset)
                                <a href="{{ route('projects.datasets.show', [$step->project, $step->table->dataset]) }}" class="text-secondary small text-decoration-none">
                                    {{ $step->project->name }}
                                </a>
                            @else
                                <span class="text-secondary small">{{ $step->project->name }}</span>
                            @endif
                        </td>
                        <td><span class="badge {{ $step->status->badgeClass() }}">{{ $step->status->label() }}</span></td>
                        <td class="pe-3 small text-secondary text-nowrap">{{ $step->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
