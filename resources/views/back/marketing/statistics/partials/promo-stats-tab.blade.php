@php
    $sentLabel = $sentLabel ?? 'Poslano';
    $unusedLabel = $unusedLabel ?? 'Neiskorišteno';
@endphp

<div class="row">
    <div class="col-12 col-md-6 col-xl-3 mb-3">
        <div class="block block-rounded text-center h-100">
            <div class="block-content py-3">
                <div class="font-size-sm text-muted text-uppercase">{{ $sentLabel }}</div>
                <div class="font-size-h3 font-w600 mt-1">{{ number_format($stats['summary']['sent_count'], 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3 mb-3">
        <div class="block block-rounded text-center h-100">
            <div class="block-content py-3">
                <div class="font-size-sm text-muted text-uppercase">Iskorišteno</div>
                <div class="font-size-h3 font-w600 mt-1">{{ number_format($stats['summary']['used_count'], 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3 mb-3">
        <div class="block block-rounded text-center h-100">
            <div class="block-content py-3">
                <div class="font-size-sm text-muted text-uppercase">Konverzija</div>
                <div class="font-size-h3 font-w600 mt-1">{{ number_format($stats['summary']['conversion_rate'], 1, ',', '.') }}%</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3 mb-3">
        <div class="block block-rounded text-center h-100">
            <div class="block-content py-3">
                <div class="font-size-sm text-muted text-uppercase">Promet</div>
                <div class="font-size-h3 font-w600 mt-1">{{ \App\Helpers\Currency::main($stats['summary']['revenue_total'], true) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-2">
    <div class="col-12">
        <div class="chart-container promo-wide">
            <canvas id="{{ $chartId }}"></canvas>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 font-size-sm text-muted promo-stats-meta">
            <div>
                {{ $unusedLabel }}:
                <strong>{{ number_format($stats['summary']['unused_count'], 0, ',', '.') }}</strong>
            </div>
            <div>
                Ukupni odobreni popust:
                <strong>{{ \App\Helpers\Currency::main($stats['summary']['discount_total'], true) }}</strong>
            </div>
            <div>
                Najbolji popust:
                @if ($stats['summary']['best_discount'])
                    <strong>{{ $stats['summary']['best_discount']['discount_label'] ?? ('-' . $stats['summary']['best_discount']['discount'] . '%') }}</strong>
                    <span>({{ number_format($stats['summary']['best_discount']['conversion_rate'], 1, ',', '.') }}% konverzija)</span>
                @else
                    <strong>—</strong>
                @endif
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-vcenter font-size-sm">
                <thead>
                <tr>
                    <th>Popust</th>
                    <th class="text-center">{{ $sentLabel }}</th>
                    <th class="text-center">Kupnje</th>
                    <th class="text-center">Konv.</th>
                    <th class="text-right">Promet</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($stats['by_discount'] as $row)
                    <tr>
                        <td><strong>{{ $row['discount_label'] ?? ('-' . $row['discount'] . '%') }}</strong></td>
                        <td class="text-center">{{ number_format($row['sent_count'], 0, ',', '.') }}</td>
                        <td class="text-center">{{ number_format($row['used_count'], 0, ',', '.') }}</td>
                        <td class="text-center">{{ number_format($row['conversion_rate'], 1, ',', '.') }}%</td>
                        <td class="text-right">{{ \App\Helpers\Currency::main($row['revenue_total'], true) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
