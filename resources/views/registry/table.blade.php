@php
    $singular = ['fleets' => 'fleet', 'vehicles' => 'vehicle', 'departments' => 'department'][$kind];
    $identityColumn = $kind === 'vehicles' ? 'registration_number' : 'code';
    $icon = ['fleets' => 'fleets', 'vehicles' => 'truck', 'departments' => 'departments'][$kind];
@endphp
<div class="table-responsive users-table-scroll" tabindex="0" aria-label="{{ __('dashcams.'.$kind) }}"><table class="table align-middle users-table registry-table"><thead><tr>
    @foreach(['id' => '#', 'name' => __('dashcams.name'), $identityColumn => __('dashcams.'.$identityColumn)] as $column => $label)
    <th scope="col" aria-sort="{{ $sort === $column ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}"><button type="button" data-sort="{{ $column }}">{{ $label }}<span aria-hidden="true">{{ $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
    @endforeach
    <th scope="col">{{ __('dashcams.'.($kind === 'fleets' ? 'vehicles' : 'fleet')) }}</th>
    @if($kind === 'vehicles')<th scope="col">{{ __('dashcams.department') }}</th>@endif
    <th scope="col">{{ __('dashcams.'.match ($kind) { 'fleets' => 'description_field', 'departments' => 'vehicles', default => 'title' }) }}</th><th scope="col" class="text-end">{{ __('dashcams.actions') }}</th>
</tr></thead><tbody>
@forelse($records as $record)
<tr><td class="users-row-number">{{ $records->firstItem() + $loop->index }}</td><td><div class="users-identity"><span class="users-avatar"><x-icon :name="$icon" /></span><strong>{{ $record->name }}</strong></div></td><td>{{ $record->{$identityColumn} ?: '—' }}</td><td>{{ $kind === 'fleets' ? $record->vehicles_count : $record->fleet->name }}</td>
@if($kind === 'vehicles')<td>{{ $record->department?->name ?: '—' }}</td>@endif
<td>{{ match ($kind) { 'fleets' => $record->description ?: '—', 'departments' => $record->vehicles_count, default => $record->dashcams_count } }}</td><td><div class="users-row-actions">@if($canManage)<button type="button" class="users-icon-button" data-registry-edit="{{ $record->id }}" aria-label="{{ __('dashcams.edit_'.$singular) }} — {{ $record->name }}"><x-icon name="edit" /></button>@endif</div></td></tr>
@empty
<tr><td colspan="{{ $kind === 'vehicles' ? 7 : 6 }}" class="users-empty"><x-icon :name="$icon" /><strong>{{ __('dashcams.no_records') }}</strong></td></tr>
@endforelse
</tbody></table></div>
@include('users.pagination', ['paginator' => $records])
