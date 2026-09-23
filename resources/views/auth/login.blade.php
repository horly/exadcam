@extends('layouts.auth')

@section('title', __('Connexion'))

@section('content')
    <div class="form-heading">
        <div class="form-eyebrow">{{ __('L’ESPACE DE VOTRE FLOTTE') }}</div>
        <h1>{{ __('Bienvenue dans') }}<br><span>{{ __('votre espace.') }}</span></h1>
        <p>{{ __('Connectez-vous pour retrouver votre flotte et vos vidéos embarquées.') }}</p>
    </div>

    <div class="form-section-label">{{ __('Connexion à votre compte') }}</div>

    @php($generalErrors = collect($errors->getMessages())->except(['email', 'password'])->flatten())
    <div class="auth-error" id="login-error" role="alert" tabindex="-1" @if ($generalErrors->isEmpty()) hidden @endif>
        <x-icon name="info" />
        <div><p data-login-error-message>{{ $generalErrors->implode(' ') }}</p>
            <a href="{{ route('login') }}" data-login-refresh hidden>{{ __('Actualiser le formulaire') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('login.store') }}" id="login-form"
        data-redirect="{{ route('dashboard') }}"
        data-network-error="{{ __('Impossible de joindre le serveur. Vérifiez votre connexion et réessayez.') }}"
        data-server-error="{{ __('La connexion est momentanément indisponible. Réessayez dans un instant.') }}"
        data-session-error="{{ __('Votre session a expiré. Actualisez le formulaire pour vous reconnecter.') }}"
        data-throttle-error="{{ __('Trop de tentatives. Patientez un instant avant de réessayer.') }}">
        @csrf
        <div class="auth-field">
            <label class="form-label" for="email">{{ __('Adresse e-mail') }}</label>
            <div class="auth-input-wrap">
                <x-icon name="mail" class="input-icon" />
                <input class="form-control @error('email') is-invalid @enderror" type="email" id="email" name="email" value="{{ is_string(old('email')) ? old('email') : '' }}" placeholder="{{ __('vous@entreprise.com') }}" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" maxlength="254" required aria-describedby="email-error" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                    data-required-message="{{ __('validation.required', ['attribute' => __('validation.attributes.email')]) }}"
                    data-max-message="{{ __('validation.max.string', ['attribute' => __('validation.attributes.email'), 'max' => 254]) }}"
                    data-email-message="{{ __('validation.email', ['attribute' => __('validation.attributes.email')]) }}">
            </div>
            <p class="auth-field-error" id="email-error" aria-live="polite" aria-atomic="true" @if (! $errors->has('email')) hidden @endif>{{ $errors->first('email') }}</p>
        </div>
        <div class="auth-field">
            <label class="form-label" for="password">{{ __('Mot de passe') }}</label>
            <div class="auth-input-wrap">
                <x-icon name="lock" class="input-icon" />
                <input class="form-control password-input @error('password') is-invalid @enderror" type="password" id="password" name="password" placeholder="{{ __('Votre mot de passe') }}" autocomplete="current-password" maxlength="4096" required aria-describedby="password-error" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                    data-required-message="{{ __('validation.required', ['attribute' => __('validation.attributes.password')]) }}"
                    data-max-message="{{ __('validation.max.string', ['attribute' => __('validation.attributes.password'), 'max' => 4096]) }}">
                <button type="button" class="password-toggle" aria-label="{{ __('Afficher le mot de passe') }}" aria-controls="password" aria-pressed="false" data-password-toggle data-show-label="{{ __('Afficher le mot de passe') }}" data-hide-label="{{ __('Masquer le mot de passe') }}" hidden><x-icon name="eye" data-eye-open /><x-icon name="eye-off" data-eye-closed class="d-none" /></button>
            </div>
            <p class="auth-field-error" id="password-error" aria-live="polite" aria-atomic="true" @if (! $errors->has('password')) hidden @endif>{{ $errors->first('password') }}</p>
        </div>
        <div class="auth-options">
            <div class="form-check"><input class="form-check-input" type="checkbox" name="remember" value="1" id="remember" @checked(old('remember') === '1')><label class="form-check-label" for="remember">{{ __('Rester connecté') }}</label></div>
            <button class="auth-text-button" type="button" data-bs-toggle="modal" data-bs-target="#access-help">{{ __('Besoin d’aide ?') }}</button>
        </div>
        <button class="btn auth-submit" type="submit" data-login-submit>
            <span data-submit-label>{{ __('Se connecter') }}</span><x-icon name="arrow" data-submit-arrow />
            <span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-submit-spinner></span>
        </button>
        <span class="visually-hidden" role="status" aria-live="polite" data-login-status data-loading-label="{{ __('Connexion en cours…') }}"></span>
    </form>
    <div class="auth-access-note"><span class="access-note-icon"><x-icon name="shield" /></span><p><strong>{{ __('Votre espace professionnel') }}</strong>{{ __('Pour obtenir un accès, contactez votre administrateur EXADCAM.') }}</p></div>
@endsection

@push('modals')
    <div class="modal fade" id="access-help" tabindex="-1" aria-labelledby="access-help-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="access-help-title">{{ __('Besoin d’aide pour vous connecter ?') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Fermer') }}"></button></div>
            <div class="modal-body"><p>{{ __('Si vous n’avez pas encore de compte ou si vous avez oublié votre mot de passe, contactez votre administrateur EXADCAM en lui indiquant votre adresse e-mail.') }}</p><p class="mb-0 text-secondary">{{ __('La récupération automatique par e-mail n’est pas encore disponible.') }}</p></div>
            <div class="modal-footer"><button type="button" class="btn auth-dialog-button" data-bs-dismiss="modal">{{ __('Compris') }}</button></div>
        </div></div>
    </div>
@endpush
