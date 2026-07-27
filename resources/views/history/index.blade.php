@extends('layouts.app')

@section('title', 'Historique')

@section('content')
<div class="mb-4">
    <h1 class="h5 fw-bold mb-0">Historique</h1>
    <p class="text-secondary small mb-0">Toute l'activité de vos projets, la plus récente en premier.</p>
</div>

@if ($activity->isEmpty())
    <div class="df-card text-center py-5">
        <p class="text-secondary mb-0">Aucune activité pour le moment.</p>
    </div>
@else
    <div class="df-card p-0 mb-3">
        <ul class="list-unstyled mb-0">
            @foreach ($activity as $entry)
                <li class="d-flex justify-content-between gap-3 py-2 px-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div>
                        <span class="small">{{ $entry->description }}</span>
                        @if ($entry->project)
                            <a href="{{ route('projects.show', $entry->project) }}" class="small text-secondary text-decoration-none ms-2">
                                {{ $entry->project->name }}
                            </a>
                        @endif
                    </div>
                    <span class="small text-secondary text-nowrap">{{ $entry->created_at->diffForHumans() }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    {{ $activity->links() }}
@endif
@endsection
