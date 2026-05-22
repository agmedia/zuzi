@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Marketing statistike</h1>
                <nav class="flex-sm-00-auto ml-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">Marketing</li>
                        <li class="breadcrumb-item active" aria-current="page">Statistike</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        @php
            $promoMonths = [
                1 => 'Siječanj',
                2 => 'Veljača',
                3 => 'Ožujak',
                4 => 'Travanj',
                5 => 'Svibanj',
                6 => 'Lipanj',
                7 => 'Srpanj',
                8 => 'Kolovoz',
                9 => 'Rujan',
                10 => 'Listopad',
                11 => 'Studeni',
                12 => 'Prosinac',
            ];
        @endphp

        <div class="block block-rounded">
            <div class="block-header block-header-default promo-header">
                <h3 class="block-title">Promo kodovi</h3>

                <div class="promo-header-actions">
                    <form
                        action="{{ route('marketing.statistics.expired-coupons.destroy') }}"
                        method="post"
                        onsubmit="return confirm('Obrisati istekle promo kodove iz akcija? Ova radnja se ne može poništiti.');"
                    >
                        @csrf
                        <input type="hidden" name="year" value="{{ $promoStats['filters']['year'] }}">
                        <input type="hidden" name="month" value="{{ $promoStats['filters']['month'] }}">
                        <button type="submit" class="btn btn-sm btn-alt-danger" {{ $expiredCouponCount > 0 ? '' : 'disabled' }}>
                            <i class="fa fa-trash-alt mr-1"></i>
                            Obriši istekle kodove
                            <span class="badge badge-light ml-1">{{ number_format($expiredCouponCount, 0, ',', '.') }}</span>
                        </button>
                    </form>
                </div>

                <form action="{{ route('marketing.statistics') }}" method="get" id="promo-filters-form" class="promo-filters-form">
                    <div class="promo-filters">
                        <div class="promo-filter-item">
                            <select name="year" id="promo-year" class="form-control promo-filter-select" aria-label="Godina">
                                @foreach($promoStats['filters']['years'] as $year)
                                    <option value="{{ $year }}" {{ $promoStats['filters']['year'] === (int) $year ? 'selected' : '' }}>{{ $year }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="promo-filter-item">
                            <select name="month" id="promo-month" class="form-control promo-filter-select" aria-label="Mjesec">
                                @foreach($promoMonths as $month => $monthName)
                                    <option value="{{ $month }}" {{ $promoStats['filters']['month'] === (int) $month ? 'selected' : '' }}>{{ $monthName }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Kodovi poslani iz admina</h3>
            </div>
            <div class="block-content">
                @include('back.marketing.statistics.partials.promo-stats-tab', [
                    'stats' => $promoStats['admin'],
                    'chartId' => 'adminPromoStatsChart',
                    'sentLabel' => 'Poslano',
                    'unusedLabel' => 'Neiskorišteno',
                ])
            </div>
        </div>

        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Ostali promo kodovi</h3>
            </div>
            <div class="block-content">
                @include('back.marketing.statistics.partials.promo-stats-tab', [
                    'stats' => $promoStats['other'],
                    'chartId' => 'otherPromoStatsChart',
                    'sentLabel' => 'Kodova',
                    'unusedLabel' => 'Bez kupnje',
                ])
            </div>
        </div>
    </div>
@endsection

@push('css_after')
    <style>
        .chart-container { position: relative; width: 100%; }
        .chart-container.promo-wide { height: 340px; }
        .promo-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .promo-header-actions { margin-left: auto; }
        .promo-filters-form { margin-left: auto; }
        .promo-filters { display: flex; align-items: center; justify-content: flex-end; gap: .75rem; flex-wrap: wrap; }
        .promo-filter-item { flex: 0 0 auto; }
        .promo-filter-select { min-width: 180px; }
        .promo-stats-meta { gap: .5rem 1.5rem; }

        @media (max-width: 767.98px) {
            .promo-header { align-items: stretch; }
            .promo-header-actions { width: 100%; margin-left: 0; }
            .promo-header-actions .btn { width: 100%; }
            .promo-filters-form { width: 100%; margin-left: 0; }
            .promo-filters { width: 100%; justify-content: stretch; }
            .promo-filter-item { flex: 1 1 100%; }
            .promo-filter-select { min-width: 0; width: 100%; }
        }
    </style>
@endpush

@push('js_after')
    <script src="{{ asset('js/plugins/chart.js/Chart.bundle.min.js') }}"></script>

    <script>
        const promoCharts = [
            {
                id: 'adminPromoStatsChart',
                sentLabel: 'Poslano',
                data: @json($promoStats['admin']['chart'])
            },
            {
                id: 'otherPromoStatsChart',
                sentLabel: 'Kodova',
                data: @json($promoStats['other']['chart'])
            }
        ];

        function renderPromoChart(config) {
            const canvas = document.getElementById(config.id);

            if (!canvas || !config.data) {
                return;
            }

            new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: config.data.labels,
                    datasets: [
                        {
                            label: config.sentLabel,
                            data: config.data.sent,
                            borderColor: 'rgba(236, 72, 153, 1)',
                            backgroundColor: 'rgba(236, 72, 153, .18)',
                            fill: true,
                            tension: 0,
                            lineTension: 0
                        },
                        {
                            label: 'Kupnje s kuponom',
                            data: config.data.used,
                            borderColor: 'rgba(16, 185, 129, 1)',
                            backgroundColor: 'rgba(16, 185, 129, .12)',
                            fill: false,
                            tension: 0,
                            lineTension: 0
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    tooltips: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true,
                                precision: 0
                            }
                        }]
                    }
                }
            });
        }

        promoCharts.forEach(renderPromoChart);

        $('#promo-year, #promo-month').on('change', function() {
            $('#promo-filters-form').trigger('submit');
        });
    </script>
@endpush
