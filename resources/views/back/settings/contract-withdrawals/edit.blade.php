@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <div>
                    <h1 class="font-size-h2 font-w400 mt-2 mb-1">Postavke jednostranog raskida</h1>
                    <div class="text-muted">Primatelji e-mailova i upute za povrat robe</div>
                </div>
                <a class="btn btn-alt-secondary mt-3 mt-sm-0" href="{{ route('contract-withdrawals.index') }}">
                    <i class="fa fa-list mr-1"></i>Zaprimljene izjave
                </a>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        <div class="row">
            <div class="col-lg-8">
                <form method="POST" action="{{ route('contract-withdrawal-settings.update') }}">
                    @csrf
                    {{ method_field('PATCH') }}

                    <div class="block block-rounded">
                        <div class="block-header block-header-default">
                            <h3 class="block-title">Slanje i povrat robe</h3>
                        </div>
                        <div class="block-content">
                            <div class="form-group">
                                <label for="withdrawal-admin-email">E-mail administratora *</label>
                                <input
                                    class="form-control @error('admin_email') is-invalid @enderror"
                                    id="withdrawal-admin-email"
                                    type="email"
                                    name="admin_email"
                                    value="{{ old('admin_email', $settings['admin_email']) }}"
                                    required
                                    maxlength="191"
                                >
                                @error('admin_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <small class="form-text text-muted">Na ovu adresu stiže svaka nova izjava o raskidu ugovora.</small>
                            </div>

                            <div class="form-group">
                                <label for="withdrawal-return-address">Adresa za povrat robe *</label>
                                <textarea
                                    class="form-control @error('return_address') is-invalid @enderror"
                                    id="withdrawal-return-address"
                                    name="return_address"
                                    rows="4"
                                    required
                                    maxlength="1000"
                                >{{ old('return_address', $settings['return_address']) }}</textarea>
                                @error('return_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <small class="form-text text-muted">Prikazuje se na obrascu i u potvrdi korisniku.</small>
                            </div>

                            <div class="form-group">
                                <label for="withdrawal-cost-policy">Izravni trošak povrata robe *</label>
                                <select
                                    class="form-control @error('return_cost_policy') is-invalid @enderror"
                                    id="withdrawal-cost-policy"
                                    name="return_cost_policy"
                                    required
                                >
                                    <option value="consumer" @if(old('return_cost_policy', $settings['return_cost_policy']) === 'consumer') selected @endif>
                                        Trošak snosi potrošač
                                    </option>
                                    <option value="merchant" @if(old('return_cost_policy', $settings['return_cost_policy']) === 'merchant') selected @endif>
                                        Trošak snosi Zuzi Shop
                                    </option>
                                </select>
                                @error('return_cost_policy') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group">
                                <label for="withdrawal-instructions">Dodatne upute korisniku</label>
                                <textarea
                                    class="form-control @error('instructions') is-invalid @enderror"
                                    id="withdrawal-instructions"
                                    name="instructions"
                                    rows="7"
                                    maxlength="5000"
                                >{{ old('instructions', $settings['instructions']) }}</textarea>
                                @error('instructions') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <small class="form-text text-muted">Upute se šalju u potvrdi primitka izjave.</small>
                            </div>
                        </div>
                        <div class="block-content bg-body-light">
                            <button class="btn btn-hero-success mb-3" type="submit">
                                <i class="fa fa-save mr-1"></i>Spremi postavke
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-lg-4">
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Što sustav radi</h3>
                    </div>
                    <div class="block-content">
                        <ul class="pl-3">
                            <li class="mb-3">Po završnoj potvrdi trajno evidentira sadržaj izjave i vrijeme podnošenja.</li>
                            <li class="mb-3">Korisniku bez odgađanja šalje potvrdu na e-mail.</li>
                            <li class="mb-3">Administratoru šalje zasebnu operativnu obavijest.</li>
                            <li class="mb-3">Omogućuje praćenje statusa, internu napomenu i ponovno slanje e-mailova.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
