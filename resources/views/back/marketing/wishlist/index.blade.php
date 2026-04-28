@extends('back.layouts.backend')

@section('content')
    @php
        // Koji tab je aktivan? (default: wishlists)
        $activeTab = request('tab', 'wishlists');
    @endphp

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Liste želja</h1>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Liste želja i najtraženiji artikli</h3>
                <div class="block-options">
                    {{-- Očisti filtere uvijek vraća na aktivni tab --}}
                    <a class="btn btn-primary" href="{{ route('wishlists', ['tab' => $activeTab]) }}">
                        <i class="ci-trash"></i> Očisti filtere
                    </a>
                </div>
            </div>

            <div class="block-content">
                {{-- Tabs navigacija (linkovi, ne JS tabovi) --}}
                <ul class="nav nav-tabs nav-tabs-block" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab === 'wishlists' ? 'active' : '' }}"
                           href="{{ route('wishlists', array_merge(request()->except('page'), ['tab' => 'wishlists'])) }}">
                            Sve liste želja
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab === 'top-products' ? 'active' : '' }}"
                           href="{{ route('wishlists', array_merge(request()->except('page'), ['tab' => 'top-products'])) }}">
                            Najtraženiji artikli
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab === 'stats' ? 'active' : '' }}"
                           href="{{ route('wishlists', array_merge(request()->except('page'), ['tab' => 'stats'])) }}">
                            Statistike
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    {{-- TAB 1: Sve liste želja --}}
                    @if ($activeTab === 'wishlists')
                        <div class="tab-pane fade show active" id="tab-wishlists" role="tabpanel">
                            <div class="block-content pt-3">
                                {{-- Filter box --}}
                                <div class="bg-body-dark p-3 mb-3">
                                    <form method="get" action="{{ route('wishlists') }}">
                                        {{-- zadrži aktivni tab --}}
                                        <input type="hidden" name="tab" value="wishlists">
                                        <div class="form-group row">
                                            <div class="col-md-8">
                                                <div class="input-group">
                                                    <input type="text"
                                                           class="form-control"
                                                           name="search"
                                                           value="{{ request()->input('search') }}"
                                                           placeholder="Pretraži po nazivu ili šifri artikla">
                                                    <div class="input-group-append">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fa fa-search"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="form-text small">
                                                    Pretraži po nazivu ili šifri artikla.
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="wishlist-stock-filter" class="small text-muted">Filtriraj stanje</label>
                                                <div class="input-group">
                                                    <select class="form-control" id="wishlist-stock-filter" name="stock">
                                                        <option value="">Svi artikli</option>
                                                        <option value="unsent" {{ ($stockFilter ?? request('stock')) === 'unsent' ? 'selected' : '' }}>Nije poslano</option>
                                                        <option value="in-stock" {{ ($stockFilter ?? request('stock')) === 'in-stock' ? 'selected' : '' }}>Samo na stanju</option>
                                                        <option value="out-of-stock" {{ ($stockFilter ?? request('stock')) === 'out-of-stock' ? 'selected' : '' }}>Samo bez zalihe</option>
                                                    </select>
                                                    <div class="input-group-append">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fa fa-filter"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                {{-- Tablica: sve liste želja --}}
                                <div class="table-responsive">
                                    <table class="table table-borderless table-striped table-vcenter">
                                        <thead>
                                        <tr>
                                            <th style="width: 80px;">Slika</th>
                                            <th>Naziv</th>
                                            <th>Šifra</th>
                                            <th>Stanje</th>
                                            <th>E-mail (korisnik)</th>
                                            <th>Status</th>
                                            <th>Dodano</th>
                                            <th>Poslano</th>
                                            <th class="text-right">Akcija</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($wishlists as $w)
                                            <tr>
                                                <td>
                                                    @if($w->product && $w->product->image)
                                                        <img src="{{ asset($w->product->image) }}" height="60" alt="">
                                                    @endif
                                                </td>
                                                <td>{{ $w->product->name ?? '---' }}</td>
                                                <td>{{ $w->product->sku ?? '---' }}</td>
                                                <td>
                                                    @if($w->product)
                                                        @if((int) $w->product->quantity !== 0)
                                                            <span class="badge badge-success">Na stanju ({{ (int) $w->product->quantity }})</span>
                                                        @else
                                                            <span class="badge badge-secondary">Nema zalihe</span>
                                                        @endif
                                                    @else
                                                        ---
                                                    @endif
                                                </td>
                                                <td>{{ $w->email }}</td>
                                                <td>
                                                    @if((int) $w->sent === 1)
                                                        <span class="badge badge-success">Poslano</span>
                                                    @else
                                                        <span class="badge badge-warning">Na čekanju</span>
                                                    @endif
                                                </td>
                                                <td>{{ optional($w->created_at)->format('d.m.Y H:i') ?? '---' }}</td>
                                                <td>{{ optional($w->sent_at)->format('d.m.Y H:i') ?? '---' }}</td>
                                                <td class="text-right text-nowrap">
                                                    @if((int) $w->sent === 1)
                                                        <button type="button" class="btn btn-sm btn-alt-secondary" disabled>Već poslano</button>
                                                    @elseif($w->product && (int) $w->product->quantity !== 0)
                                                        <form method="POST" action="{{ route('wishlists.items.send', ['wishlist' => $w->id]) }}" class="d-inline">
                                                            @csrf
                                                            <button type="submit"
                                                                    class="btn btn-sm btn-alt-success"
                                                                    onclick="return confirm('Poslati wishlist obavijest ovom korisniku?');">
                                                                Pošalji korisniku
                                                            </button>
                                                        </form>
                                                    @else
                                                        <button type="button" class="btn btn-sm btn-alt-secondary" disabled>Nema zalihe</button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9">Nema zapisa u listi želja.</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Paginacija zadržava aktivni tab i sve upite --}}
                                {{ $wishlists->appends(array_merge(request()->query(), ['tab' => 'wishlists']))->links() }}
                            </div>
                        </div>
                    @endif

                    {{-- TAB 2: Najtraženiji artikli --}}
                    @if ($activeTab === 'top-products')
                        <div class="tab-pane fade show active" id="tab-top-products" role="tabpanel">
                            <div class="block-content pt-3">
                                <div class="bg-body-dark p-3 mb-3">
                                    <form method="get" action="{{ route('wishlists') }}">
                                        <input type="hidden" name="tab" value="top-products">
                                        <div class="form-group row mb-0">
                                            <div class="col-md-4">
                                                <label for="stock-filter" class="small text-muted">Filtriraj stanje</label>
                                                <div class="input-group">
                                                    <select class="form-control" id="stock-filter" name="stock">
                                                        <option value="">Svi artikli</option>
                                                        <option value="unsent" {{ ($stockFilter ?? request('stock')) === 'unsent' ? 'selected' : '' }}>Nije poslano</option>
                                                        <option value="in-stock" {{ ($stockFilter ?? request('stock')) === 'in-stock' ? 'selected' : '' }}>Samo na stanju</option>
                                                        <option value="out-of-stock" {{ ($stockFilter ?? request('stock')) === 'out-of-stock' ? 'selected' : '' }}>Samo bez zalihe</option>
                                                    </select>
                                                    <div class="input-group-append">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fa fa-filter"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-borderless table-striped">
                                        <thead>
                                        <tr>
                                            <th>Naziv artikla</th>
                                            <th>Šifra</th>
                                            <th>Cijena</th>
                                            <th>Stanje</th>
                                            <th class="text-right">Broj aktivnih prijava</th>
                                            <th class="text-right">Akcija</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse ($topProducts as $item)
                                            <tr>
                                                <td>
                                                    @if($item->product)
                                                        <a class="font-w600" href="{{ route('wishlists.products.show', ['product' => $item->product_id]) }}">
                                                            {{ $item->product->name }}
                                                        </a>
                                                    @else
                                                        ---
                                                    @endif
                                                </td>
                                                <td>{{ optional($item->product)->sku ?? '---' }}</td>
                                                <td class="text-nowrap">
                                                    @if($item->product)
                                                        @php($hasSpecialPrice = $item->product->special() && (float) $item->product->special() < (float) $item->product->price)

                                                        @if($hasSpecialPrice)
                                                            <div class="font-w600">{{ $item->product->main_special_text }}</div>
                                                            <div class="small text-muted"><s>{{ $item->product->main_price_text }}</s></div>
                                                        @else
                                                            <div class="font-w600">{{ $item->product->main_price_text }}</div>
                                                        @endif
                                                    @else
                                                        ---
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($item->product)
                                                        @if((int) $item->product->quantity !== 0)
                                                            <span class="badge badge-success">Na stanju ({{ (int) $item->product->quantity }})</span>
                                                        @else
                                                            <span class="badge badge-secondary">Nema zalihe</span>
                                                        @endif
                                                    @else
                                                        ---
                                                    @endif
                                                </td>
                                                <td class="text-right">
                                                    @if($item->product)
                                                        <a class="font-w600" href="{{ route('wishlists.products.show', ['product' => $item->product_id]) }}">
                                                            {{ $item->total }}
                                                        </a>
                                                    @else
                                                        {{ $item->total }}
                                                    @endif
                                                </td>
                                                <td class="text-right text-nowrap">
                                                    @if($item->product && (int) $item->product->quantity !== 0)
                                                        <form method="POST" action="{{ route('wishlists.products.send', ['product' => $item->product_id]) }}" class="d-inline">
                                                            @csrf
                                                            <button type="submit"
                                                                    class="btn btn-sm btn-alt-success"
                                                                    onclick="return confirm('Poslati wishlist obavijesti za ovaj artikl?');">
                                                                Pošalji mailove
                                                            </button>
                                                        </form>
                                                    @else
                                                        <button type="button" class="btn btn-sm btn-alt-secondary" disabled>Nema zalihe</button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6">Nema zapisa za najtraženije artikle.</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>

                                    {{-- Paginacija zadržava aktivni tab i sve upite --}}
                                    {{ $topProducts->appends(array_merge(request()->query(), ['tab' => 'top-products']))->links() }}
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($activeTab === 'stats')
                        <div class="tab-pane fade show active" id="tab-stats" role="tabpanel">
                            <div class="block-content pt-3">
                                <div class="bg-body-dark p-3 mb-3">
                                    <form method="get" action="{{ route('wishlists') }}">
                                        <input type="hidden" name="tab" value="stats">
                                        <div class="form-group row mb-0">
                                            <div class="col-md-6">
                                                <label for="stats-search" class="small text-muted">Pretraži artikl</label>
                                                <div class="input-group">
                                                    <input type="text"
                                                           class="form-control"
                                                           id="stats-search"
                                                           name="search"
                                                           value="{{ request()->input('search') }}"
                                                           placeholder="Naziv ili šifra artikla">
                                                    <div class="input-group-append">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fa fa-search"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="stats-stock-filter" class="small text-muted">Filtriraj stanje</label>
                                                <div class="input-group">
                                                    <select class="form-control" id="stats-stock-filter" name="stock">
                                                        <option value="">Svi artikli</option>
                                                        <option value="in-stock" {{ ($stockFilter ?? request('stock')) === 'in-stock' ? 'selected' : '' }}>Samo na stanju</option>
                                                        <option value="out-of-stock" {{ ($stockFilter ?? request('stock')) === 'out-of-stock' ? 'selected' : '' }}>Samo bez zalihe</option>
                                                    </select>
                                                    <div class="input-group-append">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fa fa-filter"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <div class="block block-rounded">
                                    <div class="block-header block-header-default">
                                        <h3 class="block-title">Skupni Pregled</h3>
                                    </div>
                                    <div class="block-content">
                                        <div class="table-responsive">
                                            <table class="table table-borderless table-striped table-vcenter">
                                                <tbody>
                                                <tr>
                                                    <th style="width: 35%;">Poslano wishlist mailova</th>
                                                    <td class="text-right font-w600">{{ data_get($statsSummary, 'sent_entries_count', 0) }}</td>
                                                    <th style="width: 35%;">Prijava s kupnjom nakon maila</th>
                                                    <td class="text-right font-w600">{{ data_get($statsSummary, 'converted_entries_count', 0) }}</td>
                                                </tr>
                                                <tr>
                                                    <th>Prodanih komada</th>
                                                    <td class="text-right font-w600">{{ data_get($statsSummary, 'matched_units_count', 0) }}</td>
                                                    <th>Prihod nakon maila</th>
                                                    <td class="text-right font-w600">{{ \App\Helpers\Currency::main(data_get($statsSummary, 'matched_revenue_total', 0), true) }}</td>
                                                </tr>
                                                <tr>
                                                    <th>Artikala s barem jednom kupnjom</th>
                                                    <td class="text-right font-w600">{{ data_get($statsSummary, 'products_with_sales_count', 0) }}</td>
                                                    <th>Konverzija prijava</th>
                                                    <td class="text-right font-w600">{{ number_format((float) data_get($statsSummary, 'conversion_rate', 0), 1, ',', '.') }}%</td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-borderless table-striped table-vcenter">
                                        <thead>
                                        <tr>
                                            <th>Naziv artikla</th>
                                            <th>Šifra</th>
                                            <th class="text-right">Poslano</th>
                                            <th class="text-right">Kupci nakon maila</th>
                                            <th class="text-right">Narudžbe</th>
                                            <th class="text-right">Komada</th>
                                            <th class="text-right">Prihod</th>
                                            <th class="text-right">Konverzija</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse ($statsProducts ?? [] as $item)
                                            @php($stats = $statsProductPurchaseStats[$item->product_id] ?? [])
                                            <tr>
                                                <td>
                                                    @if($item->product)
                                                        <a class="font-w600" href="{{ route('wishlists.products.show', ['product' => $item->product_id]) }}">
                                                            {{ $item->product->name }}
                                                        </a>
                                                    @else
                                                        ---
                                                    @endif
                                                </td>
                                                <td>{{ optional($item->product)->sku ?? '---' }}</td>
                                                <td class="text-right">{{ data_get($stats, 'sent_entries_count', (int) ($item->sent_entries_count ?? 0)) }}</td>
                                                <td class="text-right">{{ data_get($stats, 'converted_entries_count', 0) }}</td>
                                                <td class="text-right">{{ data_get($stats, 'matched_orders_count', 0) }}</td>
                                                <td class="text-right">{{ data_get($stats, 'matched_units_count', 0) }}</td>
                                                <td class="text-right text-nowrap">{{ \App\Helpers\Currency::main(data_get($stats, 'matched_revenue_total', 0), true) }}</td>
                                                <td class="text-right">{{ number_format((float) data_get($stats, 'conversion_rate', 0), 1, ',', '.') }}%</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8">Nema podataka za statistiku wishlist mailova.</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="small text-muted mt-3">
                                    Statistika kupnje je procjena na temelju istog e-maila, istog artikla i narudžbe nastale nakon slanja wishlist maila.
                                </div>

                                {{ optional($statsProducts)->appends(array_merge(request()->query(), ['tab' => 'stats']))->links() }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
