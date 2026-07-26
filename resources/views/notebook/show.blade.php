@extends('layouts.app')

@section('title', 'Notebook')

@section('content')
<div class="mb-3">
    <a href="{{ route('projects.show', $project) }}" class="text-decoration-none small text-secondary">&larr; {{ $project->name }}</a>
</div>

<h1 class="h5 fw-bold mb-1">Notebook</h1>
<p class="text-secondary small mb-4">
    Historique complet des transformations appliquées, dans l'ordre. Rejouable sur un nouveau fichier importé.
</p>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@if ($stepsByTable->isEmpty())
    <div class="df-card text-center py-5">
        <p class="text-secondary mb-0">Aucune étape enregistrée pour le moment.</p>
    </div>
@else
    @foreach ($stepsByTable as $tableId => $steps)
        @php $table = $steps->first()->table; @endphp
        <div class="df-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-bold mb-0">{{ $table->name }}</h2>
                @if ($tables->count() > 1)
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#replayModal{{ $tableId }}">
                        Rejouer sur...
                    </button>
                @endif
            </div>

            <ol class="list-group list-group-numbered mb-0">
                @foreach ($steps as $step)
                    <li class="list-group-item d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold small">{{ $step->label }}</div>
                            <div class="text-secondary small">
                                {{ $step->step_type->label() }}
                                @if ($step->rows_affected !== null)
                                    · {{ $step->rows_affected }} ligne(s) concernée(s)
                                @endif
                            </div>
                            @if ($step->rationale)
                                <div class="small mt-1 fst-italic">💬 {{ $step->rationale }}</div>
                            @endif
                        </div>
                        <div class="text-end">
                            <span class="badge {{ $step->status->badgeClass() }}">{{ $step->status->label() }}</span>
                            <div class="text-secondary small mt-1">{{ $step->applied_at->diffForHumans() }}</div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="modal fade" id="replayModal{{ $tableId }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('projects.notebook.replay', $project) }}">
                        @csrf
                        <input type="hidden" name="source_table_id" value="{{ $tableId }}">
                        <div class="modal-header">
                            <h5 class="modal-title">Rejouer le pipeline de « {{ $table->name }} »</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label">Table cible</label>
                            <select name="target_table_id" class="form-select" required>
                                <option value="">Choisir une table...</option>
                                @foreach ($tables as $candidate)
                                    @if ($candidate->id !== $tableId)
                                        <option value="{{ $candidate->id }}">{{ $candidate->dataset->name }} · {{ $candidate->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <div class="form-text">Les {{ $steps->count() }} étape(s) enregistrée(s) seront rejouées, dans l'ordre, sur cette table.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Rejouer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection
