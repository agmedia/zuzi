@extends('back.layouts.backend')

@section('content')
    @php
        $specialPrice = $product->special();
        $hasSpecialPrice = $specialPrice && (float) $specialPrice < (float) $product->price;
    @endphp

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <div class="flex-sm-fill">
                    <h1 class="font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Detalji prijava za knjigu</h1>
                    <div class="font-size-sm text-muted">{{ $product->name }}</div>
                </div>
                <nav class="flex-sm-00-auto ml-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('wishlists', ['tab' => 'top-products']) }}">Wishlist</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Detalji knjige</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        <div class="row row-deck">
            <div class="col-md-8">
                <div class="block block-rounded h-100">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Knjiga</h3>
                        <div class="block-options">
                            <a class="btn btn-sm btn-alt-secondary" href="{{ route('wishlists', ['tab' => 'top-products']) }}">
                                Natrag na listu
                            </a>
                            @if((int) $product->quantity !== 0 && $pendingCount > 0)
                                <form method="POST" action="{{ route('wishlists.products.send', ['product' => $product]) }}" class="d-inline">
                                    @csrf
                                    <button type="submit"
                                            class="btn btn-sm btn-alt-success"
                                            onclick="return confirm('Poslati wishlist obavijesti za ovaj artikl?');">
                                        Pošalji mailove
                                    </button>
                                </form>
                            @endif
                            <a class="btn btn-sm btn-alt-secondary" href="{{ route('products.edit', ['product' => $product]) }}">
                                Uredi artikl
                            </a>
                            @if($product->url)
                                <a class="btn btn-sm btn-alt-secondary" href="{{ url($product->url) }}" target="_blank">
                                    Otvori artikl
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="block-content">
                        <div class="table-responsive">
                            <table class="table table-borderless table-striped table-vcenter">
                                <tbody>
                                <tr>
                                    <th style="width: 180px;">Naziv</th>
                                    <td>{{ $product->name }}</td>
                                </tr>
                                <tr>
                                    <th>Šifra</th>
                                    <td>{{ $product->sku ?: '---' }}</td>
                                </tr>
                                <tr>
                                    <th>Cijena</th>
                                    <td>
                                        @if($hasSpecialPrice)
                                            <span class="font-w600">{{ \App\Helpers\Currency::main($specialPrice, true) }}</span>
                                            <span class="small text-muted ml-2"><s>{{ \App\Helpers\Currency::main($product->price, true) }}</s></span>
                                        @else
                                            <span class="font-w600">{{ \App\Helpers\Currency::main($product->price, true) }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Stanje</th>
                                    <td>
                                        @if((int) $product->quantity !== 0)
                                            <span class="badge badge-success">Na stanju ({{ (int) $product->quantity }})</span>
                                        @else
                                            <span class="badge badge-secondary">Nema zalihe</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Status artikla</th>
                                    <td>
                                        @if((int) $product->status === 1)
                                            <span class="badge badge-success">Aktivan</span>
                                        @else
                                            <span class="badge badge-danger">Neaktivan</span>
                                        @endif
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="row">
                    <div class="col-12">
                        <div class="block block-rounded">
                            <div class="block-content block-content-full text-center">
                                <div class="font-size-h2 font-w700 text-primary">{{ $pendingCount }}</div>
                                <div class="font-size-sm text-muted text-uppercase">Aktivne prijave</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="block block-rounded">
                            <div class="block-content block-content-full text-center">
                                <div class="font-size-h2 font-w700 text-success">{{ $sentCount }}</div>
                                <div class="font-size-sm text-muted text-uppercase">Već obaviješteno</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="block block-rounded">
                            <div class="block-content block-content-full text-center">
                                <div class="font-size-h2 font-w700">{{ $totalCount }}</div>
                                <div class="font-size-sm text-muted text-uppercase">Ukupno prijava</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Sve prijave za ovu knjigu</h3>
            </div>
            <div class="block-content">
                <div class="table-responsive">
                    <table class="table table-borderless table-striped table-vcenter">
                        <thead>
                        <tr>
                            <th>Kupac</th>
                            <th>E-mail</th>
                            <th>Status</th>
                            <th>Datum prijave</th>
                            <th>Poslano</th>
                            <th class="text-right">Akcija</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($entries as $entry)
                            @php
                                $details = optional($entry->user)->details;
                                $customerName = trim(collect([
                                    optional($details)->fname,
                                    optional($details)->lname,
                                ])->filter()->implode(' '));
                            @endphp
                            <tr>
                                <td>
                                    <div class="font-w600">{{ $customerName ?: (optional($entry->user)->name ?: 'Gost prijava') }}</div>
                                    @if($entry->user_id)
                                        <div class="small text-muted">Korisnik #{{ $entry->user_id }}</div>
                                    @endif
                                </td>
                                <td>{{ $entry->email }}</td>
                                <td>
                                    @if((int) $entry->sent === 1)
                                        <span class="badge badge-success">Poslano</span>
                                    @else
                                        <span class="badge badge-warning">Na čekanju</span>
                                    @endif
                                </td>
                                <td>{{ optional($entry->created_at)->format('d.m.Y H:i') ?? '---' }}</td>
                                <td>{{ optional($entry->sent_at)->format('d.m.Y H:i') ?? '---' }}</td>
                                <td class="text-right text-nowrap">
                                    @if((int) $entry->sent === 1)
                                        <button type="button" class="btn btn-sm btn-alt-secondary" disabled>Već poslano</button>
                                    @elseif((int) $product->quantity !== 0)
                                        <form method="POST" action="{{ route('wishlists.items.send', ['wishlist' => $entry->id]) }}" class="d-inline">
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
                                <td colspan="6">Nema prijava za ovu knjigu.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $entries->links() }}
            </div>
        </div>
    </div>
@endsection
