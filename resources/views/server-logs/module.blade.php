@if(auth()->user()->isSuperadmin())
<section id="server-logs-module" class="panel server-logs-panel" data-module-view="server-logs" data-module-title="{{ __('server_logs.title') }}" data-module-description="{{ __('server_logs.description') }}" hidden>
    <div class="server-logs-toolbar">
        <div class="server-logs-sources" role="group" aria-label="{{ __('server_logs.sources') }}">
            @foreach(['gps' => 'signal', 'video' => 'camera', 'audio' => 'signal', 'recordings' => 'clock', 'laravel' => 'report'] as $source => $icon)
                <button type="button" data-log-source="{{ $source }}" class="server-log-button {{ $source === 'gps' ? 'active' : '' }}" aria-pressed="{{ $source === 'gps' ? 'true' : 'false' }}"><x-icon :name="$icon" />{{ __('server_logs.'.$source) }}</button>
            @endforeach
        </div>
        <div class="server-logs-actions">
            <label class="server-logs-select" for="server-logs-lines">{{ __('server_logs.lines') }}<select id="server-logs-lines" class="form-select">@foreach([100,300,600,1000] as $lines)<option value="{{ $lines }}" @selected($lines === 300)>{{ $lines }}</option>@endforeach</select></label>
            <button type="button" id="server-logs-refresh" class="server-log-button"><x-icon name="refresh" />{{ __('server_logs.refresh') }}</button>
            <button type="button" id="server-logs-pause" class="server-log-button" aria-pressed="false"><span data-pause-symbol aria-hidden="true">Ⅱ</span><span data-pause-label>{{ __('server_logs.pause') }}</span></button>
        </div>
    </div>
    <div class="server-logs-filters">
        <label class="users-search" for="server-logs-search"><x-icon name="search" /><input id="server-logs-search" type="search" maxlength="100" placeholder="{{ __('server_logs.search') }}" aria-label="{{ __('server_logs.search') }}"></label>
        <label class="server-logs-select" for="server-logs-level">{{ __('server_logs.level') }}<select id="server-logs-level" class="form-select"><option value="all">{{ __('server_logs.all') }}</option><option value="errors">{{ __('server_logs.errors') }}</option></select></label>
        <label class="server-logs-follow"><input type="checkbox" id="server-logs-follow" checked> {{ __('server_logs.follow') }}</label>
    </div>
    <div class="server-logs-status" role="status"><span id="server-logs-state" class="server-logs-state">{{ __('server_logs.loading') }}</span><span id="server-logs-meta"></span></div>
    <pre id="server-logs-output" class="server-logs-output" tabindex="0" aria-label="{{ __('server_logs.output') }}">{{ __('server_logs.loading') }}</pre>
    <p class="server-logs-note">{{ __('server_logs.hint') }}</p>
</section>
@push('styles')<link rel="stylesheet" href="{{ asset('css/server-logs.css') }}?v=server-logs-1">@endpush
@push('scripts')
<script id="server-logs-config" type="application/json">{!! \Illuminate\Support\Js::encode(['url' => route('server-logs.content'), 'locale' => app()->getLocale(), 'labels' => trans('server_logs')]) !!}</script>
<script src="{{ asset('js/server-logs.js') }}?v=server-logs-1" defer></script>
@endpush
@endif
