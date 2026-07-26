@extends('layouts.app')

@section('title', $project->name)

@section('content')
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="h5 fw-bold mb-0">{{ $project->name }}</h1>
            <span class="badge {{ $project->status->badgeClass() }}">{{ $project->status->label() }}</span>
        </div>
        @if ($project->description)
            <p class="text-secondary small mb-1">{{ $project->description }}</p>
        @endif
        @if ($project->businessContextLine())
            <span class="df-badge-context">{{ $project->businessContextLine() }}</span>
        @endif
    </div>

    <div class="d-flex gap-2">
        <a href="{{ route('projects.notebook.show', $project) }}" class="btn btn-outline-secondary btn-sm">
            Notebook
        </a>
        <a href="{{ route('projects.dashboards.index', $project) }}" class="btn btn-outline-secondary btn-sm">
            Dashboards
        </a>
        <a href="{{ route('projects.relationships.index', $project) }}" class="btn btn-outline-secondary btn-sm">
            Relations entre tables
        </a>
        <a href="{{ route('projects.reports.index', $project) }}" class="btn btn-outline-secondary btn-sm">
            Rapports
        </a>
        <a href="{{ route('projects.assistant.index', $project) }}" class="btn btn-primary btn-sm">
            Assistant IA
        </a>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editProjectModal">
            Modifier
        </button>
        <form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Supprimer définitivement ce projet et ses datasets ?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm">Supprimer</button>
        </form>
    </div>
</div>

<div class="df-card mb-4">
    <x-pipeline-stepper :project="$project" />
</div>

@if (session('errors_import'))
    <div class="alert alert-danger">
        <strong>Certains fichiers n'ont pas pu être importés :</strong>
        <ul class="mb-0 small">
            @foreach (session('errors_import') as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h6 fw-bold mb-0">Datasets</h2>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importDatasetModal">
        Importer des données
    </button>
</div>

@if ($project->datasets->isEmpty())
    <div class="df-card text-center py-5">
        <p class="text-secondary mb-3">Aucun dataset importé pour le moment.</p>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importDatasetModal">
            Importer mon premier fichier
        </button>
    </div>
@else
    <div class="df-card p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr class="small text-secondary">
                    <th class="ps-3">Nom</th>
                    <th>Format</th>
                    <th>Statut</th>
                    <th>Tables</th>
                    <th>Lignes</th>
                    <th>Importé</th>
                    <th class="pe-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($project->datasets as $dataset)
                    <tr>
                        <td class="ps-3">
                            <a href="{{ route('projects.datasets.show', [$project, $dataset]) }}" class="text-decoration-none fw-semibold">
                                {{ $dataset->name }}
                            </a>
                        </td>
                        <td>{{ $dataset->format->label() }}</td>
                        <td><span class="badge {{ $dataset->status->badgeClass() }}">{{ $dataset->status->label() }}</span></td>
                        <td>{{ $dataset->tables->count() }}</td>
                        <td>{{ number_format($dataset->tables->sum('row_count'), 0, ',', ' ') }}</td>
                        <td class="text-secondary small">{{ $dataset->created_at->diffForHumans() }}</td>
                        <td class="pe-3 text-end">
                            <form method="POST" action="{{ route('projects.datasets.destroy', [$project, $dataset]) }}" onsubmit="return confirm('Supprimer ce dataset ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="modal fade" id="editProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('projects.update', $project) }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Modifier le projet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-name" class="form-label">Nom du projet</label>
                        <input id="edit-name" type="text" name="name" class="form-control" value="{{ old('name', $project->name) }}" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-description" class="form-label">Description</label>
                        <textarea id="edit-description" name="description" class="form-control" rows="3">{{ old('description', $project->description) }}</textarea>
                    </div>

                    <hr class="my-3">
                    <p class="text-secondary small mb-3">Contexte métier <span class="text-secondary">(optionnel)</span></p>

                    <div class="mb-3">
                        <label for="edit-domain" class="form-label">Domaine</label>
                        <select id="edit-domain" name="domain" class="form-select" data-reveal-other="#edit-domain-other-field">
                            <option value="">—</option>
                            @foreach (\App\Enums\ProjectDomain::cases() as $domain)
                                <option value="{{ $domain->value }}" {{ old('domain', $project->domain?->value) === $domain->value ? 'selected' : '' }}>{{ $domain->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 {{ old('domain', $project->domain?->value) === 'other' ? '' : 'd-none' }}" id="edit-domain-other-field">
                        <input type="text" name="domain_other" class="form-control" placeholder="Précisez le domaine" value="{{ old('domain_other', $project->domain_other) }}">
                    </div>

                    <div class="mb-0">
                        <label for="edit-objective" class="form-label">Objectif</label>
                        <select id="edit-objective" name="objective" class="form-select" data-reveal-other="#edit-objective-other-field">
                            <option value="">—</option>
                            @foreach (\App\Enums\ProjectObjective::cases() as $objective)
                                <option value="{{ $objective->value }}" {{ old('objective', $project->objective?->value) === $objective->value ? 'selected' : '' }}>{{ $objective->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-0 mt-3 {{ old('objective', $project->objective?->value) === 'other' ? '' : 'd-none' }}" id="edit-objective-other-field">
                        <input type="text" name="objective_other" class="form-control" placeholder="Précisez l'objectif" value="{{ old('objective_other', $project->objective_other) }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="importDatasetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('projects.datasets.import', $project) }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Importer des données</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Fichier(s) CSV, Excel, JSON ou Parquet</label>

                    <div class="df-dropzone" data-dropzone>
                        <input id="import-files" type="file" name="files[]" class="d-none @error('files') is-invalid @enderror @error('files.*') is-invalid @enderror" multiple required
                               accept=".csv,.xlsx,.xls,.json,.parquet" data-dropzone-input>
                        <div class="df-dropzone-idle" data-dropzone-idle>
                            <div class="df-dropzone-glyph">⇪</div>
                            <p class="mb-1"><strong>Glissez vos fichiers ici</strong> ou cliquez pour parcourir</p>
                            <p class="text-secondary small mb-0">.csv · .xlsx · .json · .parquet</p>
                        </div>
                        <ul class="df-dropzone-files d-none list-unstyled mb-0" data-dropzone-list></ul>
                    </div>

                    @error('files')
                        <span class="invalid-feedback d-block">{{ $message }}</span>
                    @enderror
                    @error('files.*')
                        <span class="invalid-feedback d-block">{{ $message }}</span>
                    @enderror
                    <p class="text-secondary small mt-2 mb-0">
                        Un fichier Excel multi-feuilles crée automatiquement une table par feuille.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Importer</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($errors->any() && old('name') === null)
    <script>
        document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal('#importDatasetModal').show());
    </script>
@endif
@endsection
