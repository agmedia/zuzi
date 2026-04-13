@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Komentari artikala</h1>
            </div>
        </div>
    </div>

    <div class="content content-full">
        @include('back.layouts.partials.session')

        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Svi komentari ({{ $reviews->total() }})</h3>
                <div class="block-options">
                    <form action="{{ route('reviews') }}" method="GET" class="d-flex flex-wrap align-items-center gap-2">
                        <div class="block-options-item mr-2">
                            <input type="text" class="form-control" name="search" placeholder="Pretraži komentar, kupca ili artikl" value="{{ request('search') }}">
                        </div>
                        <div class="block-options-item mr-2">
                            <select class="form-control" name="status">
                                <option value="">Svi statusi</option>
                                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Neodobreni</option>
                                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Odobreni</option>
                            </select>
                        </div>
                        <div class="block-options-item mr-2">
                            <button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-search mr-1"></i> Filtriraj</button>
                        </div>
                        <div class="block-options-item">
                            <a href="{{ route('reviews') }}" class="btn btn-alt-secondary btn-sm">Očisti</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="block-content">
                <div class="table-responsive">
                    <table class="table table-striped table-borderless table-vcenter">
                        <thead class="thead-light">
                        <tr>
                            <th style="width: 110px;">Datum</th>
                            <th style="min-width: 240px;">Artikl</th>
                            <th style="width: 90px;">Ocjena</th>
                            <th>Komentar</th>
                            <th style="width: 210px;">Kupac</th>
                            <th class="text-center" style="width: 90px;">Status</th>
                            <th class="text-right" style="width: 110px;">Akcije</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($reviews as $review)
                            <tr>
                                <td class="text-nowrap">{{ optional($review->created_at)->format('d.m.Y') }}</td>
                                <td>
                                    @if ($review->product)
                                        <a class="font-w600" href="{{ route('products.edit', ['product' => $review->product]) }}">
                                            {{ $review->product->name }}
                                        </a>
                                        <div class="font-size-sm text-muted">#{{ $review->product_id }} · {{ $review->product->sku }}</div>
                                    @else
                                        <span class="text-muted">Obrisan artikl (#{{ $review->product_id }})</span>
                                    @endif
                                </td>
                                <td>{{ number_format((float) $review->stars, 1) }}</td>
                                <td>{{ \Illuminate\Support\Str::limit(strip_tags((string) $review->message), 110) }}</td>
                                <td>
                                    <div>{{ trim($review->fname . ' ' . $review->lname) }}</div>
                                    <div class="font-size-sm text-muted">{{ $review->email }}</div>
                                </td>
                                <td class="text-center">
                                    @if ($review->status)
                                        <span class="badge badge-success">Odobren</span>
                                    @else
                                        <span class="badge badge-warning">Na čekanju</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <a class="btn btn-sm btn-alt-secondary" href="{{ route('reviews.edit', ['review' => $review]) }}">
                                        <i class="fa fa-fw fa-pencil-alt"></i>
                                    </a>
                                    <button class="btn btn-sm btn-alt-danger" onclick="event.preventDefault(); deleteItem({{ $review->id }}, '{{ route('reviews.destroy.api') }}');">
                                        <i class="fa fa-fw fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">Nema komentara za prikaz.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $reviews->links() }}
            </div>
        </div>
    </div>
@endsection
