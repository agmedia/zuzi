@once
    @push('css_after')
        <style>
            .account-titlebar,
            .account-page {
                max-width: 1480px;
                margin-left: auto;
                margin-right: auto;
            }

            .account-titlebar {
                padding-left: .75rem;
                padding-right: .75rem;
            }

            .account-layout {
                --account-card-min-height: 600px;
            }

            .account-sidebar-column,
            .account-content-column {
                display: flex;
                flex-direction: column;
            }

            .account-sidebar-card,
            .account-content-card {
                flex: 1 1 auto;
                width: 100%;
                background: #fff;
                border: 1px solid rgba(55, 63, 80, .07);
                border-radius: .5rem;
                box-shadow: 0 1.25rem 3rem rgba(43, 52, 69, .08);
            }

            .account-sidebar-card {
                overflow: hidden;
            }

            .account-user-panel {
                display: flex;
                align-items: center;
                gap: .9rem;
                padding: 1.35rem 1.5rem;
            }

            .account-avatar {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex: 0 0 auto;
                width: 3rem;
                height: 3rem;
                border-radius: 50%;
                background: #fdf0f7;
                color: var(--cz-primary);
                font-weight: 700;
                letter-spacing: 0;
            }

            .account-user-name {
                color: #373f50;
                font-size: 1rem;
                font-weight: 700;
                line-height: 1.25;
            }

            .account-user-email {
                color: #5b6680;
                font-size: .9rem;
                overflow-wrap: anywhere;
            }

            .account-user-panel .min-w-0 {
                min-width: 0;
            }

            .account-mobile-nav {
                flex-shrink: 0;
            }

            .account-sidebar-kicker {
                background: #f8f6f9;
                color: #6c7485;
                font-size: .78rem;
                font-weight: 700;
                letter-spacing: 0;
                padding: .85rem 1.5rem;
                text-transform: uppercase;
            }

            .account-nav-list {
                padding: .45rem;
            }

            .account-nav-item {
                margin-bottom: .25rem;
            }

            .account-nav-item:last-child {
                margin-bottom: 0;
            }

            .account-nav-link {
                gap: .75rem;
                min-height: 3.15rem;
                border-radius: .45rem;
                color: #535d72;
                font-weight: 600;
                transition: color .18s ease, background-color .18s ease, box-shadow .18s ease;
            }

            .account-nav-link > i {
                width: 1.35rem;
                margin-top: 0;
                text-align: center;
                opacity: .68;
            }

            .account-nav-link:hover {
                background: #fbf4f8;
                color: var(--cz-primary);
            }

            .account-nav-link.active {
                background: #fdf0f7;
                color: var(--cz-primary);
                box-shadow: inset 3px 0 0 var(--cz-primary);
            }

            .account-nav-link.active > i {
                opacity: 1;
            }

            .account-referral-panel {
                border-top: 1px solid #edf0f5;
                background: #fbfcff;
                padding: 1.25rem 1.5rem;
            }

            .account-referral-panel small {
                color: #5b6680;
                line-height: 1.45;
            }

            .account-content-card {
                display: flex;
                flex-direction: column;
                padding: 1.5rem;
            }

            .account-card-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 1rem;
                margin-bottom: 1.5rem;
                padding-bottom: 1.35rem;
                border-bottom: 1px solid #edf0f5;
            }

            .account-card-titlewrap {
                display: flex;
                align-items: flex-start;
                gap: .85rem;
                min-width: 0;
            }

            .account-card-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex: 0 0 auto;
                width: 2.75rem;
                height: 2.75rem;
                border-radius: .5rem;
                background: #fdf0f7;
                color: var(--cz-primary);
                font-size: 1.15rem;
            }

            .account-card-title {
                color: #373f50;
                font-size: 1.1rem;
                font-weight: 700;
                line-height: 1.3;
                margin-bottom: .2rem;
            }

            .account-card-subtitle {
                color: #6c7485;
                font-size: .9rem;
                line-height: 1.45;
                margin-bottom: 0;
            }

            .account-logout-button {
                flex-shrink: 0;
                white-space: nowrap;
            }

            .account-section {
                margin-bottom: 1.5rem;
            }

            .account-section-title {
                display: flex;
                align-items: center;
                gap: .5rem;
                margin-bottom: 1rem;
                padding-bottom: .75rem;
                border-bottom: 1px solid #edf0f5;
                color: #373f50;
                font-size: 1rem;
                font-weight: 700;
            }

            .account-form-actions {
                margin-top: .25rem;
                padding-top: 1.25rem;
                border-top: 1px solid #edf0f5;
            }

            .account-table-shell {
                overflow-x: auto;
                overflow-y: hidden;
                border: 1px solid #edf0f5;
                border-radius: .5rem;
            }

            .account-table-shell .table {
                margin-bottom: 0;
                vertical-align: middle;
            }

            .account-table-shell thead th {
                border-bottom: 1px solid #e2e7ef;
                background: #fbfcff;
                color: #373f50;
                font-weight: 700;
                white-space: nowrap;
            }

            .account-table-shell tbody tr:last-child td {
                border-bottom: 0;
            }

            .account-status-badge {
                border-radius: .35rem;
                font-weight: 600;
            }

            .account-empty-state {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 260px;
                border: 1px dashed #dfe5ef;
                border-radius: .5rem;
                background: #fbfcff;
                color: #6c7485;
                text-align: center;
            }

            .account-summary-strip {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: .75rem;
                margin-bottom: 1.15rem;
                padding: 1rem;
                border: 1px solid #edf0f5;
                border-radius: .5rem;
                background: #fbfcff;
            }

            .account-points-pill {
                display: inline-flex;
                align-items: center;
                gap: .45rem;
                border-radius: .45rem;
                background: #fdf0f7;
                color: var(--cz-primary);
                font-weight: 700;
                padding: .55rem .75rem;
            }

            .account-notice-panel {
                max-width: 700px;
                margin: 0 auto;
                padding-top: 1rem;
                text-align: center;
            }

            .account-notice-panel__title {
                color: var(--cz-primary);
                font-size: 1.45rem;
                font-weight: 700;
                letter-spacing: 0;
                line-height: 1.2;
            }

            .account-notice-panel__text {
                color: #232735;
                font-size: .98rem;
                line-height: 1.55;
            }

            .account-notice-panel__coupon {
                max-width: 560px;
                margin: 1.45rem auto;
                padding: 1.35rem 1.25rem;
                border: 2px dashed var(--cz-primary);
                border-radius: .5rem;
                background: #fffafb;
            }

            .account-notice-panel__coupon-label {
                color: #232735;
                font-size: .95rem;
                line-height: 1.3;
            }

            .account-notice-panel__code {
                color: var(--cz-primary);
                font-size: 1.85rem;
                font-weight: 700;
                letter-spacing: 0;
                line-height: 1.2;
            }

            .account-notice-panel__discount {
                color: #232735;
                font-size: .95rem;
                font-weight: 700;
                line-height: 1.35;
            }

            .account-notice-panel__button {
                min-width: 200px;
                font-size: .95rem;
                font-weight: 700;
                padding-bottom: .7rem;
                padding-top: .7rem;
            }

            .account-notice-panel__date {
                color: #6c7485;
                font-size: .9rem;
            }

            .account-recommendations {
                border-top: 1px solid #edf0f5;
            }

            @media (min-width: 992px) {
                .account-sidebar-card,
                .account-content-card {
                    min-height: var(--account-card-min-height);
                }
            }

            @media (min-width: 768px) {
                .account-content-card {
                    padding: 2rem;
                }
            }

            @media (max-width: 991.98px) {
                .account-card-header {
                    flex-direction: column;
                }

                .account-logout-button {
                    align-self: flex-start;
                }
            }

            @media (max-width: 575.98px) {
                .account-user-panel {
                    align-items: flex-start;
                    flex-direction: column;
                }

                .account-mobile-nav {
                    width: 100%;
                }

                .account-content-card {
                    padding: 1.15rem;
                }

                .account-card-icon {
                    width: 2.45rem;
                    height: 2.45rem;
                }

                .account-notice-panel__title {
                    font-size: 1.25rem;
                }

                .account-notice-panel__text,
                .account-notice-panel__coupon-label,
                .account-notice-panel__discount,
                .account-notice-panel__button {
                    font-size: .9rem;
                }

                .account-notice-panel__code {
                    font-size: 1.55rem;
                }

                .account-notice-panel__button {
                    min-width: 0;
                    width: 100%;
                }

                .account-form-actions .btn {
                    width: 100%;
                }
            }
        </style>
    @endpush
@endonce

<div class="page-title-overlap pt-2" >
    <section class="account-titlebar d-lg-flex justify-content-between py-2 py-lg-3">
        <div class="order-lg-2 mb-3 mb-lg-0 pt-lg-2">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-dark flex-lg-nowrap justify-content-center justify-content-lg-start">
                    <li class="breadcrumb-item"><a class="text-nowrap" href="/"><i class="ci-home"></i>Naslovnica</a></li>

                    <li class="breadcrumb-item text-nowrap active" aria-current="page">Moj račun</li>
                </ol>
            </nav>
        </div>
        <div class="order-lg-1 pe-lg-4 text-center text-lg-start">
            <h1 class="h3 text-primary mb-0">Moj račun</h1>
        </div>
    </section>
</div>
