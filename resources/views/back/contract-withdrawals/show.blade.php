@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <div>
                    <h1 class="font-size-h2 font-w400 mt-2 mb-1">{{ $withdrawal->reference }}</h1>
                    <div class="text-muted">Izjava o jednostranom raskidu ugovora</div>
                </div>
                <div class="mt-3 mt-sm-0">
                    <a class="btn btn-alt-secondary" href="{{ route('contract-withdrawals.index') }}">
                        <i class="fa fa-arrow-left mr-1"></i>Natrag na listu
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        @if ($withdrawal->notification_error)
            <div class="alert alert-warning">
                <h4 class="alert-heading font-size-base">Obavijesti nisu u cijelosti poslane</h4>
                <div style="white-space: pre-line;">{{ $withdrawal->notification_error }}</div>
            </div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Nedvosmislena izjava</h3>
                        <span class="badge badge-{{ $statusColors[$withdrawal->status] ?? 'secondary' }}">
                            {{ $statuses[$withdrawal->status] ?? $withdrawal->status }}
                        </span>
                    </div>
                    <div class="block-content">
                        <div class="alert alert-primary font-w600" style="line-height: 1.7;">
                            {{ $withdrawal->declaration }}
                        </div>

                        <div class="table-responsive">
                            <table class="table table-vcenter">
                                <tbody>
                                    <tr><th style="width: 230px;">Referenca</th><td>{{ $withdrawal->reference }}</td></tr>
                                    <tr><th>Podneseno</th><td>{{ optional($withdrawal->submitted_at)->format('d.m.Y. H:i:s T') }}</td></tr>
                                    <tr><th>Ime i prezime</th><td>{{ $withdrawal->full_name }}</td></tr>
                                    <tr><th>E-mail</th><td><a href="mailto:{{ $withdrawal->email }}">{{ $withdrawal->email }}</a></td></tr>
                                    <tr><th>Telefon</th><td>{{ $withdrawal->phone ?: '—' }}</td></tr>
                                    <tr><th>Adresa</th><td>{{ $withdrawal->address_line }}, {{ $withdrawal->postal_code }} {{ $withdrawal->city }}, {{ $withdrawal->country_code }}</td></tr>
                                    <tr>
                                        <th>Broj narudžbe / ugovora</th>
                                        <td>
                                            {{ $withdrawal->order_number }}
                                            @if ($withdrawal->order)
                                                <a class="ml-2" href="{{ route('orders.show', $withdrawal->order) }}">
                                                    Otvori narudžbu #{{ $withdrawal->order->id }}
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr><th>Datum narudžbe</th><td>{{ optional($withdrawal->contract_date)->format('d.m.Y.') ?: '—' }}</td></tr>
                                    <tr><th>Datum primitka robe</th><td>{{ optional($withdrawal->received_date)->format('d.m.Y.') ?: '—' }}</td></tr>
                                    <tr><th>Proizvodi / dio ugovora</th><td style="white-space: pre-line;">{{ $withdrawal->items }}</td></tr>
                                    <tr><th>Dodatna napomena</th><td style="white-space: pre-line;">{{ $withdrawal->note ?: '—' }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Evidencija potvrde</h3>
                    </div>
                    <div class="block-content">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="font-w600">Potvrda korisniku</div>
                                @if ($withdrawal->consumer_notified_at)
                                    <span class="text-success">
                                        <i class="fa fa-check-circle mr-1"></i>
                                        {{ $withdrawal->consumer_notified_at->format('d.m.Y. H:i:s') }}
                                    </span>
                                @else
                                    <span class="text-danger"><i class="fa fa-times-circle mr-1"></i>Nije potvrđeno</span>
                                @endif
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="font-w600">Obavijest administratoru</div>
                                @if ($withdrawal->admin_notified_at)
                                    <span class="text-success">
                                        <i class="fa fa-check-circle mr-1"></i>
                                        {{ $withdrawal->admin_notified_at->format('d.m.Y. H:i:s') }}
                                    </span>
                                @else
                                    <span class="text-danger"><i class="fa fa-times-circle mr-1"></i>Nije poslano</span>
                                @endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('contract-withdrawals.resend', $withdrawal) }}">
                            @csrf
                            <button class="btn btn-alt-primary mb-4" type="submit">
                                <i class="fa fa-paper-plane mr-1"></i>Ponovno pošalji oba e-maila
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Obrada zahtjeva</h3>
                    </div>
                    <form method="POST" action="{{ route('contract-withdrawals.update', $withdrawal) }}">
                        @csrf
                        {{ method_field('PATCH') }}

                        <div class="block-content">
                            <div class="form-group">
                                <label for="withdrawal-status">Status</label>
                                <select class="form-control @error('status') is-invalid @enderror" id="withdrawal-status" name="status" required>
                                    @foreach ($statuses as $value => $label)
                                        <option value="{{ $value }}" @if(old('status', $withdrawal->status) === $value) selected @endif>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group">
                                <label for="withdrawal-internal-note">Interna napomena</label>
                                <textarea
                                    class="form-control @error('internal_note') is-invalid @enderror"
                                    id="withdrawal-internal-note"
                                    name="internal_note"
                                    rows="8"
                                    maxlength="5000"
                                >{{ old('internal_note', $withdrawal->internal_note) }}</textarea>
                                @error('internal_note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <small class="form-text text-muted">Ova napomena nije vidljiva korisniku.</small>
                            </div>

                            @if ($withdrawal->handler)
                                <p class="text-muted small">
                                    Zadnja obrada: {{ $withdrawal->handler->name }},
                                    {{ optional($withdrawal->handled_at)->format('d.m.Y. H:i') }}
                                </p>
                            @endif
                        </div>
                        <div class="block-content bg-body-light">
                            <button class="btn btn-hero-success mb-3" type="submit">
                                <i class="fa fa-save mr-1"></i>Spremi obradu
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
