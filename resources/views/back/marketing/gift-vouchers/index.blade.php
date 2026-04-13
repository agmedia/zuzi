@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Poklon bonovi</h1>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Kupljeni poklon bonovi <small class="font-weight-light">{{ $giftVouchers->total() }}</small></h3>
            </div>

            <div class="block-content bg-body-dark">
                <form action="{{ route('gift.vouchers') }}" method="GET">
                    <div class="row">
                        <div class="col-lg-7">
                            <div class="input-group mb-3">
                                <input
                                    type="text"
                                    class="form-control py-3"
                                    name="search"
                                    value="{{ request('search') }}"
                                    placeholder="Pretraži po kodu, broju narudžbe, kupcu ili primatelju..."
                                >
                                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <select class="form-control" name="status" onchange="this.form.submit()">
                                <option value="">Svi statusi</option>
                                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Na čekanju</option>
                                <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Poslan</option>
                                <option value="redeemed" {{ request('status') === 'redeemed' ? 'selected' : '' }}>Iskorišten</option>
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <a href="{{ route('gift.vouchers') }}" class="btn btn-light w-100">Reset</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="block-content">
                <div class="table-responsive">
                    <table class="table table-borderless table-striped table-vcenter font-size-sm">
                        <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Narudžba</th>
                            <th>Kupac</th>
                            <th>Primatelj</th>
                            <th>Iznos</th>
                            <th>Kod</th>
                            <th>Status</th>
                            <th>Poruka</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($giftVouchers as $giftVoucher)
                            <tr>
                                <td>{{ optional($giftVoucher->created_at)->format('d.m.Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('orders.show', ['order' => $giftVoucher->order_id]) }}">
                                        #{{ $giftVoucher->order_id }}
                                    </a>
                                </td>
                                <td>
                                    {{ $giftVoucher->buyer_name ?: '---' }}
                                    @if($giftVoucher->buyer_email)
                                        <br><small>{{ $giftVoucher->buyer_email }}</small>
                                    @endif
                                </td>
                                <td>
                                    {{ $giftVoucher->recipient_name ?: '---' }}
                                    <br><small>{{ $giftVoucher->recipient_email }}</small>
                                </td>
                                <td><strong>€ {{ number_format($giftVoucher->amount, 2, ',', '.') }}</strong></td>
                                <td><code>{{ $giftVoucher->code ?: 'Još nije generiran' }}</code></td>
                                <td><span class="badge badge-{{ $giftVoucher->status_color }}">{{ $giftVoucher->display_status }}</span></td>
                                <td style="max-width: 280px; white-space: normal;">{{ $giftVoucher->message ?: '---' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">Nema poklon bonova za prikaz.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $giftVouchers->links() }}
            </div>
        </div>
    </div>
@endsection
