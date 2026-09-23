<div class="table-responsive users-table-scroll" tabindex="0" aria-label="{{ __('users.history') }}">
    <table class="table users-table"><thead><tr>
        @foreach (['id' => __('users.history_number'), 'device' => __('users.history_device'), 'ip_address' => __('users.history_ip'), 'logged_in_at' => __('users.history_date')] as $column => $label)
            <th scope="col" aria-sort="{{ $sort === $column ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}"><button type="button" data-sort="{{ $column }}">{{ $label }}<span aria-hidden="true">{{ $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
        @endforeach
    </tr></thead><tbody>
        @forelse ($histories as $history)
            <tr><td>{{ $histories->firstItem() + $loop->index }}</td><td>{{ $history->device ?: '—' }}</td><td>{{ $history->ip_address ?: '—' }}</td><td><time datetime="{{ $history->logged_in_at->toIso8601String() }}">{{ $history->logged_in_at->format('d/m/Y H:i:s') }}</time></td></tr>
        @empty
            <tr><td colspan="4" class="users-empty">{{ __('users.history_empty') }}</td></tr>
        @endforelse
    </tbody></table>
</div>
@include('users.pagination', ['paginator' => $histories])
