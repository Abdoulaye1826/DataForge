@extends('layouts.guest')

@section('content')
<h1 class="h5 fw-bold mb-3">Vérifiez votre adresse e-mail</h1>

@if (session('resent'))
    <div class="alert alert-success" role="alert">
        Un nouveau lien de vérification vient de vous être envoyé par e-mail.
    </div>
@endif

<p class="text-secondary mb-0">
    Avant de continuer, merci de vérifier votre boîte mail : un lien de vérification vous y attend.
    Vous ne l'avez pas reçu ?
    <form class="d-inline" method="POST" action="{{ route('verification.resend') }}">
        @csrf
        <button type="submit" class="btn btn-link p-0 m-0 align-baseline">Cliquez ici pour en recevoir un autre</button>.
    </form>
</p>
@endsection
