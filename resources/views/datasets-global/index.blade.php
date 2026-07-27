@extends('layouts.app')

@section('title', 'Datasets')

@section('content')
<div class="mb-4">
    <h1 class="h5 fw-bold mb-0">Datasets</h1>
    <p class="text-secondary small mb-0">Tous vos jeux de données, à travers tous vos projets.</p>
</div>

@if ($datasets->isEmpty())
    <div class="df-card text-center py-5">
        <p class="text-secondary mb-3">Aucun dataset pour le moment.</p>
        <a href="{{ route('projects.index') }}" class="btn btn-primary btn-sm">Créer un projet pour en importer un</a>
    </div>
@else
    <div class="df-card p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr class="small text-secondary">
                    <th class="ps-3">Dataset</th>
                    <th>Projet</th>
                    <th>Format</th>
                    <th>Statut</th>
                    <th>Tables</th>
                    <th class="pe-3">Importé</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($datasets as $dataset)
                    <tr>
                        <td class="ps-3">
                            <a href="{{ route('projects.datasets.show', [$dataset->project, $dataset]) }}" class="fw-semibold text-decoration-none">
                                {{ $dataset->name }}
                            </a>
                        </td>
                        <td>
                            <a href="{{ route('projects.show', $dataset->project) }}" class="text-secondary small text-decoration-none">
                                {{ $dataset->project->name }}
                            </a>
                        </td>
                        <td><span class="small">{{ $dataset->format->label() }}</span></td>
                        <td><span class="badge {{ $dataset->status->badgeClass() }}">{{ $dataset->status->label() }}</span></td>
                        <td><span class="small text-secondary">{{ $dataset->tables_count }}</span></td>
                        <td class="pe-3"><span class="small text-secondary text-nowrap">{{ $dataset->created_at->diffForHumans() }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
