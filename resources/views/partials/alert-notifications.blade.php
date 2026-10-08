@if($dashboard['can_map'])
<div id="cam-alert-toasts" class="cam-alert-toasts" aria-label="{{ __('notifications.title') }}"></div>
<script type="application/json" id="cam-notification-config">{!! \Illuminate\Support\Js::encode(['user' => auth()->id(), 'url' => route('dashboard.alerts.recent'), 'locale' => app()->getLocale(), 'labels' => trans('notifications')]) !!}</script>
@push('styles')<link rel="stylesheet" href="{{ asset('css/alert-notifications.css') }}?v=cam-alerts-map-20261008">@endpush
@push('scripts')<script type="module" src="{{ asset('js/alert-notifications.mjs') }}?v=cam-alerts-map-20261008"></script>@endpush
@endif
