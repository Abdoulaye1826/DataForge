@extends('layouts.app')

@section('title', 'Paramètres')

@section('content')
<div class="mb-4">
    <h1 class="h5 fw-bold mb-0">Paramètres</h1>
    <p class="text-secondary small mb-0">Votre compte {{ config('app.name') }}.</p>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="df-card">
            <h2 class="h6 fw-bold mb-3">Profil</h2>

            <form method="POST" action="{{ route('settings.profile.update') }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="name" class="form-label">Nom</label>
                    <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', auth()->user()->name) }}" required>
                    @error('name')
                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Adresse e-mail</label>
                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email', auth()->user()->email) }}" required>
                    @error('email')
                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="df-card">
            <h2 class="h6 fw-bold mb-3">Mot de passe</h2>

            <form method="POST" action="{{ route('settings.password.update') }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="current_password" class="form-label">Mot de passe actuel</label>
                    <input id="current_password" type="password" class="form-control @error('current_password') is-invalid @enderror" name="current_password" autocomplete="current-password" required>
                    @error('current_password')
                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Nouveau mot de passe</label>
                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" autocomplete="new-password" required>
                    @error('password')
                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">Confirmer le nouveau mot de passe</label>
                    <input id="password_confirmation" type="password" class="form-control" name="password_confirmation" autocomplete="new-password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-sm">Changer le mot de passe</button>
            </form>
        </div>
    </div>
</div>
@endsection
