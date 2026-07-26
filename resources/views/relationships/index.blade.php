@extends('layouts.app')

@section('title', "Relations · {$project->name}")

@section('content')
<div class="mb-3">
    <a href="{{ route('projects.show', $project) }}" class="text-decoration-none small text-secondary">
        &larr; {{ $project->name }}
    </a>
</div>

<h1 class="h5 fw-bold mb-1">Relations entre tables</h1>
<p class="text-secondary small mb-4">
    Jointures suggérées automatiquement à partir des noms de colonnes et des valeurs communes, entre toutes les tables du projet (feuilles d'un même fichier ou fichiers importés séparément). Rien n'est appliqué sans votre confirmation.
</p>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@if ($relationships->isEmpty())
    <div class="df-card text-center py-5">
        <p class="text-secondary mb-0">Aucune relation détectée entre les tables de ce projet.</p>
    </div>
@else
    <div class="df-card p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr class="small text-secondary">
                    <th class="ps-3">Table source</th>
                    <th>Colonne</th>
                    <th></th>
                    <th>Table cible</th>
                    <th>Colonne</th>
                    <th>Type</th>
                    <th>Confiance</th>
                    <th>Statut</th>
                    <th class="pe-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($relationships as $relationship)
                    <tr>
                        <td class="ps-3">
                            <div class="fw-semibold">{{ $relationship->sourceTable->name }}</div>
                            <div class="text-secondary small">{{ $relationship->sourceTable->dataset->name }}</div>
                        </td>
                        <td><code>{{ $relationship->sourceColumn->name }}</code></td>
                        <td class="text-secondary">&rarr;</td>
                        <td>
                            <div class="fw-semibold">{{ $relationship->targetTable->name }}</div>
                            <div class="text-secondary small">{{ $relationship->targetTable->dataset->name }}</div>
                        </td>
                        <td><code>{{ $relationship->targetColumn->name }}</code></td>
                        <td><span class="badge text-bg-light border">{{ $relationship->relationship_type->label() }}</span></td>
                        <td>{{ number_format($relationship->confidence_score * 100, 0) }}%</td>
                        <td><span class="badge {{ $relationship->status->badgeClass() }}">{{ $relationship->status->label() }}</span></td>
                        <td class="pe-3 text-end">
                            @if ($relationship->status->value === 'suggested')
                                <form method="POST" action="{{ route('projects.relationships.confirm', [$project, $relationship]) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success">Confirmer</button>
                                </form>
                                <form method="POST" action="{{ route('projects.relationships.reject', [$project, $relationship]) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Rejeter</button>
                                </form>
                            @elseif ($relationship->status->value === 'confirmed')
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#joinModal{{ $relationship->id }}">
                                    Joindre
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @foreach ($relationships->where('status', \App\Enums\RelationshipStatus::Confirmed) as $relationship)
        <div class="modal fade" id="joinModal{{ $relationship->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('projects.relationships.join', [$project, $relationship]) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Joindre {{ $relationship->sourceTable->name }} et {{ $relationship->targetTable->name }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-secondary small">
                                Sur <code>{{ $relationship->sourceColumn->name }}</code> = <code>{{ $relationship->targetColumn->name }}</code>.
                                Le résultat devient une nouvelle table analysable (qualité, EDA, insights, graphiques générés automatiquement).
                            </p>
                            <label class="form-label">Type de jointure</label>
                            <select name="join_type" class="form-select" required>
                                <option value="inner">Inner (seulement les lignes qui correspondent des deux côtés)</option>
                                <option value="left" selected>Left (toutes les lignes de {{ $relationship->sourceTable->name }})</option>
                                <option value="right">Right (toutes les lignes de {{ $relationship->targetTable->name }})</option>
                                <option value="outer">Outer (toutes les lignes des deux côtés)</option>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Joindre</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection
