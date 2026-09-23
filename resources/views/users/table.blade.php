<div class="table-responsive users-table-scroll" tabindex="0" aria-label="{{ __('users.title') }}">
    <table class="table align-middle users-table">
        <thead><tr>
            @foreach (['id' => '#', 'name' => __('users.name'), 'role' => __('users.role')] as $column => $label)
                <th scope="col" aria-sort="{{ $sort === $column ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}"><button type="button" data-sort="{{ $column }}">{{ $label }}<span aria-hidden="true">{{ $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
            @endforeach
            <th scope="col">{{ __('users.fleet') }}</th>
            @foreach (['email' => __('users.email'), 'phone' => __('users.phone'), 'status' => __('users.status')] as $column => $label)
                <th scope="col" aria-sort="{{ $sort === $column ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}"><button type="button" data-sort="{{ $column }}">{{ $label }}<span aria-hidden="true">{{ $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
            @endforeach
            <th scope="col" class="text-end">{{ __('users.actions') }}</th>
        </tr></thead>
        <tbody>
            @forelse ($users as $managedUser)
                <tr>
                    <td class="users-row-number">{{ $users->firstItem() + $loop->index }}</td>
                    <td><div class="users-identity"><span class="users-avatar">@if ($managedUser->profilePhotoUrl())<img src="{{ $managedUser->profilePhotoUrl() }}" alt="" width="36" height="36">@else{{ mb_strtoupper(mb_substr($managedUser->name, 0, 1)) }}@endif</span><strong>{{ $managedUser->name }}</strong></div></td>
                    <td><span class="users-role users-role-{{ $managedUser->role->value }}">{{ __('users.role_'.$managedUser->role->value) }}</span></td>
                    <td class="users-fleet-cell">{{ $managedUser->fleet?->name ?? ($managedUser->isSuperadmin() ? __('users.all_fleets') : __('users.no_fleet')) }}@if ($managedUser->fleet)<small>{{ $managedUser->fleet->code }}</small>@endif</td>
                    <td class="users-email">{{ $managedUser->email }}</td>
                    <td>{{ $managedUser->phone ?: '—' }}</td>
                    <td><span class="users-status {{ $managedUser->isActive() ? 'is-active' : '' }}"><i></i>{{ __('users.'.($managedUser->isActive() ? 'active' : 'disabled')) }}</span></td>
                    <td><div class="users-row-actions">
                        <button type="button" data-user-history="{{ $managedUser->id }}" class="users-icon-button" aria-label="{{ __('users.history_for', ['name' => $managedUser->name]) }}" title="{{ __('users.history') }}"><x-icon name="clock" /></button>
                        @can('update', $managedUser)
                            <button type="button" data-user-edit="{{ $managedUser->id }}" class="users-icon-button" aria-label="{{ __('users.edit_for', ['name' => $managedUser->name]) }}" title="{{ __('users.edit') }}"><x-icon name="edit" /></button>
                            <button type="button" data-user-delete="{{ $managedUser->id }}" class="users-icon-button users-delete-action" aria-label="{{ __('users.delete_for', ['name' => $managedUser->name]) }}" title="{{ __('users.delete') }}"><x-icon name="trash" /></button>
                        @else
                            <span class="users-protected" title="{{ __('users.protected') }}"><x-icon name="shield" /><span class="visually-hidden">{{ __('users.protected') }}</span></span>
                        @endcan
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="8" class="users-empty"><x-icon name="users" /><strong>{{ __('users.empty') }}</strong><span>{{ __('users.empty_hint') }}</span></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@include('users.pagination', ['paginator' => $users])
