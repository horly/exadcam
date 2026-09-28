@php
 $platform = auth()->user()->isSuperadmin();
 $canManage = \App\Support\FleetAccess::allows(auth()->user(), \App\Models\User::PERMISSION_DASHCAMS_MANAGE);
 $canVideo = \App\Support\FleetAccess::allows(auth()->user(), \App\Models\User::PERMISSION_VIDEO_VIEW);
@endphp
<div class="table-responsive users-table-scroll" tabindex="0" aria-label="{{ __('dashcams.title') }}">
<table class="table align-middle users-table dashcam-table"><thead><tr>
    @foreach(['id' => '#', 'name' => __('dashcams.name'), ...($platform ? ['model' => __('dashcams.model'), 'imei' => 'IMEI'] : [])] as $column => $label)
    <th scope="col" aria-sort="{{ $sort === $column ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}"><button type="button" data-sort="{{ $column }}">{{ $label }}<span aria-hidden="true">{{ $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
    @endforeach
    <th scope="col">{{ __('dashcams.vehicle') }} / {{ __('dashcams.fleet') }}</th><th scope="col">{{ __('dashcams.state') }}</th>
    <th scope="col" aria-sort="{{ $sort === 'last_seen_at' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}"><button type="button" data-sort="last_seen_at">{{ __('dashcams.last_contact') }}<span aria-hidden="true">↕</span></button></th><th scope="col" class="text-end">{{ __('dashcams.actions') }}</th>
</tr></thead><tbody>
@forelse($dashcams as $dashcam)
    @php($online = $dashcam->enabled && $dashcam->last_seen_at?->gte(now()->subMinutes(3)))
    <tr>
        <td class="users-row-number">{{ $dashcams->firstItem() + $loop->index }}</td>
        <td><div class="users-identity"><span class="users-avatar"><x-icon name="camera" /></span><div><strong>{{ $platform ? $dashcam->name : ($dashcam->vehicle?->name ?? __('dashcams.title')) }}</strong><small>{{ $dashcam->channels }} {{ __('dashcams.channels') }}@if($platform) · {{ $dashcam->frame_rate }} {{ __('dashcams.frame_rate') }}@endif</small></div></div></td>
        @if($platform)<td><span class="users-role">{{ $dashcam->model ?? __('dashcams.unknown_model') }}</span>@if($platform)<small>{{ $dashcam->transport }} · JT808 {{ $dashcam->protocol_version ?? '—' }}</small>@endif</td>@endif
        @if($platform)<td><code>{{ $dashcam->imei }}</code>@if($dashcam->isSmartVision())<small>SIM ID · {{ $dashcam->terminal_id_2013 }}</small>@endif</td>@endif
        <td class="users-fleet-cell">@if($dashcam->vehicle)<strong>{{ $dashcam->vehicle->name }}</strong><small>@if($dashcam->vehicle->registration_number){{ $dashcam->vehicle->registration_number }} · @endif{{ $dashcam->vehicle->fleet->name }}</small>@else<span class="dashcam-unassigned" title="{{ __('dashcams.unassigned_hint') }}">{{ __('dashcams.unassigned') }}</span>@endif</td>
        <td><span class="users-status {{ $online ? 'is-active' : '' }}"><i></i>{{ __('dashcams.'.(!$dashcam->enabled ? 'disabled' : ($online ? 'online' : 'offline_short'))) }}</span></td>
        <td>{{ $dashcam->last_seen_at?->timezone('Africa/Kinshasa')->format('d/m/Y H:i:s') ?? '—' }}@if($platform)<small>{{ __('dashcams.observed') }} : JT808 {{ $dashcam->last_protocol ?? '—' }}</small>@endif</td>
        <td><div class="dashcam-actions">@if($canVideo)<select class="form-select form-select-sm" data-camera-channel="{{ $dashcam->id }}" aria-label="{{ __('dashcams.channel') }} — {{ $platform ? $dashcam->name : ($dashcam->vehicle?->name ?? __('dashcams.title')) }}">@foreach(range(1,$dashcam->channels) as $channel)<option value="{{ $channel }}">CH{{ $channel }}</option>@endforeach</select><button type="button" class="btn btn-primary users-primary" data-dashcam-live="{{ $dashcam->id }}" @disabled(!$dashcam->enabled)>{{ __('dashcams.live') }}</button>@endif @if($canManage)<button type="button" class="users-icon-button" data-dashcam-edit="{{ $dashcam->id }}" aria-label="{{ __('dashcams.edit') }} — {{ $platform ? $dashcam->name : ($dashcam->vehicle?->name ?? __('dashcams.title')) }}" title="{{ __('dashcams.edit') }}"><x-icon name="edit" /></button><button type="button" class="users-icon-button" data-dashcam-toggle="{{ $dashcam->id }}" data-enabled="{{ $dashcam->enabled ? '1' : '0' }}" aria-label="{{ __('dashcams.'.($dashcam->enabled ? 'disable' : 'enable')) }} — {{ $platform ? $dashcam->name : ($dashcam->vehicle?->name ?? __('dashcams.title')) }}" title="{{ __('dashcams.'.($dashcam->enabled ? 'disable' : 'enable')) }}"><x-icon name="{{ $dashcam->enabled ? 'lock' : 'shield' }}" /></button>@endif</div></td>
    </tr>
@empty
    <tr><td colspan="{{ $platform ? 8 : 6 }}" class="users-empty"><x-icon name="camera" /><strong>{{ __('dashcams.empty_result') }}</strong><span>{{ __('dashcams.empty_hint') }}</span></td></tr>
@endforelse
</tbody></table></div>
@include('users.pagination', ['paginator' => $dashcams])
