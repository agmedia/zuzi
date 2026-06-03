@extends('back.layouts.backend')

@section('content')
    <!-- Hero -->
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Nadzorna ploča</h1>
                <nav class="flex-sm-00-auto ml-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item active" aria-current="page">Nadzorna ploča</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
    <!-- END Hero -->

    <!-- Page Content -->
    <div class="content dashboard-content">
        @include('back.layouts.partials.session')

        @if( auth()->user()->id != '1716')
            <!-- Quick Overview -->
        <div class="row row-deck dashboard-kpi-grid">
                 <div class="col-6 col-lg-3 dashboard-kpi-col">
                     <a class="block block-rounded block-link-shadow dashboard-kpi-card dashboard-kpi-card-warning" href="{{ route('orders') }}">
                         <div class="block-content py-5 dashboard-kpi-content">
                             <div class="dashboard-kpi-head">
                                 <span class="dashboard-kpi-icon"><i class="fa fa-hourglass-half"></i></span>
                                 <span class="dashboard-kpi-label">Narudžbe u obradi</span>
                             </div>
                             <div class="font-size-h3 font-w700 dashboard-kpi-value">{{ $data['proccess'] }}</div>
                             <div class="dashboard-kpi-total">
                                 <span>Ukupno</span>
                                 <strong>{{ \App\Helpers\Currency::main($data['processing_total'], true) }}</strong>
                             </div>
                         </div>
                     </a>
                 </div>
                 <div class="col-6 col-lg-3 dashboard-kpi-col">
                     <a class="block block-rounded block-link-shadow dashboard-kpi-card dashboard-kpi-card-success" href="{{ route('orders') }}">
                         <div class="block-content py-5 dashboard-kpi-content">
                             <div class="dashboard-kpi-head">
                                 <span class="dashboard-kpi-icon"><i class="fa fa-check-circle"></i></span>
                                 <span class="dashboard-kpi-label">Dovršene ove godine</span>
                             </div>
                             <div class="font-size-h3 font-w700 dashboard-kpi-value">{{ $data['finished'] }}</div>
                             <div class="dashboard-kpi-total">
                                 <span>Ukupno</span>
                                 <strong>{{ \App\Helpers\Currency::main($data['finished_total'], true) }}</strong>
                             </div>
                         </div>
                     </a>
                 </div>
                 <div class="col-6 col-lg-3 dashboard-kpi-col">
                     <a class="block block-rounded block-link-shadow dashboard-kpi-card dashboard-kpi-card-info" href="{{ route('orders') }}">
                         <div class="block-content py-5 dashboard-kpi-content">
                             <div class="dashboard-kpi-head">
                                 <span class="dashboard-kpi-icon"><i class="fa fa-calendar-day"></i></span>
                                 <span class="dashboard-kpi-label">Narudžbe danas</span>
                             </div>
                             <div class="font-size-h3 font-w700 dashboard-kpi-value">{{ $data['today'] }}</div>
                             <div class="dashboard-kpi-total">
                                 <span>Ukupno</span>
                                 <strong>{{ \App\Helpers\Currency::main($data['today_total'], true) }}</strong>
                             </div>
                         </div>
                     </a>
                 </div>
                 <div class="col-6 col-lg-3 dashboard-kpi-col">
                     <a class="block block-rounded block-link-shadow dashboard-kpi-card dashboard-kpi-card-primary" href="{{ route('orders') }}">
                         <div class="block-content py-5 dashboard-kpi-content">
                             <div class="dashboard-kpi-head">
                                 <span class="dashboard-kpi-icon"><i class="fa fa-calendar-alt"></i></span>
                                 <span class="dashboard-kpi-label">Narudžbe ovaj mjesec</span>
                             </div>
                             <div class="font-size-h3 font-w700 dashboard-kpi-value">{{ $data['this_month'] }}</div>
                             <div class="dashboard-kpi-total">
                                 <span>Ukupno</span>
                                 <strong>{{ \App\Helpers\Currency::main($data['this_month_total'], true) }}</strong>
                             </div>
                         </div>
                     </a>
                 </div>
             </div>
            <!-- END Quick Overview -->

            <!-- Sales Overview Block with Tabs -->
            <div class="block block-rounded mt-2 dashboard-sales-block">
                <div class="block-header block-header-default sales-header">
                    <h3 class="block-title">Statistika prometa</h3>

                    <div class="sales-filters">
                        <div class="sales-filter-item">
                            <select id="chart-year" class="form-control sales-filter-select" aria-label="Godina">
                                @foreach($yearsWithOrders as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sales-filter-item">
                            <select id="chart-month" class="form-control sales-filter-select" aria-label="Mjesec">
                                @php
                                    $hrMonths = [
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

                                @foreach($hrMonths as $m => $name)
                                    <option value="{{ $m }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="block-content">
                    <!-- Tabs nav -->
                    <ul class="nav nav-tabs dashboard-sales-tabs" id="salesTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="sales-tab" data-toggle="tab" href="#tab-sales" role="tab"
                               aria-controls="tab-sales" aria-selected="true">Mjesečni pregled</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="monthly-tab" data-toggle="tab" href="#tab-monthly" role="tab"
                               aria-controls="tab-monthly" aria-selected="false">Godišnji pregled</a>
                        </li>
                    </ul>

                    <!-- Tabs content -->
                    <div class="tab-content mt-3" id="salesTabsContent">
                        <!-- Tab 1: Mjesečni pregled (po danima) -->
                        <div class="tab-pane fade show active" id="tab-sales" role="tabpanel" aria-labelledby="sales-tab">
                            <div class="row mb-3 mt-3 dashboard-sales-kpis">
                                <div class="col-12">
                                    <div class="row">
                                        <!-- Mjesečni promet -->
                                        <div class="col-6 col-md-3 mb-3">
                                            <div class="dashboard-sales-kpi h-100">
                                                <div class="dashboard-sales-kpi-content">
                                                    <div class="font-size-sm text-muted text-uppercase dashboard-sales-kpi-label">Mjesečni promet</div>
                                                    <div id="kpi-month-total" class="font-size-h3 font-w600 dashboard-sales-kpi-value">—</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Broj narudžbi u mjesecu (računa se iz JS serije) -->
                                        <div class="col-6 col-md-3 mb-3">
                                            <div class="dashboard-sales-kpi h-100">
                                                <div class="dashboard-sales-kpi-content">
                                                    <div class="font-size-sm text-muted text-uppercase dashboard-sales-kpi-label">Narudžbe mjesec</div>
                                                    <div class="dashboard-sales-kpi-line dashboard-sales-kpi-value">
                                                        <span id="kpi-month-orders" class="font-size-h3 font-w600">—</span>
                                                        <span id="kpi-month-items-avg" class="dashboard-sales-kpi-meta">— art./nar.</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Narudžbi danas (iz backend varijable) -->
                                        <div class="col-6 col-md-3 mb-3">
                                            <div class="dashboard-sales-kpi h-100">
                                                <div class="dashboard-sales-kpi-content">
                                                    <div class="font-size-sm text-muted text-uppercase dashboard-sales-kpi-label">Narudžbe danas</div>
                                                    <div class="dashboard-sales-kpi-line dashboard-sales-kpi-value">
                                                        <span class="font-size-h3 font-w600">{{ $data['today'] }}</span>
                                                        <span class="dashboard-sales-kpi-meta">{{ number_format($data['today_items_average'], 2, ',', '.') }} art./nar.</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Prosj. narudžba -->
                                        <div class="col-6 col-md-3 mb-3">
                                            <div class="dashboard-sales-kpi h-100">
                                                <div class="dashboard-sales-kpi-content">
                                                    <div class="font-size-sm text-muted text-uppercase dashboard-sales-kpi-label">Prosj. iznos narudžbe</div>
                                                    <div id="kpi-month-aov" class="font-size-h3 font-w600 dashboard-sales-kpi-value">—</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3 dashboard-sales-details">
                                <div class="col-12 col-md-4 mb-3">
                                    <div class="dashboard-sales-detail h-100">
                                        <div class="dashboard-sales-detail-title">Vrsta plaćanja</div>
                                        <div id="monthly-payment-methods" class="dashboard-breakdown-list">
                                            <div class="dashboard-breakdown-empty">—</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4 mb-3">
                                    <div class="dashboard-sales-detail h-100">
                                        <div class="dashboard-sales-detail-title">Izabrana dostava</div>
                                        <div id="monthly-shipping-methods" class="dashboard-breakdown-list">
                                            <div class="dashboard-breakdown-empty">—</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4 mb-3">
                                    <div class="dashboard-sales-detail h-100">
                                        <div class="dashboard-sales-detail-title">Poklon zamatanje</div>
                                        <div class="dashboard-gift-wrap-summary">
                                            <div class="dashboard-gift-wrap-item">
                                                <strong id="monthly-gift-wrap-orders">0</strong>
                                                <span>narudžbi</span>
                                            </div>
                                            <div class="dashboard-gift-wrap-item">
                                                <strong id="monthly-gift-wrap-items">0</strong>
                                                <span>kom.</span>
                                            </div>
                                            <div class="dashboard-gift-wrap-item">
                                                <strong id="monthly-gift-wrap-total">0,00 €</strong>
                                                <span>ukupno</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="chart-container large">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>

                        <!-- Tab 2: Godišnji pregled -->
                        <div class="tab-pane fade" id="tab-monthly" role="tabpanel" aria-labelledby="monthly-tab">
                            <div class="row mb-3 mt-3 dashboard-sales-kpis">
                                <div class="col-12">
                                    <div class="row">
                                        <div class="col-6 col-md-3 mb-3">
                                            <div class="dashboard-sales-kpi h-100">
                                                <div class="dashboard-sales-kpi-content">
                                                    <div class="font-size-sm text-muted text-uppercase dashboard-sales-kpi-label">Godišnji promet</div>
                                                    <div id="kpi-year-total" class="font-size-h3 font-w600 dashboard-sales-kpi-value">—</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-6 col-md-3 mb-3">
                                            <div class="dashboard-sales-kpi h-100">
                                                <div class="dashboard-sales-kpi-content">
                                                    <div class="font-size-sm text-muted text-uppercase dashboard-sales-kpi-label">Narudžbe godina</div>
                                                    <div class="dashboard-sales-kpi-line dashboard-sales-kpi-value">
                                                        <span id="kpi-year-orders" class="font-size-h3 font-w600">—</span>
                                                        <span id="kpi-year-items-avg" class="dashboard-sales-kpi-meta">— art./nar.</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-6 col-md-3 mb-3">
                                            <div class="dashboard-sales-kpi h-100">
                                                <div class="dashboard-sales-kpi-content">
                                                    <div class="font-size-sm text-muted text-uppercase dashboard-sales-kpi-label">Zamatanje godina</div>
                                                    <div class="dashboard-sales-kpi-line dashboard-sales-kpi-value">
                                                        <span id="kpi-year-gift-wrap-orders" class="font-size-h3 font-w600">—</span>
                                                        <span class="dashboard-sales-kpi-meta">nar.</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-6 col-md-3 mb-3">
                                            <div class="dashboard-sales-kpi h-100">
                                                <div class="dashboard-sales-kpi-content">
                                                    <div class="font-size-sm text-muted text-uppercase dashboard-sales-kpi-label">Prosj. iznos narudžbe</div>
                                                    <div id="kpi-year-aov" class="font-size-h3 font-w600 dashboard-sales-kpi-value">—</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3 dashboard-sales-details">
                                <div class="col-12 col-md-4 mb-3">
                                    <div class="dashboard-sales-detail h-100">
                                        <div class="dashboard-sales-detail-title">Vrsta plaćanja</div>
                                        <div id="yearly-payment-methods" class="dashboard-breakdown-list">
                                            <div class="dashboard-breakdown-empty">—</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4 mb-3">
                                    <div class="dashboard-sales-detail h-100">
                                        <div class="dashboard-sales-detail-title">Izabrana dostava</div>
                                        <div id="yearly-shipping-methods" class="dashboard-breakdown-list">
                                            <div class="dashboard-breakdown-empty">—</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4 mb-3">
                                    <div class="dashboard-sales-detail h-100">
                                        <div class="dashboard-sales-detail-title">Poklon zamatanje</div>
                                        <div class="dashboard-gift-wrap-summary">
                                            <div class="dashboard-gift-wrap-item">
                                                <strong id="yearly-gift-wrap-orders">0</strong>
                                                <span>narudžbi</span>
                                            </div>
                                            <div class="dashboard-gift-wrap-item">
                                                <strong id="yearly-gift-wrap-items">0</strong>
                                                <span>kom.</span>
                                            </div>
                                            <div class="dashboard-gift-wrap-item">
                                                <strong id="yearly-gift-wrap-total">0,00 €</strong>
                                                <span>ukupno</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="chart-container large">
                                <canvas class="js-chartjs-overview"></canvas>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        @endif

        <!-- Top Products and Latest Orders -->
        <div class="row dashboard-list-row">
            <div class="col-xl-6">
                <div class="block block-rounded dashboard-list-block">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Zadnje narudžbe</h3>
                    </div>
                    <div class="block-content">
                        <table class="table table-borderless table-striped table-vcenter font-size-sm dashboard-list-table dashboard-orders-table">
                            <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td class="font-w600 text-center dashboard-list-id" style="width: 5%;">
                                        <a href="{{ route('orders.show', ['order' => $order]) }}">{{ $order->id }}</a>
                                    </td>
                                    <td class="dashboard-list-main">
                                        <a href="{{ route('orders.show', ['order' => $order]) }}">{{ $order->payment_fname . ' ' . $order->payment_lname }}</a>
                                    </td>
                                    <td class="text-right dashboard-list-status" style="width: 5%;">
                                        @php($status = $order->status)
                                        <span class="badge badge-pill badge-{{ $status->color ?? 'secondary' }}">
                                            {{ $status->title ?? ('Nepoznat status (#' . $order->order_status_id . ')') }}
                                        </span>
                                    </td>
                                    <td class="font-w600 text-right dashboard-list-price" style="width: 20%;">{{ \App\Helpers\Currency::main($order->total, true) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="block block-rounded dashboard-list-block">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Zadnje prodani artikli</h3>
                    </div>
                    <div class="block-content">
                        <table class="table table-borderless table-striped table-vcenter font-size-sm dashboard-list-table dashboard-products-table">
                            <tbody>
                            @foreach ($products->take(9) as $product)
                                <tr>
                                    <td class="text-center dashboard-list-id" style="width: 5%;">
                                        <a class="font-w600" href="{{ route('products.edit', ['product' => $product->product_id]) }}">{{ $product->id }}</a>
                                    </td>
                                    <td class="dashboard-list-main">
                                        <a href="{{ route('products.edit', ['product' => $product->product_id]) }}">{{ $product->name }}</a>
                                    </td>
                                    <td class="font-w600 text-right dashboard-list-price" style="width: 20%;">{{ \App\Helpers\Currency::main($product->price, true) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('css_after')
    <style>
        .dashboard-content {
            padding-top: 1.25rem;
        }

        .chart-container {
            position: relative;
            width: 100%;
        }

        .chart-container.small { height: 200px; }
        .chart-container.medium { height: 280px; }
        .chart-container.large { height: 340px; }

        .dashboard-kpi-grid {
            margin-bottom: .25rem;
        }

        .dashboard-kpi-grid > .dashboard-kpi-col {
            display: block;
        }

        .dashboard-kpi-card {
            position: relative;
            min-width: 100%;
            margin-bottom: 1rem;
            overflow: hidden;
            border: 1px solid #e8edf5;
            border-left-width: 4px;
            box-shadow: 0 1px 3px rgba(31, 45, 61, .06);
            transition: box-shadow .15s ease, transform .15s ease;
        }

        .dashboard-kpi-card:hover {
            box-shadow: 0 6px 18px rgba(31, 45, 61, .09);
            transform: translateY(-1px);
        }

        .dashboard-kpi-content {
            display: flex;
            min-height: 0;
            flex-direction: column;
            align-items: stretch;
            justify-content: flex-start;
            padding: 1rem 1.2rem 1.05rem !important;
        }

        .dashboard-kpi-head {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            min-height: 2.15rem;
            gap: .7rem;
        }

        .dashboard-kpi-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.25rem;
            height: 2.25rem;
            flex: 0 0 auto;
            border-radius: .35rem;
            font-size: .95rem;
        }

        .dashboard-kpi-card-warning {
            border-left-color: #f3a41d;
        }

        .dashboard-kpi-card-warning .dashboard-kpi-icon {
            color: #b76f00;
            background: #fff4dc;
        }

        .dashboard-kpi-card-warning .dashboard-kpi-value {
            color: #f3a41d;
        }

        .dashboard-kpi-card-success {
            border-left-color: #75b843;
        }

        .dashboard-kpi-card-success .dashboard-kpi-icon {
            color: #4f8425;
            background: #eef8e7;
        }

        .dashboard-kpi-card-success .dashboard-kpi-value {
            color: #75b843;
        }

        .dashboard-kpi-card-info {
            border-left-color: #3f9be5;
        }

        .dashboard-kpi-card-info .dashboard-kpi-icon {
            color: #1f6fae;
            background: #e8f4ff;
        }

        .dashboard-kpi-card-info .dashboard-kpi-value {
            color: #3f9be5;
        }

        .dashboard-kpi-card-primary {
            border-left-color: #e50077;
        }

        .dashboard-kpi-card-primary .dashboard-kpi-icon {
            color: #b6005f;
            background: #ffe8f4;
        }

        .dashboard-kpi-card-primary .dashboard-kpi-value {
            color: #e50077;
        }

        .dashboard-kpi-label,
        .dashboard-sales-kpi-label {
            line-height: 1.3;
        }

        .dashboard-kpi-label {
            flex: 1 1 auto;
            color: #687482;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: 0;
            text-align: left;
            text-transform: uppercase;
        }

        .dashboard-kpi-value {
            margin: .85rem 0 .65rem;
            font-size: 1.75rem;
            line-height: 1.05;
        }

        .dashboard-kpi-total {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: .6rem;
            padding-top: .7rem;
            border-top: 1px solid #edf1f7;
            color: #687482;
            line-height: 1.25;
        }

        .dashboard-kpi-total span {
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .dashboard-kpi-total strong {
            color: #3f4854;
            font-size: .92rem;
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
        }

        .dashboard-sales-block {
            margin-bottom: 1.5rem;
        }

        .dashboard-sales-block .block-content {
            padding-bottom: 1.15rem;
            overflow-x: hidden;
        }

        .sales-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .95rem 1.15rem;
        }

        .sales-filters {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .75rem;
            flex-wrap: wrap;
            margin-left: auto;
        }

        .sales-filter-item {
            flex: 0 0 auto;
        }

        .sales-filter-select {
            min-width: 180px;
        }

        .dashboard-sales-tabs {
            border-bottom: 1px solid #e8edf5;
        }

        .dashboard-sales-tabs .nav-link {
            border: 0;
            border-bottom: 2px solid transparent;
            color: #687482;
            font-weight: 600;
            letter-spacing: 0;
            padding: .65rem .9rem;
        }

        .dashboard-sales-tabs .nav-link:hover {
            color: #3f4854;
        }

        .dashboard-sales-tabs .nav-link.active {
            border-bottom-color: #e50077;
            color: #e50077;
            background: transparent;
        }

        .dashboard-sales-kpis > .col-12 > .row {
            margin-right: 0;
            margin-left: 0;
            overflow: hidden;
            border: 1px solid #e8edf5;
            border-radius: .35rem;
            background: #f8fafc;
        }

        .dashboard-sales-kpis > .col-12 > .row > [class*=col-] {
            margin-bottom: 0 !important;
            padding-right: 0;
            padding-left: 0;
        }

        .dashboard-sales-kpi {
            height: 100%;
            padding: .9rem 1rem;
            border-right: 1px solid #e8edf5;
        }

        .dashboard-sales-kpis > .col-12 > .row > [class*=col-]:last-child .dashboard-sales-kpi {
            border-right: 0;
        }

        .dashboard-sales-kpi-content {
            min-width: 0;
        }

        .dashboard-sales-kpi-label {
            color: #687482 !important;
            font-size: .72rem !important;
            font-weight: 700;
            letter-spacing: 0;
        }

        .dashboard-sales-kpi-value {
            margin-top: .35rem;
            color: #3f4854;
            font-size: 1.35rem;
            line-height: 1.15;
        }

        .dashboard-sales-kpi-line {
            display: flex;
            align-items: baseline;
            gap: .55rem;
            flex-wrap: wrap;
        }

        .dashboard-sales-kpi-meta {
            color: #687482;
            font-size: .78rem;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
        }

        .dashboard-sales-details {
            margin-right: -.5rem;
            margin-left: -.5rem;
        }

        .dashboard-sales-details > [class*=col-] {
            padding-right: .5rem;
            padding-left: .5rem;
        }

        .dashboard-sales-detail {
            position: relative;
            display: flex;
            min-height: 10.25rem;
            flex-direction: column;
            padding: .9rem 1rem;
            border: 1px solid #e8edf5;
            border-radius: .35rem;
            background: #fff;
            box-shadow: 0 1px 2px rgba(31, 45, 61, .03);
        }

        .dashboard-sales-detail-title {
            color: #687482;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: 0;
            line-height: 1.3;
            text-transform: uppercase;
        }

        .dashboard-breakdown-list {
            display: grid;
            gap: .45rem;
            margin-top: .65rem;
            max-height: 7.35rem;
            padding-right: .25rem;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }

        .dashboard-breakdown-list::-webkit-scrollbar {
            width: 5px;
        }

        .dashboard-breakdown-list::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: #cbd5e1;
        }

        .dashboard-breakdown-list::-webkit-scrollbar-track {
            background: transparent;
        }

        .dashboard-breakdown-list.is-scrollable {
            padding-bottom: .25rem;
        }

        .dashboard-breakdown-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: baseline;
            min-height: 2.05rem;
            gap: .75rem;
            padding-bottom: .45rem;
            border-bottom: 1px solid #edf1f7;
        }

        .dashboard-breakdown-row:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .dashboard-breakdown-label {
            min-width: 0;
            overflow: hidden;
            color: #3f4854;
            font-size: .86rem;
            font-weight: 600;
            line-height: 1.25;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dashboard-breakdown-value {
            flex: 0 0 auto;
            color: #3f4854;
            font-size: .84rem;
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
        }

        .dashboard-breakdown-value small {
            display: block;
            color: #687482;
            font-size: .68rem;
            font-weight: 600;
            line-height: 1.2;
        }

        .dashboard-breakdown-empty {
            margin-top: .65rem;
            color: #8a96a5;
            font-size: .84rem;
            font-weight: 600;
        }

        .dashboard-gift-wrap-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .65rem;
            margin-top: .65rem;
            align-items: stretch;
        }

        .dashboard-gift-wrap-item {
            display: flex;
            min-height: 3.55rem;
            flex-direction: column;
            justify-content: center;
            min-width: 0;
            padding: .55rem .6rem;
            border-radius: .3rem;
            background: #f8fafc;
            text-align: center;
        }

        .dashboard-gift-wrap-item strong,
        .dashboard-gift-wrap-item span {
            display: block;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dashboard-gift-wrap-item strong {
            color: #3f4854;
            font-size: 1rem;
            line-height: 1.2;
        }

        .dashboard-gift-wrap-item span {
            margin-top: .15rem;
            color: #687482;
            font-size: .68rem;
            font-weight: 700;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .dashboard-list-row {
            margin-top: .5rem;
        }

        .dashboard-list-block {
            height: calc(100% - 1.75rem);
        }

        .dashboard-list-block .block-header {
            padding: .95rem 1.15rem;
        }

        .dashboard-list-block .block-content {
            padding-top: .75rem;
            padding-bottom: 1rem;
        }

        .dashboard-list-table {
            width: 100%;
            table-layout: fixed;
            margin-bottom: 0;
        }

        .dashboard-list-table td {
            padding: .65rem .75rem;
            vertical-align: middle;
        }

        .dashboard-list-id {
            width: 4.5rem !important;
            white-space: nowrap;
        }

        .dashboard-list-status {
            width: 6.5rem !important;
            white-space: nowrap;
        }

        .dashboard-list-price {
            width: 5.5rem !important;
        }

        .dashboard-list-table tbody tr {
            border-top: 1px solid #edf1f7;
        }

        .dashboard-list-table tbody tr:first-child {
            border-top: 0;
        }

        .dashboard-list-table.table-striped tbody tr:nth-of-type(odd) {
            background-color: #f8fafc;
        }

        .dashboard-list-table tbody tr:hover {
            background-color: #fff7fb;
        }

        .dashboard-list-table a {
            color: #e50077;
        }

        .dashboard-list-main {
            min-width: 0;
            overflow: hidden;
        }

        .dashboard-list-main a {
            display: block;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dashboard-list-id a {
            font-weight: 700;
        }

        .dashboard-list-price {
            color: #3f4854;
            white-space: nowrap;
        }

        .dashboard-list-status .badge {
            padding: .27rem .55rem;
            font-size: .68rem;
            line-height: 1;
        }

        @media (max-width: 767.98px) {
            .dashboard-content {
                padding-top: .875rem;
            }

            .dashboard-kpi-grid {
                margin-right: -.4rem;
                margin-left: -.4rem;
                margin-bottom: .15rem;
            }

            .dashboard-kpi-col {
                padding-right: .4rem;
                padding-left: .4rem;
            }

            .dashboard-kpi-card {
                margin-bottom: .8rem;
            }

            .dashboard-kpi-content {
                padding: .8rem .75rem .85rem !important;
            }

            .dashboard-kpi-head {
                align-items: center;
                min-height: 2rem;
                gap: .45rem;
            }

            .dashboard-kpi-icon {
                width: 1.75rem;
                height: 1.75rem;
                font-size: .82rem;
            }

            .dashboard-kpi-value {
                margin: .65rem 0 .5rem;
                font-size: 1.45rem;
            }

            .dashboard-kpi-label {
                font-size: .68rem;
                line-height: 1.2;
                text-align: left;
            }

            .dashboard-kpi-total {
                display: block;
                padding-top: .55rem;
                font-size: .76rem;
                text-align: left;
            }

            .dashboard-kpi-total span {
                display: block;
                margin-bottom: .12rem;
                font-size: .62rem;
            }

            .dashboard-kpi-total strong {
                display: block;
                font-size: .8rem;
                text-align: left;
            }

            .dashboard-sales-block {
                margin-top: .1rem !important;
            }

            .sales-header {
                align-items: stretch;
                padding: .9rem .95rem;
            }

            .sales-header .block-title {
                width: 100%;
            }

            .sales-filters {
                display: grid;
                width: 100%;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                justify-content: stretch;
                gap: .5rem;
                margin-left: 0;
            }

            .sales-filter-item {
                min-width: 0;
            }

            .sales-filter-select {
                width: 100%;
                min-width: 0;
            }

            .dashboard-sales-tabs {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: .5rem;
                border-bottom: 0;
            }

            .dashboard-sales-tabs .nav-item {
                margin-bottom: 0;
            }

            .dashboard-sales-tabs .nav-link {
                width: 100%;
                min-height: 42px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 1px solid #dfe7f3;
                border-radius: .25rem;
                text-align: center;
                white-space: normal;
            }

            .dashboard-sales-tabs .nav-link.active {
                border-color: #e50077;
                color: #e50077;
            }

            .dashboard-sales-kpis {
                margin-bottom: .5rem !important;
            }

            .dashboard-sales-kpis > .col-12 > .row {
                margin-right: 0;
                margin-left: 0;
            }

            .dashboard-sales-kpis > .col-12 > .row > [class*=col-]:nth-child(2n) .dashboard-sales-kpi {
                border-right: 0;
            }

            .dashboard-sales-kpis > .col-12 > .row > [class*=col-]:nth-child(n+3) .dashboard-sales-kpi {
                border-top: 1px solid #e8edf5;
            }

            .dashboard-sales-kpi {
                padding: .75rem .8rem;
            }

            .dashboard-sales-kpi-content {
                min-height: 0;
            }

            .dashboard-sales-kpi-value {
                font-size: 1.15rem;
                line-height: 1.2;
            }

            .dashboard-sales-kpi-label {
                font-size: .66rem !important;
            }

            .chart-container.large {
                height: 260px;
            }

            .dashboard-list-row {
                margin-top: .3rem;
            }
        }

        @media (max-width: 575.98px) {
            .dashboard-list-block .block-content {
                overflow-x: visible;
            }

            .dashboard-list-table {
                min-width: 0 !important;
                margin-bottom: 0;
            }

            .dashboard-list-table,
            .dashboard-list-table tbody,
            .dashboard-list-table tr,
            .dashboard-list-table td {
                display: block;
                width: 100% !important;
            }

            .dashboard-list-table tr {
                position: relative;
                margin-bottom: .5rem;
                padding: .65rem .75rem;
                border: 1px solid #edf1f7;
                border-radius: .35rem;
                background: #fff;
            }

            .dashboard-orders-table tr {
                min-height: 4.15rem;
            }

            .dashboard-list-table tr:last-child {
                margin-bottom: 0;
            }

            .dashboard-list-table td {
                padding: .1rem 0 !important;
                border: 0 !important;
                text-align: left !important;
            }

            .dashboard-list-table .dashboard-list-id {
                font-size: .74rem;
                line-height: 1.2;
            }

            .dashboard-list-table .dashboard-list-main {
                margin-top: .12rem;
                padding-right: 6.2rem !important;
                width: auto !important;
                max-width: none;
                overflow: hidden;
                font-size: .86rem;
                line-height: 1.3;
            }

            .dashboard-list-table .dashboard-list-main a {
                width: 100%;
                max-width: 100%;
                white-space: nowrap;
            }

            .dashboard-list-table .dashboard-list-price {
                position: absolute;
                top: .65rem;
                right: .75rem;
                width: auto !important;
                font-size: .86rem;
                line-height: 1.2;
                text-align: right !important;
                white-space: nowrap;
            }

            .dashboard-list-table .dashboard-list-status {
                margin-top: .4rem;
            }

            .dashboard-orders-table .dashboard-list-status {
                position: absolute;
                top: 1.9rem;
                right: .75rem;
                width: auto !important;
                max-width: 6.25rem;
                margin-top: 0;
                text-align: right !important;
            }

            .dashboard-orders-table .dashboard-list-status .badge {
                max-width: 100%;
                padding: .16rem .38rem;
                overflow: hidden;
                font-size: .54rem;
                line-height: 1.05;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
        }

        @media (max-width: 374.98px) {
            .sales-filters,
            .dashboard-sales-tabs {
                grid-template-columns: 1fr;
            }

            .dashboard-kpi-content {
                padding-right: .65rem !important;
                padding-left: .65rem !important;
            }

            .dashboard-kpi-label {
                font-size: .62rem;
            }

            .dashboard-kpi-total strong {
                font-size: .74rem;
            }
        }
    </style>
@endpush

@push('js_after')
    <script src="{{ asset('js/plugins/chart.js/Chart.bundle.min.js') }}"></script>

    <script>
        // =====================
        // PROMET I NARUDŽBE (po danima u mjesecu)
        // =====================
        let ctx = document.getElementById('salesChart').getContext('2d');
        let salesChart;

        // Formatiranje valuta i brojeva (hr-HR, EUR)
        const fmtCurrency = new Intl.NumberFormat('hr-HR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 2 });
        const fmtInt = new Intl.NumberFormat('hr-HR', { maximumFractionDigits: 0 });
        const fmtDecimal = new Intl.NumberFormat('hr-HR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // Helper: složi pune serije za sve dane u mjesecu (prazno => 0)
        function prepareMonthSeries(year, month, raw) {
            const daysInMonth = new Date(year, month, 0).getDate(); // month: 1–12
            const map = {};
            (raw || []).forEach(d => {
                const day = parseInt(d.day, 10);
                map[day] = {
                    total: Number(d.total) || 0,
                    orders: Number(d.orders) || 0,
                    itemQuantity: Number(d.item_quantity) || 0
                };
            });

            const labels = [];
            const values = [];
            const counts = [];
            const itemQuantities = [];

            for (let day = 1; day <= daysInMonth; day++) {
                labels.push(day + '.');
                const row = map[day] || { total: 0, orders: 0, itemQuantity: 0 };
                values.push(row.total);
                counts.push(row.orders);
                itemQuantities.push(row.itemQuantity);
            }
            return { labels, values, counts, itemQuantities };
        }

        // Izračun KPI-ja i upis u DOM
        function updateMonthStats(total, orders, averageItems) {
            const aov = orders > 0 ? (total / orders) : 0;
            const avgItems = Number.isFinite(Number(averageItems)) ? Number(averageItems) : 0;
            document.getElementById('kpi-month-total').textContent  = fmtCurrency.format(total);
            document.getElementById('kpi-month-orders').textContent = fmtInt.format(orders);
            document.getElementById('kpi-month-items-avg').textContent = fmtDecimal.format(avgItems) + ' art./nar.';
            document.getElementById('kpi-month-aov').textContent    = fmtCurrency.format(aov);
        }

        function renderBreakdownList(elementId, rows) {
            const el = document.getElementById(elementId);
            el.innerHTML = '';
            el.classList.toggle('is-scrollable', Boolean(rows && rows.length > 3));

            if (! rows || ! rows.length) {
                const empty = document.createElement('div');
                empty.className = 'dashboard-breakdown-empty';
                empty.textContent = 'Nema podataka';
                el.appendChild(empty);
                return;
            }

            rows.forEach(row => {
                const item = document.createElement('div');
                item.className = 'dashboard-breakdown-row';

                const label = document.createElement('span');
                label.className = 'dashboard-breakdown-label';
                label.textContent = row.label || 'Nepoznato';
                label.title = label.textContent;

                const value = document.createElement('span');
                value.className = 'dashboard-breakdown-value';
                value.textContent = fmtInt.format(Number(row.orders) || 0) + ' nar.';

                const total = document.createElement('small');
                total.textContent = fmtCurrency.format(Number(row.total) || 0);
                value.appendChild(total);

                item.appendChild(label);
                item.appendChild(value);
                el.appendChild(item);
            });
        }

        function updateMonthlyDetails(summary) {
            const data = summary || {};
            const giftWrap = data.gift_wrap || {};

            renderBreakdownList('monthly-payment-methods', data.payment_methods || []);
            renderBreakdownList('monthly-shipping-methods', data.shipping_methods || []);

            document.getElementById('monthly-gift-wrap-orders').textContent = fmtInt.format(Number(giftWrap.orders) || 0);
            document.getElementById('monthly-gift-wrap-items').textContent = fmtInt.format(Number(giftWrap.items) || 0);
            document.getElementById('monthly-gift-wrap-total').textContent = fmtCurrency.format(Number(giftWrap.total) || 0);
        }

        function updateYearStats(summary) {
            const data = summary || {};
            const orders = Number(data.orders) || 0;
            const total = Number(data.total) || 0;
            const averageItems = Number(data.avg_items) || 0;
            const giftWrap = data.gift_wrap || {};
            const aov = orders > 0 ? (total / orders) : 0;

            document.getElementById('kpi-year-total').textContent = fmtCurrency.format(total);
            document.getElementById('kpi-year-orders').textContent = fmtInt.format(orders);
            document.getElementById('kpi-year-items-avg').textContent = fmtDecimal.format(averageItems) + ' art./nar.';
            document.getElementById('kpi-year-gift-wrap-orders').textContent = fmtInt.format(Number(giftWrap.orders) || 0);
            document.getElementById('kpi-year-aov').textContent = fmtCurrency.format(aov);
        }

        function updateYearDetails(summary) {
            const data = summary || {};
            const giftWrap = data.gift_wrap || {};

            renderBreakdownList('yearly-payment-methods', data.payment_methods || []);
            renderBreakdownList('yearly-shipping-methods', data.shipping_methods || []);

            document.getElementById('yearly-gift-wrap-orders').textContent = fmtInt.format(Number(giftWrap.orders) || 0);
            document.getElementById('yearly-gift-wrap-items').textContent = fmtInt.format(Number(giftWrap.items) || 0);
            document.getElementById('yearly-gift-wrap-total').textContent = fmtCurrency.format(Number(giftWrap.total) || 0);
        }

        function loadMonth(year, month) {
            $.get('{{ route('dashboard.chart.month') }}', { year, month }, function(data) {
                const days = Array.isArray(data) ? data : (data.days || []);
                const summary = Array.isArray(data) ? {} : (data.summary || {});
                const series = prepareMonthSeries(Number(year), Number(month), days);

                // KPI: sumiraj promet i narudžbe za mjesec
                const monthTotal  = Number(summary.total) || series.values.reduce((s, v) => s + Number(v || 0), 0);
                const monthOrders = Number(summary.orders) || series.counts.reduce((s, v) => s + Number(v || 0), 0);
                const monthItems = Number(summary.item_quantity) || series.itemQuantities.reduce((s, v) => s + Number(v || 0), 0);
                const monthAverageItems = Number(summary.avg_items) || (monthOrders > 0 ? monthItems / monthOrders : 0);
                updateMonthStats(monthTotal, monthOrders, monthAverageItems);
                updateMonthlyDetails(summary);

                renderChart(series);
            });
        }

        function loadYear(year) {
            $.get('{{ route('dashboard.chart.year') }}', { year }, function(data) {
                const summary = data.summary || {};

                updateYearStats(summary);
                updateYearDetails(summary);
            });
        }

        function renderChart(series) {
            const labels = series.labels;
            const values = series.values;
            const counts = series.counts;

            if (salesChart) salesChart.destroy();
            salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Promet (€)',
                            data: values,
                            borderColor: 'rgba(6, 101, 208, 1)',
                            backgroundColor: 'rgba(6, 101, 208, .3)',
                            fill: true,
                            yAxisID: 'y-axis-1',
                            tension: 0,
                            lineTension: 0
                        },
                        {
                            label: 'Broj narudžbi',
                            data: counts,
                            borderColor: 'rgba(0, 51, 153, 1)',
                            backgroundColor: 'rgba(0, 51, 153, .2)',
                            fill: false,
                            yAxisID: 'y-axis-2',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: 'rgba(0, 51, 153, 1)',
                            pointBorderColor: '#fff',
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
                        callbacks: {
                            label: function(tooltipItem, data) {
                                const label = data.datasets[tooltipItem.datasetIndex].label || '';
                                const value = tooltipItem.yLabel;
                                if (label.includes('Promet')) {
                                    return label + ': ' + value + '€';
                                } else {
                                    return label + ': ' + value;
                                }
                            }
                        }
                    },
                    scales: {
                        yAxes: [
                            {
                                id: 'y-axis-1',
                                position: 'left',
                                ticks: {
                                    beginAtZero: true,
                                    callback: value => value + '€'
                                }
                            },
                            {
                                id: 'y-axis-2',
                                position: 'right',
                                ticks: { beginAtZero: true },
                                gridLines: { drawOnChartArea: false }
                            }
                        ]
                    }
                }
            });
        }

        // Automatski refresh na promjenu selecta
        $('#chart-year, #chart-month').on('change', function() {
            const year  = $('#chart-year').val();
            const month = $('#chart-month').val();
            loadMonth(year, month);
            loadYear(year);
        });

        // Inicijalni prikaz trenutnog mjeseca
        const now = new Date();
        $('#chart-year').val(now.getFullYear());
        $('#chart-month').val(now.getMonth() + 1);
        loadMonth(now.getFullYear(), now.getMonth() + 1);
        loadYear(now.getFullYear());

        // =====================
        // MJESEČNI PREGLED (ova vs prošla godina)
        // =====================
        $(() => {
            let this_year = sort('{!! $this_year !!}');
            let last_year = sort('{!! $last_year !!}');

            let chartOverviewCon  = jQuery('.js-chartjs-overview');

            let chartOverviewOptions = {
                maintainAspectRatio: false,
                responsive: true,
                scales: {
                    yAxes: [{
                        ticks: { suggestedMax: this_year.top }
                    }]
                },
                tooltips: {
                    intersect: false,
                    callbacks: {
                        label: function(tooltipItems, data) {
                            return  tooltipItems.yLabel + '€';
                        }
                    }
                }
            };

            let chartOverviewData = {
                labels: this_year.names,
                datasets: [
                    {
                        label: 'Ova godina',
                        fill: false,
                        borderColor: 'rgba(6, 101, 208, 1)',
                        backgroundColor: 'rgba(6, 101, 208, .3)',
                        data: this_year.values,
                        tension: 0, lineTension: 0,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Prošla godina',
                        fill: false,
                        borderColor: 'rgba(0, 51, 153, 1)',
                        backgroundColor: 'rgba(0, 51, 153, .2)',
                        data: last_year.values,
                        tension: 0, lineTension: 0,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }
                ]
            };

            if (chartOverviewCon.length) {
                new Chart(chartOverviewCon, {
                    type: 'line',
                    data: chartOverviewData,
                    options: chartOverviewOptions
                });
            }
        });

        // helper za dekodiranje podataka iz PHP
        function sort(data) {
            let data_data = JSON.parse(data.replace(/&quot;/g,'"'));
            let data_names = [];
            let data_values = [];
            let top = 0;
            let step_size = 100;

            for (let i = 0; i < data_data.length; i++) {
                data_names.push(data_data[i].title + '.');
                data_values.push(data_data[i].value);
            }
            for (let i = 0; i < data_values.length; i++) {
                if (data_values[i] > top) top = data_values[i];
            }

            return { values: data_values, names: data_names, top: top, step: step_size };
        }
    </script>
@endpush
