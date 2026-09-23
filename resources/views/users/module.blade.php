@can('viewAny', \App\Models\User::class)
<section id="users-module" data-module-view="users" data-module-title="{{ __('users.title') }}" data-module-description="{{ __('users.description') }}" lang="{{ app()->getLocale() }}" hidden>
    <div class="users-overview">
        <div class="users-stat"><span class="users-stat-icon"><x-icon name="users" /></span><div><strong data-users-stat="total">—</strong><span>{{ __('users.total_accounts') }}</span></div></div>
        <div class="users-stat"><span class="users-stat-icon green"><x-icon name="shield" /></span><div><strong data-users-stat="active">—</strong><span>{{ __('users.active_accounts') }}</span></div></div>
        @if (auth()->user()->isSuperadmin())<div class="users-stat"><span class="users-stat-icon"><x-icon name="lock" /></span><div><strong data-users-stat="admins">—</strong><span>{{ __('users.admin_accounts') }}</span></div></div>@endif
    </div>
    <div id="users-notice" class="users-notice" role="status" aria-live="polite" hidden></div>
    <div class="panel users-list-panel">
        <div class="users-toolbar">
            <div><h2>{{ __('users.directory') }}</h2><p>{{ __('users.directory_hint') }}</p></div>
            <button type="button" id="user-create" class="btn btn-primary users-primary"><x-icon name="plus" />{{ __('users.new_user') }}</button>
        </div>
        <div class="users-filters">
            <label class="users-search" for="users-search"><x-icon name="search" /><input type="search" id="users-search" maxlength="255" placeholder="{{ __('users.search_hint') }}" autocomplete="off" aria-label="{{ __('users.search') }}"></label>
            <div class="users-page-size"><label for="users-page-size">{{ __('users.show') }}</label><select id="users-page-size" class="form-select form-select-sm">@foreach ([5, 10, 25, 50] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach</select></div>
            <button type="button" id="users-refresh" class="users-icon-button" aria-label="{{ __('users.refresh') }}" title="{{ __('users.refresh') }}"><x-icon name="refresh" /></button>
        </div>
        <div id="users-list-error" class="alert alert-danger m-3" role="alert" hidden></div>
        <div id="users-table" aria-busy="true"><div class="users-loading" role="status"><span class="spinner-border spinner-border-sm"></span>{{ __('users.loading') }}</div></div>
    </div>
</section>

<div class="modal fade users-modal" id="user-form-modal" tabindex="-1" aria-labelledby="user-form-title" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content">
        <div class="modal-header"><span class="users-modal-icon"><x-icon name="users" /></span><div><p>{{ __('users.account_management') }}</p><h2 id="user-form-title" class="modal-title">{{ __('users.create_title') }}</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('users.close') }}"></button></div>
        <form id="user-form" novalidate autocomplete="off">
            @csrf
            <div class="modal-body">
                <div id="user-form-alert" class="alert alert-danger" role="alert" hidden></div>
                <div id="user-fleet-notice" class="alert alert-warning" role="status" hidden>{{ __('users.no_active_fleet') }}</div>
                <p class="users-required-note">{{ __('users.required_hint') }}</p>
                <div class="row g-3">
                    @foreach (['name' => 'text', 'email' => 'email'] as $field => $type)
                        <div class="col-md-6"><label for="user-{{ $field }}" class="form-label">{{ __('users.'.$field) }} <span aria-hidden="true">*</span></label><input id="user-{{ $field }}" name="{{ $field }}" type="{{ $type }}" class="form-control" required maxlength="{{ $field === 'email' ? 254 : 255 }}" autocomplete="{{ $field === 'email' ? 'off' : 'name' }}" aria-describedby="user-{{ $field }}-error"><div id="user-{{ $field }}-error" class="invalid-feedback" data-field-error="{{ $field }}"></div></div>
                    @endforeach
                    <div class="col-md-6"><label for="user-role" class="form-label">{{ __('users.role') }} <span aria-hidden="true">*</span></label><select id="user-role" name="role" class="form-select" required aria-describedby="user-role-error"><option value="user">{{ __('users.role_user') }}</option>@if (auth()->user()->isSuperadmin())<option value="admin">{{ __('users.role_admin') }}</option>@endif</select><div id="user-role-error" class="invalid-feedback" data-field-error="role"></div></div>
                    <div class="col-md-6"><label for="user-fleet" class="form-label">{{ __('users.fleet') }} <span aria-hidden="true">*</span></label><div class="users-fleet-picker"><input type="search" id="user-fleet-search" class="form-control form-control-sm mb-2" placeholder="{{ __('users.search_fleet') }}" aria-label="{{ __('users.search_fleet') }}" autocomplete="off"><select id="user-fleet" name="fleet_id" class="form-select" required aria-describedby="user-fleet-error"><option value="">{{ __('users.choose_fleet') }}</option></select></div><div id="user-fleet-error" class="invalid-feedback" data-field-error="fleet_id"></div></div>
                    <div class="col-md-6"><label for="user-phone" class="form-label">{{ __('users.phone') }}</label><input id="user-phone" name="phone" type="tel" class="form-control" maxlength="40" placeholder="{{ __('users.phone_placeholder') }}" aria-describedby="user-phone-error"><div id="user-phone-error" class="invalid-feedback" data-field-error="phone"></div></div>
                    <div class="col-md-6"><label for="user-address" class="form-label">{{ __('users.address') }}</label><input id="user-address" name="address" class="form-control" maxlength="255" aria-describedby="user-address-error"><div id="user-address-error" class="invalid-feedback" data-field-error="address"></div></div>
                    <div class="col-12"><div class="users-section-title">{{ __('users.security') }}</div><p id="user-password-hint" class="users-field-hint">{{ __('users.password_hint') }}</p></div>
                    @foreach (['password', 'password_confirmation'] as $field)
                        <div class="col-md-6"><label for="user-{{ $field }}" class="form-label">{{ __('users.'.$field) }}</label><div class="users-password-field"><input id="user-{{ $field }}" type="password" name="{{ $field }}" class="form-control" autocomplete="new-password" required aria-describedby="user-{{ $field }}-error user-password-hint"><button type="button" data-user-password="user-{{ $field }}" aria-label="{{ __('users.show_password') }}" aria-pressed="false"><x-icon name="eye" /></button></div><div id="user-{{ $field }}-error" class="invalid-feedback" data-field-error="{{ $field }}"></div></div>
                    @endforeach
                    <div class="col-12"><ul class="users-password-rules" aria-label="{{ __('users.password_hint') }}">@foreach (['min', 'mixed', 'number', 'symbol'] as $rule)<li data-user-password-rule="{{ $rule }}">{{ __('users.password_'.$rule) }}</li>@endforeach</ul></div>
                    <div class="col-12" id="user-permissions-panel"><div class="users-section-title"><x-icon name="shield" />{{ __('users.permissions_title') }}</div><p class="users-field-hint">{{ __('users.permissions_description') }}</p><div class="users-permissions">
                        @foreach (\App\Models\User::CLIENT_PERMISSIONS as $permission)
                            <label class="users-permission"><input type="checkbox" class="form-check-input" name="permissions[]" value="{{ $permission }}"><span><strong>{{ __('users.permission_'.str_replace('.', '_', $permission)) }}</strong><small>{{ __('users.permission_'.str_replace('.', '_', $permission).'_description') }}</small></span></label>
                        @endforeach
                    </div><div class="invalid-feedback" data-field-error="permissions" id="user-permissions-error"></div></div>
                    <div class="col-12" id="user-admin-permissions" hidden><div class="users-admin-note"><x-icon name="shield" />{{ __('users.admin_permissions') }}</div></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('users.cancel') }}</button><button type="submit" id="user-save" class="btn btn-primary users-primary">{{ __('users.create_button') }}</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade users-modal" id="user-history-modal" tabindex="-1" aria-labelledby="user-history-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content">
    <div class="modal-header"><span class="users-modal-icon"><x-icon name="clock" /></span><div><p>{{ __('users.history') }}</p><h2 id="user-history-title" class="modal-title"></h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('users.close') }}"></button></div>
    <div class="modal-body"><label class="users-search mb-3" for="user-history-search"><x-icon name="search" /><input type="search" id="user-history-search" maxlength="255" placeholder="{{ __('users.search') }}" aria-label="{{ __('users.search_history') }}"></label><div id="user-history-error" class="alert alert-danger" role="alert" hidden></div><div id="user-history-table"></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('users.close') }}</button></div>
</div></div></div>

<div class="modal fade users-modal" id="user-delete-modal" tabindex="-1" aria-labelledby="user-delete-title" aria-hidden="true" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><span class="users-modal-icon danger"><x-icon name="trash" /></span><h2 id="user-delete-title" class="modal-title">{{ __('users.delete_confirm_title') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('users.close') }}"></button></div>
    <div class="modal-body"><p id="user-delete-description"></p><p class="users-field-hint">{{ __('users.delete_hint') }}</p><div id="user-delete-error" class="alert alert-danger" role="alert" hidden></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('users.cancel') }}</button><button type="button" id="user-delete-confirm" class="btn btn-danger">{{ __('users.delete_confirm_submit') }}</button></div>
</div></div></div>

@push('scripts')
    <script id="users-config" type="application/json">{!! \Illuminate\Support\Js::encode(['url' => route('users.index'), 'optionsUrl' => route('users.options'), 'isPlatform' => auth()->user()->isSuperadmin(), 'strings' => trans('users')]) !!}</script>
    <script src="{{ asset('js/users.js') }}?v=users-1" defer></script>
@endpush
@endcan
