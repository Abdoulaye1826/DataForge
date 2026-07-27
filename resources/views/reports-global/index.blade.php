@extends('layouts.app')

@section('title', 'Rapports')

@section('content')
<div class="mb-4">
    <h1 class="h5 fw-bold mb-0">Rapports</h1>
    <p class="text-secondary small mb-0">Tous les rapports générés, à travers tous vos projets.</p>
</div>

@if ($reports->isEmpty())
    <div class="df-card text-center py-5">
        <p class="text-secondary mb-3">Aucun rapport généré pour le moment.</p>
        <a href="{{ route('projects.index') }}" class="btn btn-primary btn-sm">Aller à un projet pour en générer un</a>
    </div>
@else
    <div class="df-card p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr class="small text-secondary">
                    <th class="ps-3">Rapport</th>
                    <th>Projet</th>
                    <th>Taille</th>
                    <th>Généré</th>
                    <th class="pe-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reports as $report)
                    <tr>
                        <td class="ps-3 fw-semibold">{{ $report->title }}</td>
                        <td>
                            <a href="{{ route('projects.show', $report->project) }}" class="text-secondary small text-decoration-none">
                                {{ $report->project->name }}
                            </a>
                        </td>
                        <td class="small text-secondary">{{ number_format($report->size_bytes / 1024, 0, ',', ' ') }} Ko</td>
                        <td class="small text-secondary text-nowrap">{{ $report->created_at->diffForHumans() }}</td>
                        <td class="pe-3 text-end text-nowrap">
                            <a href="{{ route('projects.reports.download', [$report->project, $report]) }}" class="btn btn-sm btn-outline-secondary">
                                Télécharger
                            </a>
                            <form method="POST" action="{{ route('projects.reports.destroy', [$report->project, $report]) }}" class="d-inline" onsubmit="return confirm('Supprimer ce rapport ?');">
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
