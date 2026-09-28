@foreach(['fleets' => 'fleet', 'vehicles' => 'vehicle', 'departments' => 'department'] as $kind => $singular)
<section data-registry-module="{{ $kind }}" data-module-view="{{ $kind }}" data-module-title="{{ __('dashcams.'.$kind) }}" data-module-description="{{ __('dashcams.'.$singular.'_description') }}" lang="{{ app()->getLocale() }}" hidden>
@if(\App\Support\FleetAccess::registry(auth()->user(), $kind))
    <div class="alert" data-registry-notice role="status" aria-live="polite" hidden></div>
    <div class="panel users-list-panel">
        <div class="users-filters registry-filters"><label class="users-search" for="registry-{{ $kind }}-search"><x-icon name="search" /><input id="registry-{{ $kind }}-search" type="search" maxlength="100" data-registry-search placeholder="{{ __('dashcams.registry_search') }}" aria-label="{{ __('dashcams.registry_search') }}"></label><div class="users-page-size"><label for="registry-{{ $kind }}-size">{{ __('dashcams.show') }}</label><select id="registry-{{ $kind }}-size" data-registry-size class="form-select form-select-sm">@foreach([5,10,25,50] as $size)<option @selected($size === 10)>{{ $size }}</option>@endforeach</select></div><button type="button" data-registry-refresh class="users-icon-button" aria-label="{{ __('dashcams.refresh') }}"><x-icon name="refresh" /></button>@if(\App\Support\FleetAccess::createRegistry(auth()->user(), $kind))<button type="button" data-registry-create class="btn btn-primary users-primary"><x-icon name="plus" />{{ __('dashcams.new_'.$singular) }}</button>@endif</div>
        <div data-registry-table><p class="users-loading">{{ __('dashcams.loading') }}</p></div>
    </div>
@else
    <div class="panel p-4">{{ __('dashcams.no_access') }}</div>
@endif
</section>
@if(\App\Support\FleetAccess::registry(auth()->user(), $kind, true))
<div class="modal fade users-modal" id="registry-{{ $kind }}-modal" tabindex="-1" aria-labelledby="registry-{{ $kind }}-title" aria-hidden="true" data-bs-backdrop="static"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><span class="users-modal-icon"><x-icon name="{{ match ($kind) { 'fleets' => 'fleets', 'departments' => 'departments', default => 'truck' } }}" /></span><h2 id="registry-{{ $kind }}-title" class="modal-title" data-registry-title></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('dashcams.close') }}"></button></div>
    <form data-registry-form novalidate>@csrf
        <div class="modal-body"><div data-registry-error class="alert alert-danger" role="alert" hidden></div><p class="users-required-note">{{ __('dashcams.required_note') }}</p><div class="row g-3">
        @if($kind !== 'fleets')
            <div class="col-12" @if(!auth()->user()->isSuperadmin()) hidden @endif><label class="form-label" for="registry-{{ $singular }}-fleet">{{ __('dashcams.fleet') }} *</label><select id="registry-{{ $singular }}-fleet" name="fleet_id" class="form-select" required data-searchable-database data-search-placeholder="{{ __('dashcams.search_fleet') }}" data-no-results="{{ __('dashcams.no_option_match') }}" aria-describedby="registry-{{ $kind }}-fleet_id-error"><option value="">{{ __('dashcams.choose_fleet') }}</option></select><div id="registry-{{ $kind }}-fleet_id-error" class="invalid-feedback" data-registry-field="fleet_id"></div></div>
        @endif
        @if($kind === 'vehicles')
            <div class="col-12"><label class="form-label" for="registry-vehicle-department">{{ __('dashcams.department') }} <span class="text-muted">({{ __('dashcams.optional') }})</span></label><select id="registry-vehicle-department" name="department_id" class="form-select" data-searchable-database data-search-placeholder="{{ __('dashcams.search_department') }}" data-no-results="{{ __('dashcams.no_option_match') }}" aria-describedby="registry-vehicles-department_id-error registry-vehicle-department-hint"><option value="">{{ __('dashcams.no_department') }}</option></select><div id="registry-vehicles-department_id-error" class="invalid-feedback" data-registry-field="department_id"></div><p id="registry-vehicle-department-hint" class="form-text" data-department-hint>{{ __('dashcams.department_hint') }}</p></div>
        @endif
        @if($kind === 'departments')
            <p class="form-text mb-0">{{ __('dashcams.department_naming_hint') }}</p>
        @endif
        @foreach((match ($kind) { 'fleets' => ['name' => [255,true], 'code' => [32,true], 'description' => [2000,false]], 'departments' => ['name' => [255,true], 'code' => [50,false], 'description' => [2000,false]], default => ['name' => [255,true], 'registration_number' => [40,false], 'brand' => [80,false], 'model' => [80,false]] }) as $field => [$max,$required])
            <div class="{{ $field === 'description' ? 'col-12' : 'col-md-6' }}"><label class="form-label" for="registry-{{ $kind }}-{{ $field }}">{{ __('dashcams.'.($field === 'model' ? 'vehicle_model' : ($field === 'description' ? 'description_field' : $field))) }} {{ $required ? '*' : '' }}</label><input id="registry-{{ $kind }}-{{ $field }}" name="{{ $field }}" class="form-control" maxlength="{{ $max }}" @required($required) aria-describedby="registry-{{ $kind }}-{{ $field }}-error"><div id="registry-{{ $kind }}-{{ $field }}-error" class="invalid-feedback" data-registry-field="{{ $field }}"></div></div>
        @endforeach
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('dashcams.cancel') }}</button><button type="submit" class="btn btn-primary users-primary">{{ __('dashcams.save') }}</button></div>
    </form>
</div></div></div>
@endif
@endforeach
@if(\App\Support\FleetAccess::browse(auth()->user()))
@push('scripts')<script src="{{ asset('js/fleet-registry.js') }}?v=fleet-access-1" defer></script>@endpush
@endif
