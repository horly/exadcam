@php
    $navigationModules = [
        ['key' => 'reports', 'title' => 'Rapports', 'icon' => 'report', 'description' => 'Retrouvez les bilans de vos trajets, de vos alertes et de votre flotte.'],
    ];
@endphp

@foreach ($navigationModules as $module)
    <section class="panel module-placeholder" data-module-view="{{ $module['key'] }}"
        data-module-title="{{ __($module['title']) }}" data-module-description="{{ __($module['description']) }}"
        aria-labelledby="{{ $module['key'] }}-module-title" lang="{{ app()->getLocale() }}" hidden>
        <span class="module-placeholder-icon"><x-icon :name="$module['icon']" /></span>
        <span class="module-status">{{ __('EN PRÉPARATION') }}</span>
        <h2 id="{{ $module['key'] }}-module-title">{{ __($module['title']) }}</h2>
        <p>{{ __('La navigation est prête. Les fonctionnalités de ce module restent à développer.') }}</p>
        <a class="text-action" href="#overview" data-nav="overview">{{ __('Retour au tableau de bord') }}<x-icon name="arrow" /></a>
    </section>
@endforeach
