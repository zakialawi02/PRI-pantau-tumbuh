@if ($paginator->hasPages())
    <div class="flex w-full flex-col items-end px-4 pt-4">
        <nav class="flex items-center justify-end space-x-2">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <span class="bg-neutral cursor-not-allowed rounded p-2 opacity-50">
                    <svg class="text-muted h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </span>
            @else
                <a class="text-foreground bg-background hover:bg-primary rounded p-2 transition" href="{{ $paginator->previousPageUrl() }}">
                    <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
            @endif

            {{-- Pagination Elements --}}
            @php
                $elements = [];
                $current = $paginator->currentPage();
                $last = $paginator->lastPage();

                if ($last <= 6) {
                    $elements = range(1, $last);
                } else {
                    $elements[] = 1;

                    if ($current > 3) {
                        $elements[] = '...';
                    }

                    $start = max(2, $current - 1);
                    $end = min($last - 1, $current + 1);

                    for ($i = $start; $i <= $end; $i++) {
                        $elements[] = $i;
                    }

                    if ($current < $last - 2) {
                        $elements[] = '...';
                    }

                    $elements[] = $last;
                }
            @endphp

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="text-muted px-3 py-1 text-sm">…</span>
                @else
                    @if ($element == $paginator->currentPage())
                        <span class="bg-primary text-neutral rounded px-3 py-1 text-sm font-semibold">
                            {{ $element }}
                        </span>
                    @else
                        <a class="text-foreground bg-background hover:bg-primary hover:text-neutral rounded px-3 py-1 text-sm transition" href="{{ $paginator->url($element) }}">
                            {{ $element }}
                        </a>
                    @endif
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <a class="text-foreground bg-background hover:bg-primary rounded p-2 transition" href="{{ $paginator->nextPageUrl() }}">
                    <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @else
                <span class="bg-neutral cursor-not-allowed rounded p-2 opacity-50">
                    <svg class="text-muted h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </span>
            @endif
        </nav>

        {{-- Info Summary --}}
        <div class="text-muted/80 mt-2 text-sm">
            Showing <strong>{{ $paginator->firstItem() }}</strong> to <strong>{{ $paginator->lastItem() }}</strong>
            of <strong>{{ $paginator->total() }}</strong> results ({{ $paginator->lastPage() }} pages)
        </div>
    </div>
@endif
