@extends('layouts.guest')

@section('title', 'Confirmer le mot de passe')

@section('content')
<h1 class="df-auth-title">Confirmer le mot de passe</h1>
<p class="df-auth-subtitle">Merci de confirmer votre mot de passe avant de continuer.</p>

<form method="POST" action="{{ route('password.confirm') }}">
    @csrf

    <div class="mb-3">
        <label for="password" class="form-label">Mot de passe</label>
        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
        @error('password')
            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <button type="submit" class="btn btn-primary w-100 mb-3">Confirmer</button>

    @if (Route::has('password.request'))
        <div class="text-center">
            <a class="small text-secondary" href="{{ route('password.request') }}">Mot de passe oublié ?</a>
        </div>
    @endif
</form>
@endsection
