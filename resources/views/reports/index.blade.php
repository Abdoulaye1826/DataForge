@extends('layouts.app')

@section('title', "Rapports · {$project->name}")

@section('content')
<div class="mb-3">
    <a href="{{ route('projects.show', $project) }}" class="text-decoration-none small text-secondary">&larr; {{ $project->name }}</a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h5 fw-bold mb-1">Rapports</h1>
        <p class="text-secondary small mb-0">
            Un PDF avec la qualité, l'analyse exploratoire, les insights IA et les graphiques clés de chaque table du projet.
        </p>
    </div>
    <form method="POST" action="{{ route('projects.reports.store', $project) }}">
        @csrf
        <button type="submit" class="btn btn-primary btn-sm">Générer un rapport</button>
    </form>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@if ($reports->isEmpty())
    <div class="df-card text-center py-5">
        <p class="text-secondary mb-3">Aucun rapport généré pour le moment.</p>
        <form method="POST" action="{{ route('projects.reports.store', $project) }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">Générer mon premier rapport</button>
        </form>
    </div>
@else
    <div class="df-card p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr class="small text-secondary">
                    <th class="ps-3">Rapport</th>
                    <th>Contenu</th>
                    <th>Taille</th>
                    <th>Généré</th>
                    <th class="pe-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reports as $report)
                    <tr>
                        <td class="ps-3 fw-semibold">{{ $report->title }}</td>
                        <td class="small">
                            @foreach ($report->sections as $section)
                                <span class="badge text-bg-light border">{{ $section }}</span>
                            @endforeach
                        </td>
                        <td class="small text-secondary">{{ number_format($report->size_bytes / 1024, 0, ',', ' ') }} Ko</td>
                        <td class="small text-secondary">{{ $report->created_at->diffForHumans() }}</td>
                        <td class="pe-3 text-end">
                            <a href="{{ route('projects.reports.download', [$project, $report]) }}" class="btn btn-sm btn-outline-secondary">
                                Télécharger
                            </a>
                            <form method="POST" action="{{ route('projects.reports.destroy', [$project, $report]) }}" class="d-inline" onsubmit="return confirm('Supprimer ce rapport ?');">
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
@endsection
