<div class="modal fade users-modal" id="dashcam-form-modal" tabindex="-1" aria-labelledby="dashcam-form-title" aria-hidden="true" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
<div class="modal-header"><span class="users-modal-icon"><x-icon name="camera" /></span><h2 id="dashcam-form-title" class="modal-title">{{ __('dashcams.edit') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('dashcams.close') }}"></button></div>
<form id="dashcam-form" novalidate autocomplete="off">@csrf
<div class="modal-body"><div id="dashcam-form-alert" class="alert alert-danger" role="alert" hidden></div>
<div class="mb-3"><label for="dashcam-name" class="form-label">{{ __('dashcams.name') }} *</label><input id="dashcam-name" name="name" class="form-control" maxlength="100" required><div class="invalid-feedback" data-dashcam-error="name"></div></div>
<label class="form-label" for="dashcam-vehicle_id">{{ __('dashcams.vehicle') }} *</label><select id="dashcam-vehicle_id" name="vehicle_id" class="form-select" required data-searchable-database data-search-placeholder="{{ __('dashcams.search_vehicle') }}" data-no-results="{{ __('dashcams.no_option_match') }}"><option value="">{{ __('dashcams.choose_vehicle') }}</option></select><div class="invalid-feedback" data-dashcam-error="vehicle_id"></div>
<div id="dashcam-assignment-notice" hidden><p class="form-text" id="dashcam-assignment-message"></p></div>
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('dashcams.cancel') }}</button><button type="submit" class="btn btn-primary users-primary">{{ __('dashcams.save') }}</button></div>
</form></div></div></div>
