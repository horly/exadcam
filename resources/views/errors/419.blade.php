@extends('layouts.auth')

@section('title', __('Session expirée'))

@section('content')
    <div class="form-heading">
        <span class="welcome-icon"><x-icon name="clock" /></span>
        <div class="form-eyebrow">{{ __('SESSION EXPIRÉE') }}</div>
        <h1>{{ __('Reprenons la connexion.') }}</h1>
        <p>{{ __('Cette page est restée ouverte trop longtemps. Ouvrez un nouveau formulaire pour vous connecter.') }}</p>
    </div>
    <a class="btn auth-submit" href="{{ route('login') }}">{{ __('Retour à la connexion') }}<x-icon name="arrow" /></a>
@endsection
