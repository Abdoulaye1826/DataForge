@extends('layouts.guest')

@section('title', 'Connexion')

@section('content')
<h1 class="df-auth-title">Bon retour parmi nous</h1>
<p class="df-auth-subtitle">Connectez-vous pour retrouver vos projets et vos dashboards.</p>

<form method="POST" action="{{ route('login') }}">
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label">Adresse e-mail</label>
        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
        @error('email')
            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Mot de passe</label>
        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
        @error('password')
            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
        <label class="form-check-label" for="remember">Se souvenir de moi</label>
    </div>

    <button type="submit" class="btn btn-primary w-100 mb-3">Se connecter</button>

    @if (Route::has('password.request'))
        <div class="text-center">
            <a class="text-secondary small" href="{{ route('password.request') }}">Mot de passe oublié ?</a>
        </div>
    @endif

    @if (Route::has('register'))
        <div class="text-center mt-2">
            <span class="text-secondary small">Pas encore de compte ?</span>
            <a class="small" href="{{ route('register') }}">Créer un compte</a>
        </div>
    @endif
</form>
@endsection
