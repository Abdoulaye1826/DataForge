@extends('layouts.guest')

@section('content')
<h1 class="h5 fw-bold mb-1">Mot de passe oublié</h1>
<p class="text-secondary mb-4">Recevez un lien de réinitialisation par e-mail.</p>

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
