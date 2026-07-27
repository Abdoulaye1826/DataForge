@extends('layouts.guest')

@section('title', 'Mot de passe oublié')

@section('content')
<h1 class="df-auth-title">Mot de passe oublié</h1>
<p class="df-auth-subtitle">Indiquez votre e-mail, nous vous envoyons un lien de réinitialisation.</p>

@if (session('status'))
    <div class="alert alert-success" role="alert">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('password.email') }}">
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label">Adresse e-mail</label>
        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
        @error('email')
            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <button type="submit" class="btn btn-primary w-100">Envoyer le lien de réinitialisation</button>
</form>
@endsection
