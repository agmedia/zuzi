@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Natjecanje prognoze</h1>
                <nav class="flex-sm-00-auto ml-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">Marketing</li>
                        <li class="breadcrumb-item active" aria-current="page">Prognoze</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content content-full">
        @include('back.layouts.partials.session')

        <div class="row">
            <div class="col-xl-4">
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Službeni rezultat</h3>
                    </div>
                    <div class="block-content">
                        <p class="text-muted font-size-sm">
                            Unesite službene podatke utakmice. Pobjednik se računa samo među prijavama s točnim rezultatom.
                        </p>

                        <form action="{{ route('admin.match-predictions.calculate-winner') }}" method="POST">
                            @csrf

                            <div class="form-group">
                                <label for="official-croatia-goals">Hrvatska golovi <span class="text-danger">*</span></label>
                                <input type="number" class="form-control @error('croatia_goals') is-invalid @enderror" id="official-croatia-goals" name="croatia_goals" value="{{ old('croatia_goals') }}" min="0" max="20" step="1">
                                @error('croatia_goals') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group">
                                <label for="official-england-goals">Gana golovi <span class="text-danger">*</span></label>
                                <input type="number" class="form-control @error('england_goals') is-invalid @enderror" id="official-england-goals" name="england_goals" value="{{ old('england_goals') }}" min="0" max="20" step="1">
                                @error('england_goals') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group">
                                <label for="official-first-goal-minute">Minuta prvog gola <span class="text-danger">*</span></label>
                                <input type="number" class="form-control @error('first_goal_minute') is-invalid @enderror" id="official-first-goal-minute" name="first_goal_minute" value="{{ old('first_goal_minute') }}" min="1" max="120" step="1">
                                @error('first_goal_minute') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group">
                                <label for="official-yellow-cards-total">Ukupan broj žutih kartona <span class="text-danger">*</span></label>
                                <input type="number" class="form-control @error('yellow_cards_total') is-invalid @enderror" id="official-yellow-cards-total" name="yellow_cards_total" value="{{ old('yellow_cards_total') }}" min="0" max="30" step="1">
                                @error('yellow_cards_total') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <button type="submit" class="btn btn-primary btn-block" onclick="return confirm('Izračunati pobjednika i resetirati postojeće oznake pobjednika?');">
                                Izračunaj pobjednika
                            </button>
                        </form>
                    </div>
                </div>

                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Postavke</h3>
                    </div>
                    <div class="block-content">
                        <dl class="row mb-0 font-size-sm">
                            <dt class="col-5">Status</dt>
                            <dd class="col-7">
                                @if (config('match_prediction.enabled'))
                                    <span class="badge badge-success">Uključeno</span>
                                @else
                                    <span class="badge badge-secondary">Isključeno</span>
                                @endif
                            </dd>
                            <dt class="col-5">Rok</dt>
                            <dd class="col-7">{{ $deadline->format('d.m.Y. H:i') }}</dd>
                            <dt class="col-5">Prijava</dt>
                            <dd class="col-7">{{ number_format($totalPredictions, 0, ',', '.') }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Zaprimljene prognoze <small class="font-weight-light">{{ $predictions->total() }}</small></h3>
                        <div class="block-options">
                            <a href="{{ route('admin.match-predictions.export') }}" class="btn btn-sm btn-alt-primary">
                                <i class="fa fa-download mr-1"></i> CSV export
                            </a>
                        </div>
                    </div>

                    <div class="block-content bg-body-dark">
                        <form action="{{ route('admin.match-predictions.index') }}" method="GET">
                            <div class="input-group mb-3">
                                <input
                                    type="text"
                                    class="form-control"
                                    name="search"
                                    value="{{ request('search') }}"
                                    placeholder="Pretraži po ID-u, imenu ili emailu..."
                                >
                                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i></button>
                                <a href="{{ route('admin.match-predictions.index') }}" class="btn btn-light">Reset</a>
                            </div>
                        </form>
                    </div>

                    <div class="block-content">
                        <div class="table-responsive">
                            <table class="table table-striped table-borderless table-vcenter font-size-sm">
                                <thead class="thead-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Vrijeme prijave</th>
                                    <th>Ime i prezime</th>
                                    <th>Email</th>
                                    <th>Prognoza</th>
                                    <th>Prvi gol</th>
                                    <th>Žuti kartoni</th>
                                    <th>IP adresa</th>
                                    <th>Newsletter</th>
                                    <th>Pobjednik</th>
                                    <th>Kontaktiran</th>
                                    <th class="text-right">Akcije</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($predictions as $prediction)
                                    <tr>
                                        <td>#{{ $prediction->id }}</td>
                                        <td class="text-nowrap">{{ optional($prediction->created_at)->format('d.m.Y. H:i') }}</td>
                                        <td>
                                            <div class="font-w600">{{ $prediction->first_name }} {{ $prediction->last_name }}</div>
                                        </td>
                                        <td>{{ $prediction->email }}</td>
                                        <td class="text-nowrap">
                                            <strong>{{ $prediction->croatia_goals }} : {{ $prediction->england_goals }}</strong>
                                        </td>
                                        <td>{{ $prediction->first_goal_minute ?? '---' }}</td>
                                        <td>{{ $prediction->yellow_cards_total ?? '---' }}</td>
                                        <td>{{ $prediction->ip_address ?? '---' }}</td>
                                        <td>
                                            @if ($prediction->newsletter_consent)
                                                <span class="badge badge-success">Da</span>
                                            @else
                                                <span class="badge badge-secondary">Ne</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($prediction->is_winner)
                                                <span class="badge badge-warning">Pobjednik</span>
                                                <div class="text-muted">Score: {{ $prediction->winner_score }}</div>
                                            @else
                                                <span class="badge badge-secondary">Ne</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($prediction->contacted_at)
                                                {{ $prediction->contacted_at->format('d.m.Y. H:i') }}
                                            @else
                                                <span class="text-muted">---</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if ($prediction->is_winner && ! $prediction->contacted_at)
                                                <form action="{{ route('admin.match-predictions.mark-contacted', ['matchPrediction' => $prediction]) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-alt-success" onclick="return confirm('Označiti dobitnika kao kontaktiranog?');">
                                                        <i class="fa fa-envelope mr-1"></i> Kontaktiran
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted">---</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="text-center text-muted">Nema zaprimljenih prognoza.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{ $predictions->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
