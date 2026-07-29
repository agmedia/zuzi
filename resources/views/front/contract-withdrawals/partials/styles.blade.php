<style>
    .withdrawal-page {
        --withdrawal-primary: #e50077;
        --withdrawal-primary-dark: #bf0063;
        --withdrawal-ink: #2b3445;
        --withdrawal-muted: #6c7482;
        --withdrawal-line: #e5e9f0;
        max-width: 1180px;
        margin: 0 auto;
        padding: 1rem 0 3.5rem;
    }

    .withdrawal-page__intro {
        max-width: 760px;
        margin-bottom: 0;
        color: var(--withdrawal-muted);
        line-height: 1.65;
    }

    .withdrawal-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 330px;
        gap: 1.5rem;
        align-items: start;
    }

    .withdrawal-card {
        border: 1px solid var(--withdrawal-line);
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 .75rem 2.25rem rgba(43, 52, 69, .065);
    }

    .withdrawal-card__body {
        padding: clamp(1.25rem, 3vw, 2rem);
    }

    .withdrawal-section + .withdrawal-section {
        margin-top: 2rem;
        padding-top: 1.75rem;
        border-top: 1px solid var(--withdrawal-line);
    }

    .withdrawal-section__title {
        display: flex;
        align-items: center;
        gap: .7rem;
        margin-bottom: 1.15rem;
        color: var(--withdrawal-ink);
        font-size: 1.05rem;
        font-weight: 800;
    }

    .withdrawal-section__number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.85rem;
        height: 1.85rem;
        border-radius: 999px;
        background: rgba(229, 0, 119, .1);
        color: var(--withdrawal-primary);
        font-size: .8rem;
    }

    .withdrawal-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .withdrawal-form-grid__full {
        grid-column: 1 / -1;
    }

    .withdrawal-page .form-label {
        margin-bottom: .45rem;
        color: var(--withdrawal-ink);
        font-size: .85rem;
        font-weight: 700;
    }

    .withdrawal-page .form-control {
        min-height: 3rem;
        border-color: #dce1e9;
        border-radius: .7rem;
        background: #fff;
    }

    .withdrawal-page textarea.form-control {
        min-height: auto;
        resize: vertical;
    }

    .withdrawal-page .form-control:focus {
        border-color: rgba(229, 0, 119, .65);
        box-shadow: 0 0 0 .2rem rgba(229, 0, 119, .11);
    }

    .withdrawal-field-help {
        margin-top: .4rem;
        color: var(--withdrawal-muted);
        font-size: .75rem;
        line-height: 1.45;
    }

    .withdrawal-submit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 3.15rem;
        padding: .75rem 1.45rem;
        border: 0;
        border-radius: .7rem;
        background: var(--withdrawal-primary);
        color: #fff;
        font-size: .92rem;
        font-weight: 800;
        box-shadow: 0 .55rem 1.15rem rgba(229, 0, 119, .2);
        transition: background-color .18s ease, transform .18s ease, box-shadow .18s ease;
    }

    .withdrawal-submit:hover,
    .withdrawal-submit:focus {
        background: var(--withdrawal-primary-dark);
        color: #fff;
        box-shadow: 0 .7rem 1.35rem rgba(229, 0, 119, .26);
        transform: translateY(-1px);
    }

    .withdrawal-submit:disabled {
        cursor: wait;
        opacity: .65;
        transform: none;
    }

    .withdrawal-aside {
        position: sticky;
        top: 6rem;
    }

    .withdrawal-aside h2 {
        margin-bottom: 1rem;
        color: var(--withdrawal-ink);
        font-size: 1.05rem;
    }

    .withdrawal-aside__list {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .withdrawal-aside__list li {
        position: relative;
        padding-left: 1.25rem;
        color: #535d6d;
        font-size: .84rem;
        line-height: 1.6;
    }

    .withdrawal-aside__list li + li {
        margin-top: .85rem;
    }

    .withdrawal-aside__list li::before {
        position: absolute;
        top: .58rem;
        left: 0;
        width: .42rem;
        height: .42rem;
        border-radius: 999px;
        background: var(--withdrawal-primary);
        content: "";
    }

    .withdrawal-address {
        margin-top: 1.25rem;
        padding-top: 1.1rem;
        border-top: 1px solid var(--withdrawal-line);
        color: #535d6d;
        font-size: .82rem;
        line-height: 1.6;
        white-space: pre-line;
    }

    .withdrawal-scope-note {
        margin-bottom: 1.25rem;
        padding: .85rem 1rem;
        border-left: 3px solid #8492a6;
        border-radius: .35rem;
        background: #f6f8fb;
        color: #566171;
        font-size: .82rem;
        line-height: 1.55;
    }

    .withdrawal-review-statement {
        padding: 1.25rem;
        border: 1px solid rgba(229, 0, 119, .23);
        border-radius: .8rem;
        background: #fff5fa;
        color: var(--withdrawal-ink);
        font-size: 1rem;
        font-weight: 800;
        line-height: 1.65;
    }

    .withdrawal-review-list {
        margin: 1.25rem 0 0;
    }

    .withdrawal-review-list__row {
        display: grid;
        grid-template-columns: 235px minmax(0, 1fr);
        gap: 1rem;
        padding: .85rem 0;
        border-bottom: 1px solid var(--withdrawal-line);
    }

    .withdrawal-review-list dt {
        color: var(--withdrawal-muted);
        font-size: .8rem;
        font-weight: 700;
    }

    .withdrawal-review-list dd {
        margin: 0;
        color: var(--withdrawal-ink);
        font-size: .9rem;
        line-height: 1.55;
        white-space: pre-line;
    }

    .withdrawal-review-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: .85rem;
        margin-top: 1.5rem;
    }

    .withdrawal-edit-link {
        display: inline-flex;
        align-items: center;
        min-height: 3.15rem;
        padding: .7rem 1.2rem;
        border: 1px solid #d5dae3;
        border-radius: .7rem;
        color: #4a5566;
        font-size: .88rem;
        font-weight: 700;
    }

    .withdrawal-edit-link:hover {
        border-color: #bfc6d1;
        background: #f7f8fa;
        color: #2b3445;
    }

    @media (max-width: 991.98px) {
        .withdrawal-grid {
            grid-template-columns: 1fr;
        }

        .withdrawal-aside {
            position: static;
        }
    }

    @media (max-width: 575.98px) {
        .withdrawal-page {
            padding-top: .5rem;
        }

        .withdrawal-form-grid,
        .withdrawal-review-list__row {
            grid-template-columns: 1fr;
        }

        .withdrawal-form-grid__full {
            grid-column: auto;
        }

        .withdrawal-review-list__row {
            gap: .25rem;
        }

        .withdrawal-review-actions > *,
        .withdrawal-review-actions form,
        .withdrawal-review-actions button {
            width: 100%;
        }
    }
</style>
