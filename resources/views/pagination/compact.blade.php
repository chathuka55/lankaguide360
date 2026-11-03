{{-- Pagination for the site and the admin panel (Paginator::defaultView). One page on each side of
     the current one keeps it short; the summary and the links wrap instead of squeezing each other. --}}
@if ($paginator->hasPages())
    @php
        $window = \Illuminate\Pagination\UrlWindow::make($paginator->onEachSide(1));
        $elements = array_filter([$window['first'], is_array($window['slider']) ? '...' : null, $window['slider'], is_array($window['last']) ? '...' : null, $window['last']]);
        $link = 'grid h-9 min-w-9 place-items-center rounded-lg px-2.5 text-sm font-medium';
    @endphp
    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
        <p class="text-sm text-slate-600">
            Showing <span class="font-semibold text-slate-900">{{ number_format($paginator->firstItem()) }}</span>–<span class="font-semibold text-slate-900">{{ number_format($paginator->lastItem()) }}</span>
            of <span class="font-semibold text-slate-900">{{ number_format($paginator->total()) }}</span>
        </p>
        <ul class="flex flex-wrap items-center gap-1">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="{{ $link }} cursor-not-allowed text-slate-300" aria-disabled="true"><span class="sr-only">Previous</span><x-icon name="chevron-left" class="size-4" /></span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }} bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50"><span class="sr-only">Previous</span><x-icon name="chevron-left" class="size-4" /></a>
                @endif
            </li>
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="{{ $link }} text-slate-400">…</span></li>
                @else
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="{{ $link }} bg-primary-800 text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="{{ $link }} bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50" aria-label="Page {{ $page }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }} bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50"><span class="sr-only">Next</span><x-icon name="chevron-right" class="size-4" /></a>
                @else
                    <span class="{{ $link }} cursor-not-allowed text-slate-300" aria-disabled="true"><span class="sr-only">Next</span><x-icon name="chevron-right" class="size-4" /></span>
                @endif
            </li>
        </ul>
    </nav>
@endif
