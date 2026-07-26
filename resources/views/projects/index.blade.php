@extends('layouts.app')

@section('title', 'Projets')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h5 fw-bold mb-0">Projets</h1>
        <p class="text-secondary small mb-0">Chaque analyse commence par un projet.</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createProjectModal">
        Nouveau projet
    </button>
</div>

@if ($projects->isEmpty())
    <div class="df-card text-center py-5">
        <p class="text-secondary mb-3">Vous n'avez pas encore de projet.</p>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createProjectModal">
            Créer mon premier projet
        </button>
    </div>
@else
    <div class="row g-3">
        @foreach ($projects as $project)
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('projects.show', $project) }}" class="df-card d-block text-decoration-none text-reset h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h2 class="h6 fw-bold mb-0">{{ $project->name }}</h2>
                        <span class="badge {{ $project->status->badgeClass() }}">{{ $project->status->label() }}</span>
                    </div>
                    <p class="text-secondary small mb-2">
                        {{ $project->description ? \Illuminate\Support\Str::limit($project->description, 100) : 'Aucune description.' }}
                    </p>
                    @if ($project->businessContextLine())
                        <p class="small mb-3">
                            <span class="df-badge-context">{{ $project->businessContextLine() }}</span>
                        </p>
                    @endif
                    <div class="d-flex justify-content-between text-secondary small">
                        <span>{{ $project->datasets_count }} dataset(s)</span>
                        <span>{{ $project->last_activity_at?->diffForHumans() ?? '—' }}</span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endif

<div class="modal fade" id="createProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('projects.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nouveau projet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nom du projet</label>
                        <input id="name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required autofocus>
                        @error('name')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description <span class="text-secondary">(optionnel)</span></label>
                        <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description') }}</textarea>
                        @error('description')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <hr class="my-3">
                    <p class="text-secondary small mb-3">
                        Contexte métier <span class="text-secondary">(optionnel, mais aide l'IA à mieux cibler ses analyses)</span>
                    </p>

                    <div class="mb-3">
                        <label for="domain" class="form-label">Domaine</label>
                        <select id="domain" name="domain" class="form-select" data-reveal-other="#domain-other-field">
                            <option value="">—</option>
                            @foreach (\App\Enums\ProjectDomain::cases() as $domain)
                                <option value="{{ $domain->value }}" {{ old('domain') === $domain->value ? 'selected' : '' }}>{{ $domain->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="domain-other-field">
                        <input type="text" name="domain_other" class="form-control" placeholder="Précisez le domaine" value="{{ old('domain_other') }}">
                    </div>

                    <div class="mb-0">
                        <label for="objective" class="form-label">Objectif</label>
                        <select id="objective" name="objective" class="form-select" data-reveal-other="#objective-other-field">
                            <option value="">—</option>
                            @foreach (\App\Enums\ProjectObjective::cases() as $objective)
                                <option value="{{ $objective->value }}" {{ old('objective') === $objective->value ? 'selected' : '' }}>{{ $objective->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-0 mt-3 d-none" id="objective-other-field">
                        <input type="text" name="objective_other" class="form-control" placeholder="Précisez l'objectif" value="{{ old('objective_other') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer le projet</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
