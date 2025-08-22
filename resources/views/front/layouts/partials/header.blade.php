<!-- Navbar-->
<header class="bg-dark shadow-sm fixed-top" data-fixed-element>
    <div class="navbar navbar-expand-lg navbar-dark py-0">
        <div class="container-fluid">
            <a class="navbar-brand d-none d-md-block me-1 flex-shrink-0 py-0" href="{{ route('index') }}">
                <div class="logo-bg" style="background-color:#fff;margin-left:-30px; padding: 0 0 0 30px; ">
                    <img src="{{ asset('media/img/zuzi-logo.webp') }}" width="90"  alt="Web shop | ZUZI Shop | Prodaja knjiga | Otkup knjiga | Webshop">
                    <span class="arrow"></span>
                </div>
            </a>
            <a class="navbar-brand pt-0 pb-0 d-md-none me-0" href="{{ route('index') }}">
                <div class="logo-bg" style="background-color:#fff;margin-left:-30px; padding: 0 0 0 30px; ">
                    <img src="{{ asset('media/img/zuzi-logo.webp') }}" width="55" alt="Žuži Shop">
                    <span class="arrow-mb"></span>
                </div>
            </a>
            <!-- Search-->


            <form action="{{ route('pretrazi') }}" id="search-form-first" method="get" class="w-100 d-none d-lg-flex flex-nowrap mx-4"  role="search">

                <div class="dropdown w-100">
                    <div class="input-group ">
                        <i class="ci-search position-absolute top-50 start-0 text-dark translate-middle-y  fs-base ms-3"></i>
                        <input class="form-control rounded-start ps-5" type="text"
                               name="{{ config('settings.search_keyword') }}"
                               value="{{ request()->query('pojam') ?: '' }}"
                               placeholder="Traži što voliš, Zuzi nek’ ti govori…" id="search_box" data-toggle="dropdown" aria-haspopup="true" autocomplete="off" aria-expanded="false" onkeyup="javascript:load_data(this.value)">
                    </div>
                    <div id="search_result" class="live-search"></div>
                </div>
            </form>
            <!-- Toolbar-->
            <div class="navbar-toolbar d-flex flex-shrink-0 align-items-center ms-xl-2">
                <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" aria-label="Open the menu" data-bs-target="#sideNav"><span class="navbar-toggler-icon" aria-hidden="true"></span></button><a class="navbar-tool d-flex d-lg-none" href="#searchBox" data-bs-toggle="collapse" aria-label="Search" role="button" aria-expanded="false" aria-controls="searchBox"><span class="navbar-tool-tooltip">Pretraži</span>
                    <div class="navbar-tool-icon-box"><i class="navbar-tool-icon ci-search"></i></div></a>

                @if(auth()->user())
                    <a class="navbar-tool ms-1 ms-lg-0 me-n1 me-lg-2" aria-label="My account" href="{{ route('login') }}" >
                        <div class="navbar-tool-icon-box"><i class="navbar-tool-icon ci-user"></i></div>
                        <div class="navbar-tool-text ms-n3"><small>{{ auth()->user()->details->fname }} {{ auth()->user()->details->lname }}</small>Moj Račun</div>
                    </a>
                @else
                    <a class="navbar-tool ms-1 ms-lg-0 me-n1 me-lg-2" data-tab-id="pills-signin-tab" aria-label="Prijavi se" href="signin-tab"  role="button" data-bs-toggle="modal" data-bs-target="#signin-modal">
                        <div class="navbar-tool-icon-box"><i class="navbar-tool-icon ci-user"></i></div>
                        <div class="navbar-tool-text ms-n3">Prijavi se</div>
                    </a>
                @endif

                <cart-nav-icon carturl="{{ route('kosarica') }}" checkouturl="{{ route('naplata') }}"></cart-nav-icon>

            </div>
        </div>
    </div>
    <!-- Search collapse-->
    <div class="collapse" id="searchBox">
        <div class="card pt-2 pb-2 border-0 rounded-0">
            <div class="container">
                <form action="{{ route('pretrazi') }}" id="search-form" method="get" role="search">
                    <div class="dropdown w-100">
                        <div class="input-group">
                            <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                            <input
                                class="form-control rounded-start ps-5"
                                type="text"
                                name="{{ config('settings.search_keyword') }}"
                                value="{{ request()->query('pojam') ?: '' }}"
                                placeholder="Traži što voliš, Zuzi nek’ ti govori…"
                                id="search_box_mobile"
                                autocomplete="off"
                                aria-expanded="false"
                            >
                            <button type="submit" class="btn btn-primary btn-lg fs-base"><i class="ci-search"></i></button>
                        </div>
                        <div id="search_result_mb" class="live-search"></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</header>

<!-- Sidebar menu-->
<aside class="offcanvas offcanvas-expand w-100 border-end zindex-lg-5 pt-lg-5" id="sideNav" style="max-width: 18.875rem;">

    <ul class="nav nav-tabs nav-justified mt-0 mt-lg-5 mb-0" role="tablist" >
        <li class="nav-item"><a class="nav-link fw-medium active" href="#categories" data-bs-toggle="tab" role="tab">Kategorije</a></li>
        <li class="nav-item"><a class="nav-link fw-medium" href="#menu" data-bs-toggle="tab" role="tab">Info</a></li>
        <li class="nav-item d-lg-none"><a class="nav-link " href="#" data-bs-dismiss="offcanvas" aria-label="Close Navigation" role="tab"><i class="ci-close fs-xs me-2"></i></a></li>
    </ul>
    <div class="offcanvas-body px-0 pt-3 pb-0" data-simplebar>
        <div class="tab-content">
            <filter-view ids="{{ isset($ids) ? $ids : null }}"
                         group="kategorija-proizvoda"
                         cat="{{ isset($cat) ? $cat : null }}"
                         subcat="{{ isset($subcat) ? $subcat : null }}"
                         author="{{ isset($author) ? $author['slug'] : null }}"
                         publisher="{{ isset($publisher) ? $publisher['slug'] : null }}">
            </filter-view>
            <!-- Menu-->
            <div class="sidebar-nav tab-pane fade" id="menu" role="tabpanel">
                <div class="widget widget-categories">
                    <div class="accordion" id="shop-menu">
                        <!-- Homepages-->
                        @foreach ($uvjeti_kupnje->sortBy('title') as $page)
                            <div class="accordion-item border-bottom">
                                <h3 class="accordion-header px-grid-gutter"><a class="nav-link-style d-block fs-md  py-3" href="{{ route('catalog.route.page', ['page' => $page]) }}"><span class="d-flex align-items-center">{{ $page->title }}</span></a></h3>
                            </div>
                        @endforeach

                        <div class="accordion-item border-bottom">
                            <h3 class="accordion-header px-grid-gutter"><a class="nav-link-style d-block fs-md  py-3" href="{{ route('kontakt') }}"><span class="d-flex align-items-center">Kontaktirajte nas</span></a></h3>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="offcanvas-footer d-block px-grid-gutter pt-4 pb-3 mb-2">


        <a class="btn-social bs-light bg-primary bs-facebook me-2 mb-2" href="https://www.facebook.com/zuziobrt/"><i class="ci-facebook"></i></a><a class="btn-social bs-light bg-primary bs-instagram me-2 mb-2" href="https://www.instagram.com/zuziobrt/"><i class="ci-instagram"></i></a>





    </div>
</aside>

@push('js_after')
    <script>
        /* ===============================
         *  Tekstualne konstante (HR)
         * =============================== */
        const TXT_SEARCHING   = 'Tražim…';
        const TXT_NO_RESULTS  = 'Nema pronađenih rezultata';
        const TXT_SEE_ALL     = 'Pogledaj sve rezultate';
        const TXT_DYM         = 'Jeste li mislili:';
        const TXT_PRODUCTS    = 'Artikli';
        const TXT_AUTHORS     = 'Autori';
        const TXT_CATEGORIES  = 'Kategorije';
        const TXT_FOUND       = 'Pronađeno';
        const TXT_RESULTS     = 'rezultata';
        const TXT_PRODUCTS_C  = 'proizvodi';
        const TXT_AUTHORS_C   = 'autori';
        const TXT_CATEGORIES_C= 'kategorije';

        /* ===============================
         *  Debounce & helpers
         * =============================== */
        const DEBOUNCE_MS = 500;
        const debounceTimers = { desktop: null, mobile: null };

        function debouncedLoad(q, which){
            clearTimeout(debounceTimers[which]);
            debounceTimers[which] = setTimeout(()=>load_data(q, which), DEBOUNCE_MS);
        }

        function escapeHtml(s){ return String(s ?? '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }

        function foldAccents(str){
            if (!str) return '';
            try { return str.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase(); }
            catch(e){
                return str.toLowerCase()
                    .replace(/č/g,'c').replace(/ć/g,'c').replace(/ž/g,'z')
                    .replace(/š/g,'s').replace(/đ/g,'d');
            }
        }

        function highlightSuggestion(query, suggestion){
            const q = String(query||''); const s = String(suggestion||'');
            const fq = foldAccents(q), fs = foldAccents(s);
            let i = 0; while (i < fq.length && i < fs.length && fq[i] === fs[i]) i++;
            let j = 0; while (j < (fq.length - i) && j < (fs.length - i) && fq[fq.length-1-j] === fs[fs.length-1-j]) j++;
            if (i + j > s.length) { i = s.length; j = 0; }
            const pre = s.slice(0,i), mid = s.slice(i, s.length-j), post = s.slice(s.length-j);
            if (!mid || foldAccents(mid).trim()==='') return escapeHtml(s);
            return escapeHtml(pre) + '<strong>' + escapeHtml(mid) + '</strong>' + escapeHtml(post);
        }

        /* ===============================
         *  Close helpers
         * =============================== */
        function closeSearch(which){
            const resSel = which==='mobile' ? '#search_result_mb' : '#search_result';
            const inputSel = which==='mobile' ? '#search_box_mobile' : '#search_box';
            $(resSel).removeClass('show').empty();
            $(inputSel).attr('aria-expanded', 'false');
        }

        $(document).on('click', function(e){
            ['#search-form-first', '#search-form'].forEach(formSel=>{
                const $form = $(formSel);
                if($form.length && !$form.is(e.target) && $form.has(e.target).length===0){
                    closeSearch('desktop'); closeSearch('mobile');
                }
            });
        });

        $(document).on('keydown', function(e){ if(e.key === 'Escape'){ closeSearch('desktop'); closeSearch('mobile'); }});

        /* ===============================
         *  Hide mobile keyboard helper
         * =============================== */
        function hideMobileKeyboard(selectorList = ['#search_box_mobile']){
            selectorList.forEach(sel=>{
                const el = document.querySelector(sel);
                if (!el) return;
                const wasReadOnly = el.readOnly;
                el.readOnly = true;
                el.blur();
                setTimeout(()=>{ el.readOnly = wasReadOnly; }, 0);
            });
            if (document.activeElement) document.activeElement.blur();
        }

        /* ===============================
         *  AJAX loader
         * =============================== */
        const CAT_GROUP = '{{ $group ?? "kategorija-proizvoda" }}';

        function renderHeaderCounts(total, c){
            return '<div class="px-3 py-2 border-bottom fs-md text-dark">'
                + TXT_FOUND + ': <strong>' + total + '</strong> ' + TXT_RESULTS + ' '
                + '(' + TXT_PRODUCTS_C + ' ' + (c.products||0)
                + ', ' + TXT_AUTHORS_C + ' ' + (c.authors||0)
                + ', ' + TXT_CATEGORIES_C + ' ' + (c.categories||0) + ')</div>';
        }

        function load_data(query, which='desktop') {
            const resSel   = which==='mobile' ? '#search_result_mb' : '#search_result';
            const inputSel = which==='mobile' ? '#search_box_mobile' : '#search_box';

            if (query.length <= 2) { closeSearch(which); return; }

            if (which === 'desktop') {
                $(resSel).html('<div class="px-3 py-2 text-muted">'+TXT_SEARCHING+'</div>').addClass('show');
            }
            $(inputSel).attr('aria-expanded', 'true');

            $.ajax({
                method: 'get',
                url: '{{ route('api.front.autocomplete') }}'
                    + '?pojam_api=' + encodeURIComponent(query)
                    + '&group=' + encodeURIComponent(CAT_GROUP),
                success: function(json, textStatus, xhr) {
                    const headerTotal = parseInt(xhr.getResponseHeader('X-Total-Count') || '0', 10);
                    let html = '';
                    const isStructured = json && (json.counts || json.products || json.authors || json.categories);
                    if (isStructured) {
                        const c = json.counts || {products:0, authors:0, categories:0};
                        const total = headerTotal > 0 ? headerTotal : ((c.products|0) + (c.authors|0) + (c.categories|0));
                        const hasDYM = !!(json.meta && json.meta.did_you_mean);

                        html += renderHeaderCounts(total, c);

                        if (total === 0) {
                            if (hasDYM) {
                                const s = json.meta.did_you_mean;
                                const highlighted = highlightSuggestion(query, s);
                                html += '<div class="px-3 py-2 text-muted">'
                                    + TXT_DYM + ' <a href="#" class="did-you-mean-link text-primary fw-semibold"'
                                    + ' data-suggestion="'+escapeHtml(s)+'">'+ highlighted + '</a>'
                                    + '</div>'
                                    + '<div class="result-text px-3 pb-3">'
                                    +   '<button type="button" class="btn btn-sm btn-primary dym-apply" data-suggestion="'+escapeHtml(s)+'">'
                                    +     TXT_SEE_ALL + ' — ' + escapeHtml(s)
                                    +   '</button>'
                                    + '</div>';
                            } else {
                                html += '<div class="result-text text-muted p-3">'+TXT_NO_RESULTS+'</div>';
                            }
                            $(resSel).html(html).addClass('show');
                            bindDYM(which);
                            if(which==='mobile') hideMobileKeyboard();
                            return;
                        }

                        if (hasDYM) {
                            const highlighted = highlightSuggestion(query, json.meta.did_you_mean);
                            html += '<div class="px-3 py-2 text-muted">'
                                + TXT_DYM + ' <a href="#" class="did-you-mean-link text-primary fw-semibold"'
                                + ' data-suggestion="'+escapeHtml(json.meta.did_you_mean)+'">'+ highlighted + '</a></div>';
                        }

                        if (json.categories && json.categories.length > 0) {
                            html += '<div class="px-3 pt-2 pb-2 fw-bold fs-md bg-secondary text-dark">'+TXT_CATEGORIES+'</div>';
                            html += '<ul class="list-group list-group-flush cat">';
                            json.categories.forEach(function(cg){
                                html += '<li class="list-group-item py-2"><a class="text-dark fw-semibold fs-md" href="'+cg.url+'">'+escapeHtml(cg.name)+'</a></li>';
                            });
                            html += '</ul>';
                        }

                        if (json.products && json.products.length > 0) {
                            html += '<div class="px-3 pt-2 pb-2 fw-bold fs-md bg-secondary text-dark">'+TXT_PRODUCTS+'</div>';
                            html += '<table class="px-3 table products"><tbody>';
                            json.products.forEach(function (item) {
                                html += '<tr>'
                                    + '<td class="image"><a href="'+item.url+'"><img width="80" alt="'+escapeHtml(item.name)+'" src="'+item.image+'"></a></td>'
                                    + '<td class="main"><a href="'+item.url+'">'+escapeHtml(item.name)
                                    + '<br><small>'+escapeHtml(item.author_title||'')+'</small></a></td>'
                                    + '<td class="price text-end"><a href="'+item.url+'"><div class="price"><span class="price">'+(item.main_price_text||'')+'</span></div></a></td>'
                                    + '</tr>';
                            });
                            html += '</tbody></table>';
                        }

                        if (json.authors && json.authors.length > 0) {
                            html += '<div class="px-3 pt-2 pb-2 fw-bold fs-md bg-secondary text-dark">'+TXT_AUTHORS+'</div>';
                            html += '<ul class="list-group list-group-flush">';
                            json.authors.forEach(function(a){
                                html += '<li class="list-group-item py-2"><a class="text-dark fs-md" href="'+a.url+'">'+escapeHtml(a.name)+'</a></li>';
                            });
                            html += '</ul>';
                        }

                        html += '<div class="result-text"><a href="'+('{{ route('pretrazi') }}' + '?pojam=' + encodeURIComponent(query))+'" class="btn btn-sm btn-primary w-100">'+TXT_SEE_ALL+'</a></div>';
                    } else {
                        const total = headerTotal > 0 ? headerTotal : (Array.isArray(json) ? json.length : 0);
                        if (Array.isArray(json) && json.length > 0) {
                            html += '<div class="px-3 py-2 border-bottom small text-muted">'+TXT_FOUND+': <strong>'+ total +'</strong> '+TXT_RESULTS+'</div>';
                            html += '<table class="table products"><tbody>';
                            json.slice(0, 15).forEach(function (item) {
                                html += '<tr>'
                                    + '<td class="image"><a href="'+item.url+'"><img width="80" alt="'+escapeHtml(item.name)+'" src="'+item.image+'"></a></td>'
                                    + '<td class="main"><a href="'+item.url+'">'+escapeHtml(item.name)
                                    + '<br><small>'+escapeHtml(item.author_title)+'</small><br><small>'+escapeHtml(item.sku)+'</small></a></td>'
                                    + '<td class="price text-end"><a href="'+item.url+'"><div class="price"><span class="price">'+item.main_price_text+'</span></div></a></td>'
                                    + '</tr>';
                            });
                            html += '</tbody></table>';
                            html += '<div class="result-text"><a href="'+('{{ route('pretrazi') }}' + '?pojam=' + encodeURIComponent(query))+'" class="btn btn-sm btn-primary w-100">'+TXT_SEE_ALL+'</a></div>';
                        } else {
                            html += '<div class="result-text text-muted">'+TXT_NO_RESULTS+'</div>';
                        }
                    }

                    $(resSel).html(html).addClass('show');
                    $(inputSel).attr('aria-expanded', 'true');
                    bindDYM(which);
                    if(which==='mobile') hideMobileKeyboard(); // spušta tipkovnicu na mobu
                },
                error: function(xhr, ajaxOptions, thrownError) {
                    $(resSel).html('<div class="result-text text-danger">Greška pri pretrazi</div>').addClass('show');
                    $(inputSel).attr('aria-expanded', 'true');
                    console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
                }
            });
        }

        function bindDYM(which){
            const inputSel = which==='mobile' ? '#search_box_mobile' : '#search_box';
            $('.did-you-mean-link, .dym-apply').off('click').on('click', function(e){
                e.preventDefault();
                const suggestion = $(this).attr('data-suggestion') || $(this).text();
                $(inputSel).val(suggestion);
                load_data(suggestion, which);
            });
        }

        document.getElementById('search_box')?.addEventListener('input', function(e){
            debouncedLoad(e.target.value, 'desktop');
        });
        document.getElementById('search_box_mobile')?.addEventListener('input', function(e){
            debouncedLoad(e.target.value, 'mobile');
        });

        $('#search_box, #search_box_mobile').on('focus', function(){
            const which = this.id === 'search_box_mobile' ? 'mobile' : 'desktop';
            const resSel = which==='mobile' ? '#search_result_mb' : '#search_result';
            if ($(resSel).html()) $(resSel).addClass('show');
        });
    </script>
@endpush



