@extends('layouts.app')

@section('title', 'Assistant IA')

@section('content')
<div class="mb-3">
    <a href="{{ route('projects.show', $project) }}" class="text-decoration-none small text-secondary">&larr; {{ $project->name }}</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="row g-3" style="height: calc(100vh - 220px);">
    <div class="col-md-3 h-100">
        <div class="df-card h-100 d-flex flex-column p-0">
            <div class="p-3 border-bottom">
                <form method="POST" action="{{ route('projects.assistant.conversations.store', $project) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm w-100">+ Nouvelle conversation</button>
                </form>
            </div>
            <div class="flex-grow-1 overflow-auto">
                @forelse ($conversations as $conversation)
                    <a href="{{ route('projects.assistant.index', [$project, 'conversation' => $conversation->id]) }}"
                       class="d-block px-3 py-2 text-decoration-none border-bottom small {{ $active && $active->id === $conversation->id ? 'bg-light fw-semibold text-dark' : 'text-secondary' }}">
                        {{ $conversation->title }}
                    </a>
                @empty
                    <p class="text-secondary small p-3 mb-0">Aucune conversation.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-md-9 h-100">
        <div class="df-card h-100 d-flex flex-column p-0">
            @if (! $active)
                <div class="d-flex align-items-center justify-content-center h-100">
                    <p class="text-secondary mb-0">Créez une conversation pour commencer.</p>
                </div>
            @else
                <div class="flex-grow-1 overflow-auto p-3" id="assistant-thread">
                    @forelse ($active->messages as $message)
                        <div class="mb-3 d-flex {{ $message->role->value === 'user' ? 'justify-content-end' : 'justify-content-start' }}">
                            <div class="p-2 rounded-3 small {{ $message->role->value === 'user' ? 'text-bg-primary' : 'df-card' }}" style="max-width: 75%; white-space: pre-wrap;">
                                {{ $message->content }}
                            </div>
                        </div>
                    @empty
                        <p class="text-secondary small mb-0">
                            Posez une question sur vos données : « Quelles corrélations importantes ? », « Prépare un résumé exécutif », « Quels graphiques recommandes-tu ? »...
                        </p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('projects.assistant.messages.store', [$project, $active]) }}" class="border-top p-3 d-flex gap-2">
                    @csrf
                    <textarea name="content" class="form-control" rows="2" placeholder="Écrivez votre question..." required></textarea>
                    <button type="submit" class="btn btn-primary">Envoyer</button>
                </form>
            @endif
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const thread = document.getElementById('assistant-thread');
        if (thread) {
            thread.scrollTop = thread.scrollHeight;
        }
    });
</script>
@endsection
