@if ($paginator->hasPages())
    <nav class="pager" aria-label="Trang">
        @if ($paginator->onFirstPage())
            <span class="page-link is-off">&lsaquo;</span>
        @else
            <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">&lsaquo;</a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="page-link is-off">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="page-link is-on">{{ $page }}</span>
                    @else
                        <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">&rsaquo;</a>
        @else
            <span class="page-link is-off">&rsaquo;</span>
        @endif
    </nav>
@endif
