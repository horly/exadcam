@props(['compact' => false])
<details {{ $attributes->merge(['class' => 'language-switcher']) }}>
    <summary aria-label="{{ __('Choisir la langue') }}">
        <x-icon name="globe" />
        <span lang="{{ app()->getLocale() }}">{{ $compact ? strtoupper(app()->getLocale()) : config('localization.supported.'.app()->getLocale()) }}</span>
        <x-icon name="down" class="language-chevron" />
    </summary>
    <div class="language-menu">
        <p>{{ __('Langue de l’interface') }}</p>
        @foreach (config('localization.supported') as $locale => $label)
            <form method="POST" action="{{ route('locale.update', $locale) }}">
                @csrf
                <button type="submit" lang="{{ $locale }}" @class(['language-option', 'is-current' => app()->isLocale($locale)]) @if (app()->isLocale($locale)) aria-current="true" @endif>
                    <span class="language-code">{{ strtoupper($locale) }}</span>
                    <span>{{ $label }}</span>
                    @if (app()->isLocale($locale))<x-icon name="check" />@endif
                </button>
            </form>
        @endforeach
    </div>
</details>
