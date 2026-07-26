@extends('layouts.app')

@section('title', 'Dashboards')

@section('content')
<div class="mb-3">
    <a href="{{ route('projects.show', $project) }}" class="text-decoration-none small text-secondary">&larr; {{ $project->name }}</a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h5 fw-bold mb-0">Dashboards</h1>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newDashboardModal">
        Nouveau dashboard
    </button>
</div>

@if ($dashboards->isEmpty())
    <div class="df-card text-center py-5">
        <p class="text-secondary mb-3">Aucun dashboard pour le moment.</p>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newDashboardModal">
            Créer mon premier dashboard
        </button>
    </div>
@else
    <div class="row g-3">
        @foreach ($dashboards as $dashboard)
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('projects.dashboards.show', [$project, $dashboard]) }}" class="df-card d-block text-decoration-none text-reset">
                    <h2 class="h6 fw-bold mb-1">{{ $dashboard->name }}</h2>
                    <p class="text-secondary small mb-0">{{ $dashboard->widgets()->count() }} widget(s)</p>
                </a>
            </div>
        @endforeach
    </div>
@endif

<div class="modal fade" id="newDashboardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('projects.dashboards.store', $project) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nouveau dashboard</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Nom</label>
                    <input type="text" name="name" class="form-control" required autofocus>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
