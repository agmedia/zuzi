@if ($paginator->hasPages())
    <div class="row mt-4">
        <div class="col-md-12 d-flex justify-content-center   mb-sm-3">

            <nav class="d-flex justify-content-between pt-2" aria-label="Page navigation">
                <ul class="pagination">
                    @if ($paginator->onFirstPage())
                        <li class="page-item disabled me-2" aria-disabled="true" aria-label="@lang('pagination.previous')">
                            <span class="page-link" aria-hidden="true"><i class="ci-arrow-left me-2"></i> Prethodna</span>
                        </li>
                    @else
                        <li class="page-item me-2"><a class="page-link" href="#top" data-scroll="" wire:click="previousPage" aria-label="@lang('pagination.previous')"><i class="ci-arrow-left me-2"></i> Prethodna</a></li>
                    @endif
                </ul>

                <ul class="pagination">

                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                                <li class="page-item disabled d-none d-sm-block" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                        @endif
                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                        <li class="page-item active d-none d-sm-block" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                                @else
                                        <li class="page-item d-none d-sm-block"><a class="page-link" href="#top" data-scroll="" wire:click="gotoPage({{ $page }})">{{ $page }}</a></li>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                </ul>
                <ul class="pagination">

                    @if ($paginator->hasMorePages())
                            <li class="page-item ms-2"><a class="page-link" href="#top" data-scroll="" wire:click="nextPage" aria-label="@lang('pagination.next')">Sljedeća<i class="ci-arrow-right ms-2"></i></a></li>
                    @else
                            <li class="page-item disabled ms-2" aria-disabled="true" aria-label="@lang('pagination.next')">
                                <span class="page-link" aria-hidden="true">Sljedeća<i class="ci-arrow-right ms-2"></i></span>
                            </li>
                    @endif
                </ul>
            </nav>
        </div>

        <div class="col-md-12  d-flex justify-content-center  mt-2">
            <p class="text-sm text-gray-700 leading-5">
                Prikazano
                <span class="font-weight-bold">{{ $paginator->firstItem() }}</span>
                do
                <span class="font-weight-bold">{{ $paginator->lastItem() }}</span>
                od
                <span class="font-weight-bold">{{ $paginator->total() }}</span>
                rezultata
            </p>
        </div>
    </div>
@endif


