<style>
    #cc-main {
        --cc-bg: #ffffff;
        --cc-primary-color: #373f50;
        --cc-secondary-color: #4b566b;
        --cc-link-color: #e50077;
        --cc-btn-primary-bg: #e50077;
        --cc-btn-primary-color: #ffffff;
        --cc-btn-primary-border-color: #e50077;
        --cc-btn-primary-hover-bg: #c70064;
        --cc-btn-primary-hover-color: #ffffff;
        --cc-btn-primary-hover-border-color: #c70064;
        --cc-btn-secondary-bg: #ffffff;
        --cc-btn-secondary-color: #373f50;
        --cc-btn-secondary-border-color: #e8d7e1;
        --cc-btn-secondary-hover-bg: #fff2f8;
        --cc-btn-secondary-hover-color: #e50077;
        --cc-btn-secondary-hover-border-color: rgba(229, 0, 119, 0.24);
        --cc-separator-border-color: #f1dce7;
        --cc-toggle-on-bg: #e50077;
        --cc-toggle-off-bg: #b8c2ce;
        --cc-toggle-readonly-bg: #f5d7e7;
        --cc-cookie-category-block-bg: #fff7fb;
        --cc-cookie-category-block-border: rgba(229, 0, 119, 0.12);
        --cc-cookie-category-block-hover-bg: #fff1f8;
        --cc-cookie-category-block-hover-border: rgba(229, 0, 119, 0.2);
        --cc-footer-bg: #fcf5f9;
        --cc-footer-color: #4b566b;
        --cc-footer-border-color: #f1dce7;
        --cc-overlay-bg: rgba(43, 52, 69, 0.58);
        --cc-modal-border-radius: 1.25rem;
        --cc-btn-border-radius: 0.95rem;
        --cc-font-family: "Quicksand", sans-serif;
    }

    #cc-main .cm,
    #cc-main .pm {
        position: relative;
        border-radius: 1.25rem;
        border: 1px solid rgba(229, 0, 119, 0.1);
        background:
            radial-gradient(circle at top right, rgba(229, 0, 119, 0.08), transparent 32%),
            linear-gradient(180deg, #ffffff 0%, #fffafc 100%);
        box-shadow: 0 24px 56px rgba(55, 63, 80, 0.18);
    }

    #cc-main .cm::before,
    #cc-main .pm::before {
        content: "";
        position: absolute;
        inset: 0 0 auto 0;
        height: 6px;
        background: linear-gradient(90deg, #e50077 0%, #ff82be 100%);
    }

    #cc-main .cm {
        max-width: 42rem;
        padding: 0;
    }

    #cc-main .cm__title,
    #cc-main .pm__title {
        color: #373f50;
        font-weight: 700;
        letter-spacing: 0.01em;
    }

    #cc-main .cm__desc,
    #cc-main .pm__section-desc,
    #cc-main .pm__section-title {
        color: #4b5563;
        line-height: 1.55;
    }

    #cc-main a,
    #cc-main .cc__link {
        color: #e50077;
    }

    #cc-main .pm__header {
        padding-top: 1.35rem;
    }

    #cc-main .pm__body {
        padding-top: 0.5rem;
    }

    #cc-main .cm__title {
        margin-bottom: 0.9rem;
    }

    #cc-main .cm__desc {
        margin-bottom: 1.2rem;
    }

    #cc-main .cm__body {
        padding: 2.1rem 2.5rem 1.35rem;
    }

    #cc-main .cm__footer,
    #cc-main .pm__footer {
        border-top: 1px solid #f1dce7;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(8px);
    }

    #cc-main .cm__footer {
        padding: 1rem 2.5rem 2rem;
    }

    #cc-main .cm__btn,
    #cc-main .pm__btn {
        border-radius: 0.75rem;
        min-height: 2.7rem;
        font-weight: 700;
        transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
    }

    #cc-main .cm__btn:not(.cm__btn--secondary),
    #cc-main .pm__btn:not(.pm__btn--secondary) {
        box-shadow: 0 12px 26px rgba(229, 0, 119, 0.18);
    }

    #cc-main .cm__btn:not(.cm__btn--secondary):hover,
    #cc-main .pm__btn:not(.pm__btn--secondary):hover {
        transform: translateY(-1px);
        box-shadow: 0 16px 30px rgba(229, 0, 119, 0.22);
    }

    #cc-main .cm__btn-group {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0;
    }

    #cc-main .cm__btn-group .cm__btn + .cm__btn {
        margin-top: 0.55rem;
    }

    #cc-main .cm__btn--secondary {
        border: 1px solid #e8d7e1;
        background: #fff;
        color: #373f50;
    }

    #cc-main .cm__btn--secondary:hover,
    #cc-main .pm__btn--secondary:hover {
        border-color: rgba(229, 0, 119, 0.24);
        background: #fff2f8;
        color: #e50077;
    }

    #cc-main .pm__badge {
        background: rgba(229, 0, 119, 0.1);
        color: #e50077;
        border-radius: 999px;
        padding: 0.28rem 0.7rem;
        font-weight: 700;
    }

    #cc-main .pm__section {
        border: 1px solid rgba(229, 0, 119, 0.12);
        border-radius: 1rem;
        background: #fff7fb;
        overflow: hidden;
        transition: border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
    }

    #cc-main .pm__section:hover {
        border-color: rgba(229, 0, 119, 0.2);
        background: #fff1f8;
        box-shadow: 0 10px 24px rgba(229, 0, 119, 0.08);
    }

    #cc-main .pm__section-title {
        color: #373f50;
        font-weight: 700;
    }

    #cc-main .pm__service-icon {
        border-color: #e50077;
    }

    #cc-main .pm__section-arrow svg {
        stroke: #e50077;
    }

    #cookie-consent-floating-button {
        position: fixed !important;
        left: 1rem !important;
        bottom: 5rem !important;
        z-index: 9500 !important;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        width: 3rem;
        height: 3rem;
        border: 1px solid rgba(229, 0, 119, 0.16);
        border-radius: 999px;
        background: #e50077;
        box-shadow: 0 16px 38px rgba(229, 0, 119, 0.26);
        transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        visibility: visible !important;
        opacity: 1 !important;
        pointer-events: auto !important;
    }

    #cookie-consent-floating-button:hover {
        transform: translateY(-1px) scale(1.04);
        background: #c70064;
        box-shadow: 0 20px 42px rgba(229, 0, 119, 0.32);
    }

    #cookie-consent-floating-button:focus-visible {
        outline: 3px solid rgba(229, 0, 119, 0.28);
        outline-offset: 3px;
    }

    #cookie-consent-floating-button img {
        display: block;
        width: 1.5rem;
        height: 1.5rem;
        flex-shrink: 0;
    }

    @media (max-width: 575.98px) {
        #cc-main .cm__body,
        #cc-main .cm__footer {
            padding-left: 1.2rem;
            padding-right: 1.2rem;
        }
    }

    @media (max-width: 767.98px) {
        #cookie-consent-floating-button {
            display: none !important;
        }
    }
</style>
