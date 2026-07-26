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
    <div class="df-card">
        <form method="POST" action="{{ route('projects.datasets.import', $project) }}" enctype="multipart/form-data">
            @csrf
            <div class="df-dropzone" data-dropzone>
                <input id="import-files-inline" type="file" name="files[]" class="d-none @error('files') is-invalid @enderror @error('files.*') is-invalid @enderror" multiple required
                       accept=".csv,.xlsx,.xls,.json,.parquet" data-dropzone-input>
                <div class="df-dropzone-idle" data-dropzone-idle>
                    <div class="df-dropzone-glyph">⇪</div>
                    <p class="mb-1"><strong>Glissez votre premier fichier ici</strong> ou cliquez pour parcourir</p>
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

            <div class="d-flex justify-content-between align-items-center mt-3">
                <p class="text-secondary small mb-0">Un fichier Excel multi-feuilles crée automatiquement une table par feuille.</p>
                <button type="submit" class="btn btn-primary btn-sm">Importer</button>
            </div>
        </form>

        <hr class="my-4">

        <p class="text-secondary small mb-3">Ou essayez avec un jeu de données d'exemple :</p>
        <div class="row g-2">
            @foreach (config('dataforge.demo_datasets') as $key => $demo)
                <div class="col-md-4">
                    <form method="POST" action="{{ route('projects.datasets.import-demo', $project) }}">
                        @csrf
                        <input type="hidden" name="dataset" value="{{ $key }}">
                        <button type="submit" class="df-demo-dataset-card">
                            <span class="fw-semibold small">{{ $demo['label'] }}</span>
                            <span class="text-secondary small">{{ $demo['description'] }}</span>
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
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

<div class="d-flex justify-content-between align-items-center mb-3 mt-4">
    <h2 class="h6 fw-bold mb-0">Connecteurs de base de données</h2>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#connectDatabaseModal">
        Connecter une base
    </button>
</div>

@error('connection')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror

@if ($project->connections->isEmpty())
    <div class="df-card">
        <p class="text-secondary small mb-0">
            Aucune connexion enregistrée. Connectez une base PostgreSQL ou MySQL pour importer une table directement, sans export de fichier.
        </p>
    </div>
@else
    <div class="df-card p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr class="small text-secondary">
                    <th class="ps-3">Nom</th>
                    <th>Moteur</th>
                    <th>Hôte</th>
                    <th>Base</th>
                    <th class="pe-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($project->connections as $connection)
                    <tr data-db-connection-row
                        data-db-connection-tables-url="{{ route('projects.database-connections.tables', [$project, $connection]) }}"
                        data-db-connection-import-url="{{ route('projects.database-connections.import', [$project, $connection]) }}">
                        <td class="ps-3 fw-semibold">{{ $connection->name }}</td>
                        <td>{{ $connection->driver->label() }}</td>
                        <td class="text-secondary small">{{ $connection->host }}:{{ $connection->port }}</td>
                        <td class="text-secondary small">{{ $connection->database }}</td>
                        <td class="pe-3 text-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-db-connection-browse>Voir les tables</button>
                            <form method="POST" action="{{ route('projects.database-connections.destroy', [$project, $connection]) }}" class="d-inline" onsubmit="return confirm('Supprimer cette connexion ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    <tr class="d-none">
                        <td colspan="5" class="ps-3 pe-3 pb-3">
                            <div data-db-connection-tables-list></div>
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

<div class="modal fade" id="importDatasetModal" tabindex="-1" aria-hidden="true"
    @if ($errors->any() && old('name') === null) data-reopen-modal-on-error @endif>
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

            <div class="modal-body pt-0">
                <hr class="my-0 mb-3">
                <p class="text-secondary small mb-3">Ou essayez avec un jeu de données d'exemple :</p>
                <div class="row g-2">
                    @foreach (config('dataforge.demo_datasets') as $key => $demo)
                        <div class="col-md-4">
                            <form method="POST" action="{{ route('projects.datasets.import-demo', $project) }}">
                                @csrf
                                <input type="hidden" name="dataset" value="{{ $key }}">
                                <button type="submit" class="df-demo-dataset-card">
                                    <span class="fw-semibold small">{{ $demo['label'] }}</span>
                                    <span class="text-secondary small">{{ $demo['description'] }}</span>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="connectDatabaseModal" tabindex="-1" aria-hidden="true"
    @if (old('driver') !== null) data-reopen-modal-on-error @endif>
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('projects.database-connections.store', $project) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Connecter une base de données</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary small">
                        La connexion est testée avant d'être enregistrée. Le mot de passe est chiffré et jamais réaffiché.
                    </p>

                    <div class="mb-3">
                        <label for="db-name" class="form-label">Nom de la connexion</label>
                        <input id="db-name" type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" placeholder="Ex. Entrepôt ventes (prod)" required>
                        @error('name')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="db-driver" class="form-label">Moteur</label>
                            <select id="db-driver" name="driver" class="form-select @error('driver') is-invalid @enderror" data-db-driver-select required>
                                <option value="">—</option>
                                @foreach (\App\Enums\DatabaseDriver::cases() as $driver)
                                    <option value="{{ $driver->value }}" {{ old('driver') === $driver->value ? 'selected' : '' }}>{{ $driver->label() }}</option>
                                @endforeach
                            </select>
                            @error('driver')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="db-port" class="form-label">Port</label>
                            <input id="db-port" type="number" name="port" class="form-control @error('port') is-invalid @enderror"
                                   value="{{ old('port') }}" data-db-port-input required>
                            @error('port')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label for="db-host" class="form-label">Hôte</label>
                        <input id="db-host" type="text" name="host" class="form-control @error('host') is-invalid @enderror"
                               value="{{ old('host') }}" placeholder="Ex. 127.0.0.1" required>
                        @error('host')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="db-database" class="form-label">Base de données</label>
                        <input id="db-database" type="text" name="database" class="form-control @error('database') is-invalid @enderror"
                               value="{{ old('database') }}" required>
                        @error('database')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="db-username" class="form-label">Utilisateur</label>
                            <input id="db-username" type="text" name="username" class="form-control @error('username') is-invalid @enderror"
                                   value="{{ old('username') }}" required>
                            @error('username')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="db-password" class="form-label">Mot de passe</label>
                            <input id="db-password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                            @error('password')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Tester &amp; connecter</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
