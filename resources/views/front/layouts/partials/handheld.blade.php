<!-- Toolbar for handheld devices (Marketplace)-->
<div class="handheld-toolbar d-none">
    <div class="d-table table-layout-fixed w-100">
        @if (Request::is(\App\Helpers\Helper::categoryGroupPath(true) . '/*' ) || Request::is(\App\Helpers\Helper::categoryGroupPath(true)))
       <!--     <a class="d-table-cell handheld-toolbar-item" href="#" data-bs-toggle="offcanvas" data-bs-target="#shop-sidebar"><span class="handheld-toolbar-icon"><i class="ci-filter-alt"></i></span><span class="handheld-toolbar-label">Filtriraj</span></a> -->
        @endif
      <!--  <a class="d-table-cell handheld-toolbar-item" href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" onclick="window.scrollTo(0, 0)"><span class="handheld-toolbar-icon"><i class="ci-menu"></i></span><span class="handheld-toolbar-label">Menu</span></a>-->
        <cart-footer-icon carturl="{{ route('kosarica') }}"></cart-footer-icon>
    </div>
</div>
