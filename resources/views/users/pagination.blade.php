<div class="users-pagination">
    <p>{{ __('users.pagination_summary', ['first' => $paginator->firstItem() ?? 0, 'last' => $paginator->lastItem() ?? 0, 'total' => $paginator->total()]) }}</p>
    <nav aria-label="{{ __('users.pagination') }}">
        <button type="button" class="users-page-button" data-page="{{ max(1, $paginator->currentPage() - 1) }}" @disabled($paginator->onFirstPage()) aria-label="{{ __('users.previous') }}"><x-icon name="chevron" class="rotate-180" /></button>
        @for ($page = max(1, $paginator->currentPage() - 2); $page <= min($paginator->lastPage(), $paginator->currentPage() + 2); $page++)
            <button type="button" class="users-page-button {{ $page === $paginator->currentPage() ? 'active' : '' }}" data-page="{{ $page }}" @if ($page === $paginator->currentPage()) aria-current="page" @endif>{{ $page }}</button>
        @endfor
        <button type="button" class="users-page-button" data-page="{{ $paginator->currentPage() + 1 }}" @disabled(! $paginator->hasMorePages()) aria-label="{{ __('users.next') }}"><x-icon name="chevron" /></button>
    </nav>
</div>
