@if(isset($simple) && $simple)
    @if($status)
        <i class="fa fa-fw fa-check text-success"></i>
    @else
        <i class="fa fa-fw fa-times text-danger"></i>
    @endif
@else
    @if($status)
        <span class="badge badge-success">Aktivan</span>
    @else
        <span class="badge badge-secondary">Neaktivan</span>
    @endif
@endif
